# AI 生成 MVP 提示词

使用方式：在确认生成代码时，把下面内容作为提示词开头。

```text
请使用 START.md 作为入口生成 OcerDNS Security Platform 的 MVP。严格遵守以下规则：

1. 当前唯一包名只有 portal-web、dns-resolver、geodns（dns-console-web 已并入 portal-web）。
2. 禁止把 archive/historical-specs 下的 admin-web、dns-control-web、control-plane 当作当前包生成。
3. 先读取 project-doc/04-MVP-SCOPE.md 和 project-doc/08-MEMBER-CENTER-V1.md，只生成 MVP 必做功能。
4. API 必须对齐 contracts/openapi.yaml。
5. 配置、心跳、日志、指标必须对齐 contracts/*.schema.json。
6. MySQL migration 必须基于 Laravel PHP 迁移文件。
7. ClickHouse DDL 必须基于 migrations/clickhouse/*.sql。
8. resolver 不允许连接 MySQL，不允许调用 portal-web。
9. DNS 查询链路只使用本地内存配置，不访问 Laravel API。
10. 生成后必须提供构建、测试、迁移和端到端验收说明。
```

## MVP 端到端目标

```text
注册 / 登录
创建 Profile
会员中心展示安全、隐私、家长监护、黑名单、白名单、统计、日志、设置、会员中心
开启安全防护和隐私保护
开启家长监护 SafeSearch
添加 block rule: ads.example.com
添加 allow rule: school.example.com
发布配置
resolver 预创建 + install 写入凭据后心跳 online
resolver 拉取 config_version=1
DoH 查询 ads.example.com 被拦截
DoH 查询 school.example.com 因白名单被放行
DoH 查询 example.com 被放行
日志上报
portal-web 查到日志
console-web 查到节点版本
```

