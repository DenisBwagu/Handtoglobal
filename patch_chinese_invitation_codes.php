<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'edit_invitation_code' => '编辑邀请码',
'code_summary' => '邀请码摘要',
'single' => '单个',
'no_employee' => '无员工',
'codes_active' => '邀请码启用',
'number_of_codes' => '邀请码数量',
'max_uses_per_code' => '每个邀请码最大使用次数',
'code_prefix' => '邀请码前缀',
'starting_balance' => '初始余额',
'remaining' => '剩余',
'are_you_sure_delete_invitation_code' => '你确定要删除此邀请码吗？',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Chinese invitation code keys added.\n";
