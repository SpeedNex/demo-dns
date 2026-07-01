package server

import (
	"context"
	"fmt"
	"log"
	"net"
	"strings"
	"sync"

	"github.com/miekg/dns"

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

	server   *dns.Server
	quitChan chan struct{}
}

// NewDNSServer 创建一个新的 DNS 服务器实例。
func NewDNSServer(cfg *config.Config, r *router.Router) *DNSServer {
	return &DNSServer{
		cfg:         cfg,
		router:      r,
		serveDomain: cfg.ServeDomain(),
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
func (s *DNSServer) isServedDomain(qname string) bool {
	if s.serveDomain == "" {
		// 未配置则服务所有域名
		return true
	}
	return strings.EqualFold(qname, s.serveDomain) || strings.HasSuffix(strings.ToLower(qname), "."+strings.ToLower(s.serveDomain))
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

// regionFromIP 简单的 IP 到 region 映射。
// 生产环境应使用 MaxMind GeoIP2 等地理库。
func (s *DNSServer) regionFromIP(ipStr string) string {
	ip := net.ParseIP(ipStr)
	if ip == nil {
		return "global"
	}

	// 私有IP → 根据配置返回默认region
	if ip.IsPrivate() || ip.IsLoopback() {
		return s.cfg.GlobalFallback()
	}

	// 简化的 IP 段映射（生产环境应使用 GeoIP 库）
	// 这里使用 healthview 中已有的节点 region 信息做简单匹配
	s.mu.RLock()
	defer s.mu.RUnlock()

	if len(s.view.Nodes) == 0 {
		return "global"
	}

	// TODO: 实现基于 GeoIP 的精确匹配
	// 暂时返回第一个节点的 region（简化实现）
	return "global"
}

// RegionFromHealthView 根据健康视图中的节点 region 分布推断客户端 region。
// 这是一个简化实现，生产环境建议使用GeoIP库。
func (s *DNSServer) RegionFromHealthView() string {
	s.mu.RLock()
	defer s.mu.RUnlock()

	regionCount := make(map[string]int)
	for _, node := range s.view.Nodes {
		if node.Status == "online" && node.Region != "" {
			regionCount[node.Region]++
		}
	}

	// 返回节点最多的 region
	bestRegion := "global"
	bestCount := 0
	for region, count := range regionCount {
		if count > bestCount {
			bestCount = count
			bestRegion = region
		}
	}
	return bestRegion
}
