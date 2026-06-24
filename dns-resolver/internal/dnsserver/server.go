package dnsserver

import (
	"context"
	"crypto/tls"
	"encoding/json"
	"fmt"
	"log"
	"net"
	"os"
	"path/filepath"
	"sync"

	"ocer-dns/dns-resolver/internal/config"
	"ocer-dns/dns-resolver/internal/metrics"
	"ocer-dns/dns-resolver/internal/profile"
	"ocer-dns/dns-resolver/internal/resolver"

	"github.com/miekg/dns"
)

type Server struct {
	cfg           *config.Config
	handler       *resolver.Handler
	metrics       *metrics.Metrics
	udpServer     *dns.Server
	tcpServer     *dns.Server
	dotServer     *dns.Server
	sniMap        sync.Map // key: remoteAddr -> sni (用于 DoT 按 SNI 识别 Profile)
	profileLoader func(string) error
}

type activeConfig struct {
	Profiles []struct {
		ProfileID     string         `json:"profile_id"`
		BlockResponse string         `json:"block_response"`
		Quota         map[string]any `json:"quota"`
		Parental      map[string]any `json:"parental"`
		Devices       []struct {
			DeviceID string `json:"device_id"`
			SourceIP string `json:"source_ip"`
		} `json:"devices"`
	} `json:"profiles"`
}

func New(
	cfg *config.Config,
	handler *resolver.Handler,
	metrics *metrics.Metrics,
	profileLoader func(string) error,
) *Server {
	s := &Server{
		cfg:     cfg,
		handler: handler,
		metrics: metrics,
	}

	s.udpServer = &dns.Server{
		Addr:    fmt.Sprintf(":%d", cfg.Listen.UDP),
		Net:     "udp",
		Handler: dns.HandlerFunc(func(w dns.ResponseWriter, req *dns.Msg) { s.handleQuery(w, req, "udp") }),
	}

	tcpPort := cfg.Listen.TCP
	if tcpPort == 0 {
		tcpPort = cfg.Listen.UDP
	}
	s.tcpServer = &dns.Server{
		Addr:    fmt.Sprintf(":%d", tcpPort),
		Net:     "tcp",
		Handler: dns.HandlerFunc(func(w dns.ResponseWriter, req *dns.Msg) { s.handleQuery(w, req, "tcp") }),
	}

	// 初始化 DoT (DNS over TLS) 服务器 — 通过 GetConfigForClient 提取 SNI
	if cfg.Listen.DoT > 0 {
		baseTLS, err := LoadTLSConfig(cfg.Listen.TLSCertFile, cfg.Listen.TLSKeyFile, cfg.ControlPlane.DNSDomain)
		if err != nil {
			log.Printf("dot: failed to load TLS config: %v (DoT not started)", err)
		} else {
			tlsCfg := baseTLS.Clone()
			tlsCfg.GetConfigForClient = func(info *tls.ClientHelloInfo) (*tls.Config, error) {
				if info.Conn != nil {
					s.sniMap.Store(info.Conn.RemoteAddr().String(), info.ServerName)
				}
				return baseTLS, nil
			}
			s.dotServer = &dns.Server{
				Addr:      fmt.Sprintf(":%d", cfg.Listen.DoT),
				Net:       "tcp-tls",
				TLSConfig: tlsCfg,
				Handler:   dns.HandlerFunc(func(w dns.ResponseWriter, req *dns.Msg) { s.handleQuery(w, req, "dot") }),
			}
		}
	}

	s.profileLoader = profileLoader
	return s
}

func (s *Server) Run(ctx context.Context) error {
	errCh := make(chan error, 3)

	go func() {
		log.Printf("Starting UDP DNS server on %s", s.udpServer.Addr)
		if err := s.udpServer.ListenAndServe(); err != nil {
			errCh <- err
		}
	}()

	go func() {
		log.Printf("Starting TCP DNS server on %s", s.tcpServer.Addr)
		if err := s.tcpServer.ListenAndServe(); err != nil {
			errCh <- err
		}
	}()

	if s.dotServer != nil {
		go func() {
			log.Printf("Starting DoT (DNS over TLS) server on :%d", s.cfg.Listen.DoT)
			if err := s.dotServer.ListenAndServe(); err != nil {
				errCh <- err
			}
		}()
	}

	select {
	case <-ctx.Done():
		_ = s.udpServer.Shutdown()
		_ = s.tcpServer.Shutdown()
		if s.dotServer != nil {
			_ = s.dotServer.Shutdown()
		}
		return nil
	case err := <-errCh:
		_ = s.udpServer.Shutdown()
		_ = s.tcpServer.Shutdown()
		if s.dotServer != nil {
			_ = s.dotServer.Shutdown()
		}
		return err
	}
}

func (s *Server) handleQuery(w dns.ResponseWriter, req *dns.Msg, proto string) {
	// ① Profile 匹配
	//    DoT: 优先通过 TLS SNI 识别; 若 SNI 无匹配则回退到源 IP
	//    UDP/TCP: 沿用源 IP 匹配
	profileUID := ""
	if proto == "dot" {
		if sni, ok := s.sniMap.Load(w.RemoteAddr().String()); ok {
			profileUID = resolver.ExtractProfileFromSNI(sni.(string))
		}
	}
	profileID, blockResponse, deviceID, safeSearchEnabled, ok := s.resolveRuntimeProfile(w.RemoteAddr(), profileUID)
	if !ok {
		reply := new(dns.Msg)
		reply.SetReply(req)
		reply.Rcode = dns.RcodeNameError
		_ = w.WriteMsg(reply)
		s.metrics.IncErrors()
		return
	}

	// ② 配额检查 — quota_status=exceeded 时拒绝
	if s.isQuotaExceeded(profileID) {
		reply := new(dns.Msg)
		reply.SetReply(req)
		reply.Rcode = dns.RcodeRefused
		_ = w.WriteMsg(reply)
		s.metrics.IncErrors()
		return
	}

	// ③ 共享 pipeline：去重 → 规则判定 → DNS 缓存 → 上游转发 → 日志
	result := s.handler.Handle(req, w.RemoteAddr().String(), proto, profileID, deviceID, blockResponse, safeSearchEnabled)

	// ④ 写出响应
	_ = w.WriteMsg(result.Reply)
}

func (s *Server) isQuotaExceeded(profileID string) bool {
	cfg, err := s.loadActiveConfig()
	if err != nil {
		return false
	}
	for _, p := range cfg.Profiles {
		if p.ProfileID == profileID {
			if p.Quota == nil {
				return false
			}
			status, _ := p.Quota["quota_status"].(string)
			return status == "exceeded"
		}
	}
	return false
}

func (s *Server) resolveRuntimeProfile(addr net.Addr, profileUID string) (profileID string, blockResponse string, deviceID string, safeSearch bool, ok bool) {
	cfg, err := s.loadActiveConfig()
	if err != nil || len(cfg.Profiles) == 0 {
		return "", "nxdomain", "", false, false
	}

	// 如果通过 SNI 直接拿到了 profileUID，优先使用
	if profileUID != "" {
		// 按需加载 Profile（loader 内部有缓存，幂等安全）
		if s.profileLoader != nil {
			if err := s.profileLoader(profileUID); err != nil {
				log.Printf("dns: lazy load profile %s: %v", profileUID, err)
			}
		}
		for _, p := range cfg.Profiles {
			if p.ProfileID == profileUID {
				safeSearch = boolFromMap(p.Parental, "safe_search") || boolFromMap(p.Parental, "force_safe_search")
				return profileUID, firstNonEmpty(p.BlockResponse, "nxdomain"), "", safeSearch, true
			}
		}
		// SNI 指定的 Profile UID 在配置中不存在时，回退到源 IP
	}

	sourceMap := make(map[string]string)
	deviceMap := make(map[string]string)
	blockMap := make(map[string]string)

	for _, profileConfig := range cfg.Profiles {
		if profileConfig.ProfileID == "" {
			continue
		}
		blockMap[profileConfig.ProfileID] = firstNonEmpty(profileConfig.BlockResponse, "nxdomain")
		for _, device := range profileConfig.Devices {
			if device.SourceIP == "" {
				continue
			}
			sourceMap[device.SourceIP] = profileConfig.ProfileID
			deviceMap[device.SourceIP] = device.DeviceID
		}
	}

	resolver := profile.New(sourceMap)
	pid, err := resolver.ResolveSourceIP(addr.String())
	if err != nil || pid == "" {
		return "", "nxdomain", "", false, false
	}

	host := remoteHost(addr.String())
	for _, profileConfig := range cfg.Profiles {
		if profileConfig.ProfileID != pid {
			continue
		}
		safeSearch = boolFromMap(profileConfig.Parental, "safe_search") || boolFromMap(profileConfig.Parental, "force_safe_search")
		break
	}

	return pid, firstNonEmpty(blockMap[pid], "nxdomain"), deviceMap[host], safeSearch, true
}

func (s *Server) loadActiveConfig() (*activeConfig, error) {
	profilesDir := s.cfg.ControlPlane.ProfilesPath
	cfg := &activeConfig{}

	entries, err := os.ReadDir(profilesDir)
	if err != nil {
		return cfg, nil
	}

	for _, entry := range entries {
		if !entry.IsDir() || len(entry.Name()) != 2 {
			continue
		}
		prefixPath := filepath.Join(profilesDir, entry.Name())
		files, _ := filepath.Glob(filepath.Join(prefixPath, "*.json"))
		for _, f := range files {
			data, err := os.ReadFile(f)
			if err != nil {
				continue
			}
			var envelope struct {
				ProfileID string          `json:"profile_id"`
				Version   int64           `json:"version"`
				Data      json.RawMessage `json:"data"`
			}
			if err := json.Unmarshal(data, &envelope); err != nil {
				continue
			}
			var profile struct {
				ProfileID     string         `json:"profile_id"`
				BlockResponse string         `json:"block_response"`
				Quota         map[string]any `json:"quota"`
				Parental      map[string]any `json:"parental"`
				Devices       []struct {
					DeviceID string `json:"device_id"`
					SourceIP string `json:"source_ip"`
				} `json:"devices"`
			}
			if err := json.Unmarshal(envelope.Data, &profile); err != nil {
				continue
			}
			cfg.Profiles = append(cfg.Profiles, profile)
		}
	}
	return cfg, nil
}

func remoteHost(addr string) string {
	host, _, err := net.SplitHostPort(addr)
	if err != nil {
		return addr
	}
	return host
}

func firstNonEmpty(values ...string) string {
	for _, value := range values {
		if value != "" {
			return value
		}
	}
	return ""
}

func boolFromMap(values map[string]any, key string) bool {
	if values == nil {
		return false
	}
	raw, ok := values[key]
	if !ok {
		return false
	}
	boolean, ok := raw.(bool)
	return ok && boolean
}
