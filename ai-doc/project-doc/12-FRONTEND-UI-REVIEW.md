# 前端项目全量审查报告

> 审查日期：2026-07-09  
> 审查范围：portal-web/web/src/ 全部前端代码  
> 审查方法：静态代码扫描 + 人工复核  
> 框架：Vue 3.5 + Element Plus 2.9 + vue-i18n 9.14

---

## 一、总体评分

| 维度 | 评分 | 说明 |
| --- | ---: | --- |
| 功能完整性 | 6.5/10 | 核心 CRUD 基本完整，但权限控制、异常处理存在明显缺失 |
| UI一致性 | 7/10 | 整体风格较统一，但颜色、按钮、空状态等存在不一致 |
| 布局合理性 | 7.5/10 | 布局结构合理，但部分页面搜索栏样式不统一 |
| 视觉统一性 | 6.5/10 | 存在大量硬编码颜色值，未完全使用 CSS 变量 |
| 可用性 | 7/10 | 主要流程可用，但部分操作缺少反馈 |
| 设计系统规范度 | 6/10 | 缺少统一设计系统，部分组件使用不规范 |
| 异常处理完整度 | 5/10 | 多处 catch 空捕获，用户无法感知失败 |
| 权限控制完整度 | 4/10 | 仅校验 token 存在，无角色/权限细分 |
| 多语言完整度 | 5/10 | 存在大量硬编码中文/英文文本 |
| 代码质量 | 7/10 | 整体结构清晰，但存在重复代码、内联样式过多 |
| 上线风险 | 6/10 | 存在 P0 级权限和确认缺失，需修复后方可上线 |

---

## 二、功能检查明细

| 模块 | 功能点 | 是否存在 | 是否完整 | 风险等级 | 问题描述 | 优化建议 |
| --- | --- | --- | --- | --- | --- | --- |
| 用户管理 | 查询/新增/编辑/删除 | ✅ | ✅ | - | 功能完整 | - |
| 用户管理 | 启用/禁用 | ✅ | ❌ | P1 | 无二次确认 | 增加 ElMessageBox.confirm |
| 管理员管理 | 启用/禁用 | ✅ | ❌ | **P0** | 高危操作无确认 | 必须增加二次确认 |
| 套餐管理 | CRUD | ✅ | ❌ | P1 | 表单无校验规则 | 增加必填字段校验 |
| 角色管理 | 删除 | ✅ | ❌ | P1 | 仅判断 is_system，无权限控制 | 增加权限码判断 |
| 审计日志 | 清空 | ✅ | ❌ | P1 | 高危批量删除，保护不足 | 增加延迟或验证码 |
| 设备管理 | 编辑/详情 | ❌ | ❌ | P2 | 仅提供删除，无详情/编辑 | 考虑增加详情查看 |
| 路由权限 | RBAC | ❌ | ❌ | **P0** | 仅检查 token，无角色细分 | 增加 meta.permission |
| 表单验证 | 统一校验 | 部分 | ❌ | P1 | 部分页面无 rules | 统一使用 el-form rules |
| 异常处理 | 错误提示 | 部分 | ❌ | P1 | 多处 catch 空捕获 | 增加 ElMessage.error |
| 操作反馈 | Loading | 部分 | ❌ | P1 | 部分表格无 v-loading | 统一添加 loading |
| 操作反馈 | 成功/失败提示 | 部分 | ❌ | P2 | 部分操作无反馈 | 统一增加 message |

---

## 三、多语言硬编码问题清单

### P0 级（必须修复 - 中文硬编码）

| 文件路径 | 行号 | 原文案 | 所属模块 | 文案类型 | 风险等级 | 建议 key | 替换建议 |
| --- | ---: | --- | --- | --- | --- | --- | --- |
| views/admin/QueryLogs.vue | 16 | Query Logs | 查询日志 | 标题 | P0 | queryLogs.title | `$t('queryLogs.title')` |
| views/admin/QueryLogs.vue | 全部 | 多处硬编码 | 查询日志 | 按钮/提示 | P0 | - | 提取到 i18n |
| views/admin/SystemConfig.vue | 全部 | DNS 域名/Redis/ClickHouse 等 | 系统配置 | 标签 | P0 | - | 提取到 i18n |
| views/admin/Users.vue | 全部 | 验证消息/确认对话框 | 用户管理 | 提示 | P0 | - | 提取到 i18n |
| views/admin/Subscriptions.vue | 全部 | 确认消息 | 订阅管理 | 确认 | P0 | - | 提取到 i18n |
| views/admin/RuleCategories.vue | 全部 | 确认消息 | 规则分类 | 确认 | P0 | - | 提取到 i18n |
| views/admin/Bill.vue | 全部 | 确认/消息文本 | 账单管理 | 提示 | P0 | - | 提取到 i18n |
| views/admin/GeoDNS.vue | 4 | 描述文本 | GeoDNS | 描述 | P0 | - | 提取到 i18n |
| views/admin/MemberCatalogs.vue | 155 | 选项值（网址） | 会员目录 | placeholder | P0 | - | 提取到 i18n |
| views/admin/RoleManagement.vue | 263 | 确认删除此角色？ | 角色管理 | 确认 | P0 | - | 提取到 i18n |
| views/Allowlist.vue | 109 | 请输入正确的格式 | 白名单 | 校验 | P0 | allowlist.invalidFormat | `t('allowlist.invalidFormat')` |
| views/Blocklist.vue | 105 | 请输入正确的格式 | 黑名单 | 校验 | P0 | blocklist.invalidFormat | `t('blocklist.invalidFormat')` |
| components/PaymentModal.vue | 121-123 | 信用卡/微信支付/支付宝 | 支付组件 | 枚举 | P0 | payment.card/wechat/alipay | 提取到 i18n |

### P1 级（建议修复 - 英文硬编码或 fallback）

| 文件路径 | 行号 | 原文案 | 所属模块 | 文案类型 | 风险等级 | 建议 key | 替换建议 |
| --- | ---: | --- | --- | --- | --- | --- | --- |
| views/admin/AdminLogin.vue | 16 | admin@example.com | 管理员登录 | placeholder | P1 | admin.login.emailPlaceholder | 提取到 i18n |
| views/admin/PublishCenter.vue | 全部 | 8处硬编码 | 发布中心 | 混合 | P1 | - | 提取到 i18n |
| views/admin/RegionManage.vue | 全部 | 7处硬编码 | 区域管理 | 混合 | P1 | - | 提取到 i18n |
| views/admin/Rules.vue | 全部 | 11处硬编码 | 规则管理 | 混合 | P1 | - | 提取到 i18n |
| views/admin/SecurityDataItem.vue | 全部 | 8处硬编码 | 安全数据 | 混合 | P1 | - | 提取到 i18n |
| views/Settings.vue | 65-76 | 密码验证 fallback | 设置 | 校验 | P1 | - | 确保 i18n 包含 |
| views/Login.vue | 5 | Personal DNS privacy and control | 登录 | 描述 | P1 | auth.tagline | 提取到 i18n |
| views/Register.vue | 5-6 | Personal DNS privacy and control / Create Workspace | 注册 | 描述/标题 | P1 | auth.tagline/auth.createWorkspace | 提取到 i18n |
| views/Dashboard.vue | 4 | Member Overview | 仪表盘 | 标题 | P1 | dashboard.memberOverview | 提取到 i18n |
| views/Security.vue | 19 | beta | 安全 | 标签 | P1 | security.beta | 提取到 i18n |
| views/user/Account.vue | 28 | Free | 账户 | 标签 | P1 | account.free | 提取到 i18n |
| views/TeamCreate.vue | 15 | ocer-dns.to/ | 团队创建 | URL | P1 | team.urlPrefix | 提取到 i18n |
| views/TeamDetail.vue | 171/278 | 确认消息 fallback | 团队详情 | 确认 | P1 | - | 确保 i18n 包含 |
| views/TeamList.vue | 123 | Invitation accepted | 团队列表 | 提示 | P1 | - | 确保 i18n 包含 |
| views/TeamInvitations.vue | 55/58 | 提示消息 fallback | 邀请 | 提示 | P1 | - | 确保 i18n 包含 |
| components/AuthShell.vue | 54-56 | OcerDNS / Edge DNS control plane / Secure DNS Platform | 认证外壳 | 品牌 | P1 | - | 提取到 i18n |

### P2 级（低风险）

| 文件路径 | 行号 | 原文案 | 所属模块 | 文案类型 | 风险等级 | 建议 |
| --- | ---: | --- | --- | --- | --- | --- |
| views/Devices.vue | 156 | — | 设备 | 占位符 | P2 | 可保留或统一 |

---

## 四、UI 问题清单

### P0 严重问题

| 问题 | 影响范围 | 修改建议 |
| --- | --- | --- |
| 路由级权限控制缺失：admin 路由仅检查 token 存在，无角色/权限细分 | 整个后台 | 增加 `meta.permission` 字段，结合后端 RBAC |
| 管理员状态变更无二次确认 | AdminAdmins.vue | 必须增加 `ElMessageBox.confirm` |
| 大量中文硬编码，多语言环境无法使用 | 全部 admin 页面 | 提取到 i18n 文件 |

### P1 重要问题

| 问题 | 影响范围 | 修改建议 |
| --- | --- | --- |
| 用户启用/禁用无确认 | Users.vue | 增加二次确认 |
| 角色删除无权限检查 | RoleManagement.vue | 增加权限码判断 |
| 套餐表单无校验规则 | Plans.vue | 增加必填字段校验 |
| 角色表单 rules 未绑定到 el-form | RoleManagement.vue | 将 rules 移至 `:rules` 属性 |
| 多处 catch 空捕获，用户无法感知失败 | 多个 admin 页面 | 增加 ElMessage.error |
| 表格无 v-loading | Blocklist/Allowlist/ProfileList/Plans | 添加 loading 状态 |
| 危险操作按钮颜色不统一（warning vs danger） | Users.vue/Teams.vue | 统一使用 type="danger" |
| 空状态位置错误 | QueryLogs.vue | 将 #empty 移到 el-table 内 |
| 搜索栏样式不统一 | Logs.vue | 统一使用 ListPage 组件 |
| 主题色不一致（#6366f1 vs #2563eb） | Logs.vue | 统一使用 var(--color-primary) |
| 审计日志加载失败静默 | AuditLogs.vue | 增加错误提示 |
| 账单/支付流加载失败静默 | Bill.vue/PaymentFlows.vue | 增加错误提示 |
| 查询日志加载失败静默 | QueryLogs.vue | 增加错误提示 |
| 管理员操作列按钮过多（5个） | AdminAdmins.vue | 精简或下沉至详情抽屉 |
| 套餐表格无空状态 | Plans.vue | 添加 #empty 插槽 |
| ParentalControl.vue 内联样式过多 | ParentalControl.vue | 提取为 CSS 类 |
| Dashboard.vue 自定义 .card 与 el-card 不一致 | Dashboard.vue | 统一使用 el-card |
| Security.vue active-color 与主题色不一致 | Security.vue | 统一使用 var(--color-primary) |

### P2 优化问题

| 问题 | 影响范围 | 优化建议 |
| --- | --- | --- |
| 硬编码颜色值（#67c23a, #909399, #fff 等） | 多个页面 | 使用 CSS 变量 |
| 内联样式覆盖 el-card 背景 | Blocklist/Allowlist | 移除内联样式 |
| 空状态未使用 el-empty 组件 | 多个页面 | 统一使用 el-empty |
| 表格操作列按钮无 tooltip | Plans.vue | 增加 tooltip |
| 卡片间距不一致 | 部分页面 | 统一 spacing 变量 |
| 搜索按钮尺寸不一致 | AuditLogs.vue | 统一 size="small" |
| 退出登录无二次确认 | AdminLayout.vue | 增加确认 |
| 清除缓存无二次确认 | AdminLayout.vue | 增加确认 |
| 自定义滚动条仅兼容 Webkit | Plans.vue | 考虑 Firefox |
| 空状态文案过于简单 | TeamList.vue | 统一空状态展示 |

---

## 五、具体修改建议

### 1. 权限控制修复（P0）

**文件**：`router/index.js`

```javascript
// 修改前
{
  path: '/admin/users',
  component: Users,
  meta: { requiresAuth: true }
}

// 修改后
{
  path: '/admin/users',
  component: Users,
  meta: { 
    requiresAuth: true,
    permission: 'user.view'  // 增加权限标识
  }
}
```

**文件**：`views/admin/AdminAdmins.vue`

```javascript
// 修改前
const handleStatus = async (row) => {
  await updateStatus(row.id, { status: row.status });
};

// 修改后
const handleStatus = async (row) => {
  await ElMessageBox.confirm(
    t('admin.confirmStatusChange'),
    t('common.warning'),
    { type: 'warning' }
  );
  await updateStatus(row.id, { status: row.status });
};
```

### 2. 异常处理修复（P1）

**文件**：`views/admin/RegionManage.vue`

```javascript
// 修改前
const fetchRegions = async () => {
  try {
    const res = await api.getRegions();
    regions.value = res.data;
  } catch {}
};

// 修改后
const fetchRegions = async () => {
  try {
    const res = await api.getRegions();
    regions.value = res.data;
  } catch (error) {
    ElMessage.error(t('common.loadFailed'));
  }
};
```

### 3. 硬编码修复示例（P0）

**文件**：`views/Allowlist.vue`

```javascript
// 修改前
callback(new Error('请输入正确的格式'));

// 修改后
callback(new Error(t('allowlist.invalidFormat')));
```

**文件**：`components/PaymentModal.vue`

```javascript
// 修改前
const METHOD_LABELS = {
  card: '信用卡',
  wechat_pay: '微信支付',
  alipay: '支付宝'
};

// 修改后
const METHOD_LABELS = {
  card: t('payment.card'),
  wechat_pay: t('payment.wechatPay'),
  alipay: t('payment.alipay')
};
```

### 4. Loading 状态修复（P1）

**文件**：`views/Blocklist.vue`

```vue
<!-- 修改前 -->
<el-table :data="list">

<!-- 修改后 -->
<el-table :data="list" v-loading="loading">
```

---

## 六、功能完整度评分

| 维度 | 完成度 | 说明 |
| --- | ---: | --- |
| 查询 | 85% | 大部分页面支持搜索，但部分缺少重置 |
| 新增 | 80% | 入口存在，但部分表单无校验 |
| 编辑 | 75% | 部分页面无编辑功能 |
| 删除 | 85% | 有确认，但部分高危操作保护不足 |
| 批量删除 | 60% | 部分页面无批量删除 |
| 权限控制 | 40% | 仅校验 token，无 RBAC |
| 异常处理 | 50% | 多处 catch 空捕获 |
| 用户体验 | 70% | 主要流程可用，反馈不够 |
| UI一致性 | 70% | 整体统一，细节不一致 |
| 响应式适配 | 60% | 未充分测试各分辨率 |
| 多语言覆盖率 | 55% | 大量硬编码 |
| 代码质量 | 70% | 结构清晰，细节需优化 |

---

## 七、缺失功能清单

| 模块 | 缺失功能 | 影响等级 | 影响说明 | 建议 |
| --- | --- | --- | --- | --- |
| 路由权限 | RBAC 权限控制 | P0 | 任何管理员可访问所有后台 | 增加 meta.permission |
| 设备管理 | 详情/编辑 | P2 | 仅能删除 | 增加详情抽屉 |
| 表单验证 | 统一校验 | P1 | 部分表单可提交空数据 | 增加 rules |
| 异常处理 | 统一错误处理 | P1 | 用户无法感知失败 | 增加 error 提示 |
| 操作反馈 | Loading 状态 | P1 | 部分表格无 loading | 统一添加 |
| 空状态 | 统一组件 | P2 | 空状态展示不一致 | 使用 el-empty |
| 确认对话框 | 高危操作确认 | P1 | 部分操作无确认 | 统一增加 |
| 搜索栏 | 统一组件 | P1 | 部分页面样式不一致 | 使用 ListPage |

---

## 八、高风险问题清单

| 风险等级 | 模块 | 问题 | 影响 | 修复建议 |
| --- | --- | --- | --- | --- |
| **P0** | 路由权限 | admin 路由无角色/权限细分 | 任何登录管理员可访问所有后台 | 增加 meta.permission + RBAC |
| **P0** | 管理员管理 | 启用/禁用管理员无确认 | 误操作导致管理员被禁用 | 增加 ElMessageBox.confirm |
| **P0** | 多语言 | 大量中文硬编码 | 英文/韩文用户无法使用 | 提取到 i18n 文件 |
| P1 | 用户管理 | 启用/禁用用户无确认 | 误操作导致用户被禁用 | 增加二次确认 |
| P1 | 角色管理 | 删除无权限检查 | 越权删除角色 | 增加权限码判断 |
| P1 | 套餐管理 | 表单无校验 | 可提交空表单 | 增加必填字段校验 |
| P1 | 异常处理 | 多处 catch 空捕获 | 用户无法感知失败 | 增加 ElMessage.error |
| P1 | 审计日志 | 清空操作保护不足 | 日志被误清 | 增加额外保护 |
| P1 | 表格加载 | 部分表格无 v-loading | 用户不知道加载中 | 统一添加 |
| P1 | 按钮风格 | 危险操作颜色不统一 | 用户无法识别危险 | 统一 type="danger" |

---

## 九、上线建议

### 结论：**修复 P0 后可上线**

### 必须修复的 P0 问题：

1. **路由级权限控制**：为 admin 路由增加 `meta.permission` 字段，结合后端 RBAC 进行权限校验
2. **管理员状态变更确认**：`AdminAdmins.vue` 的 `handleStatus` 方法必须增加二次确认
3. **核心页面硬编码**：至少修复 QueryLogs、SystemConfig、Users、Subscriptions 等核心 admin 页面的中文硬编码

### 建议修复的 P1 问题：

1. 所有 catch 空捕获增加错误提示
2. 所有表格添加 v-loading
3. 所有高危操作增加二次确认
4. 表单增加校验规则
5. 危险操作按钮统一使用 type="danger"

### 可后续优化的 P2 问题：

1. 硬编码颜色值替换为 CSS 变量
2. 空状态统一使用 el-empty
3. 内联样式提取为 CSS 类
4. 搜索栏样式统一

---

## 十、统计汇总

| 类别 | P0 | P1 | P2 | 合计 |
| --- | ---: | ---: | ---: | ---: |
| 多语言硬编码 | 13 | 16 | 1 | 30 |
| 功能完整性 | 2 | 6 | 2 | 10 |
| UI一致性 | 0 | 12 | 14 | 26 |
| 权限安全 | 2 | 3 | 1 | 6 |
| 异常处理 | 0 | 4 | 3 | 7 |
| **合计** | **17** | **41** | **21** | **79** |

---

## 十一、审查覆盖范围

### 已扫描文件

- ✅ views/admin/ 下 30+ 个管理页面
- ✅ views/ 下 15+ 个用户端页面
- ✅ components/ 下公共组件
- ✅ router/ 路由配置
- ✅ locales/ i18n 语言包

### 无法验证项

- ⚠️ 无法进行运行态视觉验证，仅基于源码静态审查
- ⚠️ 响应式布局需实际运行验证
- ⚠️ 后端权限校验需后端配合确认

---

*报告生成时间：2026-07-09*
