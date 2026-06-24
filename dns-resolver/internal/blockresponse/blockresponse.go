// Package blockresponse implements the block_response policy shared between
// UDP and DoH servers. Both paths must produce identical responses for a
// given profile (nxdomain / refused / zero_ip), so the conversion from
// string to *dns.Msg lives in a single place.
package blockresponse

import (
	"net"
	"strings"

	"github.com/miekg/dns"
)

// Mode constants map 1:1 to the block_response string in cached profiles.
const (
	ModeNXDomain = "nxdomain"
	ModeRefused  = "refused"
	ModeZeroIP   = "zero_ip"
)

// Normalize maps any case / whitespace input to a canonical mode constant.
// Unknown values fall back to nxdomain to match the legacy dnsserver default.
func Normalize(raw string) string {
	switch strings.ToLower(strings.TrimSpace(raw)) {
	case ModeRefused:
		return ModeRefused
	case ModeZeroIP:
		return ModeZeroIP
	default:
		return ModeNXDomain
	}
}

// ApplyTo writes the chosen block policy into the DNS reply.
//
//   - nxdomain: Rcode = NXDOMAIN
//   - refused:  Rcode = REFUSED
//   - zero_ip:  A/AAAA records return 0.0.0.0 / ::, other qtypes fall back
//     to NXDOMAIN (matches the original dnsserver applyBlockResponse).
func ApplyTo(reply *dns.Msg, question dns.Question, mode string) {
	switch Normalize(mode) {
	case ModeRefused:
		reply.Rcode = dns.RcodeRefused
	case ModeZeroIP:
		reply.Rcode = dns.RcodeSuccess
		switch question.Qtype {
		case dns.TypeA:
			reply.Answer = []dns.RR{&dns.A{
				Hdr: dns.RR_Header{Name: question.Name, Rrtype: dns.TypeA, Class: dns.ClassINET, Ttl: 60},
				A:   net.ParseIP("0.0.0.0"),
			}}
		case dns.TypeAAAA:
			reply.Answer = []dns.RR{&dns.AAAA{
				Hdr:  dns.RR_Header{Name: question.Name, Rrtype: dns.TypeAAAA, Class: dns.ClassINET, Ttl: 60},
				AAAA: net.ParseIP("::"),
			}}
		default:
			reply.Rcode = dns.RcodeNameError
		}
	default:
		reply.Rcode = dns.RcodeNameError
	}
}
