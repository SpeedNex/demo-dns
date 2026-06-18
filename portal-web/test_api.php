<?php
// API 闭环测试脚本
$baseUrl = 'http://127.0.0.1:8081/api/v1';

function api($method, $url, $token = null, $data = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if ($token) $headers[] = "Authorization: Bearer $token";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($resp, true) ?: $resp];
}

$pass = 0; $fail = 0;
function check($name, $r) {
    global $pass, $fail;
    $ok = $r['code'] >= 200 && $r['code'] < 300;
    echo sprintf("  [%s] %s (HTTP %d)\n", $ok ? 'OK' : 'FAIL', $name, $r['code']);
    if (!$ok) {
        echo "    Body: " . substr(is_string($r['body']) ? $r['body'] : json_encode($r['body'], JSON_UNESCAPED_UNICODE), 0, 200) . "\n";
    }
    $ok ? $pass++ : $fail++;
}

echo "========== 会员闭环测试 (test/123456) ==========\n\n";

// 1. 会员登录
echo "【认证】\n";
$r = api('POST', "$baseUrl/public/auth/login", null, ['email'=>'test@ocer.local','password'=>'123456','device_name'=>'browser']);
$userToken = $r['body']['data']['token'] ?? null;
check('会员登录', $r);
if (!$userToken) { echo "登录失败，终止测试\n"; exit(1); }

// 2. 获取当前用户信息
$r = api('GET', "$baseUrl/member/me", $userToken);
check('获取当前用户', $r);

// 3. 会员中心概览
$r = api('GET', "$baseUrl/member/member-center/overview", $userToken);
check('会员中心概览', $r);

// 4. 配置方案列表
$r = api('GET', "$baseUrl/member/profiles", $userToken);
check('配置方案列表', $r);

// 5. 安全防护
$r = api('GET', "$baseUrl/member/security", $userToken);
check('安全防护', $r);

// 6. 隐私保护
$r = api('GET', "$baseUrl/member/privacy", $userToken);
check('隐私保护', $r);

// 7. 家长控制
$r = api('GET', "$baseUrl/member/parental", $userToken);
check('家长控制', $r);

// 8. 黑名单
$r = api('GET', "$baseUrl/member/denylist", $userToken);
check('黑名单', $r);

// 9. 白名单
$r = api('GET', "$baseUrl/member/allowlist", $userToken);
check('白名单', $r);

// 10. 统计分析
$r = api('GET', "$baseUrl/member/analytics", $userToken);
check('统计分析', $r);

// 11. 查询日志
$r = api('GET', "$baseUrl/member/logs", $userToken);
check('查询日志', $r);

// 12. 设备管理
$r = api('GET', "$baseUrl/member/member-center/devices", $userToken);
check('设备列表', $r);

// 13. API Keys
$r = api('GET', "$baseUrl/member/api-keys", $userToken);
check('API Keys', $r);

// 14. 系统设置
$r = api('GET', "$baseUrl/member/settings", $userToken);
check('系统设置', $r);

// 15. 团队列表
$r = api('GET', "$baseUrl/member/teams", $userToken);
check('团队列表', $r);

// 16. 会员订阅
$r = api('GET', "$baseUrl/member/membership", $userToken);
check('会员订阅', $r);

// 17. 钱包
$r = api('GET', "$baseUrl/member/wallet", $userToken);
check('钱包', $r);

// 18. DNS 端点
$r = api('GET', "$baseUrl/member/member-center/dns-endpoints", $userToken);
check('DNS 端点', $r);

// 19. 套餐目录
$r = api('GET', "$baseUrl/member/catalogs", $userToken);
check('套餐目录', $r);

echo "\n========== 管理员闭环测试 (admin/123456) ==========\n\n";

// 20. 管理员登录
$r = api('POST', "$baseUrl/admin/login", null, ['email'=>'admin@ocer.local','password'=>'123456','device_name'=>'browser']);
$adminToken = $r['body']['data']['token'] ?? null;
check('管理员登录', $r);
if (!$adminToken) { echo "管理员登录失败，终止测试\n"; exit(1); }

// 21. 管理后台概览
$r = api('GET', "$baseUrl/admin/overview", $adminToken);
check('管理后台概览', $r);

// 22. 用户管理
$r = api('GET', "$baseUrl/admin/users", $adminToken);
check('用户管理', $r);

// 23. 设备管理
$r = api('GET', "$baseUrl/admin/devices", $adminToken);
check('设备管理(admin)', $r);

// 24. 计费统计
$r = api('GET', "$baseUrl/admin/billing-stats", $adminToken);
check('计费统计', $r);

// 25. 用户目录
$r = api('GET', "$baseUrl/admin/member-catalogs", $adminToken);
check('用户目录', $r);

echo "\n========== 测试结果 ==========\n";
echo "通过: $pass / 失败: $fail\n";
echo ($fail === 0 ? "所有测试通过!" : "存在 $fail 个失败项") . "\n";