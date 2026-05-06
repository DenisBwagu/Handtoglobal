<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/settings_helpers.php';
require_once __DIR__ . '/includes/language_helpers.php';

$siteName = get_site_name();
$siteLogo = get_site_logo();
$supportEmail = get_setting('support_email', 'support@handtoglobal.com');
$content = trim(get_setting('privacy_policy_content', ''));
if ($content === '') {
    $content = '
        <h2>Privacy Policy</h2>
        <p>' . htmlspecialchars($siteName) . ' respects your privacy and is committed to protecting the personal information you share with us.</p>
        <h3>Information We Collect</h3>
        <p>We may collect your name, email address, account details, task activity, withdrawal information, device/session data, and support messages when you use our platform.</p>
        <h3>How We Use Information</h3>
        <p>We use information to create and secure accounts, process task activity, manage balances and withdrawals, provide support, prevent fraud, improve services, and meet operational or legal requirements.</p>
        <h3>Data Sharing</h3>
        <p>We do not sell your personal information. We may share information only with service providers, payment/support partners, administrators, or authorities where required to operate the platform or comply with law.</p>
        <h3>Security</h3>
        <p>We use reasonable technical and administrative safeguards to protect account information. Users are responsible for keeping login credentials private.</p>
        <h3>Your Choices</h3>
        <p>You may contact support to request account updates, language/settings changes, or help with privacy questions.</p>
        <h3>Contact</h3>
        <p>For privacy questions, contact us at ' . htmlspecialchars($supportEmail) . '.</p>
    ';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __t('privacy_policy', 'Privacy Policy'); ?> - <?php echo htmlspecialchars($siteName); ?></title>
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
