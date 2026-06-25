# Debug Session: security-policy-e2e-test

## Session ID
`security-policy-e2e-test`

## Created
2026-06-25

## Hypothesis

### 用户反馈
用户在会员中心添加了黑名单域名，但该域名仍然可以正常访问。怀疑安全策略（黑名单/安全规则/隐私规则/家长监护）在 DNS 解析层没有生效。

### 可能原因假设

1. **H1: 黑名单添加后未发布配置**
   - 用户添加黑名单后，需要点击"发布"才能推送到 resolver
   - 如果配置未发布，resolver 还在使用旧配置

2. **H2: Profile 未正确关联到设备**
   - resolver 可能无法识别请求来自哪个 Profile
   - 设备 IP → Profile 的映射可能失效

3. **H3: Resolver 规则匹配逻辑问题**
   - engine.MatchWithProfile 可能未正确调用
   - 白名单/黑名单的优先级可能错误

4. **H4: DoH/DoT/DoQ 请求未带 profile_id**
   - 浏览器通过 DoH 访问时，可能未传递正确的 profile_id
   - resolver 无法知道该查询属于哪个 Profile

5. **H5: 缓存问题**
   - resolver 或浏览器存在 DNS 缓存
   - 配置更新后缓存未失效

## 测试计划

### Phase 1: 验证基础链路
- [ ] 1.1 登录 portal-web
- [ ] 1.2 创建测试 Profile（如不存在）
- [ ] 1.3 添加测试域名到黑名单
- [ ] 1.4 发布配置

### Phase 2: 端到端测试
- [ ] 2.1 配置浏览器 DNS 指向 resolver
- [ ] 2.2 访问被黑名单拦截的域名
- [ ] 2.3 观察 DNS 响应（应返回 NXDOMAIN/REFUSED）
- [ ] 2.4 检查 resolver 日志是否有匹配记录

### Phase 3: 安全策略矩阵测试
- [ ] 3.1 安全策略 - 威胁情报源
- [ ] 3.2 安全策略 - AI 威胁检测
- [ ] 3.3 隐私策略 - 屏蔽列表
- [ ] 3.4 家长监护 - 网站/分类拦截

## Status
[CLOSED - ROOT CAUSE FOUND]

## Root Cause

### 发现的问题

#### 问题 1: Profile 缓存未版本校验 (P0 Bug)
- **位置**: `dns-resolver/internal/agent/agent.go:219-222`
- **问题**: `FetchProfile` 方法在内存缓存命中时直接返回，不检查版本是否过期
- **影响**: Profile 发布新版本后，Resolver 继续使用内存中的旧版本规则
- **修复建议**: 内存缓存命中时，应检查版本号或依赖心跳响应的 `Updated` 字段触发重新拉取

### 测试验证

| 测试项 | 结果 | 说明 |
|--------|------|------|
| 黑名单 (blocked-test.example.com) | ✅ 拦截成功 | 发布后旧版本规则 |
| doubleclick.net (后添加) | ❌ 未拦截 | 内存缓存未刷新 |
| 威胁情报类域名 | ❌ 未测试 | 需添加后验证 |

### 测试 API Token
- Token: `124|MpgCWFBtTBeiUuRY2YbHZ9pwwa3OenLBucnTfD6X50739f3f`
- Profile ID: `8db759`
- Resolver Node ID: `16`

## Logs
- Portal API: `/Users/472733389qq.com/Desktop/ai-agent/docs/ai-doc/ai-doc/ocer-dns/logs/portal-api.log`
- Resolver: `/Users/472733389qq.com/Desktop/ai-agent/docs/ai-doc/ai-doc/ocer-dns/logs/resolver.log`
- Debug Log: `trae-debug-log-security-policy-e2e-test.ndjson`
