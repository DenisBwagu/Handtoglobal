<?php
require_once __DIR__ . '/config.php';

$conn = getConnection();

echo "=== Adding Sample Withdrawal Data ===\n\n";

// First, let's check if we have users
try {
    $stmt = $conn->prepare("SELECT id, fullname, email, balance FROM users ORDER BY id LIMIT 5");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "No users found. Creating sample users first...\n";
        
        // Create sample users
        $sample_users = [
            ['John Doe', 'john@example.com', 1500.00],
            ['Jane Smith', 'jane@example.com', 2500.00],
            ['Mike Johnson', 'mike@example.com', 800.00],
            ['Sarah Wilson', 'sarah@example.com', 3200.00],
            ['David Brown', 'david@example.com', 1200.00]
        ];
        
        foreach ($sample_users as $user) {
            $stmt = $conn->prepare("INSERT INTO users (fullname, email, balance, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute($user);
            echo "Created user: {$user[0]} with balance ${user[2]}\n";
        }
        
        // Get the users again
        $stmt = $conn->prepare("SELECT id, fullname, email, balance FROM users ORDER BY id LIMIT 5");
        $stmt->execute();
        $users = $stmt->fetchAll();
    }
    
    echo "\nFound users:\n";
    foreach ($users as $user) {
        echo "- {$user['id']}: {$user['fullname']} ({$user['email']}) - Balance: ${user['balance']}\n";
    }
    
    // Now add sample withdrawals - only using existing users
    $sample_withdrawals = [
        [
            'user_id' => $users[0]['id'],
            'amount' => 50.00,
            'asset' => 'USDT',
            'network' => 'TRC20',
            'wallet_address' => 'TXYZ1234567890abcdefghijklmnopqrstuvwxyz1234567890',
            'memo_tag' => 'MEMO123456',
            'status' => 'Pending'
        ],
        [
            'user_id' => $users[0]['id'],
            'amount' => 25.00,
            'asset' => 'USDT',
            'network' => 'TRC20',
            'wallet_address' => 'TABC9876543210zyxwvutsrqponmlkjihgfedcba9876543210',
            'memo_tag' => '',
            'status' => 'Approved'
        ],
        [
            'user_id' => $users[1]['id'],
            'amount' => 15.50,
            'asset' => 'USDT',
            'network' => 'ERC20',
            'wallet_address' => '0x1234567890abcdef1234567890abcdef12345678',
            'memo_tag' => '789456',
            'status' => 'Rejected'
        ],
        [
            'user_id' => $users[1]['id'],
            'amount' => 10.00,
            'asset' => 'BTC',
            'network' => 'Bitcoin',
            'wallet_address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
            'memo_tag' => '',
            'status' => 'Pending'
        ],
        [
            'user_id' => $users[0]['id'],
            'amount' => 30.00,
            'asset' => 'USDT',
            'network' => 'TRC20',
            'wallet_address' => 'TDEF111111111111111111111111111111111111111',
            'memo_tag' => 'TEST001',
            'status' => 'Pending'
        ]
    ];
    
    echo "\nAdding sample withdrawals...\n";
    
    foreach ($sample_withdrawals as $withdrawal) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO withdrawals 
                (user_id, amount, asset, network, wallet_address, memo_tag, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $withdrawal['user_id'],
                $withdrawal['amount'],
                $withdrawal['asset'],
                $withdrawal['network'],
                $withdrawal['wallet_address'],
                $withdrawal['memo_tag'],
                $withdrawal['status']
            ]);
            
            echo "Added withdrawal: {$withdrawal['amount']} {$withdrawal['asset']} ({$withdrawal['status']}) for user ID {$withdrawal['user_id']}\n";
            
        } catch(PDOException $e) {
            echo "Error adding withdrawal: " . $e->getMessage() . "\n";
        }
    }
    
    // Check final results
    echo "\n=== Final Withdrawal Data ===\n";
    $stmt = $conn->prepare("
        SELECT w.*, u.fullname, u.email 
        FROM withdrawals w 
        JOIN users u ON w.user_id = u.id 
        ORDER BY w.created_at DESC
    ");
    $stmt->execute();
    $withdrawals = $stmt->fetchAll();
    
    foreach ($withdrawals as $withdrawal) {
        echo "ID: {$withdrawal['id']} | User: {$withdrawal['fullname']} | Amount: \${$withdrawal['amount']} | Status: {$withdrawal['status']} | Wallet: {$withdrawal['wallet_address']}\n";
    }
    
    echo "\n=== Status Summary ===\n";
    $stmt = $conn->prepare("SELECT status, COUNT(*) as count, SUM(amount) as total FROM withdrawals GROUP BY status");
    $stmt->execute();
    $status_summary = $stmt->fetchAll();
    
    foreach ($status_summary as $summary) {
        echo "{$summary['status']}: {$summary['count']} withdrawals totaling \${$summary['total']}\n";
    }
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\n=== Sample Data Added Successfully ===\n";
echo "You can now test the withdrawals page at: http://localhost/handtoglobal/admin/withdrawals.php\n";
?>
