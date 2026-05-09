<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
'Join thousands of earners worldwide completing simple tasks and getting paid daily. No experience needed - start earning today!' => '加入全球数千名通过完成简单任务每日赚钱的用户。无需经验，立即开始赚钱！',
'of' => '的',
'of_total' => '总共',
'og_image' => 'OG 图片',
'ok' => '确定',
'operation_failed' => '操作失败',
'operation_successful' => '操作成功',
'other' => '其他',
'page' => '页面',
'partner' => '合作伙伴',
'password' => '密码',
'password_mismatch' => '密码不匹配',
'pending' => '待处理',
'phone' => '电话',
'please_fill_required_fields' => '请填写所有必填字段',
'please_wait' => '请稍候...',
'points' => '积分',
'previous' => '上一页',
'processing' => '处理中...',
'profit' => '利润',
'progress' => '进度',
'rank' => '排名',
'receive' => '接收',
'rejected' => '已拒绝',
'remove' => '移除',
'reply' => '回复',
'reward' => '奖励',
'score' => '分数',
'search' => '搜索',
'send' => '发送',
'seo' => '搜索引擎优化',
'settings_updated_successfully' => '设置更新成功',
'show_all' => '显示全部',
'showing' => '显示中',
'site_logo' => '网站标志',
'site_name' => '网站名称',
'sort' => '排序',
'success' => '成功',
'support_email' => '支持邮箱',
'task_completed' => '任务已完成',
'telegram_link' => 'Telegram 链接',
'testimonial' => '评价',
'testimonials_display' => '评价展示',
'thank_you' => '谢谢',
'this_month' => '本月',
'this_week' => '本周',
'this_year' => '今年',
'time' => '时间',
'to' => '到',
'today' => '今天',
'update' => '更新',
'updated_at' => '更新时间',
'upload' => '上传',
'user_locale' => '用户语言',
'video' => '视频',
'warning' => '警告',
'welcome' => '欢迎',
'withdrawal' => '提现',
'yes' => '是',
'yesterday' => '昨天'
];

foreach ($updates as $key => $value) {
    $cn[$key] = $value;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");

echo "Final Chinese translations completed.\n";
