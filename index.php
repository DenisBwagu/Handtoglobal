<?php
// Clear settings cache to ensure latest data
if (function_exists('get_all_settings')) {
    get_all_settings(true); // Force refresh
}

require_once 'config.php';
require_once 'includes/settings_helpers.php';
require_once 'get_translation.php';

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
            'content' => 'HandToGlobal has completely changed my life. I can now work from anywhere and earn a reliable income.',
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
    <link rel="icon" href="<?php echo htmlspecialchars(get_favicon()); ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --secondary: #7c3aed;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --text: #1a1a1a;
            --muted: #6b7280;
            --border: #e5e7eb;
            --bg: #f5f7fb;
            --white: #ffffff;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: var(--text);
            overflow-x: hidden;
        }

        /* Header Styles */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            z-index: 1000;
            padding: 1rem 0;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .logo img {
            height: 40px;
            width: auto;
        }

        /* Main Navigation (Admin/User Dashboard) - Hidden when right nav is shown */
.nav-buttons {
            display: none;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-secondary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #16a34a;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(34, 197, 94, 0.3);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        .nav-right {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .nav-right {
                display: none;
            }
            
            .nav-buttons {
                display: flex;
                gap: 12px;
                align-items: center;
            }
        }

        /* Hero Section */
        .hero {
            margin-top: 80px;
            padding: 100px 20px;
            background: var(--gradient);
            position: relative;
            overflow: hidden;
        }
        
        .hero {
            <?php $hero_image = get_setting('homepage_hero_image', '', true); ?>
            <?php if ($hero_image): ?>
                background: url('<?php echo $hero_image; ?>') center/cover no-repeat;
            <?php endif; ?>
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        .hero-content {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            color: white;
            margin-bottom: 24px;
            line-height: 1.2;
            animation: fadeInUp 1s ease-out;
        }

        .hero p {
            font-size: 1.25rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            animation: fadeInUp 1s ease-out 0.2s both;
        }

        .hero-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 1s ease-out 0.4s both;
        }

        /* Animated Logo Strip */
        .logo-strip {
            <?php $logo_strip = get_setting('homepage_logo_strip', '', true); ?>
            <?php if ($logo_strip): ?>
                background: var(--white);
                padding: 40px 0;
                overflow: hidden;
                border-bottom: 1px solid var(--border);
            <?php endif; ?>
        }

        .logo-track {
            display: flex;
            animation: scrollLeft 30s linear infinite;
            width: fit-content;
        }

        .logo-track:hover {
            animation-play-state: paused;
        }

        .logo-item {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            padding: 0 30px;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--muted);
        }

        .logo-item i {
            margin-right: 10px;
            font-size: 1.5rem;
            color: var(--primary);
        }

        /* Features Section */
        .features {
            padding: 100px 20px;
            background: var(--bg);
        }

        .features-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .features h2 {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 60px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
        }

        .feature-card {
            background: var(--white);
            padding: 40px 30px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: var(--gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 2rem;
            color: white;
        }

        .feature-card h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 16px;
        }

        .feature-card p {
            color: var(--muted);
            line-height: 1.6;
        }

        /* Testimonials Section */
        .testimonials {
            padding: 100px 20px;
            background: var(--white);
            overflow: hidden;
        }

        .testimonials h2 {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 60px;
        }

        .testimonials-track {
            display: flex;
            animation: scrollRight 40s linear infinite;
            width: fit-content;
        }

        .testimonials-track:hover {
            animation-play-state: paused;
        }

        .testimonial-card {
            flex-shrink: 0;
            background: var(--bg);
            padding: 30px;
            border-radius: 16px;
            margin: 0 20px;
            min-width: 350px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .testimonial-content {
            font-size: 1.1rem;
            color: var(--text);
            margin-bottom: 20px;
            line-height: 1.6;
            font-style: italic;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .author-avatar {
            width: 50px;
            height: 50px;
            background: var(--gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .author-info h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
        }

        .author-info .badge {
            display: inline-block;
            padding: 4px 12px;
            background: var(--primary);
            color: white;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* CTA Section */
        .cta {
            padding: 100px 20px;
            background: var(--gradient);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="2" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
            opacity: 0.3;
        }

        .cta-content {
            max-width: 800px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .cta h2 {
            font-size: 3rem;
            font-weight: 800;
            color: white;
            margin-bottom: 24px;
        }

        .cta p {
            font-size: 1.25rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 40px;
        }

        /* Footer */
        .footer {
            background: var(--text);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: white;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes scrollLeft {
            from {
                transform: translateX(0);
            }
            to {
                transform: translateX(-50%);
            }
        }

        @keyframes scrollRight {
            from {
                transform: translateX(-50%);
            }
            to {
                transform: translateX(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1.1rem;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }

            .features-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .testimonial-card {
                min-width: 300px;
                margin: 0 15px;
            }

            .cta h2 {
                font-size: 2rem;
            }

            .cta p {
                font-size: 1.1rem;
            }

            .header-content {
                flex-direction: column;
                gap: 20px;
            }

            .nav-buttons {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .hero {
                padding: 60px 15px;
            }

            .hero h1 {
                font-size: 2rem;
            }

            .features, .testimonials, .cta {
                padding: 60px 15px;
            }

            .feature-card {
                padding: 30px 20px;
            }

            .testimonial-card {
                padding: 20px;
                min-width: 250px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="logo">
                <?php $site_logo = get_setting('site_logo', '', true); ?>
                <?php if ($site_logo): ?>
                    <img src="<?php echo $site_logo; ?>" alt="<?php echo get_setting('site_name', 'HandToGlobal', true); ?>">
                <?php else: ?>
                    <i class="fas fa-hand-holding-usd"></i>
                <?php endif; ?>
                <?php echo get_setting('site_name', 'HandToGlobal'); ?>
            </a>
            
            <!-- Right Side Navigation -->
            <div class="nav-right">
                <?php if ($is_admin): ?>
                    <a href="admin/dashboard.php" class="btn btn-primary">
                        <i class="fas fa-tachometer-alt"></i>
                        Admin Dashboard
                    </a>
                <?php elseif ($is_logged_in): ?>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                    <a href="logout.php" class="btn btn-outline">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline">
                        <i class="fas fa-sign-in-alt"></i>
                        Login
                    </a>
                    <a href="register.php" class="btn btn-outline">
                        <i class="fas fa-user-plus"></i>
                        Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Transform Your Time into Income</h1>
            <p>Join thousands of earners worldwide completing simple tasks and getting paid daily. No experience needed - start earning today!</p>
            <div class="hero-buttons">
                <?php if (!$is_logged_in): ?>
                    <a href="register.php" class="btn btn-success">
                        <i class="fas fa-rocket"></i>
                        Start Earning Now
                    </a>
                <?php endif; ?>
                <a href="<?php echo $support_link; ?>" target="_blank" class="btn btn-secondary">
                    <i class="fas fa-headset"></i>
                    Get Support
                </a>
            </div>
        </div>
    </section>

    <!-- Animated Logo Strip -->
    <section class="logo-strip">
        <div class="logo-track">
            <?php 
            $logo_strip = get_setting('homepage_logo_strip', '', true);
            $logo_items = $logo_strip ? explode("\n", $logo_strip) : [];
            foreach ($logo_items as $item): ?>
                <div class="logo-item">
                    <?php echo htmlspecialchars(trim($item)); ?>
                </div>
            <?php endforeach; ?>
        </div>
            <div class="logo-item">
                <i class="fas fa-dollar-sign"></i>
                Daily Earnings
            </div>
            <div class="logo-item">
                <i class="fas fa-clock"></i>
                Flexible Hours
            </div>
            <div class="logo-item">
                <i class="fas fa-shield-alt"></i>
                Secure Payments
            </div>
            <div class="logo-item">
                <i class="fas fa-users"></i>
                Growing Community
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="features-content">
            <h2>Why Choose HandToGlobal?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h3>Simple Tasks</h3>
                    <p>Complete easy tasks that require no special skills. Perfect for beginners and experienced workers alike.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h3>Instant Payments</h3>
                    <p>Get paid quickly and securely. Multiple withdrawal options available for your convenience.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-globe-americas"></i>
                    </div>
                    <h3>Work Anywhere</h3>
                    <p>Access tasks from anywhere in the world. All you need is an internet connection.</p>
                </div>
            </div>
        </div>
    </section>

    <?php 
    $testimonials_display = get_setting('testimonials_display', 'both');
    if ($testimonials_display !== 'none'): 
?>
    <!-- Testimonials Section -->
    <section class="testimonials">
        <h2>What Our Community Says</h2>
        <div class="testimonials-track">
            <?php foreach ($testimonials as $testimonial): ?>
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        "<?php echo htmlspecialchars($testimonial['content']); ?>"
                    </div>
                    <div class="testimonial-author">
                        <div class="author-avatar">
                            <?php echo strtoupper(substr($testimonial['name'], 0, 1)); ?>
                        </div>
                        <div class="author-info">
                            <h4><?php echo htmlspecialchars($testimonial['name']); ?></h4>
                            <span class="badge"><?php echo ucfirst(htmlspecialchars($testimonial['type'])); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Duplicate testimonials for seamless loop -->
            <?php foreach ($testimonials as $testimonial): ?>
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        "<?php echo htmlspecialchars($testimonial['content']); ?>"
                    </div>
                    <div class="testimonial-author">
                        <div class="author-avatar">
                            <?php echo strtoupper(substr($testimonial['name'], 0, 1)); ?>
                        </div>
                        <div class="author-info">
                            <h4><?php echo htmlspecialchars($testimonial['name']); ?></h4>
                            <span class="badge"><?php echo ucfirst(htmlspecialchars($testimonial['type'])); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA Section -->
    <section class="cta">
        <div class="cta-content">
            <h2>Ready to Start Earning?</h2>
            <p>Join thousands of people who are already making money with HandToGlobal. Your journey to financial freedom starts here.</p>
            <?php if (!$is_logged_in): ?>
                <a href="register.php" class="btn btn-success" style="font-size: 1.1rem; padding: 16px 32px;">
                    <i class="fas fa-rocket"></i>
                    Get Started Now
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-links">
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
                <a href="<?php echo $support_link; ?>" target="_blank">Support</a>
                <a href="#" onclick="window.open('privacy.php', '_blank')">Privacy Policy</a>
                <a href="#" onclick="window.open('terms.php', '_blank')">Terms of Service</a>
            </div>
            <p>&copy; <?php echo date('Y'); ?> <?php echo get_setting('site_name', 'HandToGlobal'); ?>. All rights reserved.</p>
        </div>
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
    </script>
</body>
</html>
