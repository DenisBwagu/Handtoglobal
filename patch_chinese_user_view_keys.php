<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'updating_live' => '实时更新中',
'working_on' => '当前工作',
'level_complete' => '等级完成',
'task_completions' => '任务完成记录',
'login_as_user' => '以用户身份登录',
'reset_password' => '重置密码',
'unlock_level' => '解锁等级',
'select_level_unlock' => '选择要解锁的等级',
'select_level' => '选择等级',
'adjust_balance' => '调整余额',
'operation' => '操作',
'reason' => '原因',
'select_reason' => '选择原因',
'manual_adjustment' => '手动调整',
'penalty' => '处罚',
'correction' => '修正',
'refund' => '退款',
'user_limits' => '用户限制',
'custom_message' => '自定义消息',
'toggle_user_status' => '切换用户状态',
'flush_levels' => '重置等级',
'flush_account' => '重置账户',
'task' => '任务',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");

echo "Chinese user view keys added.\n";
