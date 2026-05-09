<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'add_contact' => '添加联系人',
'registered' => '已注册',
'percentage' => '百分比',
'no_data' => '无数据',
'new' => '新建',
'contacted' => '已联系',
'converted' => '已转化',
'lost' => '流失',
'created' => '创建时间',
'notes' => '备注',
'create_contact' => '创建联系人',
'update_contact' => '更新联系人',
'are_you_sure_delete_contact' => '你确定要删除此联系人吗？',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Chinese contact keys added.\n";
