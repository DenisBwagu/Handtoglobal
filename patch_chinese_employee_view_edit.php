<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'edit_employee' => '编辑员工',
'codes' => '邀请码',
'recruited' => '已招募',
'used_invitation_codes' => '已使用邀请码',
'code' => '代码',
'max_uses' => '最大使用次数',
'used' => '已使用',
'no_recruited' => '暂无招募用户',
'recruited_users' => '招募用户',
'no_contacts' => '暂无联系人',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Chinese employee view/edit keys added.\n";
