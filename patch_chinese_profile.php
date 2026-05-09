<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'profile_information' => '个人资料信息',
'full_name' => '全名',
'security' => '安全',
'current_password' => '当前密码',
'new_password' => '新密码',
'confirm_new_password' => '确认新密码',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Chinese profile keys added.\n";
