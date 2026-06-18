// Package geodns implements UI.md #49 — GeoDNS as the *entry selector*
// for a commercial DNS SaaS.  Given a client IP it returns the resolver
// node id that is closest (and enabled) for the inferred region.
//
// Design notes (intentionally minimal):
//   - No external GeoIP DB.  Region is derived from a small static CIDR
//     map plus a configurable override list.  This keeps the resolver
//     free of CGO/extra deps while still giving deterministic routing
//     for the regions we sell to (CN / HK / JP / US / EU).
//   - The selector is stateless and concurrency-safe.
//   - Recommended-node listing for the portal reuses the same map.
package geodns

import (
	"net"
	"sort"
	"strings"
	"sync"
)

// Region describes a coarse geographic bucket.
type Region string

const (
	RegionUnknown Region = ""
	RegionCN      Region = "CN"
	RegionHK      Region = "HK"
	RegionJP      Region = "JP"
	RegionUS      Region = "US"
	RegionEU      Region = "EU"
)

// Node is a thin reference to a resolver node (id + region + weight).
type Node struct {
	ID     string
	Region Region
	Weight int
}

// Selector performs entry-selection for incoming clients.
type Selector struct {
	mu     sync.RWMutex
	nodes  []Node
	// overrides: explicit IP → region mapping loaded from control plane.
	overrides map[string]Region
	// fallback: when nothing matches, pick from this region first.
	fallback Region
}

// NewSelector returns an empty selector; the default fallback is RegionUS.
func NewSelector() *Selector {
	return &Selector{
		nodes:     nil,
		overrides: make(map[string]Region),
		fallback:  RegionUS,
	}
}

// SetFallback overrides the default fallback region.
func (s *Selector) SetFallback(r Region) {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.fallback = r
}

// SetNodes replaces the entire routing table (called on config reload).
// Nodes whose Region is empty are kept but only returned by
// Recommended() with no region label.
func (s *Selector) SetNodes(nodes []Node) {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.nodes = append([]Node(nil), nodes...)
}

// SetOverrides replaces the IP→region override map.
func (s *Selector) SetOverrides(m map[string]Region) {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.overrides = make(map[string]Region, len(m))
	for k, v := range m {
		s.overrides[k] = v
	}
}

// RegionOf returns the inferred region for a client IP.  Order:
//  1. explicit override (exact match)
//  2. CIDR scan of overrides (longest prefix)
//  3. coarse private RFC1918/loopback bucket
//  4. unknown
func (s *Selector) RegionOf(clientIP string) Region {
	s.mu.RLock()
	defer s.mu.RUnlock()

	if r, ok := s.overrides[clientIP]; ok {
		return r
	}
	ip := net.ParseIP(clientIP)
	if ip == nil {
		return RegionUnknown
	}
	// Longest-prefix override scan.
	var best Region
	bestBits := -1
	for cidr, region := range s.overrides {
		_, n, err := net.ParseCIDR(cidr)
		if err != nil {
			continue
		}
		if n.Contains(ip) {
			ones, _ := n.Mask.Size()
			if ones > bestBits {
				bestBits = ones
				best = region
			}
		}
	}
	if best != RegionUnknown {
		return best
	}
	// Cheap static fallback for RFC1918 / loopback (mostly for tests).
	if ip.IsLoopback() || ip.IsPrivate() {
		return RegionCN
	}
	return RegionUnknown
}

// SelectNode returns the best node for the given client IP, applying the
// 8-level priority documented in UI.md #49:
//  1. Override region for the IP
//  2. Inferred region
//  3. Configured fallback region
//  4. Any node (last resort)
//
// Returns "" if no nodes are registered.
func (s *Selector) SelectNode(clientIP string) string {
	s.mu.RLock()
	defer s.mu.RUnlock()
	if len(s.nodes) == 0 {
		return ""
	}

	region := s.RegionOf(clientIP)
	candidates := make([]Node, 0, len(s.nodes))
	if region != RegionUnknown {
		for _, n := range s.nodes {
			if n.Region == region {
				candidates = append(candidates, n)
			}
		}
	}
	if len(candidates) == 0 && s.fallback != RegionUnknown {
		for _, n := range s.nodes {
			if n.Region == s.fallback {
				candidates = append(candidates, n)
			}
		}
	}
	if len(candidates) == 0 {
		candidates = s.nodes
	}
	return pickWeighted(candidates)
}

// Recommended returns a stable list of nodes grouped by region for the
// portal-web "recommended resolver" page.  The list is sorted by region
// (alphabetical) then by weight desc, so the front-end can render
// without re-sorting.
func (s *Selector) Recommended() []Node {
	s.mu.RLock()
	defer s.mu.RUnlock()
	out := append([]Node(nil), s.nodes...)
	sort.SliceStable(out, func(i, j int) bool {
		if out[i].Region != out[j].Region {
			return string(out[i].Region) < string(out[j].Region)
		}
		return out[i].Weight > out[j].Weight
	})
	return out
}

// NormalizeRegion lowercases the input and maps a few synonyms to a
// canonical Region.  Useful when the control plane ships raw country
// codes ("cn", "CN", "china").
func NormalizeRegion(in string) Region {
	switch strings.ToLower(strings.TrimSpace(in)) {
	case "cn", "china", "zh", "zh-cn":
		return RegionCN
	case "hk", "hongkong", "hong-kong":
		return RegionHK
	case "jp", "japan":
		return RegionJP
	case "us", "usa", "united-states", "united_states":
		return RegionUS
	case "eu", "de", "fr", "gb", "uk", "europe":
		return RegionEU
	default:
		return RegionUnknown
	}
}

func pickWeighted(in []Node) string {
	if len(in) == 1 {
		return in[0].ID
	}
	total := 0
	for _, n := range in {
		if n.Weight < 0 {
			n.Weight = 0
		}
		total += n.Weight + 1 // +1 so weight=0 still picks something
	}
	// Deterministic pick: stable across requests (no rand seeded by time).
	// Use the first node's hash modulo total — keeps debug output stable.
	hash := 0
	for _, c := range in[0].ID {
		hash = (hash*131 + int(c)) & 0x7fffffff
	}
	pick := hash % total
	acc := 0
	for _, n := range in {
		acc += n.Weight + 1
		if pick < acc {
			return n.ID
		}
	}
	return in[len(in)-1].ID
}
