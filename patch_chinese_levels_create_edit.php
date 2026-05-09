<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'icon' => '图标',
'task_type' => '任务类型',
'select_type' => '选择类型',
'name_items' => '命名项目',
'reward_per_task' => '每任务奖励',
'number_of_tasks' => '任务数量',
'requires_deposit' => '需要存款',
'tasks_count' => '任务数量',
'deposit_amount' => '存款金额',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Chinese levels create/edit keys added.\n";
