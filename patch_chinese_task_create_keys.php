<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'create_task' => '创建任务',
'add_task' => '添加任务',
'task_title' => '任务标题',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Chinese task create keys added.\n";
