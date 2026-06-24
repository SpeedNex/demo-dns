# Member Center V1 Change Report

本次修改根据最新产品要求，将第一版 `portal-web` 会员中心明确为以下固定功能入口：

```text
安全
隐私
家长监护
黑名单
白名单
统计
日志
设置
会员中心 / 套餐订阅
```

## 修改重点

1. 新增 `project-doc/08-MEMBER-CENTER-V1.md`，作为会员中心 V1 主规格。
2. 更新 `README.md` 和 `START.md`，把会员中心规格加入必读顺序和生成优先级。
3. 更新 `project-doc/04-MVP-SCOPE.md`、`project-doc/02-features.md`、`project-doc/00-GOAL.md`，将安全、隐私、家长监护 Lite 纳入 MVP。
4. 更新 `project-doc/03-DATA-FLOW.md` 和 `project-doc/06-CLOSED-LOOP-AND-DATA-DESTINATIONS.md`，补充会员中心配置发布闭环。
5. 更新 `specs/portal-web/api.md`，新增会员中心总览、安全、隐私、家长监护、白名单、黑名单、设置、套餐等 API 说明。
6. 更新 `contracts/openapi.yaml`，新增对应 API 契约和 schema。
7. 更新 `specs/portal-web/data-schema.md` 和 `migrations/postgresql/001_portal_web_mvp.sql`，新增 `privacy_enabled`、`safe_search_enabled`、`log_mode` 和 `profile_feature_settings`。
8. 更新 `contracts/resolver-config.schema.json` 和示例，resolver 配置包现在包含 `security`、`privacy`、`parental` 三类设置。

## V1 不变原则

- 仍然参考 NextDNS Lite：Free 300,000 queries/month，Pro/Business/Education unlimited queries。
- 不做 DNS 查询按量计费。
- 不做自动超额扣费。
- dns-resolver 不直接写 PostgreSQL / Redis / ClickHouse。
- 查询日志仍然通过 dns-console-web 进入 ClickHouse。
- 财务事实仍然以 portal-web PostgreSQL 为准。

## 新增验收重点

- 会员中心导航必须显示全部 9 个入口。
- 白名单优先级必须高于黑名单、安全、隐私和家长监护。
- 隐私 `log_mode=disabled` 时不得展示详细域名日志，但仍允许最小化 query_count 聚合用于 Free 额度。
- 家长监护 V1 只做 DNS 层 Lite：成人内容、安全搜索、YouTube 受限模式，不做 App 时长和孩子端 App。
