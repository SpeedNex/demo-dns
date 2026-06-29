package config

import (
	"encoding/json"
	"fmt"
)

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

// SecurityData 兼容 JSON 对象 {} 和空数组 []。
// PHP json_encode 空对象可能输出 []，Go 标准 map 无法解析。
type SecurityData map[string][]string

func (sd *SecurityData) UnmarshalJSON(data []byte) error {
	// 尝试解析为对象 map[string][]string
	var m map[string][]string
	if err := json.Unmarshal(data, &m); err == nil {
		*sd = SecurityData(m)
		return nil
	}
	// 兼容空数组 []（PHP json_encode 空对象可能输出数组）
	var arr []any
	if err := json.Unmarshal(data, &arr); err == nil {
		*sd = make(SecurityData)
		return nil
	}
	return fmt.Errorf("cannot unmarshal SecurityData: %s", string(data))
}

type ProfileConfig struct {
	ProfileID      string              `json:"profile_id"`
	UserID         string              `json:"user_id"`
	TeamID         *string             `json:"team_id"`
	Version        int64               `json:"version"`
	DefaultAction  string              `json:"default_action"`
	BlockResponse  string              `json:"block_response"`
	Security       map[string]any      `json:"security"`
	SecurityData   SecurityData        `json:"security_data"`
	Adblock        map[string]any      `json:"adblock"`
	Privacy        map[string]any      `json:"privacy"`
	Parental       map[string]any      `json:"parental"`
	Devices        []DeviceEntry       `json:"devices"`
	Rules          []RuleConfig        `json:"rules"`
	Quota          map[string]any      `json:"quota"`
}

type DeviceEntry struct {
	DeviceID     string `json:"device_id"`
	Name         string `json:"name"`
	SourceIP     string `json:"source_ip"`
	DeviceType   string `json:"device_type"`
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
