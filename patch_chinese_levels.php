<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'order' => '排序',
'deposit' => '存款',
'are_you_sure_delete_level' => '你确定要删除此等级吗？',
'no_levels_found' => '未找到等级。点击“添加”创建第一个等级。',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Chinese levels keys added.\n";
