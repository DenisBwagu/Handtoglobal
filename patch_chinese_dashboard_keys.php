<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
    'total_users' => '用户总数',
    'active_users' => '活跃用户',
    'total_paid_out' => '总支付金额',
    'active_combos' => '活跃组合',
    'create_employee' => '创建员工',
    'generate_codes' => '生成邀请码',
    'view_withdrawals' => '查看提现',
    'admin_dashboard_overview' => '管理员仪表板概览',
    'level_progress_live' => '等级进度（实时）',
    'recent_activity_feed' => '最近活动动态',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Chinese dashboard keys patched.\n";
