package matching

import (
	"strings"
	"sync"
)

// Decision represents the result of a DNS query match.
type Decision struct {
	Action   string `json:"action"`   // ALLOW / BLOCK / REWRITE / DROP
	Reason   string `json:"reason"`   // allowlist / blocklist / security / parental / adblock
	Category string `json:"category"` // malware / phishing / adult / gambling / ads / ...
}

// Engine is the core rule matching engine with 8-level policy priority.
//
// Priority (highest → lowest):
//  1. Allow List (user-defined)
//  2. Block List (user-defined)
//  3. Security - Malware
//  4. Security - Phishing
//  5. Security - Ransomware / Cryptojacking / Botnet C2
//  6. Parental Control (adult / gambling / violence / social_media / ...)
//  7. Ad Block (OISD / AdGuard / EasyList)
//  8. Default Allow
type Engine struct {
	mu sync.RWMutex

	// Legacy single-profile fields (kept for backward compatibility).
	allowExact         map[string]bool
	allowTrie          *Trie
	blockExact         map[string]bool
	blockTrie          *Trie
	securityCategories map[string]map[string]bool // category -> domain set
	parentalCategories map[string]map[string]bool // category -> domain set
	adBlockDomains     map[string]bool
	adBlockTrie        *Trie

	// profileID-isolated engines (UI.md #39).  Keyed by profile id.
	// When present, Match(profileID, domain) routes to the per-profile engine.
	// Falls back to legacy fields when profileID is empty / unknown.
	profileEngines map[string]*profileEngine
}

// profileEngine is a per-profile rule set (UI.md #39).
type profileEngine struct {
	profileID          string
	allowExact         map[string]bool
	allowTrie          *Trie
	blockExact         map[string]bool
	blockTrie          *Trie
	securityCategories map[string]map[string]bool
	parentalCategories map[string]map[string]bool
	adBlockDomains     map[string]bool
	adBlockTrie        *Trie
}

// NewEngine creates a new rule matching engine.
func NewEngine() *Engine {
	return &Engine{
		allowExact:         make(map[string]bool),
		blockExact:        make(map[string]bool),
		allowTrie:          NewTrie(),
		blockTrie:          NewTrie(),
		securityCategories: make(map[string]map[string]bool),
		parentalCategories: make(map[string]map[string]bool),
		adBlockDomains:     make(map[string]bool),
		adBlockTrie:        NewTrie(),
		profileEngines:     make(map[string]*profileEngine),
	}
}

// LoadAllowRules replaces the allow rule set (Level 1).
func (e *Engine) LoadAllowRules(exact []string, wildcard []string) {
	e.mu.Lock()
	defer e.mu.Unlock()

	e.allowExact = make(map[string]bool)
	e.allowTrie = NewTrie()

	for _, domain := range exact {
		e.allowExact[normalizeDomain(domain)] = true
	}
	for _, domain := range wildcard {
		e.allowTrie.Insert(reverseDomain(normalizeDomain(domain)))
	}
}

// LoadBlockRules replaces the block rule set (Level 2).
func (e *Engine) LoadBlockRules(exact []string, wildcard []string) {
	e.mu.Lock()
	defer e.mu.Unlock()

	e.blockExact = make(map[string]bool)
	e.blockTrie = NewTrie()

	for _, domain := range exact {
		e.blockExact[normalizeDomain(domain)] = true
	}
	for _, domain := range wildcard {
		e.blockTrie.Insert(reverseDomain(normalizeDomain(domain)))
	}
}

// LoadSecurityCategory loads a specific security category (Level 3-5).
func (e *Engine) LoadSecurityCategory(category string, domains []string) {
	e.mu.Lock()
	defer e.mu.Unlock()

	set := make(map[string]bool, len(domains))
	for _, d := range domains {
		set[normalizeDomain(d)] = true
	}
	e.securityCategories[category] = set
}

// LoadParentalCategory loads a parental control category (Level 6).
func (e *Engine) LoadParentalCategory(category string, domains []string) {
	e.mu.Lock()
	defer e.mu.Unlock()

	set := make(map[string]bool, len(domains))
	for _, d := range domains {
		set[normalizeDomain(d)] = true
	}
	e.parentalCategories[category] = set
}

// LoadAdBlockDomains loads the ad blocking domain set (Level 7).
func (e *Engine) LoadAdBlockDomains(exact []string, wildcard []string) {
	e.mu.Lock()
	defer e.mu.Unlock()

	e.adBlockDomains = make(map[string]bool)
	e.adBlockTrie = NewTrie()

	for _, domain := range exact {
		e.adBlockDomains[normalizeDomain(domain)] = true
	}
	for _, domain := range wildcard {
		e.adBlockTrie.Insert(reverseDomain(normalizeDomain(domain)))
	}
}

// Match checks a domain against all rule sets with the defined priority.
func (e *Engine) Match(domain string) *Decision {
	e.mu.RLock()
	defer e.mu.RUnlock()

	domain = normalizeDomain(domain)

	// Level 1: Allow list (highest priority)
	if e.matchExactSet(e.allowExact, domain) || e.allowTrie.Search(reverseDomain(domain)) {
		return &Decision{Action: "ALLOW", Reason: "allowlist"}
	}

	// Level 2: Block list
	if e.matchExactSet(e.blockExact, domain) || e.blockTrie.Search(reverseDomain(domain)) {
		return &Decision{Action: "BLOCK", Reason: "blocklist"}
	}

	// Level 3-5: Security categories
	for category, set := range e.securityCategories {
		if set[domain] {
			return &Decision{Action: "BLOCK", Reason: "security", Category: category}
		}
	}

	// Level 6: Parental control categories
	for category, set := range e.parentalCategories {
		if set[domain] {
			return &Decision{Action: "BLOCK", Reason: "parental", Category: category}
		}
	}

	// Level 7: Ad block
	if e.adBlockDomains[domain] || e.adBlockTrie.Search(reverseDomain(domain)) {
		return &Decision{Action: "BLOCK", Reason: "adblock", Category: "ads"}
	}

	// Level 8: Default allow
	return &Decision{Action: "ALLOW", Reason: "default"}
}

func (e *Engine) matchExactSet(set map[string]bool, domain string) bool {
	return set[domain]
}

// normalizeDomain normalizes a domain for matching.
func normalizeDomain(domain string) string {
	domain = strings.TrimSuffix(domain, ".")
	domain = strings.ToLower(domain)
	return strings.TrimPrefix(domain, "*.")
}

// reverseDomain reverses the labels of a domain for Trie matching.
// e.g., "example.com" -> "com.example"
func reverseDomain(domain string) string {
	domain = strings.TrimPrefix(domain, "*")
	parts := strings.Split(domain, ".")
	for i, j := 0, len(parts)-1; i < j; i, j = i+1, j-1 {
		parts[i], parts[j] = parts[j], parts[i]
	}
	return strings.Join(parts, ".")
}

// LoadProfileRules stores a per-profile rule set keyed by profileID (UI.md #38/#39).
// This is additive; the legacy single-profile fields are unchanged.
func (e *Engine) LoadProfileRules(profileID string, allowExact, allowWildcard, blockExact, blockWildcard, adblockExact, adblockWildcard []string, security, parental map[string][]string) {
	e.mu.Lock()
	defer e.mu.Unlock()

	pe := &profileEngine{
		profileID:          profileID,
		allowExact:         make(map[string]bool, len(allowExact)),
		allowTrie:          NewTrie(),
		blockExact:         make(map[string]bool, len(blockExact)),
		blockTrie:          NewTrie(),
		securityCategories: make(map[string]map[string]bool, len(security)),
		parentalCategories: make(map[string]map[string]bool, len(parental)),
		adBlockDomains:     make(map[string]bool, len(adblockExact)),
		adBlockTrie:        NewTrie(),
	}
	for _, d := range allowExact {
		pe.allowExact[normalizeDomain(d)] = true
	}
	for _, d := range allowWildcard {
		pe.allowTrie.Insert(reverseDomain(normalizeDomain(d)))
	}
	for _, d := range blockExact {
		pe.blockExact[normalizeDomain(d)] = true
	}
	for _, d := range blockWildcard {
		pe.blockTrie.Insert(reverseDomain(normalizeDomain(d)))
	}
	for k, v := range security {
		set := make(map[string]bool, len(v))
		for _, d := range v {
			set[normalizeDomain(d)] = true
		}
		pe.securityCategories[k] = set
	}
	for k, v := range parental {
		set := make(map[string]bool, len(v))
		for _, d := range v {
			set[normalizeDomain(d)] = true
		}
		pe.parentalCategories[k] = set
	}
	for _, d := range adblockExact {
		pe.adBlockDomains[normalizeDomain(d)] = true
	}
	for _, d := range adblockWildcard {
		pe.adBlockTrie.Insert(reverseDomain(normalizeDomain(d)))
	}
	e.profileEngines[profileID] = pe
}

// MatchWithProfile routes a domain through the per-profile engine (UI.md #39).
// Falls back to legacy Match when profileID is empty or unknown.
func (e *Engine) MatchWithProfile(profileID, domain string) *Decision {
	if profileID != "" {
		e.mu.RLock()
		pe, ok := e.profileEngines[profileID]
		e.mu.RUnlock()
		if ok {
			return matchProfileEngine(pe, domain)
		}
	}
	return e.Match(domain)
}

// HasProfile returns true if the per-profile engine slot exists.
// Safe for concurrent use.
func (e *Engine) HasProfile(profileID string) bool {
	e.mu.RLock()
	defer e.mu.RUnlock()
	_, ok := e.profileEngines[profileID]
	return ok
}

// RemoveProfile deletes a per-profile engine slot.
// Safe for concurrent use.
func (e *Engine) RemoveProfile(profileID string) {
	e.mu.Lock()
	defer e.mu.Unlock()
	delete(e.profileEngines, profileID)
}

func matchProfileEngine(pe *profileEngine, domain string) *Decision {
	domain = normalizeDomain(domain)
	rev := reverseDomain(domain)

	if pe.allowExact[domain] || pe.allowTrie.Search(rev) {
		return &Decision{Action: "ALLOW", Reason: "allowlist"}
	}
	if pe.blockExact[domain] || pe.blockTrie.Search(rev) {
		return &Decision{Action: "BLOCK", Reason: "blocklist"}
	}
	for cat, set := range pe.securityCategories {
		if set[domain] {
			return &Decision{Action: "BLOCK", Reason: "security", Category: cat}
		}
	}
	for cat, set := range pe.parentalCategories {
		if set[domain] {
			return &Decision{Action: "BLOCK", Reason: "parental", Category: cat}
		}
	}
	if pe.adBlockDomains[domain] || pe.adBlockTrie.Search(rev) {
		return &Decision{Action: "BLOCK", Reason: "adblock", Category: "ads"}
	}
	return &Decision{Action: "ALLOW", Reason: "default"}
}
