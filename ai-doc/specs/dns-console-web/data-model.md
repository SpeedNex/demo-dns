# dns-console-web 数据模型

> dns-console-web 是面向 **运维 / 平台管理员** 的内部控制台。负责节点接入、配置发布、查询日志接入、配额与审计。
>
> **本控制台管理员登录与 portal-web 用户登录物理隔离**：
> - portal-web 用户登录表：001.users（见 specs/portal-web/data-schema.md）。
> - 控制台管理员登录表：002.dns_admins（见本文 §1.11）。
> - 两表不共享行、不共享 password_hash 列，禁止通过 users.role = admin 复用，也禁止把管理员账号写入 users。
> - 用户审计 001.audit_logs 与管理员审计 002.dns_admin_audit_logs 物理分离，互不引用。
