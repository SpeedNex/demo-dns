package resolver

import (
	"log"
	"net"
	"regexp"
	"strings"
	"sync"

	"ocer-dns/dns-resolver/internal/matching"
	"ocer-dns/dns-resolver/internal/rules"
)

// ResolutionContext holds the full context of a DNS query resolution.
type ResolutionContext struct {
	ProfileUID        string
	DeviceUID         string
	DeviceType        string
	SafeSearchEnabled bool
	ClientIP          net.IP
	Domain            string
	QueryType         string
	Protocol          string // "doh", "dot", "udp"
}

// profileSecurity stores the parsed security algorithm config for a profile.
type profileSecurity struct {
	IDNHomograph           bool
	TypoSquatting          bool
	DGAProtection          bool
	DNSRebind              bool
	BlockedTLD             bool
	BlockDynamicDNS        bool
	BlockDisguisedTrackers bool
	DnsRebindWhitelist     []string
	EntropyThreshold       float64
	DigitRatio             float64
	TypoThreshold          int
	BrandDomains           []string
}

// ProfileResolutionLayer handles the complete resolution pipeline:
// Profile identification → Device identification → Policy loading → Decision
type ProfileResolutionLayer struct {
	engine   *matching.Engine
	mu       sync.RWMutex
	security map[string]*profileSecurity
}

var profileUIDPattern = regexp.MustCompile(`^[a-f0-9]{6}$`)

// IsValidProfileID reports whether id is a syntactically valid profile ID.
// The current format is a 6-character lowercase hex string.
// Keep in sync with portal-web's Profile ID generation logic.
func IsValidProfileID(id string) bool {
	return profileUIDPattern.MatchString(id)
}

// New creates a new ProfileResolutionLayer.
func New(engine *matching.Engine) *ProfileResolutionLayer {
	return &ProfileResolutionLayer{
		engine:   engine,
		security: make(map[string]*profileSecurity),
	}
}

// LoadSecurityConfig stores or updates the security algorithm config for a profile.
func (prl *ProfileResolutionLayer) LoadSecurityConfig(profileID string, cfg map[string]any) {
	ps := &profileSecurity{
		IDNHomograph:           toBool(cfg["idn_homograph"]),
		TypoSquatting:          toBool(cfg["typo_squatting"]),
		DGAProtection:          toBool(cfg["dga_protection"]),
		DNSRebind:              toBool(cfg["dns_rebind"]),
		BlockedTLD:             toBool(cfg["block_tld"]),
		BlockDynamicDNS:        toBool(cfg["block_dynamic_dns"]),
		BlockDisguisedTrackers: toBool(cfg["block_disguised_trackers"]),
		EntropyThreshold:       4.2,
		DigitRatio:             0.5,
		TypoThreshold:          1,
	}
	if et, ok := toFloatOk(cfg["dga_entropy_threshold"]); ok && et > 0 {
		ps.EntropyThreshold = et
	}
	if dr, ok := toFloatOk(cfg["dga_digit_ratio"]); ok && dr > 0 {
		ps.DigitRatio = dr
	}
	if tt, ok := toIntOk(cfg["typo_threshold"]); ok && tt > 0 {
		ps.TypoThreshold = tt
	}
	if wl, ok := cfg["dns_rebind_whitelist"]; ok {
		if list, ok2 := wl.([]string); ok2 {
			ps.DnsRebindWhitelist = list
		}
	}
	if brands, ok := cfg["brand_domains"]; ok {
		if list, ok2 := brands.([]string); ok2 {
			ps.BrandDomains = list
		} else if list, ok2 := brands.([]any); ok2 {
			for _, b := range list {
				if s, ok3 := b.(string); ok3 {
					ps.BrandDomains = append(ps.BrandDomains, s)
				}
			}
		}
	}

	prl.mu.Lock()
	prl.security[profileID] = ps
	prl.mu.Unlock()
}

// RemoveSecurityConfig removes the security config for a profile.
func (prl *ProfileResolutionLayer) RemoveSecurityConfig(profileID string) {
	prl.mu.Lock()
	delete(prl.security, profileID)
	prl.mu.Unlock()
}

// GetDNSRebindConfig returns the DNS rebinding protection config for a profile.
func (prl *ProfileResolutionLayer) GetDNSRebindConfig(profileID string) (enabled bool, whitelist []string) {
	prl.mu.RLock()
	defer prl.mu.RUnlock()
	ps, ok := prl.security[profileID]
	if !ok {
		return false, nil
	}
	return ps.DNSRebind, ps.DnsRebindWhitelist
}

// GetDisguisedTrackersConfig returns whether disguised tracker protection is enabled for a profile.
func (prl *ProfileResolutionLayer) GetDisguisedTrackersConfig(profileID string) bool {
	prl.mu.RLock()
	defer prl.mu.RUnlock()
	ps, ok := prl.security[profileID]
	if !ok {
		return false
	}
	return ps.BlockDisguisedTrackers
}

// Resolve runs the full resolution pipeline for a DNS query.
func (prl *ProfileResolutionLayer) Resolve(ctx *ResolutionContext) *matching.Decision {
	// UI.md #39/#40: route to the per-profile engine so each profile's
	// rules are evaluated in isolation.  Falls back to the legacy engine
	// only when no profile id is available.
	decision := prl.engine.MatchWithProfile(ctx.ProfileUID, ctx.Domain)
	if decision.Action != "ALLOW" {
		return decision
	}

	// Phase 1: Pre-forwarding security algorithm checks (domain-based).
	// These run when the matching engine returned ALLOW (no rule match).
	prl.mu.RLock()
	ps, hasSecurity := prl.security[ctx.ProfileUID]
	prl.mu.RUnlock()

	if hasSecurity && ps != nil {
		// IDN Homograph protection
		if ps.IDNHomograph {
			idnRes := rules.CheckIDNHomograph(ctx.Domain, false)
			if idnRes.Blocked {
				log.Printf("[安全] profile=%s domain=%s 类型=idn_homograph 原因=%s",
					ctx.ProfileUID, ctx.Domain, idnRes.Reason)
				return &matching.Decision{Action: "BLOCK", Reason: "idn_homograph", Category: "security"}
			}
		}

		// DGA protection
		if ps.DGAProtection {
			dgaRes := rules.CheckDGA(ctx.Domain, ps.EntropyThreshold, ps.DigitRatio)
			if dgaRes.Blocked {
				log.Printf("[安全] profile=%s domain=%s 类型=dga entropy=%.2f ratio=%.2f len=%d",
					ctx.ProfileUID, ctx.Domain, dgaRes.Entropy, dgaRes.Ratio, dgaRes.Length)
				return &matching.Decision{Action: "BLOCK", Reason: "dga", Category: "security"}
			}
		}

		// Typosquatting protection
		if ps.TypoSquatting && len(ps.BrandDomains) > 0 {
			typoRes := rules.CheckTyposquatting(ctx.Domain, ps.BrandDomains, ps.TypoThreshold)
			if typoRes.Blocked {
				log.Printf("[安全] profile=%s domain=%s 类型=typosquatting brand=%s dist=%d",
					ctx.ProfileUID, ctx.Domain, typoRes.Brand, typoRes.Distance)
				return &matching.Decision{Action: "BLOCK", Reason: "typosquatting", Category: "security"}
			}
		}

		// Blocked TLD protection
		if ps.BlockedTLD {
			tldRes := rules.CheckBlockedTLD(ctx.Domain)
			if tldRes.Blocked {
				log.Printf("[安全] profile=%s domain=%s 类型=blocked-tld tld=%s",
					ctx.ProfileUID, ctx.Domain, tldRes.TLD)
				return &matching.Decision{Action: "BLOCK", Reason: "blocked_tld", Category: "security"}
			}
		}

		// Dynamic DNS protection
		if ps.BlockDynamicDNS {
			dynRes := rules.CheckDynDNS(ctx.Domain)
			if dynRes.Blocked {
				log.Printf("[安全] profile=%s domain=%s 类型=dynamic-dns provider=%s",
					ctx.ProfileUID, ctx.Domain, dynRes.Provider)
				return &matching.Decision{Action: "BLOCK", Reason: "dynamic_dns", Category: "security"}
			}
		}
	}

	// UI.md #44: SafeSearch enforcement.  When the profile has SafeSearch
	// enabled (encoded as profile.Parental["safe_search"] == true) and the
	// domain is a supported search engine, swap the A record target to
	// the safe endpoint.  We piggy-back on Decision.Category so the
	// caller can apply a REWRITE in a later step without touching
	// the existing path here.
	if ctx.SafeSearchEnabled {
		if redirect, ok := SafeSearchRedirect(ctx.Domain); ok {
			decision.Action = "REWRITE"
			decision.Reason = "safesearch"
			decision.Category = redirect
		}
	}

	log.Printf("[查询] profile=%s device=%s domain=%s action=%s reason=%s",
		ctx.ProfileUID, ctx.DeviceUID, ctx.Domain, decision.Action, decision.Reason)

	return decision
}

// Helper functions for type conversion
func toBool(v any) bool {
	if v == nil {
		return false
	}
	switch val := v.(type) {
	case bool:
		return val
	case string:
		return val == "true" || val == "1"
	}
	return false
}

func toFloatOk(v any) (float64, bool) {
	if v == nil {
		return 0, false
	}
	switch val := v.(type) {
	case float64:
		return val, true
	case int:
		return float64(val), true
	case int64:
		return float64(val), true
	}
	return 0, false
}

func toIntOk(v any) (int, bool) {
	if v == nil {
		return 0, false
	}
	switch val := v.(type) {
	case int:
		return val, true
	case float64:
		return int(val), true
	case int64:
		return int(val), true
	}
	return 0, false
}

// SafeSearchRedirect returns the rewritten target host for a search
// engine domain when SafeSearch enforcement is on (UI.md #44).  The
// caller decides whether to apply the rewrite based on profile flags.
func SafeSearchRedirect(domain string) (string, bool) {
	switch strings.ToLower(domain) {
	case "www.google.com", "google.com":
		return "forcesafesearch.google.com", true
	case "www.bing.com", "bing.com":
		return "strict.bing.com", true
	case "www.youtube.com", "youtube.com", "m.youtube.com":
		return "restrictmoderate.youtube.com", true
	case "duckduckgo.com":
		return "safe.duckduckgo.com", true
	}
	return "", false
}

// ExtractProfileFromPath extracts the profile UID from a DoH URL path.
// Format: /{profile_id}/dns-query
func ExtractProfileFromPath(path string) string {
	path = strings.TrimPrefix(path, "/")
	path = strings.TrimSuffix(path, "/dns-query")

	if !profileUIDPattern.MatchString(strings.ToLower(path)) {
		return ""
	}

	return strings.ToLower(path)
}

// ExtractDeviceFromHeaders extracts device information from HTTP headers.
func ExtractDeviceFromHeaders(headers map[string]string) (deviceUID string, deviceType string) {
	deviceUID = headers["X-Device-ID"]
	deviceType = headers["X-Device-Type"]
	return
}

// ExtractProfileFromSNI extracts the profile UID from a TLS SNI.
// Format: {profile_id}.dns.example.com
func ExtractProfileFromSNI(sni string) string {
	parts := strings.SplitN(sni, ".", 2)
	if len(parts) < 2 {
		return ""
	}
	profileUID := strings.ToLower(parts[0])
	if profileUIDPattern.MatchString(profileUID) {
		return profileUID
	}
	return ""
}
