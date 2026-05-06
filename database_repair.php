<?php
/**
 * HandToGlobal safe database repair script.
 *
 * Repairs MariaDB/InnoDB tables that can appear in phpMyAdmin as:
 * #1932 - Table 'database.table' doesn't exist in engine
 *
 * Scope: notifications, task_combos, user_combos, user_tasks.
 * Healthy tables are preserved. Only missing or corrupted target tables are recreated.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

$repairTables = [
    'notifications' => [
        'columns' => [
            'id' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'user_id' => 'INT UNSIGNED NOT NULL',
            'title' => 'VARCHAR(255) NOT NULL',
            'message' => 'TEXT NOT NULL',
            'is_read' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ],
        'indexes' => [
            'KEY idx_notifications_user_read (user_id, is_read)',
            'KEY idx_notifications_created_at (created_at)',
        ],
    ],
    'task_combos' => [
        'columns' => [
            'id' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'level' => 'VARCHAR(50) NOT NULL',
            'start_task' => 'INT UNSIGNED NOT NULL',
            'end_task' => 'INT UNSIGNED NOT NULL',
            'combo_amount' => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
            'combo_message' => 'TEXT NULL',
            'status' => "VARCHAR(30) NOT NULL DEFAULT 'active'",
            'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ],
        'indexes' => [
            'KEY idx_task_combos_level_start (level, start_task)',
            'KEY idx_task_combos_status (status)',
        ],
    ],
    'user_combos' => [
        'columns' => [
            'id' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'user_id' => 'INT UNSIGNED NOT NULL',
            'combo_id' => 'INT UNSIGNED NOT NULL',
            'status' => "VARCHAR(30) NOT NULL DEFAULT 'pending'",
            'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ],
        'indexes' => [
            'KEY idx_user_combos_user (user_id)',
            'KEY idx_user_combos_combo (combo_id)',
            'UNIQUE KEY uniq_user_combo (user_id, combo_id)',
        ],
    ],
    'user_tasks' => [
        'columns' => [
            'id' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'user_id' => 'INT UNSIGNED NOT NULL',
            'task_id' => 'INT UNSIGNED NOT NULL',
            'status' => "VARCHAR(30) NOT NULL DEFAULT 'pending'",
            'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ],
        'indexes' => [
            'KEY idx_user_tasks_user (user_id)',
            'KEY idx_user_tasks_task (task_id)',
            'UNIQUE KEY uniq_user_task (user_id, task_id)',
        ],
    ],
];

function repair_log($message)
{
    echo $message . PHP_EOL;
}

function table_name_is_safe($table)
{
    return preg_match('/^[a-zA-Z0-9_]+$/', $table) === 1;
}

function table_exists_in_schema(PDO $conn, $table)
{
    $stmt = $conn->prepare('
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
    ');
    $stmt->execute([DB_NAME, $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function table_is_usable(PDO $conn, $table, &$error = null)
{
    if (!table_name_is_safe($table)) {
        $error = 'Unsafe table name.';
        return false;
    }

    try {
        $conn->query("SELECT 1 FROM `$table` LIMIT 1");
        return true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return false;
    }
}

function quarantine_orphan_tablespace(PDO $conn, $table)
{
    if (!table_name_is_safe($table)) {
        throw new RuntimeException('Unsafe table name for tablespace quarantine.');
    }

    $dataDir = (string)$conn->query('SELECT @@datadir')->fetchColumn();
    $databaseDir = rtrim($dataDir, "\\/") . DIRECTORY_SEPARATOR . DB_NAME;
    $timestamp = date('YmdHis');
    $candidates = [
        $databaseDir . DIRECTORY_SEPARATOR . $table . '.ibd',
        $databaseDir . DIRECTORY_SEPARATOR . strtolower($table) . '.ibd',
        $databaseDir . DIRECTORY_SEPARATOR . $table . '.cfg',
        $databaseDir . DIRECTORY_SEPARATOR . strtolower($table) . '.cfg',
    ];

    $moved = false;
    foreach (array_unique($candidates) as $path) {
        if (!is_file($path)) {
            continue;
        }

        $target = $path . '.corrupt_' . $timestamp . '.bak';
        if (!@rename($path, $target)) {
            throw new RuntimeException("Could not quarantine orphan tablespace file: $path");
        }

        repair_log("  Quarantined orphan tablespace file: $target");
        $moved = true;
    }

    if (!$moved) {
        repair_log('  No orphan tablespace file found on disk to quarantine.');
    }
}

function create_repair_table(PDO $conn, $table, array $definition)
{
    $parts = [];
    foreach ($definition['columns'] as $column => $sql) {
        $parts[] = "`$column` $sql";
    }
    foreach ($definition['indexes'] as $indexSql) {
        $parts[] = $indexSql;
    }

    $sql = "CREATE TABLE `$table` (" . PHP_EOL .
        '    ' . implode(',' . PHP_EOL . '    ', $parts) . PHP_EOL .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    try {
        $conn->exec($sql);
    } catch (Throwable $e) {
        if (strpos($e->getMessage(), '1813') === false && stripos($e->getMessage(), 'Tablespace') === false) {
            throw $e;
        }

        repair_log('  Existing orphan tablespace detected during create. Quarantining and retrying.');
        quarantine_orphan_tablespace($conn, $table);
        $conn->exec($sql);
    }
}

function column_exists(PDO $conn, $table, $column)
{
    $stmt = $conn->prepare('
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ');
    $stmt->execute([DB_NAME, $table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function ensure_columns(PDO $conn, $table, array $definition)
{
    foreach ($definition['columns'] as $column => $sql) {
        if ($column === 'id') {
            continue;
        }

        if (!column_exists($conn, $table, $column)) {
            $conn->exec("ALTER TABLE `$table` ADD COLUMN `$column` $sql");
            repair_log("  Added missing column: $table.$column");
        }
    }
}

repair_log('=== HandToGlobal Safe Database Repair ===');
repair_log('Database: ' . DB_NAME);
repair_log('Started: ' . date('Y-m-d H:i:s'));
repair_log('');

try {
    $conn = getConnection();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    repair_log('Database connection: OK');
} catch (Throwable $e) {
    repair_log('Database connection: FAILED');
    repair_log($e->getMessage());
    exit(1);
}

$summary = [
    'healthy' => 0,
    'created' => 0,
    'repaired' => 0,
    'failed' => 0,
];

foreach ($repairTables as $table => $definition) {
    repair_log('');
    repair_log("Checking `$table`...");

    try {
        $exists = table_exists_in_schema($conn, $table);
        $usableError = null;
        $usable = $exists && table_is_usable($conn, $table, $usableError);

        if (!$exists) {
            create_repair_table($conn, $table, $definition);
            repair_log("  Created missing table `$table`.");
            $summary['created']++;
            continue;
        }

        if (!$usable) {
            repair_log("  Corruption detected: $usableError");
            repair_log("  Dropping corrupted table `$table` only.");
            $conn->exec('SET FOREIGN_KEY_CHECKS = 0');
            $conn->exec("DROP TABLE IF EXISTS `$table`");
            $conn->exec('SET FOREIGN_KEY_CHECKS = 1');
            create_repair_table($conn, $table, $definition);
            repair_log("  Recreated corrupted table `$table`.");
            $summary['repaired']++;
            continue;
        }

        ensure_columns($conn, $table, $definition);
        repair_log("  Healthy table preserved: `$table`.");
        $summary['healthy']++;
    } catch (Throwable $e) {
        $summary['failed']++;
        repair_log("  FAILED: " . $e->getMessage());
        try {
            $conn->exec('SET FOREIGN_KEY_CHECKS = 1');
        } catch (Throwable $ignored) {
            // Ignore cleanup failure.
        }
    }
}

repair_log('');
repair_log('=== Repair Summary ===');
repair_log('Healthy preserved: ' . $summary['healthy']);
repair_log('Missing created: ' . $summary['created']);
repair_log('Corrupted repaired: ' . $summary['repaired']);
repair_log('Failed: ' . $summary['failed']);
repair_log('');

if ($summary['failed'] > 0) {
    repair_log('Repair completed with errors. Review messages above before exporting.');
    exit(1);
}

repair_log('Repair completed successfully. You can refresh phpMyAdmin and export the database again.');
