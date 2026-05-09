<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'combos_management' => '组合管理',
'manage_combo_offers' => '创建和管理用户组合优惠',
'all_combos' => '所有组合',
'start_task' => '开始任务',
'end_task' => '结束任务',
'multiplier' => '倍数',
'deactivate' => '停用',
'activate' => '启用',
'are_you_sure_delete_combo' => '你确定要删除此组合吗？',
'create_new_combo' => '创建新组合',
'select_user' => '选择用户',
'select_level_first' => '请先选择等级',
'error_loading_tasks' => '加载任务失败',
'select_task' => '选择任务',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Chinese combos keys added.\n";
