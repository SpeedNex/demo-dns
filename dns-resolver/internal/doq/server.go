package doq

import (
	"context"
	"crypto/tls"
	"encoding/binary"
	"encoding/json"
	"io"
	"log"
	"net"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"ocer-dns/dns-resolver/internal/config"
	"ocer-dns/dns-resolver/internal/logging"
	"ocer-dns/dns-resolver/internal/metrics"
	"ocer-dns/dns-resolver/internal/profile"
	"ocer-dns/dns-resolver/internal/resolver"

	"github.com/miekg/dns"
	"github.com/quic-go/quic-go"
)

// Server handles DNS over QUIC (RFC 9250) with full Profile Resolution Layer.
type Server struct {
	cfg           *config.Config
	handler       *resolver.Handler
	logBuffer     *logging.Buffer
	metrics       *metrics.Metrics
	listener      *quic.Listener
	mu            sync.Mutex
	profileLoader func(string) error
}

// activeConfig mirrors the DNS/DoH profile config schema.
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

// New creates a new DoQ server.
func New(
	cfg *config.Config,
	handler *resolver.Handler,
	logBuffer *logging.Buffer,
	collector *metrics.Metrics,
	profileLoader func(string) error,
) *Server {
	return &Server{
		cfg:           cfg,
		handler:       handler,
		logBuffer:     logBuffer,
		metrics:       collector,
		profileLoader: profileLoader,
	}
}

// Run starts the DoQ QUIC listener. Blocks until ctx is cancelled.
func (s *Server) Run(ctx context.Context, tlsCfg *tls.Config) error {
	addr := s.cfg.Listen.DoQ
	if addr == 0 {
		return nil
	}

	listener, err := quic.ListenAddr(
		net.JoinHostPort("", intToStr(addr)),
		tlsCfg,
		&quic.Config{
			MaxIdleTimeout:       30 * time.Second,
			HandshakeIdleTimeout: 10 * time.Second,
			MaxIncomingStreams:   256,
		},
	)
	if err != nil {
		return err
	}
	s.mu.Lock()
	s.listener = listener
	s.mu.Unlock()

	log.Printf("doq: listening on QUIC :%d (DNS over QUIC, RFC 9250)", addr)

	for {
		conn, err := listener.Accept(ctx)
		if err != nil {
			if ctx.Err() != nil {
				return nil
			}
			log.Printf("doq: accept error: %v", err)
			return err
		}
		go s.handleConnection(ctx, conn)
	}
}

// Stop gracefully shuts down the DoQ listener.
func (s *Server) Stop() error {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.listener != nil {
		return s.listener.Close()
	}
	return nil
}

func profileUIDFromSNI(serverName string) string {
	parts := strings.SplitN(serverName, ".", 2)
	if len(parts) < 2 {
		return ""
	}
	return strings.ToLower(parts[0])
}

func (s *Server) handleConnection(ctx context.Context, conn *quic.Conn) {
	remoteAddr := conn.RemoteAddr().String()
	// 从 QUIC TLS 连接状态中提取 SNI → profileUID
	profileUID := profileUIDFromSNI(conn.ConnectionState().TLS.ServerName)
	defer conn.CloseWithError(0, "bye")
	for {
		stream, err := conn.AcceptStream(ctx)
		if err != nil {
			return
		}
		go s.handleStream(stream, remoteAddr, profileUID)
	}
}

func (s *Server) handleStream(stream *quic.Stream, remoteAddr string, profileUID string) {
	defer stream.Close()

	// Read 2-byte length prefix
	var lenBuf [2]byte
	if _, err := io.ReadFull(stream, lenBuf[:]); err != nil {
		return
	}
	msgLen := binary.BigEndian.Uint16(lenBuf[:])
	if msgLen == 0 {
		return
	}

	msgBuf := make([]byte, msgLen)
	if _, err := io.ReadFull(stream, msgBuf); err != nil {
		return
	}

	req := new(dns.Msg)
	if err := req.Unpack(msgBuf); err != nil {
		return
	}

	// ① Profile 匹配 — 优先通过 TLS SNI(profileUID) 识别，回退到源 IP
	profileID, blockResponse, deviceID, safeSearchEnabled, ok := s.resolveRuntimeProfile(remoteAddr, profileUID)
	if !ok {
		reply := new(dns.Msg)
		reply.SetReply(req)
		reply.Rcode = dns.RcodeNameError
		s.writeStream(stream, reply)
		s.metrics.IncErrors()
		return
	}

	// ② 配额检查
	if s.isQuotaExceeded(profileID) {
		reply := new(dns.Msg)
		reply.SetReply(req)
		reply.Rcode = dns.RcodeRefused
		s.writeStream(stream, reply)
		s.metrics.IncErrors()
		return
	}

	// ③ 共享 pipeline
	result := s.handler.Handle(req, remoteAddr, "doq", profileID, deviceID, blockResponse, safeSearchEnabled)

	// ④ 写出响应
	s.writeStream(stream, result.Reply)
}

func (s *Server) writeStream(stream *quic.Stream, reply *dns.Msg) {
	packed, err := reply.Pack()
	if err != nil {
		s.metrics.IncErrors()
		return
	}
	respLen := make([]byte, 2)
	binary.BigEndian.PutUint16(respLen, uint16(len(packed)))
	(*stream).Write(respLen)
	(*stream).Write(packed)
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

func (s *Server) resolveRuntimeProfile(remoteAddr string, profileUID string) (profileID string, blockResponse string, deviceID string, safeSearch bool, ok bool) {
	cfg, err := s.loadActiveConfig()
	if err != nil || len(cfg.Profiles) == 0 {
		return "", "nxdomain", "", false, false
	}

	// 如果通过 TLS SNI 直接拿到了 profileUID，优先使用
	if profileUID != "" {
		// 按需加载 Profile（loader 内部有缓存，幂等安全）
		if s.profileLoader != nil {
			if err := s.profileLoader(profileUID); err != nil {
				log.Printf("doq: lazy load profile %s: %v", profileUID, err)
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
	pid, err := resolver.ResolveSourceIP(remoteAddr)
	if err != nil || pid == "" {
		return "", "nxdomain", "", false, false
	}

	host := remoteHost(remoteAddr)
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

func intToStr(n int) string {
	if n == 0 {
		return "0"
	}
	var buf [20]byte
	pos := len(buf)
	for n > 0 {
		pos--
		buf[pos] = byte('0' + n%10)
		n /= 10
	}
	return string(buf[pos:])
}
