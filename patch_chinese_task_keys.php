<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'title' => '标题',
'reward_amount' => '奖励金额',
'instructions' => '说明',
'item_image' => '任务图片',
'external_link' => '外部链接',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");

echo "Chinese task keys added.\n";
