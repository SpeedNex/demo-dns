// Package blockresponse implements the block_response policy shared between
// UDP and DoH servers. Both paths must produce identical responses for a
// given profile (nxdomain / refused / zero_ip), so the conversion from
// string to *dns.Msg lives in a single place.
package blockresponse

import (
	"net"
	"strings"
	"time"

	"github.com/miekg/dns"
)

// 项目 SOA 域名常量（可从 config 下发覆盖）
var (
	// DefaultSOANS 是权威 DNS 服务器名称
	DefaultSOANS = "ns1.OCER-DNS.com."
	// DefaultSOAMbox 是管理员邮箱（@ 替换为 .）
	DefaultSOAMbox = "admin.OCER-DNS.com."
	// DefaultDomain 是默认解析域名的根域名
	DefaultDomain = "OCER-DNS.com."
)

// Mode constants map 1:1 to the block_response string in profile config.
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
//   - nxdomain: Rcode = NXDOMAIN, includes SOA with neg TTL=60s
//   - refused:  Rcode = REFUSED, includes SOA with neg TTL=60s
//   - zero_ip:  A/AAAA records return 0.0.0.0 / :: with TTL=60s,
//     other qtypes fall back to NXDOMAIN
//
// 2026-06-26 fix: NXDOMAIN/REFUSED 响应添加 SOA 记录，设置短负缓存 TTL(60s)，
// 防止客户端 OS 长期缓存 NXDOMAIN 导致规则解除后域名仍不可访问。
func ApplyTo(reply *dns.Msg, question dns.Question, mode string) {
	switch Normalize(mode) {
	case ModeRefused:
		reply.Rcode = dns.RcodeRefused
		addNegativeSOA(reply, question)
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
			addNegativeSOA(reply, question)
		}
	default:
		reply.Rcode = dns.RcodeNameError
		addNegativeSOA(reply, question)
	}
}

// addNegativeSOA 为 NXDOMAIN/REFUSED 响应添加 SOA 记录，
// 告知客户端该负响应的缓存 TTL（RFC 2308）。
// Ns/Mbox 使用项目配置的域名常量，Minttl 字段控制负缓存时长（60 秒）。
func addNegativeSOA(reply *dns.Msg, question dns.Question) {
	soa := &dns.SOA{
		Hdr:     dns.RR_Header{Name: question.Name, Rrtype: dns.TypeSOA, Class: dns.ClassINET, Ttl: 60},
		Ns:      DefaultSOANS,
		Mbox:    DefaultSOAMbox,
		Serial:  uint32(time.Now().Unix()), // 2026-06-30: 使用时间戳，利于排查和缓存一致性
		Refresh: 3600,
		Retry:   600,
		Expire:  86400,
		Minttl:  60, // 负缓存 TTL：最长 60 秒
	}
	reply.Ns = []dns.RR{soa}
}
