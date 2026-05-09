<?php
$file = __DIR__ . '/languages/chinese.php';
$cn = include $file;

$updates = [
    'total_deposits' => '总存款',
    'total_withdrawals' => '总提现',
    'total_bonuses_paid' => '已支付总奖金',
    'total_deductions' => '总扣款',
    'total_task_rewards' => '任务总奖励',
    'platform_net' => '平台净额',
    'profit_analysis' => '利润分析',
    'money_in' => '资金流入',
    'money_out' => '资金流出',
    'net_profit_loss' => '净利润/亏损',
    'outstanding_balances' => '未结余额',
    'where_money_going' => '资金去向',
    'task_rewards' => '任务奖励',
    'bonuses_paid' => '已支付奖金',
    'approved_withdrawals' => '已批准提现',
    'balance_adjustments' => '余额调整',
];

foreach ($updates as $k => $v) {
    $cn[$k] = $v;
}

file_put_contents($file, "<?php\n\nreturn " . var_export($cn, true) . ";\n");
echo "Chinese finance keys patched.\n";
