# 变更日志

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-08 | code | 实现会员中心 Parental / Privacy 页面全部功能：blocked_items 使用真实 url、blocked_categories 动态转规则、block_adult_content / block_gambling / privacy trackers 等开关与 rule_items 分类规则动态关联，resolver 端实现 parental 总开关、时间限制、IP 匿名化、deep_tracking 按设备类型生效 | portal-web/app/Application/Member/ProfilePublishApplicationService.php, dns-resolver/internal/resolver/resolver.go, dns-resolver/internal/resolver/handler.go, dns-resolver/internal/doh/server.go, dns-resolver/internal/doq/server.go, dns-resolver/internal/dnsserver/server.go | ok |
| 2026-07-08 | code | `/admin/rules/items` 列表返回规则源名称、规则分类中文名并在前端展示，修复规则条目未显示所属规则库及分类名的问题 | portal-web/app/Http/Controllers/Api/V1/Admin/AdminRuleItemController.php, portal-web/web/src/views/admin/RuleItems.vue, portal-web/web/src/locales/zh-CN.json, portal-web/web/src/locales/en.json, portal-web/web/src/locales/ko.json | ok |
| 2026-07-08 | code | `/user/dns-endpoints` 返回在线 GeoDNS 节点 IPv4/IPv6，用于传统 DNS 服务器配置 | portal-web/app/Domain/Profile/UserWorkspaceService.php | ok |
| 2026-07-08 | code | 对齐会员中心 Security 页面开关 key 与 dns-resolver，修复 DNS 重新绑定、误植域名、DGA、特定 TLD、新注册域名、挖矿、儿童色情等开关不生效问题 | portal-web/app/Domain/Profile/MemberCatalogService.php | ok |
| 2026-07-08 | code | 修复 Security / ParentalControl / Privacy 页面未根据 member-catalogs enabled 状态过滤显示开关的问题 | portal-web/web/src/views/Security.vue, portal-web/web/src/views/ParentalControl.vue, portal-web/web/src/views/Privacy.vue | ok |
