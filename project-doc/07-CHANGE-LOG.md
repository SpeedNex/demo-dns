# 变更日志

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-08 | code | `/user/dns-endpoints` 返回在线 GeoDNS 节点 IPv4/IPv6，用于传统 DNS 服务器配置 | portal-web/app/Domain/Profile/UserWorkspaceService.php | ok |
| 2026-07-08 | code | 对齐会员中心 Security 页面开关 key 与 dns-resolver，修复 DNS 重新绑定、误植域名、DGA、特定 TLD、新注册域名、挖矿、儿童色情等开关不生效问题 | portal-web/app/Domain/Profile/MemberCatalogService.php | ok |
| 2026-07-08 | code | 修复 Security / ParentalControl 页面未根据 member-catalogs enabled 状态过滤显示开关的问题 | portal-web/web/src/views/Security.vue, portal-web/web/src/views/ParentalControl.vue | ok |
