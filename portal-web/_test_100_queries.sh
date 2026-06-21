#!/bin/bash
# 跑 100 个域名查询到 test-loop profile 的 DoH 端点
# test-loop profile_uid=e27972
# 期望：域名解析成功 + resolver 写入日志 + ClickHouse 收到

DOH_BASE="http://127.0.0.1:8081"
PROFILE_UID="e27972"

# 100 个测试域名：包含正常/广告/成人/赌博/可解析/不可解析
DOMAINS=(
  # 可解析 50 个
  www.google.com www.github.com www.cloudflare.com www.apple.com www.microsoft.com
  www.amazon.com www.youtube.com www.facebook.com www.twitter.com www.linkedin.com
  www.baidu.com www.qq.com www.taobao.com www.jd.com www.bilibili.com
  www.wikipedia.org www.reddit.com www.stackoverflow.com www.quora.com www.medium.com
  www.duckduckgo.com www.bing.com www.yahoo.com www.nytimes.com www.bbc.com
  www.cnn.com www.theguardian.com www.washingtonpost.com www.forbes.com www.bloomberg.com
  www.gmail.com www.outlook.com www.yahoo.com mail.protonmail.com mail.qq.com
  www.sina.com.cn www.163.com www.sohu.com www.tmall.com www.weibo.com
  www.zhihu.com www.douban.com www.youku.com www.iqiyi.com www.vimeo.com
  www.spotify.com www.netflix.com www.twitch.tv www.discord.com www.slack.com
  # 广告/追踪 20 个
  googleadservices.com googlesyndication.com doubleclick.net google-analytics.com
  adnxs.com scorecardresearch.com criteo.com adsrvr.org pubmatic.com
  taboola.com outbrain.com moatads.com chartbeat.com hotjar.com
  segment.com mixpanel.com newrelic.com fullstory.com mouseflow.com
  adcolony.com
  # 模拟成人/赌博 10 个
  pornhub.com xvideos.com xnxx.com xhamster.com redtube.com
  bet365.com williamhill.com 888casino.com pokerstars.com bwin.com
  # 已知会被家长/安全拦截 10 个（如果 block_response=redirected 或 null）
  tiktok.com whatsapp.net wechat.com telegram.org discord.gg
  example-malware-domain.zz example-phishing.zz example-c2.zz
  example-bad-adult.zz example-bad-gambling.zz example-bot-tracker.zz
  example-typo.zz
  # 不可解析 10 个（NXDOMAIN/empty）
  no-such-domain-12345abc.invalid another-nonexistent-xyz.zz
  random-nope-domain-123.zz cannot-resolve-me-zz-1.zz cannot-resolve-me-zz-2.zz
  bad-domain-999-1.zz bad-domain-999-2.zz bad-domain-999-3.zz
  bad-domain-999-4.zz bad-domain-999-5.zz
)

for d in "${DOMAINS[@]}"; do
  # 走 DoH (resolver 监听 8444，路由 /e27972/dns-query)
  curl -s -o /tmp/resp_$$.txt -w "%{http_code}|%{time_total}|" "http://127.0.0.1:8444/e27972/dns-query?name=$d&type=A" -H "Accept: application/dns-json" --max-time 5 2>&1
  echo "$d"
  sleep 0.05
done
