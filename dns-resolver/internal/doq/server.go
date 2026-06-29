package doq

import (
	"context"
	"crypto/tls"
	"encoding/binary"
	"io"
	"log"
	"net"
	"strings"
	"sync"
	"time"

	"ocer-dns/dns-resolver/internal/config"
	"ocer-dns/dns-resolver/internal/logging"
	"ocer-dns/dns-resolver/internal/metrics"
	"ocer-dns/dns-resolver/internal/resolver"

	"github.com/miekg/dns"
	"github.com/quic-go/quic-go"
)

// Server handles DNS over QUIC (RFC 9250) with full Profile Resolution Layer.
type Server struct {
	cfg                 *config.Config
	handler             *resolver.Handler
	logBuffer           *logging.Buffer
	metrics             *metrics.Metrics
	listener            *quic.Listener
	mu                  sync.Mutex
	profileLoader       func(string) error
	profileConfigLoader func(string) (*config.ProfileConfig, error)
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

// SetProfileConfigLoader sets the loader for retrieving profile metadata from cache.
func (s *Server) SetProfileConfigLoader(loader func(string) (*config.ProfileConfig, error)) {
	s.profileConfigLoader = loader
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

	log.Printf("[DoQ] 监听 QUIC port=%d (DNS over QUIC, RFC 9250)", addr)

	for {
		conn, err := listener.Accept(ctx)
		if err != nil {
			if ctx.Err() != nil {
				return nil
			}
			log.Printf("[DoQ] 接受连接错误 err=%v", err)
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
	profileID, blockResponse, deviceID, safeSearchEnabled, ok := profileUID, "nxdomain", "", false, profileUID != ""
	if !ok {
		reply := new(dns.Msg)
		reply.SetReply(req)
		reply.Rcode = dns.RcodeNameError
		s.writeStream(stream, reply)
		s.metrics.IncErrors()
		return
	}

	// ② 从 ProfileCache 读取 Profile 元数据，覆盖硬编码默认值
	if s.profileConfigLoader != nil {
		if pc, err := s.profileConfigLoader(profileID); err == nil && pc != nil {
			if pc.BlockResponse != "" {
				blockResponse = pc.BlockResponse
			}
			if pc.Parental != nil {
				if v, ok := pc.Parental["safe_search"].(bool); ok {
					safeSearchEnabled = v
				}
			}
			if deviceID == "" && len(pc.Devices) > 0 {
				srcIP := remoteHost(remoteAddr)
				for _, dev := range pc.Devices {
					if dev.SourceIP == srcIP {
						deviceID = dev.DeviceID
						break
					}
				}
			}
			if pc.Quota != nil {
				if v, ok := pc.Quota["quota_status"].(string); ok && v == "exceeded" {
					reply := new(dns.Msg)
					reply.SetReply(req)
					reply.Rcode = dns.RcodeRefused
					s.writeStream(stream, reply)
					s.metrics.IncErrors()
					return
				}
			}
		}
	}

	// ③ 共享 pipeline
	result := s.handler.Handle(req, remoteAddr, "doq", profileID, deviceID, "", blockResponse, safeSearchEnabled)

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



func remoteHost(addr string) string {
	host, _, err := net.SplitHostPort(addr)
	if err != nil {
		return addr
	}
	return host
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
