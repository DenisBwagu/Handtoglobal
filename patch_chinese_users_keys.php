<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'users_management' => '用户管理',
'manage_all_registered_users' => '管理所有注册用户',
'all_users' => '所有用户',
'blocked' => '已封锁',
'are_you_sure_delete_user' => '你确定要删除此用户吗？',
'no_users_found_selected' => '未找到符合条件的用户。',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Chinese users keys added.\n";
