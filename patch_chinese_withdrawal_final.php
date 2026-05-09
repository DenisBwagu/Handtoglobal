<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'asset_network' => '资产/网络',
'pending' => '待处理',
'approved' => '已批准',
'rejected' => '已拒绝',
'approve' => '批准',
'reject' => '拒绝',
'delete' => '删除',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Final Chinese withdrawal keys added.\n";
