<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'export_csv' => '导出 CSV',
'generate' => '生成',
'no_invitation_codes_found' => '未找到邀请码。请在上方生成你的第一个邀请码！',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Chinese invitation button keys added.\n";
