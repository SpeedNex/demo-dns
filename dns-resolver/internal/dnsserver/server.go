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
	profileLoader func(string) error
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
	// ① Profile 匹配 — 通过 SNI 或 profileUID
	profileUID := ""
	if proto == "dot" {
		if sni, ok := s.sniMap.Load(w.RemoteAddr().String()); ok {
			profileUID = resolver.ExtractProfileFromSNI(sni.(string))
		}
	}

	// ② 共享 pipeline：去重 → 规则判定 → DNS 缓存 → 上游转发 → 日志
	result := s.handler.Handle(req, w.RemoteAddr().String(), proto, profileUID, "", "", blockresponse.ModeNXDomain, false)

	// ③ 写出响应
	_ = w.WriteMsg(result.Reply)
}

func remoteHost(addr string) string {
	host, _, err := net.SplitHostPort(addr)
	if err != nil {
		return addr
	}
	return host
}
