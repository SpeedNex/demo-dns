package resolver

import (
	"context"
	"crypto/sha1"
	"encoding/hex"
	"log"
	"net"
	"strings"
	"time"

	"ocer-dns/dns-resolver/internal/blockresponse"
	"ocer-dns/dns-resolver/internal/cache"
	"ocer-dns/dns-resolver/internal/config"
	"ocer-dns/dns-resolver/internal/dnscache"
	"ocer-dns/dns-resolver/internal/logging"
	"ocer-dns/dns-resolver/internal/metrics"
	"ocer-dns/dns-resolver/internal/rules"

	"github.com/miekg/dns"
)

// Result 保存一次 DNS 查询解析的结果，由各个协议处理器写入对应传输层。
type Result struct {
	Reply     *dns.Msg
	Action    string // "ALLOW" / "BLOCK" / "REWRITE" / "ERROR" / "REFUSED"
	Reason    string
	Category  string
	ProfileID string
	DeviceID  string
	Domain    string
	QueryType string
	Rcode     int
}

// Handler 提供各协议共享的 DNS 解析 pipeline：
//
//	① 去重检查  ② 规则引擎判定  ③ DNS 缓存  ④ 上游转发  ⑤ 日志
//
// 各协议服务器负责：协议层解析、Profile 匹配、配额检查、传输层写出。
type Handler struct {
	cfg             *config.Config
	resolutionLayer *ProfileResolutionLayer
	logBuffer       *logging.Buffer
	metrics         *metrics.Metrics
	dedupCache      *cache.Cache
	dnsCache        *dnscache.DNSCache
	client          *dns.Client
	dedupTTL        time.Duration
}

// NewHandler 创建共享解析处理器。
func NewHandler(
	cfg *config.Config,
	resolutionLayer *ProfileResolutionLayer,
	logBuffer *logging.Buffer,
	metrics *metrics.Metrics,
	dedupCache *cache.Cache,
	dnsCache *dnscache.DNSCache,
) *Handler {
	return &Handler{
		cfg:             cfg,
		resolutionLayer: resolutionLayer,
		logBuffer:       logBuffer,
		metrics:         metrics,
		dedupCache:      dedupCache,
		dnsCache:        dnsCache,
		client: &dns.Client{
			Net:     "udp",
			Timeout: 5 * time.Second,
		},
		dedupTTL: 5 * time.Second,
	}
}

// Handle 执行完整解析 pipeline。调用方需在调用前完成 Profile 匹配和配额检查。
func (h *Handler) Handle(
	req *dns.Msg,
	clientAddr string,
	protocol string,
	profileID string,
	deviceID string,
	deviceType string,
	blockResponse string,
	safeSearchEnabled bool,
) *Result {
	h.metrics.IncQueries()

	reply := new(dns.Msg)
	reply.SetReply(req)

	if len(req.Question) == 0 {
		reply.Rcode = dns.RcodeFormatError
		h.metrics.IncErrors()
		return &Result{Reply: reply, Action: "ERROR", Reason: "no_question", Rcode: dns.RcodeFormatError}
	}

	question := req.Question[0]
	domain := strings.TrimSuffix(question.Name, ".")

	// 2026-06-29: 跳过局域网本地域名后缀（.lan / .local / .home），
	// 这些是客户端误加的多播 DNS 搜索后缀，不应进入规则引擎和日志。
	if strings.HasSuffix(domain, ".lan") || strings.HasSuffix(domain, ".local") || strings.HasSuffix(domain, ".home") {
		reply.Rcode = dns.RcodeNameError
		return &Result{Reply: reply, Action: "SKIP", Reason: "local_domain", Rcode: dns.RcodeNameError}
	}

	queryType := dns.TypeToString[question.Qtype]
	startedAt := time.Now()
	clientIP := remoteHost(clientAddr)

	// ① 去重（best-effort，不影响正确性）
	dedupKey := dedupFingerprint(clientIP, domain, queryType)
	dedupCtx, dedupCancel := context.WithTimeout(context.Background(), 250*time.Millisecond)
	firstSeen, dedupErr := h.dedupCache.MarkSeen(dedupCtx, dedupKey, h.dedupTTL)
	dedupCancel()
	if dedupErr != nil {
		log.Printf("[缓存] 去重错误 key=%s err=%v 视为首次", dedupKey, dedupErr)
		firstSeen = true
	}

	// ② 规则引擎判定
	decision := h.resolutionLayer.Resolve(&ResolutionContext{
		ProfileUID:        profileID,
		DeviceUID:         deviceID,
		SafeSearchEnabled: safeSearchEnabled,
		ClientIP:          net.ParseIP(clientIP),
		Domain:            domain,
		QueryType:         queryType,
		Protocol:          protocol,
	})

	switch decision.Action {
	case "BLOCK":
		h.metrics.IncBlocked()
		blockresponse.ApplyTo(reply, question, blockResponse)
		if firstSeen {
			h.appendLog(profileID, deviceID, deviceType, domain, "BLOCK", decision.Reason, decision.Category, clientIP, queryType, protocol, reply.Rcode, startedAt)
		}
		return &Result{
			Reply: reply, Action: "BLOCK", Reason: decision.Reason, Category: decision.Category,
			ProfileID: profileID, DeviceID: deviceID, Domain: domain, QueryType: queryType, Rcode: reply.Rcode,
		}

	case "REWRITE":
		reply.Answer = []dns.RR{
			&dns.CNAME{
				Hdr: dns.RR_Header{
					Name: question.Name, Rrtype: dns.TypeCNAME, Class: dns.ClassINET, Ttl: 60,
				},
				Target: dns.Fqdn(decision.Category),
			},
		}
		h.metrics.IncAllowed()
		if firstSeen {
			h.appendLog(profileID, deviceID, deviceType, domain, "REWRITE", decision.Reason, decision.Category, clientIP, queryType, protocol, reply.Rcode, startedAt)
		}
		return &Result{
			Reply: reply, Action: "REWRITE", Reason: decision.Reason, Category: decision.Category,
			ProfileID: profileID, DeviceID: deviceID, Domain: domain, QueryType: queryType, Rcode: reply.Rcode,
		}
	}

	// ③ DNS 缓存
	cacheKey := dnscache.MakeKey(domain, question.Qtype, profileID)
	if cached, ok := h.dnsCache.Get(context.Background(), cacheKey); ok {
		h.metrics.IncAllowed()
		if firstSeen {
			h.appendLog(profileID, deviceID, deviceType, domain, "ALLOW", "cache_hit", "", clientIP, queryType, protocol, cached.Rcode, startedAt)
		}
		return &Result{
			Reply: cached, Action: "ALLOW", Reason: "cache_hit",
			ProfileID: profileID, DeviceID: deviceID, Domain: domain, QueryType: queryType, Rcode: cached.Rcode,
		}
	}

	// ④ 上游转发（双上游容错）
	upstreamReply, _, err := h.client.Exchange(req, upstreamAddr(h.cfg.Upstream[0]))
	if err != nil && len(h.cfg.Upstream) > 1 {
		upstreamReply, _, err = h.client.Exchange(req, upstreamAddr(h.cfg.Upstream[1]))
	}
	if err != nil {
		reply.Rcode = dns.RcodeServerFailure
		h.metrics.IncErrors()
		if firstSeen {
			h.appendLog(profileID, deviceID, deviceType, domain, "ERROR", "upstream_timeout", "", clientIP, queryType, protocol, reply.Rcode, startedAt)
		}
		return &Result{
			Reply: reply, Action: "ERROR", Reason: "upstream_timeout",
			ProfileID: profileID, DeviceID: deviceID, Domain: domain, QueryType: queryType, Rcode: reply.Rcode,
		}
	}

	h.dnsCache.Set(context.Background(), cacheKey, upstreamReply)

	// ⑤ DNS Rebinding 后置检测（需要上游响应中的 IP）
	rebindEnabled, rebindWhitelist := h.resolutionLayer.GetDNSRebindConfig(profileID)
	if rebindEnabled && upstreamReply != nil {
		for _, rr := range upstreamReply.Answer {
			if a, ok := rr.(*dns.A); ok {
				res := rules.CheckDNSRebinding(domain, a.A, rebindWhitelist)
				if res.Blocked {
					h.metrics.IncBlocked()
					blockresponse.ApplyTo(reply, question, blockResponse)
					if firstSeen {
						h.appendLog(profileID, deviceID, deviceType, domain, "BLOCK", "dns_rebind", "security", clientIP, queryType, protocol, reply.Rcode, startedAt)
					}
					log.Printf("[安全] profile=%s domain=%s 类型=dns_rebind ip=%s 原因=%s",
						profileID, domain, res.IP.String(), res.Reason)
					return &Result{
						Reply: reply, Action: "BLOCK", Reason: "dns_rebind", Category: "security",
						ProfileID: profileID, DeviceID: deviceID, Domain: domain, QueryType: queryType, Rcode: reply.Rcode,
					}
				}
			}
			if aaaa, ok := rr.(*dns.AAAA); ok {
				res := rules.CheckDNSRebinding(domain, aaaa.AAAA, rebindWhitelist)
				if res.Blocked {
					h.metrics.IncBlocked()
					blockresponse.ApplyTo(reply, question, blockResponse)
					if firstSeen {
						h.appendLog(profileID, deviceID, deviceType, domain, "BLOCK", "dns_rebind", "security", clientIP, queryType, protocol, reply.Rcode, startedAt)
					}
					log.Printf("[安全] profile=%s domain=%s 类型=dns_rebind ip=%s 原因=%s",
						profileID, domain, res.IP.String(), res.Reason)
					return &Result{
						Reply: reply, Action: "BLOCK", Reason: "dns_rebind", Category: "security",
						ProfileID: profileID, DeviceID: deviceID, Domain: domain, QueryType: queryType, Rcode: reply.Rcode,
					}
				}
			}
		}
	}

	// ⑥ CNAME Tracker 后置检测：检查上游响应中的 CNAME 记录是否指向已知跟踪服务。
	// 对应 UI 中的"拦截伪装过的第三方跟踪器"功能。
	cnameTrackerEnabled := h.resolutionLayer.GetDisguisedTrackersConfig(profileID)
	if cnameTrackerEnabled && upstreamReply != nil {
		for _, rr := range upstreamReply.Answer {
			if cname, ok := rr.(*dns.CNAME); ok {
				res := rules.CheckCNAMETracker(cname.Target)
				if res.Blocked {
					h.metrics.IncBlocked()
					blockresponse.ApplyTo(reply, question, blockResponse)
					if firstSeen {
						h.appendLog(profileID, deviceID, deviceType, domain, "BLOCK", "cname_tracker", "privacy", clientIP, queryType, protocol, reply.Rcode, startedAt)
					}
					log.Printf("[安全] profile=%s domain=%s 类型=cname_tracker cname=%s provider=%s",
						profileID, domain, res.CNAME, res.Provider)
					return &Result{
						Reply: reply, Action: "BLOCK", Reason: "cname_tracker", Category: "privacy",
						ProfileID: profileID, DeviceID: deviceID, Domain: domain, QueryType: queryType, Rcode: reply.Rcode,
					}
				}
			}
		}
	}

	h.metrics.IncAllowed()
	if firstSeen {
		h.appendLog(profileID, deviceID, deviceType, domain, "ALLOW", "default", "", clientIP, queryType, protocol, upstreamReply.Rcode, startedAt)
	}
	return &Result{
		Reply: upstreamReply, Action: "ALLOW", Reason: "default",
		ProfileID: profileID, DeviceID: deviceID, Domain: domain, QueryType: queryType, Rcode: upstreamReply.Rcode,
	}
}

// appendLog 写入日志缓冲。
func (h *Handler) appendLog(profileID, deviceID, deviceType, domain, action, reason, category, clientIP, queryType, protocol string, rcode int, startedAt time.Time) {
	if h.logBuffer == nil {
		return
	}
	h.logBuffer.Append(logging.LogEntry{
		ProfileUID:     profileID,
		DeviceUID:      deviceID,
		DeviceType:     deviceType,
		Domain:         domain,
		Action:         strings.ToUpper(action),
		Reason:         reason,
		Category:       category,
		ClientIP:       clientIP,
		QueryType:      queryType,
		ResponseCode:   rcode,
		ResponseTimeMs: time.Since(startedAt).Milliseconds(),
		QueriedAt:      time.Now().Unix(),
		Protocol:       protocol,
	})
}

func dedupFingerprint(clientIP, domain, qtype string) string {
	h := sha1.New()
	h.Write([]byte(clientIP))
	h.Write([]byte{0})
	h.Write([]byte(domain))
	h.Write([]byte{0})
	h.Write([]byte(qtype))
	return hex.EncodeToString(h.Sum(nil))
}

func remoteHost(addr string) string {
	host, _, err := net.SplitHostPort(addr)
	if err != nil {
		return addr
	}
	return host
}

func upstreamAddr(addr string) string {
	if strings.Contains(addr, ":") {
		return addr
	}
	return addr + ":53"
}