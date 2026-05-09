<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'manage_all_employees' => '管理所有员工',
'all_employees' => '所有员工',
'role' => '角色',
'created' => '创建时间',
'are_you_sure_delete_employee' => '你确定要删除此员工吗？',
'no_employees_found_click' => '未找到员工。点击',
'create_first_employee' => '创建第一个员工。',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Chinese employees keys added.\n";
