package rules

import (
	"strings"
)

// DynDNSResult 标识一个域名是否属于动态 DNS 服务
type DynDNSResult struct {
	Blocked bool
	Reason  string
	Provider string
}

// knownDynDNSProviders 是已知的动态 DNS 提供商域名后缀列表。
// 这些服务允许用户注册子域名并动态更新 IP，常被攻击者用于
// 维持对受感染系统的访问。
var knownDynDNSProviders = []string{
	// 主流 DDNS 服务
	"ddns.net",
	"no-ip.com",
	"noip.com",
	"duckdns.org",
	"dyndns.org",
	"dynu.com",
	"dyn.com",
	"dnsdynamic.org",
	"changeip.com",
	"freedns.afraid.org",
	"nsupdate.info",
	"dnsomatic.com",
	"strangled.net",
	"zoneedit.com",
	// 路由器/厂商 DDNS
	"asuscomm.com",
	"synology.me",
	"qnap.com",
	"myqnapcloud.com",
	"tplinkdns.com",
	"myds.me",
	"oray.com",
	"3322.org",
	// 其他常见 DDNS
	"dtdns.com",
	"dyndns.tv",
	"dyndns.ws",
	"dynalias.com",
	"gotdns.org",
	"homeserver.dk",
	"myddns.com",
	"mydyndns.org",
	"selfip.com",
	"servebbs.com",
	"servemp3.com",
	"myftp.biz",
	"mynetav.net",
	"mynetav.org",
}

// CheckDynDNS 检查域名是否属于已知的动态 DNS 提供商。
func CheckDynDNS(domain string) DynDNSResult {
	dn := strings.TrimSuffix(strings.ToLower(domain), ".")
	if dn == "" {
		return DynDNSResult{}
	}

	for _, provider := range knownDynDNSProviders {
		if dn == provider || strings.HasSuffix(dn, "."+provider) {
			return DynDNSResult{
				Blocked:  true,
				Reason:   "dynamic-dns",
				Provider: provider,
			}
		}
	}

	return DynDNSResult{}
}
