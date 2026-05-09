<?php
// Clear settings cache to ensure latest data
if (function_exists('get_all_settings')) {
    get_all_settings(true); // Force refresh
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/settings_helpers.php';
require_once __DIR__ . '/includes/language_helpers.php';

$siteName = get_site_name();
$siteLogoUrl = get_site_logo();
$faviconUrl = get_favicon();

// Get testimonials from database (force refresh for instant updates)
$testimonials = [];
try {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM testimonials WHERE is_active = 1 ORDER BY display_order ASC, created_at DESC LIMIT 10");
    $stmt->execute();
    $testimonials = $stmt->fetchAll();
} catch(PDOException $e) {
    $testimonials = [];
}

// Fallback testimonials if none exist
if (empty($testimonials)) {
    $testimonials = [
        [
            'name' => 'Sarah Johnson',
            'content' => $siteName . ' has completely changed my life. I can now work from anywhere and earn a reliable income.',
            'type' => 'client',
            'display_order' => 1
        ],
        [
            'name' => 'Michael Chen',
            'content' => 'The platform is intuitive and the support team is amazing. I earned my first $100 within just 3 days!',
            'type' => 'user',
            'display_order' => 2
        ],
        [
            'name' => 'Emma Williams',
            'content' => 'Finally, a platform that delivers what it promises. Great tasks, fair payments, and excellent community.',
            'type' => 'client',
            'display_order' =>3
        ]
    ];
}

// Check if user is logged in
$is_logged_in = function_exists('isLoggedIn') && isLoggedIn();
$is_admin = function_exists('isAdminLoggedIn') && isAdminLoggedIn();

// Get support link from settings (force refresh for instant updates)
$support_link = get_telegram_link();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(get_meta_title()); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars(get_meta_description()); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars(get_meta_keywords()); ?>">
    <meta name="robots" content="<?php echo htmlspecialchars(get_meta_robots()); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars(get_meta_title()); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(get_meta_description()); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars(get_og_image()); ?>">
    <link rel="icon" href="<?php echo htmlspecialchars($faviconUrl); ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <style>
*{margin:0;padding:0;box-sizing:border-box}
:root{
    --green:#16d39a;
    --green-soft:#d8f8ec;
    --dark:#07111f;
    --dark-2:#0b1627;
    --text:#101827;
    --muted:#64748b;
    --border:#dbe3ee;
    --bg:#ffffff;
    --soft:#f8fafc;
    --white:#ffffff;
}
body{font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;color:var(--text);background:#fff;overflow-x:hidden}
a{text-decoration:none}
.header{height:58px;background:#fff;position:fixed;top:0;left:0;right:0;z-index:1000;border-bottom:1px solid #eee}
.header-content{max-width:1180px;margin:auto;height:58px;display:flex;align-items:center;justify-content:space-between;padding:0 22px}
.logo{display:flex;align-items:center;gap:8px;font-size:15px;font-weight:800;color:#2563eb}
.logo img{height:28px;width:auto}
.desktop-nav{display:flex;gap:24px}
.desktop-nav a{font-size:13px;color:#334155;font-weight:700}
.nav-right{display:flex;align-items:center;gap:12px}
.btn{display:inline-flex;align-items:center;justify-content:center;border-radius:8px;padding:11px 18px;font-weight:800;font-size:13px;border:0;cursor:pointer;transition:.25s}
.btn-light{color:#0f172a;background:#fff}
.btn-dark{color:#fff;background:#07111f}
.btn-success{background:var(--green);color:#fff}
.btn-glass{background:rgba(255,255,255,.13);color:#fff;border:1px solid rgba(255,255,255,.3)}
.large-btn{padding:15px 28px}

.hero{margin-top:58px;min-height:430px;background:url('<?php echo htmlspecialchars(get_setting('homepage_hero_image', '', true)); ?>') center/cover no-repeat;position:relative;display:flex;align-items:center;justify-content:center;text-align:center;color:#fff}
.hero-overlay{position:absolute;inset:0;background:rgba(3,9,20,.62)}
.hero-content{position:relative;z-index:2;max-width:760px;padding:40px 20px 20px}
.hero-badge{display:inline-block;background:rgba(24,201,139,.18);border:1px solid rgba(24,201,139,.45);color:#d1fae5;padding:8px 16px;border-radius:999px;font-size:12px;font-weight:800;margin-bottom:18px}
.hero h1{font-size:46px;line-height:1.02;font-weight:900;letter-spacing:-2px;margin-bottom:18px}
.hero h1 span{color:var(--green)}
.hero p{font-size:17px;line-height:1.7;color:#e5e7eb;max-width:640px;margin:0 auto 26px}
.hero-buttons{display:flex;justify-content:center;gap:14px;flex-wrap:wrap}
.hero-mini-stats{margin:20px auto 0;display:flex;justify-content:center;gap:14px}
.hero-mini-stats div{min-width:95px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.22);border-radius:12px;padding:13px 18px}
.hero-mini-stats strong{display:block;font-size:22px}
.hero-mini-stats span{font-size:12px;color:#dbeafe}

.trusted-strip{padding:34px 20px;text-align:center;background:#fff;border-bottom:1px solid var(--border)}
.trusted-strip p{font-size:12px;color:#64748b;margin-bottom:20px;font-weight:700}
.trusted-strip{
    padding:34px 0;
    text-align:center;
    background:#fff;
    border-bottom:1px solid var(--border);
    overflow:hidden;
}

.trusted-strip p{
    font-size:12px;
    color:#64748b;
    margin-bottom:20px;
    font-weight:700;
}

.trusted-logos{
    display:flex;
    align-items:center;
    gap:60px;
    width:max-content;
    animation:trustedScroll 28s linear infinite;
    color:#777;
    font-size:24px;
    font-weight:900;
    opacity:.65;
}

.trusted-logos:hover{
    animation-play-state:paused;
}

.trusted-logos span{
    flex:0 0 auto;
    min-width:120px;
}
.trusted-logo-image{
    width:130px;
    height:55px;
    display:flex;
    align-items:center;
    justify-content:center;
}

.trusted-logo-image img{
    max-width:120px;
    max-height:45px;
    object-fit:contain;
    filter:grayscale(100%);
    opacity:.75;
}

@keyframes trustedScroll{
    from{
        transform:translateX(100vw);
    }
    to{
        transform:translateX(-100%);
    }
}

.about-section{padding:80px 20px;background:#fff}
.about-grid{max-width:980px;margin:auto;display:grid;grid-template-columns:1.1fr .9fr;gap:60px;align-items:center}
.section-tag{font-size:12px;color:var(--green);font-weight:900;letter-spacing:.12em;margin-bottom:10px;text-transform:uppercase}
.section-tag.center{text-align:center}
.about-text h2,.how-section h2,.features-modern h2,.testimonials-modern h2,.cta-box h2{font-size:34px;line-height:1.15;font-weight:900;margin-bottom:14px}
.about-text p,.section-subtitle{color:#64748b;font-size:15px;line-height:1.8}
.about-features{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:24px}
.about-box{border:1px solid var(--border);border-radius:12px;padding:16px;background:#fff;font-weight:800;font-size:13px}
.about-box i{color:var(--green);display:block;margin-bottom:8px}
.about-image img{width:100%;height:270px;object-fit:cover;border-radius:18px;box-shadow:0 22px 50px rgba(15,23,42,.18)}

.how-section{
    padding:82px 20px;
    background:
        radial-gradient(circle at center, rgba(22,211,154,.18), transparent 35%),
        linear-gradient(180deg,#07111f 0%,#0b1627 100%);
    color:#fff;
    text-align:center;
    position:relative;
}
.how-section::before{
    content:"";
    position:absolute;
    inset:0;
    background-image:radial-gradient(rgba(255,255,255,.08) 1px, transparent 1px);
    background-size:18px 18px;
    opacity:.35;
    pointer-events:none;
}

.how-section > *{
    position:relative;
    z-index:1;
}

body.dark-mode{
    background:#07111f;
    color:#e5e7eb;
}

body.dark-mode .header,
body.dark-mode .trusted-strip,
body.dark-mode .about-section,
body.dark-mode .features-modern,
body.dark-mode .testimonials-modern{
    background:#07111f;
    color:#e5e7eb;
}

body.dark-mode .modern-card,
body.dark-mode .testimonial-modern-card,
body.dark-mode .about-box{
    background:#0b1627;
    border-color:rgba(255,255,255,.12);
}

body.dark-mode p,
body.dark-mode .modern-card p,
body.dark-mode .testimonial-modern-card p{
    color:#94a3b8;
}

.theme-toggle{
    width:34px;
    height:34px;
    border-radius:50%;
    border:1px solid #dbe3ee;
    background:#ffffff;
    color:#07111f;
    cursor:pointer;
    font-size:14px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    box-shadow:0 6px 18px rgba(15,23,42,.12);
}

.theme-toggle:hover{
    background:#f1f5f9;
}
.how-section .section-subtitle{color:#94a3b8;margin-bottom:30px}
.steps-grid{max-width:900px;margin:auto;display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.step-card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:24px;text-align:left}
.step-number{width:34px;height:34px;border-radius:9px;background:var(--green);display:flex;align-items:center;justify-content:center;font-weight:900;margin-bottom:14px}
.step-card h3{font-size:17px;margin-bottom:8px}
.step-card p{color:#cbd5e1;font-size:13px;line-height:1.6}

.features-modern{padding:80px 20px;background:#fff;max-width:980px;margin:auto}
.features-modern-grid{display:grid;grid-template-columns:repeat(3,1fr);border:1px solid var(--border);border-radius:16px;overflow:hidden;margin-top:26px}
.modern-card{padding:28px;border-right:1px solid var(--border);border-bottom:1px solid var(--border)}
.modern-card i{width:34px;height:34px;border-radius:8px;background:#dcfce7;color:var(--green);display:flex;align-items:center;justify-content:center;margin-bottom:14px}
.modern-card h3{font-size:16px;margin-bottom:8px}
.modern-card p{font-size:13px;color:#64748b;line-height:1.6}

.testimonials-modern{
    padding:80px 20px;
    background:#fff;
    max-width:980px;
    margin:auto;
}

.testimonials-slider{
    overflow:hidden;
    position:relative;
    margin-top:32px;
}

.testimonials-track-modern{
    display:flex;
    gap:22px;
    width:max-content;
    animation:testimonialsMove 38s linear infinite;
}

.testimonials-track-modern:hover{
    animation-play-state:paused;
}

.testimonial-modern-card{
    width:320px;
    min-height:220px;
    background:#fff;
    border:1px solid #dbe3ee;
    border-radius:18px;
    padding:26px;
    flex-shrink:0;
    box-shadow:0 10px 30px rgba(15,23,42,.05);
    display:flex;
    flex-direction:column;
    justify-content:space-between;
}

.quote-icon{
    color:#b7f3d9;
    font-size:34px;
    margin-bottom:16px;
}

.testimonial-modern-card p{
    color:#475569;
    font-size:14px;
    line-height:1.8;
    margin-bottom:28px;
}

.testimonial-bottom{
    display:flex;
    justify-content:space-between;
    align-items:center;
    border-top:1px solid #edf2f7;
    padding-top:18px;
}

.testimonial-bottom h4{
    font-size:14px;
    color:#07111f;
}

.client-badge{
    padding:5px 12px;
    border-radius:999px;
    background:#dcfce7;
    color:#16a34a;
    font-size:11px;
    font-weight:800;
}

@keyframes testimonialsMove{
    from{
        transform:translateX(0);
    }

    to{
        transform:translateX(-50%);
    }
}

.cta-modern{padding:70px 20px;background:#f8fafc}
.cta-box{max-width:820px;margin:auto;background:#07111f;color:#fff;border-radius:24px;padding:58px 30px;text-align:center}
.cta-box p{color:#cbd5e1;margin-bottom:24px}
.footer-modern{background:#050b14;color:#94a3b8;text-align:center;padding:35px 20px}
.footer-links{display:flex;justify-content:center;gap:24px;flex-wrap:wrap;margin-bottom:14px}
.footer-links a{color:#e5e7eb;font-size:13px}

@media(max-width:800px){
.desktop-nav{display:none}
.hero h1{font-size:38px}
.about-grid,.steps-grid,.features-modern-grid,.testimonial-modern-grid{grid-template-columns:1fr}
.about-features{grid-template-columns:1fr}
.hero-mini-stats{flex-direction:column;align-items:center}
}
</style>
</head>
<body>
    <!-- Header -->
<header class="header">
    <div class="header-content">
        <a href="index.php" class="logo">
            <?php if ($siteLogoUrl): ?>
                <img src="<?php echo htmlspecialchars($siteLogoUrl); ?>" alt="<?php echo htmlspecialchars($siteName); ?>">
            <?php else: ?>
                <i class="fas fa-hand-holding-usd"></i>
            <?php endif; ?>
            Hand To Global
        </a>

        <nav class="desktop-nav">
            <a href="#about">About</a>
            <a href="#how">How It Works</a>
            <a href="#features">Features</a>
            <a href="#reviews">Reviews</a>
        </nav>

       <div class="nav-right">

    <button type="button" class="theme-toggle" onclick="toggleTheme()" title="Toggle dark mode">
        <i class="fas fa-moon"></i>
    </button>

    <?php if ($is_logged_in): ?>
        <a href="dashboard.php" class="btn btn-dark">
            Dashboard
        </a>

    <?php else: ?>

        <a href="login.php" class="btn btn-light">
            Login
        </a>

        <a href="register.php" class="btn btn-dark">
            Get Started
        </a>

    <?php endif; ?>

</div>
    </div>
</header>

<!-- HERO -->
<section class="hero">
    <div class="hero-overlay"></div>

    <div class="hero-content">
        <div class="hero-badge">
            Professional Online Task Platform
        </div>

        <h1>
            Complete Tasks.<br>
            <span>Earn Rewards.</span>
        </h1>

        <p>
            Join Hand To Global and complete simple guided online tasks from anywhere in the world while earning daily rewards securely.
        </p>

        <div class="hero-buttons">
            <?php if (!$is_logged_in): ?>
                <a href="register.php" class="btn btn-success">
                    Start Earning Now
                </a>
            <?php endif; ?>

            <a href="<?php echo htmlspecialchars($support_link); ?>" target="_blank" class="btn btn-glass">
                Support
            </a>
        </div>

        <div class="hero-mini-stats">
            <div>
                <strong>40</strong>
                <span>Tasks</span>
            </div>

            <div>
                <strong>4</strong>
                <span>Levels</span>
            </div>

            <div>
                <strong>24/7</strong>
                <span>Access</span>
            </div>
        </div>
    </div>
</section>

<!-- TRUSTED -->
<section class="trusted-strip">
    <p><?php echo htmlspecialchars(get_setting('homepage_trusted_title', 'Trusted By Leading Brands')); ?></p>

    <?php
    $trustedLogoImages = json_decode(get_setting('homepage_trusted_logo_images', '[]'), true);
    $trustedLogoImages = is_array($trustedLogoImages) ? array_filter($trustedLogoImages) : [];

    $trustedLogoText = get_setting('homepage_trusted_logos', "FILA\nLEGO\nADIDAS\nSUBARU\nDELL\nNIKE", true);
    $trustedTextItems = array_filter(array_map('trim', explode("\n", $trustedLogoText)));

    $logoSpeed = (int)get_setting('homepage_logo_animation_speed', 28);
    if ($logoSpeed < 5) {
        $logoSpeed = 28;
    }
    ?>

    <div class="trusted-logos" style="animation-duration: <?php echo $logoSpeed; ?>s;">

        <?php if (!empty($trustedLogoImages)): ?>

            <?php for ($i = 0; $i < 2; $i++): ?>
                <?php foreach ($trustedLogoImages as $logo): ?>
                    <span class="trusted-logo-image">
                        <img src="<?php echo htmlspecialchars($logo); ?>" alt="Trusted Logo">
                    </span>
                <?php endforeach; ?>
            <?php endfor; ?>

        <?php else: ?>

            <?php for ($i = 0; $i < 2; $i++): ?>
                <?php foreach ($trustedTextItems as $logo): ?>
                    <span><?php echo htmlspecialchars($logo); ?></span>
                <?php endforeach; ?>
            <?php endfor; ?>

        <?php endif; ?>

    </div>
</section>

<!-- ABOUT -->
<section class="about-section" id="about">
    <div class="about-grid">

        <div class="about-text">
            <div class="section-tag">WHAT WE DO</div>

            <h2>Professional Online Task Platform</h2>

            <p>
                Hand To Global connects users worldwide with structured online tasks designed to help members grow through achievement levels while earning securely from anywhere.
            </p>

            <div class="about-features">
                <div class="about-box">
                    <i class="fas fa-check-circle"></i>
                    <span>Instant access</span>
                </div>

                <div class="about-box">
                    <i class="fas fa-globe"></i>
                    <span>Global platform</span>
                </div>

                <div class="about-box">
                    <i class="fas fa-language"></i>
                    <span>Multi-language</span>
                </div>
            </div>
        </div>

        <div class="about-image">
            <img src="<?php echo htmlspecialchars(get_setting('homepage_hero_image', '', true)); ?>" alt="About">
        </div>

    </div>
</section>

<!-- HOW IT WORKS -->
<section class="how-section" id="how">

    <div class="section-tag center">
        PROCESS
    </div>

    <h2>How It Works</h2>

    <p class="section-subtitle">
        Three simple steps to start earning
    </p>

    <div class="steps-grid">

        <div class="step-card">
            <div class="step-number">1</div>
            <h3>Register</h3>
            <p>Create your free account and access the dashboard instantly.</p>
        </div>

        <div class="step-card">
            <div class="step-number">2</div>
            <h3>Complete Tasks</h3>
            <p>Perform guided review tasks and increase your progress level.</p>
        </div>

        <div class="step-card">
            <div class="step-number">3</div>
            <h3>Withdraw Earnings</h3>
            <p>Request withdrawals securely after completing tasks successfully.</p>
        </div>

    </div>
</section>

<!-- FEATURES -->
<section class="features-modern" id="features">

    <div class="section-tag">
        FEATURES
    </div>

    <h2>Why Choose Hand To Global?</h2>

    <div class="features-modern-grid">

        <div class="modern-card">
            <i class="fas fa-layer-group"></i>
            <h3>Level Progression</h3>
            <p>Advance through structured earning levels.</p>
        </div>

        <div class="modern-card">
            <i class="fas fa-wallet"></i>
            <h3>Secure Withdrawals</h3>
            <p>Fast and protected withdrawal process.</p>
        </div>

        <div class="modern-card">
            <i class="fas fa-tasks"></i>
            <h3>Simple Tasks</h3>
            <p>Easy guided tasks for all users.</p>
        </div>

        <div class="modern-card">
            <i class="fas fa-shield-alt"></i>
            <h3>Secure Platform</h3>
            <p>Protected accounts and transactions.</p>
        </div>

        <div class="modern-card">
            <i class="fas fa-users"></i>
            <h3>Referral System</h3>
            <p>Invite users and earn additional rewards.</p>
        </div>

        <div class="modern-card">
            <i class="fas fa-headset"></i>
            <h3>24/7 Support</h3>
            <p>Professional support whenever needed.</p>
        </div>

    </div>
</section>

<!-- TESTIMONIALS -->
<section class="testimonials-modern" id="reviews">

    <div class="section-tag">
        REVIEWS
    </div>

    <h2>What Our Community Says</h2>

   <div class="testimonials-slider">

    <div class="testimonials-track-modern">

        <?php for ($loop = 0; $loop < 2; $loop++): ?>

            <?php foreach ($testimonials as $testimonial): ?>

                <div class="testimonial-modern-card">

                    <div class="quote-icon">
                        <i class="fas fa-quote-left"></i>
                    </div>

                    <p>
                        "<?php echo htmlspecialchars($testimonial['content']); ?>"
                    </p>

                    <div class="testimonial-bottom">

                        <div>
                            <h4><?php echo htmlspecialchars($testimonial['name']); ?></h4>
                        </div>

                        <span class="client-badge">
                            <?php echo ucfirst(htmlspecialchars($testimonial['type'])); ?>
                        </span>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endfor; ?>

    </div>

</div>

</section>

<!-- CTA -->
<section class="cta-modern">

    <div class="cta-box">

        <h2>Ready To Start Earning?</h2>

        <p>
            Join thousands of users already growing with Hand To Global.
        </p>

        <?php if (!$is_logged_in): ?>
            <a href="register.php" class="btn btn-success large-btn">
                Create Free Account
            </a>
        <?php endif; ?>

    </div>

</section>

<!-- FOOTER -->
<footer class="footer-modern">

    <div class="footer-links">
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
        <a href="<?php echo htmlspecialchars($support_link); ?>">Support</a>
        <a href="privacy.php">Privacy Policy</a>
        <a href="terms.php">Terms</a>
    </div>

    <p>
        © <?php echo date('Y'); ?> Hand To Global. All rights reserved.
    </p>

</footer>

    <script>
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Pause animations on hover
        document.querySelectorAll('.logo-track, .testimonials-track').forEach(track => {
            track.addEventListener('mouseenter', () => {
                track.style.animationPlayState = 'paused';
            });
            track.addEventListener('mouseleave', () => {
                track.style.animationPlayState = 'running';
            });
        });
        function toggleTheme(){
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('homepage_theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
}

if(localStorage.getItem('homepage_theme') === 'dark'){
    document.body.classList.add('dark-mode');
}
    </script>
</body>
</html>
