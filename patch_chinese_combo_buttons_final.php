<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$cn['new_combo'] = '新建组合';
$cn['create_combo'] = '创建组合';
$cn['create_new_combo'] = '创建新组合';

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Combo button Chinese keys fixed.\n";
