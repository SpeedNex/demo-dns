/user/account  会员顶部 安全防护 右侧的上线箭头图标重复，删除订阅套餐所在的面板。在第一个统计面板下面右侧保留订阅按钮，点击哦可以弹窗购买套餐。余额 的金额 **US$0.00 显示USD 金额 币种 金额的格式 全站统一，验证测试用户订阅购买闭环。**

<br />

admin/user-policy-services 数据库导航的方案改成配置 ，后台导航删除基本配置 名称

<br />

/user/profiles 会员中心右侧的**DNS 配置，删除方案，删除发布按钮**

<br />

/user/5c300b 会员中心，默认每个方案只显示一个dns IP 服务器。一个方案实现对应一个dns IP 服务器，如何实现。

<br />

/admin/menu-config 后台菜单导航，点击开关修改状态，没有正确改变，造成左侧导航整理消息，这是错误的，请修复。我需要通过点击开关来控制菜单隐藏 

<br />

/admin/profile-publish 第一类宽度加大一点 

/admin/refund-records 没列宽度适中吗，不要让文字随便按行

<br />

/admin/bill 右侧列表宽度100%

<br />

 admin/users 用户充值 \
https\://test-dns.ocerlinkdata.com/api/v1/admin/billing/charge

**请求方法**

POST

**状态代码  抱错  **{
"error": {
"code": "VALIDATION\_FAILED",
"message": "The user id field is required."
}
}

422 Unproce 

<br />

修复保证充值后的充值记录的金额都正确

/admin/bill 消费记录的 显示用户名 **总金额 金额不显示  开具日期 没有显示**

<br />

/admin/billing 缺少显示用户名 

| **wallet\_topup 多语言没有实现** |
| :------------------------ |
| <br />                    |

**编辑套餐 页面表单UI  下拉表单和表单高度统一一下**

<br />

admin/rbac 添加角色缺少菜单规则选择。

<br />

/admin/admins 管理员列表  **操作 宽度加大点，分配角色没有显示数据，分配角色抱错：**

SQLSTATE\[42S22]: Column not found: 1054 Unknown column 'role\_id' in 'field list' (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: ocer\_dns, SQL: insert into \`dns\_admin\_user\_roles\` (\`admin\_id\`, \`role\_id\`, \`assigned\_by\`, \`assigned\_at\`) values (1, 1, 1, 2026-06-25 10:03:57))

<br />

<br />

/admin/user-policy-services 这个列表显示是否合理，是否需要调整下 

<br />

/admin/member-catalogs 表单t统一高度

<br />

/admin/query-logs 用户dns 请求访问后台记录的怎么都是默认配置，其他配置访问都没生效。怎么回事 

**查询类型**

**类型**

**协议**

 宽度太窄了，加大 

<br />

对后台UI代码审查，统一下搜索表单说和新增的操作功能的位置，有的页面有点乱

