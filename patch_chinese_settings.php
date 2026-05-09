<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'homepage_images' => '主页图片',
'homepage_hero_image' => '主页主图',
'homepage_about_image' => '主页关于图片',
'homepage_banner_image' => '主页横幅图片',
'homepage_logo_strip_images' => '主页 Logo 条图片',
'legal_pages' => '法律页面',
'privacy_policy_content' => '隐私政策内容',
'terms_service_content' => '服务条款内容',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Chinese settings keys added.\n";
