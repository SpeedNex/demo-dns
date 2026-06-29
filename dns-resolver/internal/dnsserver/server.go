package dnsserver

import (
	"context"
	"crypto/tls"
	"fmt"
	"log"
	"net"
	"sync"

	"ocer-dns/dns-resolver/internal/blockresponse"
	"ocer-dns/dns-resolver/internal/config"
	"ocer-dns/dns-resolver/internal/metrics"
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
	profileLoader        func(string) error
	profileConfigLoader  func(string) (*config.ProfileConfig, error)
	deviceResolver       func(string) (string, string, bool) // (sourceIP) -> (profileID, deviceID, ok)
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
			log.Printf("[TLS] 加载失败 err=%v DoT 未启动", err)
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
		log.Printf("[启动] UDP DNS 服务器 addr=%s", s.udpServer.Addr)
		if err := s.udpServer.ListenAndServe(); err != nil {
			errCh <- err
		}
	}()

	go func() {
		log.Printf("[启动] TCP DNS 服务器 addr=%s", s.tcpServer.Addr)
		if err := s.tcpServer.ListenAndServe(); err != nil {
			errCh <- err
		}
	}()

	if s.dotServer != nil {
		go func() {
			log.Printf("[启动] DoT 服务器 port=%d", s.cfg.Listen.DoT)
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

func (s *Server) SetProfileConfigLoader(loader func(string) (*config.ProfileConfig, error)) {
	s.profileConfigLoader = loader
}

func (s *Server) SetDeviceResolver(resolver func(string) (string, string, bool)) {
	s.deviceResolver = resolver
}

func (s *Server) handleQuery(w dns.ResponseWriter, req *dns.Msg, proto string) {
	// ① Profile 匹配 — 通过 SNI 或 deviceResolver
	profileUID := ""
	deviceID := ""
	if proto == "dot" {
		if sni, ok := s.sniMap.Load(w.RemoteAddr().String()); ok {
			profileUID = resolver.ExtractProfileFromSNI(sni.(string))
		}
	}

	// ② 如果 profileUID 为空且 deviceResolver 存在，尝试通过客户端 IP 查询设备
	if profileUID == "" && s.deviceResolver != nil {
		clientIP := remoteHost(w.RemoteAddr().String())
		if pid, did, ok := s.deviceResolver(clientIP); ok {
			profileUID = pid
			deviceID = did
		}
	}

	// ③ 通过 ProfileConfigLoader 加载策略配置
	blockResponse := blockresponse.ModeNXDomain
	safeSearchEnabled := false
	if profileUID != "" && s.profileConfigLoader != nil {
		if pc, err := s.profileConfigLoader(profileUID); err == nil {
			if pc.BlockResponse != "" {
				blockResponse = pc.BlockResponse
			}
			if pc.Parental != nil {
				if v, ok := pc.Parental["safe_search"]; ok {
					if b, ok := v.(bool); ok && b {
						safeSearchEnabled = true
					}
				}
			}
			// quota: quota_status == "exceeded" 时返回 REFUSED
			if pc.Quota != nil {
				if v, ok := pc.Quota["quota_status"]; ok {
					if s, ok := v.(string); ok && s == "exceeded" {
						reply := new(dns.Msg)
						reply.SetReply(req)
						reply.Rcode = dns.RcodeRefused
						_ = w.WriteMsg(reply)
						return
					}
				}
			}
		}
	}

	// ④ 共享 pipeline：去重 → 规则判定 → DNS 缓存 → 上游转发 → 日志
	result := s.handler.Handle(req, w.RemoteAddr().String(), proto, profileUID, deviceID, "", blockResponse, safeSearchEnabled)

	// ⑤ 写出响应
	_ = w.WriteMsg(result.Reply)
}

func remoteHost(addr string) string {
	host, _, err := net.SplitHostPort(addr)
	if err != nil {
		return addr
	}
	return host
}
