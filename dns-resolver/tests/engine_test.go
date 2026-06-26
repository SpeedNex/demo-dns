package tests

import (
	"testing"

	"ocer-dns/dns-resolver/internal/rules"
)

func TestAllowRuleHasPriority(t *testing.T) {
	engine := rules.New([]rules.Rule{
		{Domain: "ads.example.com", NormalizedDomain: "ads.example.com", MatchType: "exact", ListType: "block", Action: "block"},
		{Domain: "ads.example.com", NormalizedDomain: "ads.example.com", MatchType: "exact", ListType: "allow", Action: "allow"},
	})

	if got := engine.Decide("ads.example.com"); got != rules.DecisionAllow {
		t.Fatalf("expected allow, got %s", got)
	}
}

func TestSuffixRuleBlocksSubdomain(t *testing.T) {
	engine := rules.New([]rules.Rule{
		{Domain: "example.com", NormalizedDomain: "example.com", MatchType: "suffix", ListType: "block", Action: "block"},
	})

	if got := engine.Decide("ads.example.com"); got != rules.DecisionBlock {
		t.Fatalf("expected block, got %s", got)
	}
}
