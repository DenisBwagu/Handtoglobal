<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'tasks_management' => '任务管理',
'manage_all_tasks' => '管理所有任务',
'all_tasks' => '所有任务',
'all_levels' => '所有等级',
'actions' => '操作',
'no_image' => '无图片',
'no_tasks_found' => '未找到任务',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Chinese tasks list keys added.\n";
