<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'all_status' => '所有状态',
'memo_tag' => '备注标签',
'no_withdrawals_found' => '未找到提现记录',
'withdrawal_details' => '提现详情',
'user_name' => '用户名',
'user_email' => '用户邮箱',
'asset' => '资产',
'network' => '网络',
'date_submitted' => '提交日期',
'close' => '关闭',
'reject' => '拒绝',
'approve' => '批准',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Chinese withdrawal keys added.\n";
