<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'edit_combo' => '编辑组合',
'edit_combo_details' => '编辑组合详情',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Chinese edit combo keys added.\n";
