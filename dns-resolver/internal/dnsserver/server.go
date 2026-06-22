package dnsserver

import (
	"context"
	"crypto/sha1"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"log"
	"net"
	"os"
	"path/filepath"
	"strings"
	"time"

	"ocer-dns/dns-resolver/internal/blockresponse"
	"ocer-dns/dns-resolver/internal/cache"
	"ocer-dns/dns-resolver/internal/config"
	"ocer-dns/dns-resolver/internal/dnscache"
	"ocer-dns/dns-resolver/internal/logging"
	"ocer-dns/dns-resolver/internal/metrics"
	"ocer-dns/dns-resolver/internal/profile"
	"ocer-dns/dns-resolver/internal/resolver"

	"github.com/miekg/dns"
)

type Server struct {
	cfg             *config.Config
	resolutionLayer *resolver.ProfileResolutionLayer
	logBuffer       *logging.Buffer
	metrics         *metrics.Metrics
	cache           *cache.Cache
	dnsCache        *dnscache.DNSCache
	client          *dns.Client
	udpServer       *dns.Server
	tcpServer       *dns.Server
	dotServer       *dns.Server
	dedupTTL        time.Duration
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
	resolutionLayer *resolver.ProfileResolutionLayer,
	logBuffer *logging.Buffer,
	collector *metrics.Metrics,
	cacheClient *cache.Cache,
	dnsCache *dnscache.DNSCache,
) *Server {
	handler := &Server{
		cfg:             cfg,
		resolutionLayer: resolutionLayer,
		logBuffer:       logBuffer,
		metrics:         collector,
		cache:           cacheClient,
		dnsCache:        dnsCache,
		client: &dns.Client{
			Net:     "udp",
			Timeout: 5 * time.Second,
		},
		dedupTTL: 5 * time.Second,
	}

	handler.udpServer = &dns.Server{
		Addr:    fmt.Sprintf(":%d", cfg.Listen.UDP),
		Net:     "udp",
		Handler: dns.HandlerFunc(func(w dns.ResponseWriter, req *dns.Msg) { handler.handleQuery(w, req, "udp") }),
	}

	tcpPort := cfg.Listen.TCP
	if tcpPort == 0 {
		tcpPort = cfg.Listen.UDP
	}
	handler.tcpServer = &dns.Server{
		Addr:    fmt.Sprintf(":%d", tcpPort),
		Net:     "tcp",
		Handler: dns.HandlerFunc(func(w dns.ResponseWriter, req *dns.Msg) { handler.handleQuery(w, req, "tcp") }),
	}

	// 初始化 DoT (DNS over TLS) 服务器
	if cfg.Listen.DoT > 0 {
		tlsCfg, err := LoadTLSConfig(cfg.Listen.TLSCertFile, cfg.Listen.TLSKeyFile)
		if err != nil {
			log.Printf("dot: failed to load TLS config: %v (DoT not started)", err)
		} else {
			handler.dotServer = &dns.Server{
				Addr:      fmt.Sprintf(":%d", cfg.Listen.DoT),
				Net:       "tcp-tls",
				TLSConfig: tlsCfg,
				Handler:   dns.HandlerFunc(func(w dns.ResponseWriter, req *dns.Msg) { handler.handleQuery(w, req, "dot") }),
			}
		}
	}

	return handler
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
	s.metrics.IncQueries()

	reply := new(dns.Msg)
	reply.SetReply(req)

	if len(req.Question) == 0 {
		reply.Rcode = dns.RcodeFormatError
		_ = w.WriteMsg(reply)
		s.metrics.IncErrors()
		return
	}

	question := req.Question[0]
	domain := strings.TrimSuffix(question.Name, ".")
	queryType := dns.TypeToString[question.Qtype]
	startedAt := time.Now()
	clientIP := remoteHost(w.RemoteAddr().String())

	// Query-count deduplication: skip the cache hit on the *log* path so we
	// don't burn log bandwidth on the same client asking for the same name
	// repeatedly. The DNS response itself is still served so the client
	// experience is identical. The dedup window is intentionally short
	// (default 5s) — long enough to collapse retransmits, short enough
	// not to hide traffic spikes.
	dedupKey := dedupFingerprint(clientIP, domain, queryType)
	dedupCtx, dedupCancel := context.WithTimeout(context.Background(), 250*time.Millisecond)
	firstSeen, dedupErr := s.cache.MarkSeen(dedupCtx, dedupKey, s.dedupTTL)
	dedupCancel()
	if dedupErr != nil {
		// On cache transport errors we still resolve and log the query —
		// the dedup is a best-effort optimization, never a correctness gate.
		log.Printf("dedup: cache error for key %s: %v (treating as first-seen)", dedupKey, dedupErr)
		firstSeen = true
	}

	profileID, blockResponse, deviceID, safeSearchEnabled, profileOK := s.resolveRuntimeProfile(w.RemoteAddr())
	if !profileOK {
		// UI.md #41: no IP→profile binding; refuse the query (NXDOMAIN)
		// instead of resolving on a paid default profile.
		reply.Rcode = dns.RcodeNameError
		_ = w.WriteMsg(reply)
		s.metrics.IncErrors()
		if firstSeen {
			s.appendLog("", "", domain, "BLOCK", "ip_not_bound", "", clientIP, queryType, proto, reply.Rcode, startedAt)
		}
		return
	}

	// P0: 检查配额状态 — quota_status=exceeded 时拒绝解析
	if s.isQuotaExceeded(profileID) {
		reply.Rcode = dns.RcodeRefused
		_ = w.WriteMsg(reply)
		s.metrics.IncErrors()
		if firstSeen {
			s.appendLog(profileID, deviceID, domain, "BLOCK", "quota_exceeded", "", clientIP, queryType, proto, reply.Rcode, startedAt)
		}
		return
	}
	decision := s.resolutionLayer.Resolve(&resolver.ResolutionContext{
		ProfileUID:        profileID,
		DeviceUID:         deviceID,
		SafeSearchEnabled: safeSearchEnabled,
		ClientIP:          remoteIP(w.RemoteAddr()),
		Domain:            domain,
		QueryType:         queryType,
		Protocol:          w.LocalAddr().Network(),
	})

	if decision.Action == "BLOCK" {
		s.metrics.IncBlocked()
		s.applyBlockResponse(reply, question, blockResponse)
		_ = w.WriteMsg(reply)
		if firstSeen {
			s.appendLog(profileID, deviceID, domain, "BLOCK", decision.Reason, decision.Category, clientIP, queryType, proto, reply.Rcode, startedAt)
		}
		return
	}

	if decision.Action == "REWRITE" {
		reply.Answer = []dns.RR{
			&dns.CNAME{
				Hdr: dns.RR_Header{
					Name:   question.Name,
					Rrtype: dns.TypeCNAME,
					Class:  dns.ClassINET,
					Ttl:    60,
				},
				Target: dns.Fqdn(decision.Category),
			},
		}
		s.metrics.IncAllowed()
		_ = w.WriteMsg(reply)
		if firstSeen {
			s.appendLog(profileID, deviceID, domain, "REWRITE", decision.Reason, decision.Category, clientIP, queryType, proto, reply.Rcode, startedAt)
		}
		return
	}

	// Check DNS cache first
	cacheKey := dnscache.MakeKey(domain, question.Qtype, profileID)
	if cached, ok := s.dnsCache.Get(context.Background(), cacheKey); ok {
		// Cache hit! Return cached response
		s.metrics.IncAllowed()
		_ = w.WriteMsg(cached)
		if firstSeen {
			s.appendLog(profileID, deviceID, domain, "ALLOW", "cache_hit", "", clientIP, queryType, proto, cached.Rcode, startedAt)
		}
		return
	}

	// Cache miss - query upstream
	upstreamReply, _, err := s.client.Exchange(req, upstreamAddr(s.cfg.Upstream[0]))
	if err != nil && len(s.cfg.Upstream) > 1 {
		upstreamReply, _, err = s.client.Exchange(req, upstreamAddr(s.cfg.Upstream[1]))
	}
	if err != nil {
		reply.Rcode = dns.RcodeServerFailure
		_ = w.WriteMsg(reply)
		s.metrics.IncErrors()
		if firstSeen {
			s.appendLog(profileID, deviceID, domain, "ERROR", "upstream_timeout", "", clientIP, queryType, proto, reply.Rcode, startedAt)
		}
		return
	}

	// Store in cache
	s.dnsCache.Set(context.Background(), cacheKey, upstreamReply)

	s.metrics.IncAllowed()
	_ = w.WriteMsg(upstreamReply)
	if firstSeen {
		s.appendLog(profileID, deviceID, domain, "ALLOW", "default", "", clientIP, queryType, proto, upstreamReply.Rcode, startedAt)
	}
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

func (s *Server) resolveRuntimeProfile(addr net.Addr) (profileID string, blockResponse string, deviceID string, safeSearch bool, ok bool) {
	cfg, err := s.loadActiveConfig()
	if err != nil || len(cfg.Profiles) == 0 {
		// UI.md #41: failure must NOT silently fall back to a paid
		// "default" profile.  Return ok=false so the caller can degrade
		// to public DNS or refuse the query.
		return "", "nxdomain", "", false, false
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
		// UI.md #41: no IP→device binding found.  Do not silently fall
		// back to the first profile (which is usually a paid plan).
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
	path := filepath.Join(s.cfg.ControlPlane.ProfilesPath, "active.json")
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}

	var cfg activeConfig
	if err := json.Unmarshal(data, &cfg); err != nil {
		return nil, err
	}

	return &cfg, nil
}

func (s *Server) appendLog(profileID, deviceID, domain, action, reason, category, clientIP, queryType, protocol string, rcode int, startedAt time.Time) {
	if s.logBuffer == nil {
		return
	}

	s.logBuffer.Append(logging.LogEntry{
		ProfileUID:     profileID,
		DeviceUID:      deviceID,
		Domain:         domain,
		Action:         strings.ToUpper(action),
		Reason:         reason,
		Category:       category,
		ClientIP:       clientIP,
		QueryType:      queryType,
		ResponseCode:   rcode,
		ResponseTimeMs: time.Since(startedAt).Milliseconds(),
		QueriedAt:      time.Now().Unix(),
		Protocol:       protocol,
	})
}

func (s *Server) applyBlockResponse(reply *dns.Msg, question dns.Question, blockResponse string) {
	blockresponse.ApplyTo(reply, question, blockResponse)
}

func upstreamAddr(addr string) string {
	if strings.Contains(addr, ":") {
		return addr
	}
	return addr + ":53"
}

func remoteIP(addr net.Addr) net.IP {
	return net.ParseIP(remoteHost(addr.String()))
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

// dedupFingerprint returns a stable per-(client,qname,qtype) fingerprint used
// as the cache key. SHA-1 is sufficient here — this is a 5-second dedup
// signal, not a security identifier, and SHA-1 keeps the key short.
func dedupFingerprint(clientIP, domain, qtype string) string {
	h := sha1.New()
	h.Write([]byte(clientIP))
	h.Write([]byte{0})
	h.Write([]byte(domain))
	h.Write([]byte{0})
	h.Write([]byte(qtype))
	return hex.EncodeToString(h.Sum(nil))
}
