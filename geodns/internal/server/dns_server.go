package server

import (
	"context"
	"fmt"
	"log"
	"net"
	"strings"
	"sync"

	"github.com/miekg/dns"
	"github.com/oschwald/geoip2-golang"

	"ocer-dns/geodns/internal/config"
	"ocer-dns/geodns/internal/healthview"
	"ocer-dns/geodns/internal/router"
)

// DNSServer 是 GeoDNS 的 DNS 协议服务器。
// 它接收标准 DNS A/AAAA 查询，根据客户端来源选择最优 resolver 并返回其 IP。
type DNSServer struct {
	cfg    *config.Config
	router *router.Router

	mu   sync.RWMutex
	view healthview.View

	// 服务域名，例如 "dns.example.com"
	// 对此域名的 A/AAAA 查询将返回 resolver IP
	serveDomain string

	// geoDB 是 MaxMind GeoIP2 数据库 Reader，用于根据客户端 IP 识别国家/地区。
	// 留空则禁用 GeoIP 识别，调度退化为全局模式。
	geoDB *geoip2.Reader

	server   *dns.Server
	quitChan chan struct{}
}

// NewDNSServer 创建一个新的 DNS 服务器实例。
func NewDNSServer(cfg *config.Config, r *router.Router) *DNSServer {
	var geoDB *geoip2.Reader
	if cfg.GeoIPDBPath() != "" {
		db, err := geoip2.Open(cfg.GeoIPDBPath())
		if err != nil {
			log.Printf("geodns/dns: failed to open GeoIP database %s: %v (GeoIP disabled)", cfg.GeoIPDBPath(), err)
		} else {
			log.Printf("geodns/dns: GeoIP database loaded: %s", cfg.GeoIPDBPath())
			geoDB = db
		}
	}

	return &DNSServer{
		cfg:         cfg,
		router:      r,
		serveDomain: cfg.ServeDomain(),
		geoDB:       geoDB,
		quitChan:    make(chan struct{}),
	}
}

// UpdateView 更新健康视图（由主 server 调用）。
func (s *DNSServer) UpdateView(view healthview.View) {
	s.mu.Lock()
	s.view = view
	s.mu.Unlock()
	log.Printf("geodns/dns: updated health view: %d node(s)", len(view.Nodes))
}

// Run 启动 DNS 服务器（阻塞）。
func (s *DNSServer) Run(ctx context.Context, addr string) error {
	if addr == "" {
		addr = ":53"
	}

	dns.HandleFunc(".", s.handleRequest)

	s.server = &dns.Server{
		Addr:      addr,
		Net:       "udp",
		ReusePort: true,
		NotifyStartedFunc: func() {
			log.Printf("geodns/dns: DNS server listening on UDP %s", addr)
		},
	}

	// TCP 服务器（用于大响应）
	tcpServer := &dns.Server{
		Addr:      addr,
		Net:       "tcp",
		ReusePort: true,
		Handler:   dns.HandlerFunc(s.handleRequest),
	}

	go func() {
		if err := tcpServer.ListenAndServe(); err != nil {
			log.Printf("geodns/dns: TCP server error: %v", err)
		}
	}()

	errChan := make(chan error, 1)
	go func() {
		if err := s.server.ListenAndServe(); err != nil {
			errChan <- err
		}
	}()

	select {
	case <-ctx.Done():
		s.shutdown(tcpServer)
		return ctx.Err()
	case err := <-errChan:
		s.shutdown(tcpServer)
		return err
	}
}

func (s *DNSServer) shutdown(tcpServer *dns.Server) {
	if s.server != nil {
		s.server.Shutdown()
	}
	if tcpServer != nil {
		tcpServer.Shutdown()
	}
}

// handleRequest 处理所有 DNS 查询。
func (s *DNSServer) handleRequest(w dns.ResponseWriter, r *dns.Msg) {
	m := new(dns.Msg)
	m.SetReply(r)
	m.Authoritative = true

	if len(r.Question) == 0 {
		m.SetRcode(r, dns.RcodeNameError)
		w.WriteMsg(m)
		return
	}

	question := r.Question[0]
	qname := strings.TrimSuffix(question.Name, ".")
	qtype := question.Qtype

	// 对 CAA 查询返回空 NOERROR，避免 Let's Encrypt 等 CA 视为 SERVFAIL
	// CAA 未配置时表示不限制签发 CA，符合默认预期
	if qtype == dns.TypeCAA {
		m.SetRcode(r, dns.RcodeSuccess)
		w.WriteMsg(m)
		return
	}

	// 只处理 A 和 AAAA 查询
	if qtype != dns.TypeA && qtype != dns.TypeAAAA {
		m.SetRcode(r, dns.RcodeNotImplemented)
		w.WriteMsg(m)
		return
	}

	// 检查是否是我们服务的域名
	if !s.isServedDomain(qname) {
		m.SetRcode(r, dns.RcodeNameError)
		w.WriteMsg(m)
		return
	}

	// 根据客户端来源确定 region
	clientAddr := w.RemoteAddr().String()
	region := s.resolveRegion(clientAddr, r)

	// 选择最优 resolver
	s.mu.RLock()
	view := s.view
	s.mu.RUnlock()

	pick := s.router.Pick(region, view.Nodes)
	if pick == nil {
		// 没有可用节点
		m.SetRcode(r, dns.RcodeServerFailure)
		log.Printf("geodns/dns: no eligible node for region=%s client=%s", region, clientAddr)
		w.WriteMsg(m)
		return
	}

	// 构建 DNS 响应
	ttl := uint32(30) // 短 TTL，便于快速切换
	if view.TTLSeconds > 0 && view.TTLSeconds <= 300 {
		ttl = uint32(view.TTLSeconds)
	}

	if qtype == dns.TypeA && pick.PublicIPv4 != "" {
		rr, err := dns.NewRR(fmt.Sprintf("%s %d IN A %s", question.Name, ttl, pick.PublicIPv4))
		if err == nil {
			m.Answer = append(m.Answer, rr)
		}
	} else if qtype == dns.TypeAAAA && pick.PublicIPv6 != "" {
		rr, err := dns.NewRR(fmt.Sprintf("%s %d IN AAAA %s", question.Name, ttl, pick.PublicIPv6))
		if err == nil {
			m.Answer = append(m.Answer, rr)
		}
	}

	if len(m.Answer) == 0 {
		// 请求的类型没有对应IP，返回空 NOERROR（NXDOMAIN 太重）
		m.SetRcode(r, dns.RcodeSuccess)
		log.Printf("geodns/dns: no matching IP type for node=%s qtype=%s", pick.NodeID, dns.TypeToString[qtype])
	} else {
		log.Printf("geodns/dns: %s %s -> %s (region=%s client=%s)",
			question.Name, dns.TypeToString[qtype], m.Answer[0].String(), region, clientAddr)
	}

	w.WriteMsg(m)
}

// isServedDomain 检查查询的域名是否是我们服务的域名。
// 2026-07-07: 支持两种匹配模式
// 1. 精确匹配：dns.ocerlinkdata.com（用于 DoH URL 域名）
// 2. 子域名匹配：*.dns.ocerlinkdata.com（用于 DoT/DoQ SNI 域名，如 64a8d5.dns.ocerlinkdata.com）
func (s *DNSServer) isServedDomain(qname string) bool {
	if s.serveDomain == "" {
		// 未配置则服务所有域名
		return true
	}
	// 精确匹配：dns.ocerlinkdata.com
	if strings.EqualFold(qname, s.serveDomain) {
		return true
	}
	// 子域名匹配：64a8d5.dns.ocerlinkdata.com
	return strings.HasSuffix(strings.ToLower(qname), "."+strings.ToLower(s.serveDomain))
}

// resolveRegion 根据客户端地址解析 region。
// 优先级：EDNS Client Subnet > 源IP地理库 > 默认 global
func (s *DNSServer) resolveRegion(clientAddr string, r *dns.Msg) string {
	// 1. 尝试从 EDNS Client Subnet 获取
	if r.IsEdns0() != nil {
		for _, opt := range r.IsEdns0().Option {
			if ecs, ok := opt.(*dns.EDNS0_SUBNET); ok {
				return s.regionFromIP(net.IP(ecs.Address).String())
			}
		}
	}

	// 2. 从源IP获取
	host, _, err := net.SplitHostPort(clientAddr)
	if err != nil {
		host = clientAddr
	}
	return s.regionFromIP(host)
}

// regionFromIP 根据客户端 IP 解析 region。
// 使用 MaxMind GeoIP2 数据库识别国家 ISO 代码（如 CN/JP/US），
// 与 portal-web resolver_nodes.region 字段直接对齐。
// 未配置 GeoIP 数据库时返回 "global"。
func (s *DNSServer) regionFromIP(ipStr string) string {
	ip := net.ParseIP(ipStr)
	if ip == nil {
		return s.cfg.GlobalFallback()
	}

	// 私有IP → 根据配置返回默认 region
	if ip.IsPrivate() || ip.IsLoopback() {
		return s.cfg.GlobalFallback()
	}

	// 未配置 GeoIP 数据库 → 退化到全局模式
	if s.geoDB == nil {
		return s.cfg.GlobalFallback()
	}

	// 查询 GeoIP2 数据库，获取国家 ISO 代码
	record, err := s.geoDB.Country(ip)
	if err != nil {
		log.Printf("geodns/dns: GeoIP lookup failed for %s: %v", ipStr, err)
		return s.cfg.GlobalFallback()
	}

	isoCode := record.Country.IsoCode
	if isoCode == "" {
		return s.cfg.GlobalFallback()
	}

	return isoCode
}

// Close 释放 GeoIP 数据库资源。
func (s *DNSServer) Close() {
	if s.geoDB != nil {
		s.geoDB.Close()
	}
}
