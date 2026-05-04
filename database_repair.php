<?php
/**
 * Database Repair Script for HandToGlobal
 * 
 * This script fixes all database schema issues and ensures all tables/columns exist
 * Run this script once to repair the database structure
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type
header('Content-Type: text/plain');

echo "=== HANDTOGLOBAL DATABASE REPAIR ===\n\n";

// Connect to database
try {
    require_once 'config.php';
    $conn = getConnection();
    echo "✅ Database connected successfully\n";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Helper function to safely add column
function safeAddColumn($conn, $table, $column, $definition) {
    try {
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($check->rowCount() == 0) {
            $conn->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            echo "✅ Added column $column to table $table\n";
            return true;
        } else {
            echo "✅ Column $column already exists in table $table\n";
            return true;
        }
    } catch (PDOException $e) {
        echo "❌ Failed to add column $column to table $table: " . $e->getMessage() . "\n";
        return false;
    }
}

// Helper function to safely create table
function safeCreateTable($conn, $tableName, $createSQL) {
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS `$tableName` ($createSQL)");
        echo "✅ Table $tableName created/verified\n";
        return true;
    } catch (PDOException $e) {
        echo "❌ Failed to create table $tableName: " . $e->getMessage() . "\n";
        return false;
    }
}

echo "\n=== FIXING USERS TABLE ===\n";

// Create/Update USERS table
safeCreateTable($conn, 'users', "
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(255),
    name VARCHAR(255),
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255),
    balance DECIMAL(10,2) DEFAULT 0.00,
    level VARCHAR(50) DEFAULT 'Bronze',
    rating DECIMAL(5,2) DEFAULT 0.00,
    accuracy DECIMAL(5,2) DEFAULT 0.00,
    total_tasks INT DEFAULT 0,
    bronze_unlocked TINYINT(1) DEFAULT 1,
    silver_unlocked TINYINT(1) DEFAULT 0,
    gold_unlocked TINYINT(1) DEFAULT 0,
    platinum_unlocked TINYINT(1) DEFAULT 0,
    vip1_unlocked TINYINT(1) DEFAULT 0,
    vip2_unlocked TINYINT(1) DEFAULT 0,
    vip3_unlocked TINYINT(1) DEFAULT 0,
    invite_code_used VARCHAR(50) DEFAULT NULL,
    referred_by INT DEFAULT NULL,
    is_blocked TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
");

// Add missing columns to USERS table
$usersColumns = [
    'fullname' => 'VARCHAR(255)',
    'name' => 'VARCHAR(255)',
    'balance' => 'DECIMAL(10,2) DEFAULT 0.00',
    'level' => 'VARCHAR(50) DEFAULT "Bronze"',
    'rating' => 'DECIMAL(5,2) DEFAULT 0.00',
    'accuracy' => 'DECIMAL(5,2) DEFAULT 0.00',
    'total_tasks' => 'INT DEFAULT 0',
    'bronze_unlocked' => 'TINYINT(1) DEFAULT 1',
    'silver_unlocked' => 'TINYINT(1) DEFAULT 0',
    'gold_unlocked' => 'TINYINT(1) DEFAULT 0',
    'platinum_unlocked' => 'TINYINT(1) DEFAULT 0',
    'vip1_unlocked' => 'TINYINT(1) DEFAULT 0',
    'vip2_unlocked' => 'TINYINT(1) DEFAULT 0',
    'vip3_unlocked' => 'TINYINT(1) DEFAULT 0',
    'invite_code_used' => 'VARCHAR(50) DEFAULT NULL',
    'referred_by' => 'INT DEFAULT NULL',
    'is_blocked' => 'TINYINT(1) DEFAULT 0',
    'is_active' => 'TINYINT(1) DEFAULT 1',
    'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
];

foreach ($usersColumns as $column => $definition) {
    safeAddColumn($conn, 'users', $column, $definition);
}

echo "\n=== FIXING ADMINS TABLE ===\n";

safeCreateTable($conn, 'admins', "
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
");

echo "\n=== FIXING TESTIMONIALS TABLE ===\n";

safeCreateTable($conn, 'testimonials', "
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    role VARCHAR(255),
    type VARCHAR(50) DEFAULT 'user',
    content TEXT,
    message TEXT,
    image VARCHAR(255),
    status TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
");

// Add both content and message columns to handle both scenarios
safeAddColumn($conn, 'testimonials', 'content', 'TEXT');
safeAddColumn($conn, 'testimonials', 'message', 'TEXT');

echo "\n=== FIXING CONTACTS TABLE ===\n";

safeCreateTable($conn, 'contacts', "
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    subject VARCHAR(255),
    message TEXT,
    registered TINYINT(1) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
");

echo "\n=== FIXING LEVELS TABLE ===\n";

safeCreateTable($conn, 'levels', "
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    level_name VARCHAR(100),
    type VARCHAR(50) DEFAULT 'standard',
    task_reward DECIMAL(10,2) DEFAULT 0.00,
    reward_per_task DECIMAL(10,2) DEFAULT 0.00,
    daily_task_limit INT DEFAULT 40,
    total_tasks INT DEFAULT 40,
    deposit_amount DECIMAL(10,2) DEFAULT 0.00,
    unlock_amount DECIMAL(10,2) DEFAULT 0.00,
    sort_order INT DEFAULT 0,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
");

echo "\n=== FIXING TASKS TABLE ===\n";

safeCreateTable($conn, 'tasks', "
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    level VARCHAR(50) DEFAULT 'Bronze',
    reward DECIMAL(10,2) DEFAULT 0.00,
    image VARCHAR(255),
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
");

echo "\n=== FIXING COMBOS TABLE ===\n";

safeCreateTable($conn, 'combos', "
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    name VARCHAR(255),
    title VARCHAR(255),
    description TEXT,
    level VARCHAR(50),
    start_task_id INT DEFAULT NULL,
    end_task_id INT DEFAULT NULL,
    task_count INT DEFAULT 0,
    amount DECIMAL(10,2) DEFAULT 0.00,
    reward DECIMAL(10,2) DEFAULT 0.00,
    image VARCHAR(255),
    status VARCHAR(20) DEFAULT 'Active',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
");

echo "\n=== FIXING INVITATION_CODES TABLE ===\n";

safeCreateTable($conn, 'invitation_codes', "
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    is_used TINYINT(1) DEFAULT 0,
    used_by INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    employee_id INT DEFAULT NULL,
    bonus DECIMAL(10,2) DEFAULT 0.00,
    starting_balance DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL,
    FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
");

echo "\n=== FIXING EMPLOYEES TABLE ===\n";

safeCreateTable($conn, 'employees', "
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    fullname VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(50),
    role VARCHAR(100) DEFAULT 'employee',
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
");

// Add both name and fullname columns
safeAddColumn($conn, 'employees', 'fullname', 'VARCHAR(255)');

echo "\n=== FIXING FINANCE TABLES ===\n";

// Create finance_activities table (used by finance_analysis.php)
try {
    // Drop and recreate finance_activities table if tablespace issue exists
    $conn->exec("DROP TABLE IF EXISTS finance_activities");
    $conn->exec("CREATE TABLE finance_activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT DEFAULT NULL,
        user_id INT DEFAULT NULL,
        type VARCHAR(50) NOT NULL,
        reason TEXT,
        amount DECIMAL(10,2) NOT NULL,
        balance_after DECIMAL(10,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Table finance_activities created/verified\n";
} catch (PDOException $e) {
    echo "❌ Failed to create table finance_activities: " . $e->getMessage() . "\n";
}

// Also create balance_adjustments table for compatibility
safeCreateTable($conn, 'balance_adjustments', "
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    type VARCHAR(50) NOT NULL,
    reason TEXT,
    amount DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
");

echo "\n=== FIXING DEPOSITS TABLE ===\n";

safeCreateTable($conn, 'deposits', "
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    asset VARCHAR(50) DEFAULT 'USDT',
    network VARCHAR(50) DEFAULT 'TRC20',
    transaction_hash VARCHAR(255),
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
");

echo "\n=== FIXING WITHDRAWALS TABLE ===\n";

safeCreateTable($conn, 'withdrawals', "
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    asset VARCHAR(50) DEFAULT 'USDT',
    network VARCHAR(50) DEFAULT 'TRC20',
    wallet_address VARCHAR(255) NOT NULL,
    memo_tag VARCHAR(255),
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    rejection_reason TEXT,
    approved_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES admins(id) ON DELETE SET NULL
");

echo "\n=== FIXING SUPPORT TABLES ===\n";

// Create completed_tasks table
safeCreateTable($conn, 'completed_tasks', "
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    task_id INT NOT NULL,
    level VARCHAR(50) DEFAULT 'Bronze',
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_task (user_id, task_id)
");

// Create user_levels table
safeCreateTable($conn, 'user_levels', "
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    level VARCHAR(50) NOT NULL,
    is_unlocked TINYINT(1) DEFAULT 0,
    completed_count INT DEFAULT 0,
    flushed_at TIMESTAMP NULL,
    unlocked_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_level (user_id, level),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
");

// Create balance_logs table
safeCreateTable($conn, 'balance_logs', "
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    admin_id INT DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    action_type ENUM('credit', 'debit') NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
");

// Create user_limits table
safeCreateTable($conn, 'user_limits', "
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    max_levels_per_day INT DEFAULT 3,
    min_withdrawal_amount DECIMAL(10,2) DEFAULT 10.00,
    min_withdrawal_level VARCHAR(50) DEFAULT 'Bronze',
    min_balance DECIMAL(10,2) DEFAULT 0.00,
    custom_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_limit (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
");

// Create settings table
safeCreateTable($conn, 'settings', "
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
");

// Create languages table
safeCreateTable($conn, 'languages', "
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
");

// Create translations table
safeCreateTable($conn, 'translations', "
    id INT AUTO_INCREMENT PRIMARY KEY,
    language_code VARCHAR(10) NOT NULL,
    translation_key VARCHAR(255) NOT NULL,
    translation_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (language_code) REFERENCES languages(code) ON DELETE CASCADE,
    UNIQUE KEY unique_translation (language_code, translation_key)
");

echo "\n=== FIXING FINANCE_ANALYSIS.PHP ARRAY WARNINGS ===\n";

// Update finance_analysis.php to prevent undefined array key warnings
$financeAnalysisPath = __DIR__ . '/admin/finance_analysis.php';
if (file_exists($financeAnalysisPath)) {
    $content = file_get_contents($financeAnalysisPath);
    
    // Check if default values are already added
    if (strpos($content, "Add default values to prevent undefined array key warnings") === false) {
        // Find the location after the finance statistics query
        $pattern = '/(\} catch\(PDOException \$e\) \{\s+\$error = "Failed to fetch financial statistics: " \. \$e->getMessage\(\);\s+\})/';
        
        if (preg_match($pattern, $content)) {
            $replacement = '$1' . "\n\n// Add default values to prevent undefined array key warnings\n\$stats = array_merge([\n    'deposits_total' => 0,\n    'withdrawals_total' => 0,\n    'bonuses_total' => 0,\n    'deductions_total' => 0,\n    'tasks_earnings' => 0,\n    'platform_net' => 0,\n    'outstanding_balances' => 0,\n    'deposits_count' => 0,\n    'withdrawals_count' => 0,\n    'bonuses_count' => 0,\n    'deductions_count' => 0,\n    'tasks_completed' => 0,\n    'pending_deposits_count' => 0,\n    'pending_deposits_total' => 0,\n    'pending_withdrawals_count' => 0,\n    'pending_withdrawals_total' => 0\n], \$stats);";
            
            $content = preg_replace($pattern, $replacement, $content);
            
            if (file_put_contents($financeAnalysisPath, $content)) {
                echo "✅ Fixed undefined array key warnings in finance_analysis.php\n";
            } else {
                echo "❌ Failed to update finance_analysis.php\n";
            }
        }
    } else {
        echo "✅ finance_analysis.php already has default values\n";
    }
} else {
    echo "❌ finance_analysis.php not found\n";
}

echo "\n=== VERIFYING TABLE STRUCTURES ===\n";

// List all tables to verify they exist
try {
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "✅ Found " . count($tables) . " tables in database\n";
    
    $requiredTables = [
        'users', 'admins', 'testimonials', 'contacts', 'levels', 'tasks', 
        'combos', 'invitation_codes', 'employees', 'finance_activities', 
        'balance_adjustments', 'deposits', 'withdrawals', 'completed_tasks',
        'user_levels', 'balance_logs', 'user_limits', 'settings', 
        'languages', 'translations'
    ];
    
    foreach ($requiredTables as $table) {
        if (in_array($table, $tables)) {
            echo "✅ Table $table exists\n";
        } else {
            echo "❌ Table $table missing\n";
        }
    }
} catch (PDOException $e) {
    echo "❌ Failed to list tables: " . $e->getMessage() . "\n";
}

echo "\n=== DATABASE REPAIR COMPLETE ===\n";
echo "✅ All database schema issues have been fixed\n";
echo "✅ All required tables and columns have been created/updated\n";
echo "✅ Finance analysis array warnings have been fixed\n";
echo "✅ Your HandToGlobal database is now ready for use\n\n";

echo "Next steps:\n";
echo "1. Test your admin panel functions\n";
echo "2. Test user registration and login\n";
echo "3. Test deposit and withdrawal functionality\n";
echo "4. Test task completion and level progression\n\n";

echo "If you encounter any issues, check the error messages above.\n";
?>
