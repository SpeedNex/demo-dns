package config

import "encoding/json"

type GlobalConfig struct {
	Version   int64              `json:"version"`
	Upstreams []Upstream         `json:"upstreams"`
	Plans     map[string]any     `json:"plans"`
	Rulesets  json.RawMessage      `json:"rulesets"`
	Limits    map[string]int64   `json:"limits"`
}

type RuntimeConfig struct {
	NodeID         string
	APIRoot        string
	ActiveConfig   string
	PreviousConfig string
}

type ResolverConfig struct {
	Version     int64           `json:"version"`
	Checksum    string          `json:"checksum"`
	GeneratedAt string          `json:"generated_at"`
	ExpiresAt   string          `json:"expires_at,omitempty"`
	Profiles    []ProfileConfig `json:"profiles"`
	Upstreams   []Upstream      `json:"upstreams"`
}

type ProfileConfig struct {
	ProfileID     string        `json:"profile_id"`
	UserID        string        `json:"user_id"`
	TeamID        *string       `json:"team_id"`
	Version       int64         `json:"version"`
	DefaultAction string        `json:"default_action"`
	BlockResponse string        `json:"block_response"`
	Security      FeatureSwitch `json:"security"`
	Adblock       FeatureSwitch `json:"adblock"`
	Privacy       FeatureSwitch `json:"privacy"`
	Parental      FeatureSwitch `json:"parental"`
	Rules         []RuleConfig  `json:"rules"`
	Quota         ProfileQuota  `json:"quota"`
}

type FeatureSwitch struct {
	Enabled bool `json:"enabled"`
}

type ProfileQuota struct {
	QuotaStatus string `json:"quota_status"`
}

type RuleConfig struct {
	RuleID           string `json:"rule_id"`
	ListType         string `json:"list_type"`
	MatchType        string `json:"match_type"`
	Domain           string `json:"domain"`
	NormalizedDomain string `json:"normalized_domain"`
	Action           string `json:"action"`
}

type Upstream struct {
	Address  string `json:"address"`
	Protocol string `json:"protocol"`
	Timeout  string `json:"timeout"`
}
