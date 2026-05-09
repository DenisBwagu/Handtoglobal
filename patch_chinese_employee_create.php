<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'add_new_employee_system' => '添加新员工到系统',
'employee_information' => '员工信息',
'manager' => '经理',
'administrator' => '管理员',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Chinese employee create keys added.\n";
