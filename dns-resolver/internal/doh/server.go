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
		ProfileID     string `json:"profile_id"`
		BlockResponse string `json:"block_response"`
	} `json:"profiles"`
}

func (s *Server) loadActiveConfig() (*activeConfig, error) {
	if s.cfg.ControlPlane.ProfilesPath == "" {
		return &activeConfig{}, nil
	}
	path := filepath.Join(s.cfg.ControlPlane.ProfilesPath, "active.json")
	data, err := os.ReadFile(path)
	if err != nil {
		return &activeConfig{}, err
	}
	cfg := &activeConfig{}
	if err := json.Unmarshal(data, cfg); err != nil {
		return nil, err
	}
	return cfg, nil
}

// blockResponseFor returns the configured block_response for the given
// profile UID. Falls back to the first profile's setting, then nxdomain,
// matching the UDP-side fallback chain.
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

	// Profile not found — use the first profile's setting (matches the
	// "default" behavior on the UDP side).
	first := cfg.Profiles[0]
	if first.BlockResponse != "" {
		return first.BlockResponse
	}
	return blockresponse.ModeNXDomain
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
// URL format: /{profile_uid}/dns-query
func (s *Server) handleProfileDNSQuery(w http.ResponseWriter, r *http.Request) {
	path := strings.TrimPrefix(r.URL.Path, "/")

	if !strings.HasSuffix(path, "/dns-query") {
		// Try to match as profile-specific path with dns-query
		// This is a fallback - the /dns-query handler also works
		if strings.Contains(path, "/dns-query") {
			profileUID := resolver.ExtractProfileFromPath(path)
			if profileUID != "" {
				s.resolveDNS(w, r, profileUID)
				return
			}
		}
		http.NotFound(w, r)
		return
	}

	profileUID := strings.TrimSuffix(path, "/dns-query")
	if len(profileUID) == 0 {
		s.resolveDNS(w, r, "")
		return
	}

	s.resolveDNS(w, r, profileUID)
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

		// Extract device info from headers
		deviceUID, deviceType := resolver.ExtractDeviceFromHeaders(map[string]string{
			"X-Device-ID":   r.Header.Get("X-Device-ID"),
			"X-Device-Type": r.Header.Get("X-Device-Type"),
		})

		// Build resolution context
		ctx := &resolver.ResolutionContext{
			ProfileUID: profileUID,
			DeviceUID:  deviceUID,
			DeviceType: deviceType,
			ClientIP:   remoteIPFromAddr(r.RemoteAddr),
			Domain:     domain,
			QueryType:  queryType,
			Protocol:   "doh",
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
			blockresponse.ApplyTo(reply, msg.Question[0], s.blockResponseFor(profileUID))

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
					Domain:         domain,
					Action:         "BLOCK",
					Reason:         decision.Reason,
					Category:       decision.Category,
					ClientIP:       r.RemoteAddr,
					QueryType:      queryType,
					ResponseCode:   reply.Rcode,
					ResponseTimeMs: elapsed,
					QueriedAt:      time.Now().Unix(),
				})
			}

			log.Printf("[BLOCK] %s -> %s reason=%s category=%s profile=%s",
				domain, decision.Reason, decision.Category, profileUID, deviceUID)
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
			ClientIP:       r.RemoteAddr,
			QueryType:      queryType,
			ResponseCode:   reply.Rcode,
			ResponseTimeMs: elapsed,
			QueriedAt:      time.Now().Unix(),
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
