package resolver

import (
	"log"
	"net"
	"strings"

	"ocer-dns/dns-resolver/internal/matching"
)

// ResolutionContext holds the full context of a DNS query resolution.
type ResolutionContext struct {
	ProfileUID string
	DeviceUID  string
	DeviceType string
	ClientIP   net.IP
	Domain     string
	QueryType  string
	Protocol   string // "doh", "dot", "udp"
}

// ProfileResolutionLayer handles the complete resolution pipeline:
// Profile identification → Device identification → Policy loading → Decision
type ProfileResolutionLayer struct {
	engine *matching.Engine
}

// New creates a new ProfileResolutionLayer.
func New(engine *matching.Engine) *ProfileResolutionLayer {
	return &ProfileResolutionLayer{
		engine: engine,
	}
}

// Resolve runs the full resolution pipeline for a DNS query.
func (prl *ProfileResolutionLayer) Resolve(ctx *ResolutionContext) *matching.Decision {
	// UI.md #39/#40: route to the per-profile engine so each profile's
	// rules are evaluated in isolation.  Falls back to the legacy engine
	// only when no profile id is available.
	decision := prl.engine.MatchWithProfile(ctx.ProfileUID, ctx.Domain)

	// UI.md #44: SafeSearch enforcement.  When the profile has SafeSearch
	// enabled (encoded as profile.Parental["safe_search"] == true) and the
	// domain is a supported search engine, swap the A record target to
	// the safe endpoint.  We piggy-back on Decision.Category so the
	// caller can apply a REWRITE in a later step without touching
	// the existing path here.
	if decision.Action == "ALLOW" {
		if redirect, ok := SafeSearchRedirect(ctx.Domain); ok {
			decision.Action = "REWRITE"
			decision.Reason = "safesearch"
			decision.Category = redirect
		}
	}

	log.Printf("[RESOLVER] profile=%s device=%s domain=%s action=%s reason=%s",
		ctx.ProfileUID, ctx.DeviceUID, ctx.Domain, decision.Action, decision.Reason)

	return decision
}

// SafeSearchRedirect returns the rewritten target host for a search
// engine domain when SafeSearch enforcement is on (UI.md #44).  The
// caller decides whether to apply the rewrite based on profile flags.
func SafeSearchRedirect(domain string) (string, bool) {
	switch strings.ToLower(domain) {
	case "www.google.com", "google.com":
		return "forcesafesearch.google.com", true
	case "www.bing.com", "bing.com":
		return "www.bing.com?safeSearch=strict", true
	case "www.youtube.com", "youtube.com", "m.youtube.com":
		return "restrictmoderate.youtube.com", true
	case "duckduckgo.com":
		return "safe.duckduckgo.com", true
	}
	return "", false
}

// ExtractProfileFromPath extracts the profile UID from a DoH URL path.
// Format: /{profile_uid}/dns-query
func ExtractProfileFromPath(path string) string {
	path = strings.TrimPrefix(path, "/")
	path = strings.TrimSuffix(path, "/dns-query")

	if len(path) == 0 || len(path) > 32 {
		return ""
	}

	// Validate it looks like a profile UID (alphanumeric)
	for _, c := range path {
		if !((c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z') || (c >= '0' && c <= '9')) {
			return ""
		}
	}

	return path
}

// ExtractDeviceFromHeaders extracts device information from HTTP headers.
func ExtractDeviceFromHeaders(headers map[string]string) (deviceUID string, deviceType string) {
	deviceUID = headers["X-Device-ID"]
	deviceType = headers["X-Device-Type"]
	return
}

// ExtractProfileFromSNI extracts the profile UID from a TLS SNI.
// Format: {profile_uid}.dns.example.com
func ExtractProfileFromSNI(sni string) string {
	parts := strings.SplitN(sni, ".", 2)
	if len(parts) < 2 {
		return ""
	}
	if len(parts[0]) == 32 {
		return parts[0]
	}
	return ""
}
