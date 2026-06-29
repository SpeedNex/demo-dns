package doh

import (
	"context"
	"crypto/sha1"
	"encoding/base64"
	"encoding/hex"
	"io"
	"log"
	"net"
	"net/http"
	"os"
	"strings"
	"time"

	"ocer-dns/dns-resolver/internal/blockresponse"
	"ocer-dns/dns-resolver/internal/cache"
	"ocer-dns/dns-resolver/internal/config"
	"ocer-dns/dns-resolver/internal/logging"
	"ocer-dns/dns-resolver/internal/matching"
	"ocer-dns/dns-resolver/internal/metrics"
	"ocer-dns/dns-resolver/internal/resolver"
	"ocer-dns/dns-resolver/internal/validation"

	"github.com/miekg/dns"
)

// Server handles DNS over HTTPS requests with full Profile Resolution Layer.
//
// The HTTP listener's Handler() returns a plain http.Handler, supporting
// two deployment modes:
//
//  1. Direct TLS (default) – main.go wraps the handler with tls.NewListener,
//     the resolver owns the certificates and serves HTTPS directly.
//  2. Reverse proxy         – nginx / Caddy / Envoy terminates TLS and forwards
//     plain HTTP to the resolver; X-Profile-UID header carries the profile
//     identity (see handleDNSQuery / handleProfileDNSQuery).
//
// Either mode works with full Profile Resolution Layer.
type Server struct {
	cfg                 *config.Config
	engine              *matching.Engine
	resolutionLayer     *resolver.ProfileResolutionLayer
	logBuffer           *logging.Buffer
	metrics             *metrics.Metrics
	cache               *cache.Cache
	client              *dns.Client
	hostname            string
	dedupTTL            time.Duration
	validator           *validation.Validator                       // optional (UI.md #40)
	profileLoader       func(string) error                          // 按需拉取 Profile 回调
	profileConfigLoader func(string) (*config.ProfileConfig, error) // 按需获取 Profile 元数据
}

// NewServer creates a new DoH server.
func NewServer(cfg *config.Config, engine *matching.Engine,
	resolutionLayer *resolver.ProfileResolutionLayer,
	logBuffer *logging.Buffer, metrics *metrics.Metrics, cacheClient *cache.Cache,
	profileLoader func(string) error) *Server {

	hostname, _ := os.Hostname()

	return &Server{
		cfg:             cfg,
		engine:          engine,
		resolutionLayer: resolutionLayer,
		logBuffer:       logBuffer,
		metrics:         metrics,
		cache:           cacheClient,
		client: &dns.Client{
			Net:     "udp",
			Timeout: 5 * time.Second,
		},
		hostname:      hostname,
		dedupTTL:      5 * time.Second,
		profileLoader: profileLoader,
	}
}

// SetValidator wires an optional profile validator (UI.md #40).  When set,
// resolveDNS rejects requests whose profile uid fails ownership /
// subscription checks instead of resolving them.
func (s *Server) SetValidator(v *validation.Validator) {
	s.validator = v
}

// SetProfileConfigLoader wires a function to load ProfileConfig metadata.
// When set, resolveDNS reads block_response, quota, safe_search, and device
// mappings from the loaded config instead of using hardcoded defaults.
func (s *Server) SetProfileConfigLoader(loader func(string) (*config.ProfileConfig, error)) {
	s.profileConfigLoader = loader
}

// upstreamAddr returns the full address with default port if not specified.
func upstreamAddr(addr string) string {
	if strings.Contains(addr, ":") {
		return addr
	}
	return addr + ":53"
}

// Handler returns the HTTP handler for DoH endpoints.
func (s *Server) Handler() http.Handler {
	mux := http.NewServeMux()
	mux.HandleFunc("/dns-query", s.handleDNSQuery)

	// Profile-specific DoH endpoints: /{profile_id}/dns-query
	mux.HandleFunc("/", s.handleProfileDNSQuery)

	return mux
}

// handleDNSQuery handles standard DoH requests (RFC 8484).
// Also supports X-Profile-UID header for Nginx proxy mode.
func (s *Server) handleDNSQuery(w http.ResponseWriter, r *http.Request) {
	profileUID := r.Header.Get("X-Profile-UID")
	if profileUID != "" && resolver.IsValidProfileID(profileUID) {
		s.resolveDNS(w, r, profileUID)
		return
	}
	s.resolveDNS(w, r, "")
}

// handleProfileDNSQuery handles profile-specific DoH requests.
// URL format: /{profile_id} or /{profile_id}/dns-query
// Also supports X-Profile-UID header (when running behind Nginx).
// Only stable 6-char hex profile_id values are accepted.
func (s *Server) handleProfileDNSQuery(w http.ResponseWriter, r *http.Request) {
	// Nginx 转发模式: 通过 X-Profile-UID header 传递 profile_id
	profileUID := r.Header.Get("X-Profile-UID")
	if profileUID != "" && resolver.IsValidProfileID(profileUID) {
		s.resolveDNS(w, r, profileUID)
		return
	}

	// 路径提取: /{profile_id} 或 /{profile_id}/dns-query
	path := strings.TrimPrefix(r.URL.Path, "/")

	// Support both /{profile_id} and /{profile_id}/dns-query
	profileUID = strings.TrimSuffix(path, "/dns-query")
	if profileUID == "" {
		s.resolveDNS(w, r, "")
		return
	}

	// Validate it looks like a profile UID
	if resolver.IsValidProfileID(profileUID) {
		s.resolveDNS(w, r, profileUID)
		return
	}

	http.NotFound(w, r)
}

// resolveDNS performs the full DNS resolution with Profile Resolution Layer.
func (s *Server) resolveDNS(w http.ResponseWriter, r *http.Request, profileUID string) {
	s.metrics.IncQueries()

	// UI.md #40 — Validate profile ownership / subscription when a
	// validator is wired.  Owner id is taken from the X-User-Id header
	// (set by the DoH reverse proxy after JWT verification).  We do not
	// modify the rest of the resolution pipeline.
	if s.validator != nil && profileUID != "" {
		userID := r.Header.Get("X-User-Id")
		if err := s.validator.Validate(profileUID, userID); err != nil {
			log.Printf("[DoH] profile验证失败 err=%v profile=%s user=%s", err, profileUID, userID)
			s.metrics.IncErrors()
			http.Error(w, "profile not authorized", http.StatusForbidden)
			return
		}
	}

	if r.Method != http.MethodGet && r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	var dnsQuery []byte

	if r.Method == http.MethodGet {
		b64 := r.URL.Query().Get("dns")
		if b64 == "" {
			http.Error(w, "Missing dns parameter", http.StatusBadRequest)
			return
		}
		var err error
		dnsQuery, err = base64.RawURLEncoding.DecodeString(b64)
		if err != nil {
			http.Error(w, "Invalid dns parameter", http.StatusBadRequest)
			return
		}
	} else {
		r.Body = http.MaxBytesReader(w, r.Body, 4096)
		body, err := io.ReadAll(r.Body)
		if err != nil {
			http.Error(w, "Failed to read body", http.StatusBadRequest)
			return
		}
		dnsQuery = body
	}

	// Parse the DNS query
	msg := new(dns.Msg)
	if err := msg.Unpack(dnsQuery); err != nil {
		http.Error(w, "Failed to parse DNS query", http.StatusBadRequest)
		s.metrics.IncErrors()
		return
	}

	startTime := time.Now()

	var domain string
	var queryType string
	var decision *matching.Decision
	firstSeen := true
	clientAddr := remoteIPFromAddr(r.RemoteAddr).String()
	var deviceUID string
	var deviceType string

	if len(msg.Question) > 0 {
		domain = strings.TrimSuffix(msg.Question[0].Name, ".")
		queryType = dns.TypeToString[msg.Question[0].Qtype]

		// 2026-06-29: 跳过局域网本地域名后缀（.lan / .local / .home）
		if strings.HasSuffix(domain, ".lan") || strings.HasSuffix(domain, ".local") || strings.HasSuffix(domain, ".home") {
			reply := new(dns.Msg)
			reply.SetReply(msg)
			reply.Rcode = dns.RcodeNameError
			packed, _ := reply.Pack()
			w.Header().Set("Content-Type", "application/dns-message")
			w.Write(packed)
			return
		}

		// Dedup before the resolution pipeline so log buffer + control plane
		// don't get flooded by repeated client retransmits. Disabled cache
		// is a no-op, so this branch is free in non-Redis deployments.
		dedupKey := dohDedupFingerprint(r.RemoteAddr, domain, queryType)
		dedupCtx, dedupCancel := context.WithTimeout(r.Context(), 250*time.Millisecond)
		seen, dedupErr := s.cache.MarkSeen(dedupCtx, dedupKey, s.dedupTTL)
		dedupCancel()
		if dedupErr != nil {
			log.Printf("[缓存] 去重错误 key=%s err=%v 视为首次", dedupKey, dedupErr)
			firstSeen = true
		} else {
			firstSeen = seen
		}

		// P1: 去端口化客户端 IP
		clientAddr = remoteIPFromAddr(r.RemoteAddr).String()

		// P0: 按需加载 Profile（内存/磁盘 MISS 时从 Portal 拉取）
		if profileUID != "" && s.profileLoader != nil && !s.engine.HasProfile(profileUID) {
			if err := s.profileLoader(profileUID); err != nil {
				log.Printf("[DoH] 懒加载 profile=%s err=%v", profileUID, err)
			}
		}

		// P0.5: 加载 Profile 元数据（blockResponse、quota、safeSearch 等）
		var pc *config.ProfileConfig
		if profileUID != "" && s.profileConfigLoader != nil {
			var loadErr error
			pc, loadErr = s.profileConfigLoader(profileUID)
			if loadErr != nil {
				log.Printf("[DoH] 加载profile配置 profile=%s err=%v", profileUID, loadErr)
			}
		}

		// 配额检查：quota_status == "exceeded" 时返回 403
		if pc != nil && pc.Quota != nil {
			if status, ok := pc.Quota["quota_status"]; ok {
				if s, ok := status.(string); ok && s == "exceeded" {
					log.Printf("[配额] profile=%s client=%s domain=%s 配额超限", profileUID, clientAddr, domain)
					http.Error(w, "quota exceeded", http.StatusForbidden)
					return
				}
			}
		}

		// Extract device info from headers
		deviceUID, deviceType = resolver.ExtractDeviceFromHeaders(map[string]string{
			"X-Device-ID":   r.Header.Get("X-Device-ID"),
			"X-Device-Type": r.Header.Get("X-Device-Type"),
		})

		// 如果 HTTP 头没有提供 deviceID，从 Profile 设备列表按 sourceIP 匹配
		if deviceUID == "" && pc != nil {
			for _, dev := range pc.Devices {
				if dev.SourceIP == clientAddr {
					deviceUID = dev.DeviceID
					deviceType = dev.DeviceType
					break
				}
			}
		}

		// 从 Profile 读取 safe_search 配置
		safeSearchEnabled := false
		if pc != nil && pc.Parental != nil {
			if v, ok := pc.Parental["safe_search"]; ok {
				if b, ok := v.(bool); ok {
					safeSearchEnabled = b
				}
			}
		}

		// 从 Profile 读取 block_response 模式
		blockMode := blockresponse.ModeNXDomain
		if pc != nil && pc.BlockResponse != "" {
			blockMode = pc.BlockResponse
		}

		// Build resolution context
		ctx := &resolver.ResolutionContext{
			ProfileUID:        profileUID,
			DeviceUID:         deviceUID,
			DeviceType:        deviceType,
			SafeSearchEnabled: safeSearchEnabled,
			ClientIP:          remoteIPFromAddr(r.RemoteAddr),
			Domain:            domain,
			QueryType:         queryType,
			Protocol:          "doh",
		}

		// Run the full resolution pipeline
		decision = s.resolutionLayer.Resolve(ctx)

		if decision.Action == "BLOCK" {
			s.metrics.IncBlocked()

			reply := new(dns.Msg)
			reply.SetReply(msg)
			blockresponse.ApplyTo(reply, msg.Question[0], blockMode)

			packed, err := reply.Pack()
			if err != nil {
				http.Error(w, "Failed to pack response", http.StatusInternalServerError)
				s.metrics.IncErrors()
				return
			}

			w.Header().Set("Content-Type", "application/dns-message")
			w.Write(packed)

			if firstSeen {
				// Log the blocked query
				elapsed := time.Since(startTime).Milliseconds()
				s.logBuffer.Append(logging.LogEntry{
					ProfileUID:     profileUID,
					DeviceUID:      deviceUID,
					DeviceType:     deviceType,
					Domain:         domain,
					Action:         "BLOCK",
					Reason:         decision.Reason,
					Category:       decision.Category,
					ClientIP:       clientAddr,
					QueryType:      queryType,
					ResponseCode:   reply.Rcode,
					ResponseTimeMs: elapsed,
					QueriedAt:      time.Now().Unix(),
					Protocol:       "doh",
				})
			}

			log.Printf("[查询] 拦截 domain=%s 原因=%s 分类=%s profile=%s device=%s",
				domain, decision.Reason, decision.Category, profileUID, deviceUID)
			return
		}

		if decision.Action == "REWRITE" {
			reply := new(dns.Msg)
			reply.SetReply(msg)
			reply.Answer = []dns.RR{
				&dns.CNAME{
					Hdr: dns.RR_Header{
						Name:   msg.Question[0].Name,
						Rrtype: dns.TypeCNAME,
						Class:  dns.ClassINET,
						Ttl:    60,
					},
					Target: dns.Fqdn(decision.Category),
				},
			}

			packed, err := reply.Pack()
			if err != nil {
				http.Error(w, "Failed to pack response", http.StatusInternalServerError)
				s.metrics.IncErrors()
				return
			}

			w.Header().Set("Content-Type", "application/dns-message")
			w.Write(packed)
			s.metrics.IncAllowed()

			if firstSeen {
				elapsed := time.Since(startTime).Milliseconds()
				s.logBuffer.Append(logging.LogEntry{
					ProfileUID:     profileUID,
					DeviceUID:      deviceUID,
					Domain:         domain,
					Action:         "REWRITE",
					Reason:         decision.Reason,
					Category:       decision.Category,
					ClientIP:       clientAddr,
					QueryType:      queryType,
					ResponseCode:   reply.Rcode,
					ResponseTimeMs: elapsed,
					QueriedAt:      time.Now().Unix(),
				})
			}
			return
		}
	}

	// Forward to upstream DNS servers
	reply, _, err := s.client.Exchange(msg, upstreamAddr(s.cfg.Upstream[0]))
	if err != nil {
		if len(s.cfg.Upstream) > 1 {
			reply, _, err = s.client.Exchange(msg, upstreamAddr(s.cfg.Upstream[1]))
		}
		if err != nil {
			http.Error(w, "DNS resolution failed", http.StatusGatewayTimeout)
			s.metrics.IncErrors()
			return
		}
	}

	packed, err := reply.Pack()
	if err != nil {
		http.Error(w, "Failed to pack response", http.StatusInternalServerError)
		s.metrics.IncErrors()
		return
	}

	w.Header().Set("Content-Type", "application/dns-message")
	w.Write(packed)

	s.metrics.IncAllowed()

	// Log allowed query
	if firstSeen && domain != "" {
		elapsed := time.Since(startTime).Milliseconds()
		s.logBuffer.Append(logging.LogEntry{
			ProfileUID:     profileUID,
			DeviceUID:      deviceUID,
			DeviceType:     deviceType,
			Domain:         domain,
			Action:         "ALLOW",
			Reason:         "default",
			ClientIP:       clientAddr,
			QueryType:      queryType,
			ResponseCode:   reply.Rcode,
			ResponseTimeMs: elapsed,
			QueriedAt:      time.Now().Unix(),
			Protocol:       "doh",
		})
	}
}

// dohDedupFingerprint returns a stable per-(client,qname,qtype) fingerprint
// used as the cache key. Same semantics as dnsserver.dedupFingerprint.
func dohDedupFingerprint(clientAddr, domain, qtype string) string {
	h := sha1.New()
	h.Write([]byte(clientAddr))
	h.Write([]byte{0})
	h.Write([]byte(domain))
	h.Write([]byte{0})
	h.Write([]byte(qtype))
	return hex.EncodeToString(h.Sum(nil))
}

// remoteIPFromAddr returns the host portion of "ip:port" or the original
// string if it cannot be split (e.g. unix sockets in tests).
func remoteIPFromAddr(addr string) net.IP {
	host, _, err := net.SplitHostPort(addr)
	if err != nil {
		return net.ParseIP(addr)
	}
	return net.ParseIP(host)
}
