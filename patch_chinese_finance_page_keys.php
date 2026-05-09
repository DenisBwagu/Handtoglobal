<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'deposits' => '存款',
'from_date' => '开始日期',
'to_date' => '结束日期',
'apply_filter' => '应用筛选',
'task_earnings' => '任务收益',
'pending_transactions' => '待处理交易',
'transaction_trends' => '交易趋势',
'top_users_by_balance' => '余额最高用户',
'user' => '用户',
'total_earned' => '总收入',
'recent_transactions' => '最近交易',
'type' => '类型',
'status' => '状态',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");

echo "Chinese finance page keys added.\n";
