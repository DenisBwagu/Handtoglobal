<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'view' => '查看',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Remaining Chinese withdrawal keys added.\n";
