<?php
require_once 'config.php';

$conn = getConnection();

echo "=== Database Structure Check ===\n\n";

// Check if finance_activities table exists
try {
    $stmt = $conn->prepare("DESCRIBE finance_activities");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "finance_activities table exists:\n";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
} catch(PDOException $e) {
    echo "finance_activities table does not exist\n";
}

echo "\n";

// Check deposits table structure
try {
    $stmt = $conn->prepare("DESCRIBE deposits");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "deposits table structure:\n";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
} catch(PDOException $e) {
    echo "deposits table does not exist\n";
}

echo "\n";

// Check withdrawals table structure
try {
    $stmt = $conn->prepare("DESCRIBE withdrawals");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "withdrawals table structure:\n";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
} catch(PDOException $e) {
    echo "withdrawals table does not exist\n";
}

echo "\n";

// Check completed_tasks table structure
try {
    $stmt = $conn->prepare("DESCRIBE completed_tasks");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "completed_tasks table structure:\n";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
} catch(PDOException $e) {
    echo "completed_tasks table does not exist\n";
}

echo "\n";

// Check task_combos table structure
try {
    $stmt = $conn->prepare("DESCRIBE task_combos");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "task_combos table structure:\n";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
} catch(PDOException $e) {
    echo "task_combos table does not exist\n";
}

echo "\n";

// Check users table structure (balance column)
try {
    $stmt = $conn->prepare("DESCRIBE users");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "users table structure (relevant columns):\n";
    foreach ($columns as $col) {
        if (in_array($col['Field'], ['id', 'email', 'balance', 'fullname'])) {
            echo "- {$col['Field']} ({$col['Type']})\n";
        }
    }
} catch(PDOException $e) {
    echo "users table does not exist\n";
}

echo "\n";

// Sample data counts
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM deposits");
    $stmt->execute();
    $deposits = $stmt->fetch();
    echo "Total deposits: {$deposits['count']}\n";
} catch(PDOException $e) {
    echo "Error counting deposits: " . $e->getMessage() . "\n";
}

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM withdrawals");
    $stmt->execute();
    $withdrawals = $stmt->fetch();
    echo "Total withdrawals: {$withdrawals['count']}\n";
} catch(PDOException $e) {
    echo "Error counting withdrawals: " . $e->getMessage() . "\n";
}

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM completed_tasks");
    $stmt->execute();
    $tasks = $stmt->fetch();
    echo "Total completed tasks: {$tasks['count']}\n";
} catch(PDOException $e) {
    echo "Error counting completed tasks: " . $e->getMessage() . "\n";
}

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE balance > 0");
    $stmt->execute();
    $users = $stmt->fetch();
    echo "Users with balance > 0: {$users['count']}\n";
} catch(PDOException $e) {
    echo "Error counting users with balance: " . $e->getMessage() . "\n";
}

echo "\n=== Check Complete ===\n";
?>
