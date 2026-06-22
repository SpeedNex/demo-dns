package doh

import (
	"context"
	"crypto/sha1"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"io"
	"log"
	"net"
	"net/http"
	"os"
	"path/filepath"
	"regexp"
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
// The HTTP listener is plain HTTP (ListenAndServe, no TLS) on purpose: DoH
// production deployments front this listener with a TLS-terminating reverse
// proxy (nginx, Caddy, Envoy) that owns the certificates. The resolver
// itself only needs to speak RFC 8484 wire format.
type Server struct {
	cfg             *config.Config
	engine          *matching.Engine
	resolutionLayer *resolver.ProfileResolutionLayer
	logBuffer       *logging.Buffer
	metrics         *metrics.Metrics
	cache           *cache.Cache
	client          *dns.Client
	hostname        string
	dedupTTL        time.Duration
	validator       *validation.Validator // optional (UI.md #40)
}

// NewServer creates a new DoH server.
func NewServer(cfg *config.Config, engine *matching.Engine,
	resolutionLayer *resolver.ProfileResolutionLayer,
	logBuffer *logging.Buffer, metrics *metrics.Metrics, cacheClient *cache.Cache) *Server {

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
		hostname: hostname,
		dedupTTL: 5 * time.Second,
	}
}

// SetValidator wires an optional profile validator (UI.md #40).  When set,
// resolveDNS rejects requests whose profile uid fails ownership /
// subscription checks instead of resolving them.
func (s *Server) SetValidator(v *validation.Validator) {
	s.validator = v
}

// upstreamAddr returns the full address with default port if not specified.
func upstreamAddr(addr string) string {
	if strings.Contains(addr, ":") {
		return addr
	}
	return addr + ":53"
}

// activeConfig mirrors the UDP-side schema; DoH only needs profile_id →
// block_response to keep both servers' blocked-query response consistent.
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

var profileUIDPattern = regexp.MustCompile(`^[0-9a-f]{6}$`)

func (s *Server) loadActiveConfig() (*activeConfig, error) {
	if s.cfg.ControlPlane.ProfilesPath == "" {
		log.Printf("doh: loadActiveConfig: ProfilesPath is empty")
		return &activeConfig{}, nil
	}
	path := filepath.Join(s.cfg.ControlPlane.ProfilesPath, "active.json")
	data, err := os.ReadFile(path)
	if err != nil {
		log.Printf("doh: loadActiveConfig: cannot read %s: %v", path, err)
		return &activeConfig{}, err
	}
	cfg := &activeConfig{}
	if err := json.Unmarshal(data, cfg); err != nil {
		log.Printf("doh: loadActiveConfig: json unmarshal error: %v", err)
		return nil, err
	}
	log.Printf("doh: loadActiveConfig: loaded %d profiles from %s", len(cfg.Profiles), path)
	return cfg, nil
}

// blockResponseFor returns the configured block_response for the given
// profile UID. Unknown profiles never fall back to another user's config.
func (s *Server) blockResponseFor(profileUID string) string {
	cfg, err := s.loadActiveConfig()
	if err != nil || len(cfg.Profiles) == 0 {
		return blockresponse.ModeNXDomain
	}

	for _, p := range cfg.Profiles {
		if p.ProfileID == profileUID {
			if p.BlockResponse != "" {
				return p.BlockResponse
			}
			return blockresponse.ModeNXDomain
		}
	}

	return blockresponse.ModeNXDomain
}

func (s *Server) resolveRuntimeProfile(remoteAddr string, requestedProfile string) (profileID string, blockResponse string, deviceID string, safeSearch bool, ok bool) {
	cfg, err := s.loadActiveConfig()
	if err != nil {
		log.Printf("doh: resolveRuntimeProfile: loadActiveConfig error: %v", err)
		return "", blockresponse.ModeNXDomain, "", false, false
	}
	if len(cfg.Profiles) == 0 {
		log.Printf("doh: resolveRuntimeProfile: no profiles in active.json (requestedProfile=%s)", requestedProfile)
		return "", blockresponse.ModeNXDomain, "", false, false
	}

	clientIP := remoteIPFromAddr(remoteAddr)
	clientIPText := ""
	if clientIP != nil {
		clientIPText = clientIP.String()
	}

	for _, profile := range cfg.Profiles {
		if profile.ProfileID == "" {
			continue
		}

		if requestedProfile != "" {
			if profile.ProfileID != requestedProfile {
				continue
			}
			return profile.ProfileID, firstNonEmpty(profile.BlockResponse, blockresponse.ModeNXDomain), "", boolFromMap(profile.Parental, "safe_search") || boolFromMap(profile.Parental, "force_safe_search"), true
		}

		for _, device := range profile.Devices {
			if device.SourceIP == "" || device.SourceIP != clientIPText {
				continue
			}

			return profile.ProfileID, firstNonEmpty(profile.BlockResponse, blockresponse.ModeNXDomain), device.DeviceID, boolFromMap(profile.Parental, "safe_search") || boolFromMap(profile.Parental, "force_safe_search"), true
		}
	}

	return "", blockresponse.ModeNXDomain, "", false, false
}

// Handler returns the HTTP handler for DoH endpoints.
func (s *Server) Handler() http.Handler {
	mux := http.NewServeMux()
	mux.HandleFunc("/dns-query", s.handleDNSQuery)

	// Profile-specific DoH endpoints: /{profile_uid}/dns-query
	mux.HandleFunc("/", s.handleProfileDNSQuery)

	return mux
}

// handleDNSQuery handles standard DoH requests (RFC 8484).
func (s *Server) handleDNSQuery(w http.ResponseWriter, r *http.Request) {
	s.resolveDNS(w, r, "")
}

// handleProfileDNSQuery handles profile-specific DoH requests.
// URL format: /{profile_uid} or /{profile_uid}/dns-query
// Only stable 6-char hex profile_uid values are accepted.
func (s *Server) handleProfileDNSQuery(w http.ResponseWriter, r *http.Request) {
	path := strings.TrimPrefix(r.URL.Path, "/")

	// Support both /{profile_uid} and /{profile_uid}/dns-query
	profileUID := strings.TrimSuffix(path, "/dns-query")
	if profileUID == "" {
		s.resolveDNS(w, r, "")
		return
	}

	// Validate it looks like a profile UID
	if isValidProfileUID(profileUID) {
		s.resolveDNS(w, r, profileUID)
		return
	}

	http.NotFound(w, r)
}

// isValidProfileUID checks if a string looks like a valid profile UID.
func isValidProfileUID(uid string) bool {
	return profileUIDPattern.MatchString(uid)
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
			log.Printf("doh: profile validation failed: %v (profile=%s user=%s)", err, profileUID, userID)
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

	if len(msg.Question) > 0 {
		domain = msg.Question[0].Name
		queryType = dns.TypeToString[msg.Question[0].Qtype]

		// Dedup before the resolution pipeline so log buffer + control plane
		// don't get flooded by repeated client retransmits. Disabled cache
		// is a no-op, so this branch is free in non-Redis deployments.
		dedupKey := dohDedupFingerprint(r.RemoteAddr, domain, queryType)
		dedupCtx, dedupCancel := context.WithTimeout(r.Context(), 250*time.Millisecond)
		seen, dedupErr := s.cache.MarkSeen(dedupCtx, dedupKey, s.dedupTTL)
		dedupCancel()
		if dedupErr != nil {
			log.Printf("dedup: cache error for key %s: %v (treating as first-seen)", dedupKey, dedupErr)
			firstSeen = true
		} else {
			firstSeen = seen
		}

		// P1: 去端口化客户端 IP，防止将 127.0.0.1:55309 记录为设备标识
		clientAddr = remoteIPFromAddr(r.RemoteAddr).String()

		resolvedProfileUID, blockMode, runtimeDeviceID, safeSearchEnabled, ok := s.resolveRuntimeProfile(r.RemoteAddr, profileUID)
		if !ok {
			http.Error(w, "profile not found", http.StatusForbidden)
			s.metrics.IncErrors()
			return
		}
		profileUID = resolvedProfileUID

		// P0: 检查配额状态 — quota_status=exceeded 时拒绝解析
		if s.isQuotaExceeded(profileUID) {
			http.Error(w, "quota exceeded", http.StatusForbidden)
			s.metrics.IncErrors()
			return
		}

		// Extract device info from headers
		deviceUID, deviceType := resolver.ExtractDeviceFromHeaders(map[string]string{
			"X-Device-ID":   r.Header.Get("X-Device-ID"),
			"X-Device-Type": r.Header.Get("X-Device-Type"),
		})
		if deviceUID == "" {
			deviceUID = runtimeDeviceID
		}

		// Build resolution context
		ctx := &resolver.ResolutionContext{
			ProfileUID:        resolvedProfileUID,
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

			// Reuse the shared blockresponse package so DoH replies
			// follow the same profile-configured policy (nxdomain /
			// refused / zero_ip) as the UDP server. Without this,
			// DoH used to hardcode NXDOMAIN regardless of profile
			// configuration, which made the two protocols behave
			// differently for the same client.
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
					ProfileUID:     resolvedProfileUID,
					DeviceUID:      deviceUID,
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

			log.Printf("[BLOCK] %s reason=%s category=%s profile=%s device=%s",
				domain, decision.Reason, decision.Category, resolvedProfileUID, deviceUID)
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
					ProfileUID:     resolvedProfileUID,
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
