package doq

import (
	"context"
	"crypto/tls"
	"encoding/binary"
	"io"
	"log"
	"net"
	"sync"
	"time"

	"ocer-dns/dns-resolver/internal/cache"
	"ocer-dns/dns-resolver/internal/config"
	"ocer-dns/dns-resolver/internal/logging"
	"ocer-dns/dns-resolver/internal/metrics"

	"github.com/miekg/dns"
	"github.com/quic-go/quic-go"
)

// Server handles DNS over QUIC (RFC 9250) with full Profile Resolution Layer.
//
// DoQ uses a single bidirectional QUIC stream per DNS query/response,
// with a 2-byte big-endian length prefix (same wire format as TCP).
type Server struct {
	cfg             *config.Config
	resolutionLayer interface{} // placeholder for future policy engine integration
	logBuffer       *logging.Buffer
	metrics         *metrics.Metrics
	cache           *cache.Cache
	client          *dns.Client
	dedupTTL        time.Duration
	listener        *quic.Listener
	mu              sync.Mutex
}

// New creates a new DoQ server.
func New(
	cfg *config.Config,
	logBuffer *logging.Buffer,
	collector *metrics.Metrics,
	cacheClient *cache.Cache,
) *Server {
	return &Server{
		cfg:     cfg,
		logBuffer: logBuffer,
		metrics: collector,
		cache:   cacheClient,
		client: &dns.Client{
			Net:     "udp",
			Timeout: 5 * time.Second,
		},
		dedupTTL: 5 * time.Second,
	}
}

// Run starts the DoQ QUIC listener. Blocks until ctx is cancelled.
func (s *Server) Run(ctx context.Context, tlsCfg *tls.Config) error {
	addr := s.cfg.Listen.DoQ
	if addr == 0 {
		return nil
	}

	listener, err := quic.ListenAddr(
		net.JoinHostPort("", s.portStr(addr)),
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
				return nil // graceful shutdown
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

func (s *Server) handleConnection(ctx context.Context, conn *quic.Conn) {
	defer conn.CloseWithError(0, "bye")

	for {
		stream, err := conn.AcceptStream(ctx)
		if err != nil {
			return
		}
		go s.handleStream(stream, conn.RemoteAddr())
	}
}

func (s *Server) handleStream(stream *quic.Stream, remoteAddr net.Addr) {
	defer stream.Close()

	// Read 2-byte length prefix (big-endian, same as TCP wire format)
	var lenBuf [2]byte
	if _, err := io.ReadFull(stream, lenBuf[:]); err != nil {
		return
	}
	msgLen := binary.BigEndian.Uint16(lenBuf[:])
	if msgLen == 0 {
		return
	}

	// Read DNS message
	msgBuf := make([]byte, msgLen)
	if _, err := io.ReadFull(stream, msgBuf); err != nil {
		return
	}

	// Parse DNS query
	req := new(dns.Msg)
	if err := req.Unpack(msgBuf); err != nil {
		return
	}

	s.metrics.IncQueries()

	// Resolve via upstream
	reply := s.resolve(req)
	if reply == nil {
		reply = new(dns.Msg)
		reply.SetReply(req)
		reply.Rcode = dns.RcodeServerFailure
	}

	// Write response with 2-byte length prefix
	packed, err := reply.Pack()
	if err != nil {
		s.metrics.IncErrors()
		return
	}

	respLen := make([]byte, 2)
	binary.BigEndian.PutUint16(respLen, uint16(len(packed)))
	if _, err := stream.Write(respLen); err != nil {
		return
	}
	if _, err := stream.Write(packed); err != nil {
		return
	}
}

func (s *Server) resolve(req *dns.Msg) *dns.Msg {
	reply, _, err := s.client.Exchange(req, upstreamAddr(s.cfg.Upstream[0]))
	if err != nil {
		if len(s.cfg.Upstream) > 1 {
			reply, _, err = s.client.Exchange(req, upstreamAddr(s.cfg.Upstream[1]))
		}
		if err != nil {
			s.metrics.IncErrors()
			return nil
		}
	}
	return reply
}

func (s *Server) portStr(port int) string {
	if port == 0 {
		return "0"
	}
	var buf [20]byte
	pos := len(buf)
	for port > 0 {
		pos--
		buf[pos] = byte('0' + port%10)
		port /= 10
	}
	return string(buf[pos:])
}

func upstreamAddr(addr string) string {
	if _, _, err := net.SplitHostPort(addr); err == nil {
		return addr
	}
	return addr + ":53"
}