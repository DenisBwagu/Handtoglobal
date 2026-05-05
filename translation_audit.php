<?php
require_once __DIR__ . '/config.php';

if (!function_exists('admin_logged_in_safe')) {
    function admin_logged_in_safe() {
        if (function_exists('isAdminLoggedIn')) {
            return isAdminLoggedIn();
        }
        return isset($_SESSION['admin_id']) || isset($_SESSION['admin']);
    }
}

if (!admin_logged_in_safe()) {
    header('Location: login.php');
    exit;
}

$languageFiles = [
    'english' => __DIR__ . '/languages/english.php',
    'chinese' => __DIR__ . '/languages/chinese.php',
    'german' => __DIR__ . '/languages/german.php',
    'greek' => __DIR__ . '/languages/greek.php',
    'ukrainian' => __DIR__ . '/languages/ukrainian.php',
];

$scanDirs = [
    __DIR__ . '/admin',
    __DIR__ . '/includes',
];

$rootFiles = glob(__DIR__ . '/*.php');

$foundKeys = [];

function scan_translation_file($file, &$foundKeys) {
    $content = file_get_contents($file);
    if ($content === false) return;

    preg_match_all("/__t\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/", $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $key = trim($match[1]);
        $fallback = trim($match[2]);
        if ($key !== '') {
            $foundKeys[$key] = $fallback !== '' ? $fallback : $key;
        }
    }
}

foreach ($scanDirs as $dir) {
    if (!is_dir($dir)) continue;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            scan_translation_file($file->getPathname(), $foundKeys);
        }
    }
}

foreach ($rootFiles as $file) {
    if (basename($file) !== 'translation_audit.php') {
        scan_translation_file($file, $foundKeys);
    }
}

ksort($foundKeys);

$languages = [];
$missing = [];

foreach ($languageFiles as $lang => $path) {
    if (file_exists($path)) {
        $data = include $path;
        $languages[$lang] = is_array($data) ? $data : [];
    } else {
        $languages[$lang] = [];
    }

    $missing[$lang] = [];
    foreach ($foundKeys as $key => $fallback) {
        if (!array_key_exists($key, $languages[$lang])) {
            $missing[$lang][$key] = $fallback;
        }
    }
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repair'])) {
    foreach ($languageFiles as $lang => $path) {
        $data = $languages[$lang] ?? [];

        foreach ($foundKeys as $key => $fallback) {
            if (!array_key_exists($key, $data)) {
                $data[$key] = $fallback;
            }
        }

        ksort($data);

        $export = "<?php\n\nreturn " . var_export($data, true) . ";\n";
        file_put_contents($path, $export);
    }

    header('Location: translation_audit.php?repaired=1');
    exit;
}

if (isset($_GET['repaired'])) {
    $message = 'Missing translation keys repaired successfully.';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Translation Audit</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            padding: 30px;
            color: #111827;
        }
        .card {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }
        h1, h2 {
            margin-top: 0;
        }
        .ok {
            color: #047857;
            font-weight: bold;
        }
        .bad {
            color: #dc2626;
            font-weight: bold;
        }
        button {
            background: #2563eb;
            color: white;
            border: none;
            padding: 12px 18px;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
        }
        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 6px;
        }
        ul {
            columns: 2;
        }
    </style>
</head>
<body>

<div class="card">
    <h1>Translation Audit</h1>
    <?php if ($message): ?>
        <p class="ok"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    <p>Total translation keys found: <strong><?= count($foundKeys) ?></strong></p>

    <form method="post">
        <button type="submit" name="repair" value="1">Repair Missing Translation Keys</button>
    </form>
</div>

<?php foreach ($missing as $lang => $items): ?>
    <div class="card">
        <h2><?= htmlspecialchars(ucfirst($lang)) ?></h2>

        <?php if (empty($items)): ?>
            <p class="ok">No missing keys.</p>
        <?php else: ?>
            <p class="bad"><?= count($items) ?> missing keys.</p>
            <ul>
                <?php foreach ($items as $key => $fallback): ?>
                    <li><code><?= htmlspecialchars($key) ?></code> → <?= htmlspecialchars($fallback) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

</body>
</html>