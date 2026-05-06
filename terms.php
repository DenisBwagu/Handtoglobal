<?php
require_once 'config.php';
require_once 'includes/settings_helpers.php';
require_once 'includes/language_helpers.php';

$siteName = get_site_name();
$siteLogo = get_site_logo();
$supportEmail = get_setting('support_email', 'support@handtoglobal.com');
$content = trim(get_setting('terms_content', ''));
if ($content === '') {
    $content = '
        <h2>Terms of Service</h2>
        <p>These Terms of Service govern your use of ' . htmlspecialchars($siteName) . '. By creating an account or using the platform, you agree to these terms.</p>
        <h3>Account Use</h3>
        <p>You must provide accurate information, keep your login details secure, and use the platform only for lawful purposes. Account access may be limited or suspended for fraud, abuse, duplicate activity, or policy violations.</p>
        <h3>Tasks and Earnings</h3>
        <p>Task rewards, levels, limits, bonuses, and combo multipliers are controlled by platform settings and administrator rules. Earnings are credited after valid task completion and may be reviewed for accuracy or abuse prevention.</p>
        <h3>Withdrawals</h3>
        <p>Withdrawal requests are subject to minimum limits, account status, available balance, and administrator approval. Rejected withdrawals may include a reason shown in your account history.</p>
        <h3>Platform Changes</h3>
        <p>' . htmlspecialchars($siteName) . ' may update tasks, rewards, settings, support links, languages, policies, and availability to keep the service secure and functional.</p>
        <h3>User Responsibilities</h3>
        <p>You agree not to misuse the service, submit false information, attempt unauthorized access, or interfere with platform operations.</p>
        <h3>Contact</h3>
        <p>For terms or account questions, contact us at ' . htmlspecialchars($supportEmail) . '.</p>
    ';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __t('terms_of_service', 'Terms of Service'); ?> - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { margin:0; font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif; background:#eef2ff; color:#1f2937; line-height:1.7; }
        .wrap { max-width: 920px; margin: 0 auto; padding: 32px 20px 56px; }
        .brand { display:flex; align-items:center; gap:12px; text-decoration:none; color:#4f46e5; font-weight:800; font-size:22px; margin-bottom:24px; }
        .brand img { height:36px; width:auto; object-fit:contain; }
        .panel { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:32px; box-shadow:0 10px 24px rgba(15,23,42,.08); }
        h1,h2,h3 { color:#111827; line-height:1.25; }
        h1 { margin-top:0; }
        a { color:#4f46e5; font-weight:700; }
        .back { display:inline-flex; align-items:center; gap:8px; margin-top:22px; text-decoration:none; }
    </style>
</head>
<body>
    <main class="wrap">
        <a class="brand" href="index.php">
            <?php if ($siteLogo): ?><img src="<?php echo htmlspecialchars($siteLogo); ?>" alt="<?php echo htmlspecialchars($siteName); ?>"><?php endif; ?>
            <span><?php echo htmlspecialchars($siteName); ?></span>
        </a>
        <section class="panel">
            <?php echo $content; ?>
            <a class="back" href="index.php"><i class="fas fa-arrow-left"></i> <?php echo __t('back_home', 'Back Home'); ?></a>
        </section>
    </main>
</body>
</html>
