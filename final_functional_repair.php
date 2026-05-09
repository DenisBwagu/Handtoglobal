<?php
/**
 * HandToGlobal final functional schema repair.
 *
 * Safe to run more than once. It only creates missing tables/columns/indexes
 * needed by the deploy-ready task, combo, settings, language, and withdrawal
 * flows.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: text/plain');

$conn = getConnection();

function htg_table_exists(PDO $conn, $table) {
    $stmt = $conn->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function htg_column_exists(PDO $conn, $table, $column) {
    $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetchColumn();
}

function htg_add_column(PDO $conn, $table, $column, $definition) {
    if (!htg_column_exists($conn, $table, $column)) {
        $conn->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        echo "Added $table.$column\n";
    } else {
        echo "Exists $table.$column\n";
    }
}

function htg_index_exists(PDO $conn, $table, $index) {
    $stmt = $conn->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
    $stmt->execute([$index]);
    return (bool)$stmt->fetchColumn();
}

function htg_add_index(PDO $conn, $table, $index, $columns, $unique = false) {
    if (!htg_index_exists($conn, $table, $index)) {
        if ($unique) {
            $dupCheck = $conn->query("
                SELECT COUNT(*) FROM (
                    SELECT $columns, COUNT(*) duplicate_count
                    FROM `$table`
                    GROUP BY $columns
                    HAVING duplicate_count > 1
                    LIMIT 1
                ) duplicates
            ");
            if ((int)$dupCheck->fetchColumn() > 0) {
                echo "Skipped unique index $table.$index because duplicate rows exist\n";
                return;
            }
        }
        $kind = $unique ? 'UNIQUE ' : '';
        $conn->exec("ALTER TABLE `$table` ADD {$kind}INDEX `$index` ($columns)");
        echo "Added index $table.$index\n";
    } else {
        echo "Exists index $table.$index\n";
    }
}

function htg_modify_column(PDO $conn, $table, $column, $definition) {
    if (htg_table_exists($conn, $table) && htg_column_exists($conn, $table, $column)) {
        $conn->exec("ALTER TABLE `$table` MODIFY COLUMN `$column` $definition");
        echo "Verified width $table.$column\n";
    }
}

echo "HandToGlobal final functional repair\n";

$conn->exec("
    CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL,
        setting_value TEXT NULL,
        setting_type VARCHAR(50) DEFAULT 'text',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_settings_key (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->exec("
    CREATE TABLE IF NOT EXISTS completed_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        task_id INT NOT NULL,
        answer VARCHAR(50) NULL,
        level VARCHAR(50) DEFAULT 'Bronze',
        reward DECIMAL(10,2) DEFAULT 0.00,
        completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_completed_user (user_id),
        INDEX idx_completed_task (task_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->exec("
    CREATE TABLE IF NOT EXISTS user_combo_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        combo_id INT NOT NULL,
        status VARCHAR(30) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_combo (user_id, combo_id),
        INDEX idx_user_combo_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$coreColumns = [
    'users' => [
        'language' => "VARCHAR(50) DEFAULT 'english'",
        'is_active' => 'TINYINT(1) DEFAULT 1',
        'is_blocked' => 'TINYINT(1) DEFAULT 0',
        'total_tasks' => 'INT DEFAULT 0',
        'accuracy' => 'DECIMAL(5,2) DEFAULT 0.00',
        'rating' => 'DECIMAL(5,2) DEFAULT 0.00',
    ],
    'tasks' => [
        'active' => 'TINYINT(1) DEFAULT 1',
        'status' => 'TINYINT(1) DEFAULT 1',
        'instructions' => 'TEXT NULL',
        'reward' => 'DECIMAL(10,2) DEFAULT 0.00',
        'image' => 'VARCHAR(255) NULL',
    ],
    'completed_tasks' => [
        'answer' => 'VARCHAR(50) NULL',
        'level' => "VARCHAR(50) DEFAULT 'Bronze'",
        'reward' => 'DECIMAL(10,2) DEFAULT 0.00',
        'completed_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    ],
    'combos' => [
        'user_id' => 'INT NULL',
        'start_task' => 'INT DEFAULT 1',
        'end_task' => 'INT DEFAULT 1',
        'amount' => 'DECIMAL(10,2) DEFAULT 0.00',
        'multiplier' => 'DECIMAL(10,2) DEFAULT 1.00',
        'message' => 'TEXT NULL',
        'status' => "VARCHAR(30) DEFAULT 'inactive'",
        'is_active' => 'TINYINT(1) DEFAULT 0',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ],
    'withdrawals' => [
        'coin_asset' => "VARCHAR(20) DEFAULT 'USDT'",
        'network' => "VARCHAR(80) DEFAULT 'Tron (TRC20)'",
        'memo_tag' => 'VARCHAR(255) NULL',
        'recipient_name' => 'VARCHAR(255) NULL',
        'note' => 'TEXT NULL',
        'approved_by' => 'INT NULL',
        'approved_at' => 'DATETIME NULL',
        'rejected_by' => 'INT NULL',
        'rejected_at' => 'DATETIME NULL',
        'processed_by' => 'INT NULL',
        'processed_at' => 'DATETIME NULL',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ],
    'testimonials' => [
        'is_active' => 'TINYINT(1) DEFAULT 1',
        'display_order' => 'INT DEFAULT 0',
        'content' => 'TEXT NULL',
        'message' => 'TEXT NULL',
    ],
];

foreach ($coreColumns as $table => $columns) {
    if (!htg_table_exists($conn, $table)) {
        echo "Skipped missing table $table\n";
        continue;
    }

    foreach ($columns as $column => $definition) {
        htg_add_column($conn, $table, $column, $definition);
    }
}

htg_modify_column($conn, 'admins', 'password', 'VARCHAR(255) NOT NULL');
htg_modify_column($conn, 'users', 'password', 'VARCHAR(255) NULL');

if (htg_table_exists($conn, 'completed_tasks')) {
    htg_add_index($conn, 'completed_tasks', 'uniq_completed_user_task', '`user_id`, `task_id`', true);
}
if (htg_table_exists($conn, 'combos')) {
    htg_add_index($conn, 'combos', 'idx_combo_trigger', '`level`, `start_task`, `status`, `is_active`');
}
if (htg_table_exists($conn, 'withdrawals')) {
    htg_add_index($conn, 'withdrawals', 'idx_withdrawals_user_status', '`user_id`, `status`');
}

echo "Repair complete.\n";
