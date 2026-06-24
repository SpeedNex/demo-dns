# ADR-003: API 鉴权方案 - Laravel Sanctum API Token

## 状态
已接受

## 背景
对外提供 REST API，需要鉴权与限流。
业务约束：只需要"应用级 API Key"，不需要 OAuth 2.0、OIDC、JWT 跨域联邦。

可选方案：
- **方案 A：Laravel Sanctum（个人访问令牌）**
- **方案 B：手写 API Key + 中间件**
- **方案 C：JWT（tymon/jwt-auth）**
- **方案 D：OAuth 2.0 Server**

## 决策
采用 **方案 A：Laravel Sanctum 的 Personal Access Tokens**。

接口设计：
```
Authorization: Bearer {token}
```

理由：
1. 与 Laravel 生态深度集成，`HasApiTokens` Trait 一行启用
2. 天然支持多设备令牌吊销、过期、Last-used-at 记录
3. **不引入无意义的 OAuth 复杂度**
4. Token 存放数据库，吊销 = 删行，性能与安全均衡

频率限制：使用 Laravel 内置 `RateLimiter`，Key = Bearer Token，默认 **60 次/分钟/Key**。

## 后果

### 正面
- 实现简单，一天内完成
- Token 生命周期可观测（`last_used_at`）
- 与 CSRF/Session 隔离，API 走无状态通道

### 负面
- 不直接对外做第三方联邦授权
- Token 一次性泄露即长期有效，需配合 HTTPS 与定期轮换

## 相关文档
- [1-ARCHITECTURE.md](../1-ARCHITECTURE.md)
- [api.md](../api.md)
