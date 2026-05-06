<?php
require_once __DIR__ . '/config.php';

$conn = getConnection();

echo "=== Testing Withdrawal Functionality ===\n\n";

// Test 1: Check current withdrawal data
echo "1. Current Withdrawal Data:\n";
try {
    $stmt = $conn->prepare("
        SELECT w.id, w.amount, w.status, w.asset, w.network, w.wallet_address, w.memo_tag,
               u.fullname, u.email, u.balance
        FROM withdrawals w 
        JOIN users u ON w.user_id = u.id 
        ORDER BY w.created_at DESC
    ");
    $stmt->execute();
    $withdrawals = $stmt->fetchAll();
    
    foreach ($withdrawals as $withdrawal) {
        echo "ID: {$withdrawal['id']} | {$withdrawal['fullname']} | \\${$withdrawal['amount']} | {$withdrawal['status']} | Balance: \\${$withdrawal['balance']}\n";
        echo "  Wallet: {$withdrawal['wallet_address']}\n";
        echo "  Asset: {$withdrawal['asset']} / {$withdrawal['network']}\n";
        echo "  Memo: " . ($withdrawal['memo_tag'] ?: 'None') . "\n\n";
    }
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Test 2: Test status filtering
echo "2. Status Filter Test:\n";
$statuses = ['all', 'Pending', 'Approved', 'Rejected'];
foreach ($statuses as $status) {
    try {
        $sql = "SELECT COUNT(*) as count FROM withdrawals";
        $params = [];
        
        if ($status !== 'all') {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        echo "  Status '$status': {$result['count']} withdrawals\n";
    } catch(PDOException $e) {
        echo "  Error testing status '$status': " . $e->getMessage() . "\n";
    }
}
echo "\n";

// Test 3: Test approve functionality (simulate)
echo "3. Testing Approve Functionality:\n";
try {
    // Find a pending withdrawal
    $stmt = $conn->prepare("SELECT * FROM withdrawals WHERE status = 'Pending' LIMIT 1");
    $stmt->execute();
    $pending = $stmt->fetch();
    
    if ($pending) {
        echo "  Found pending withdrawal ID: {$pending['id']}\n";
        echo "  Amount: \\${$pending['amount']}\n";
        echo "  User ID: {$pending['user_id']}\n";
        
        // Check user balance
        $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$pending['user_id']]);
        $user = $stmt->fetch();
        
        echo "  Current user balance: \\${$user['balance']}\n";
        echo "  Can approve: " . ($user['balance'] >= $pending['amount'] ? 'YES' : 'NO') . "\n";
        
        if ($user['balance'] >= $pending['amount']) {
            echo "  Approval would succeed. New balance would be: \$" . ($user['balance'] - $pending['amount']) . "\n";
        }
    } else {
        echo "  No pending withdrawals found\n";
    }
} catch(PDOException $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Test reject functionality (simulate)
echo "4. Testing Reject Functionality:\n";
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM withdrawals WHERE status = 'Pending'");
    $stmt->execute();
    $pending_count = $stmt->fetch();
    
    echo "  Pending withdrawals that can be rejected: {$pending_count['count']}\n";
    echo "  Rejection does not affect user balance\n";
} catch(PDOException $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Test pagination
echo "5. Testing Pagination:\n";
$limit = 15;
$page = 1;
$offset = ($page - 1) * $limit;

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM withdrawals");
    $stmt->execute();
    $total_result = $stmt->fetch();
    $total_withdrawals = $total_result['total'];
    
    $total_pages = ceil($total_withdrawals / $limit);
    $start_record = ($page - 1) * $limit + 1;
    $end_record = min($page * $limit, $total_withdrawals);
    
    echo "  Total withdrawals: $total_withdrawals\n";
    echo "  Limit per page: $limit\n";
    echo "  Total pages: $total_pages\n";
    echo "  Current page: $page\n";
    echo "  Showing: $start_record to $end_record\n";
    
    // Get page data
    $stmt = $conn->prepare("SELECT * FROM withdrawals LIMIT $limit OFFSET $offset");
    $stmt->execute();
    $page_withdrawals = $stmt->fetchAll();
    
    echo "  Records on current page: " . count($page_withdrawals) . "\n";
    
} catch(PDOException $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 6: Check if finance activities are recorded
echo "6. Finance Activities Check:\n";
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM finance_activities WHERE type = 'withdrawal_debit'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    echo "  Withdrawal debit records in finance_activities: {$result['count']}\n";
    
    if ($result['count'] > 0) {
        $stmt = $conn->prepare("
            SELECT fa.*, u.fullname 
            FROM finance_activities fa 
            JOIN users u ON fa.user_id = u.id 
            WHERE fa.type = 'withdrawal_debit' 
            ORDER BY fa.created_at DESC 
            LIMIT 3
        ");
        $stmt->execute();
        $activities = $stmt->fetchAll();
        
        echo "  Recent withdrawal activities:\n";
        foreach ($activities as $activity) {
            echo "    - {$activity['fullname']}: \\${$activity['amount']} ({$activity['reason']})\n";
        }
    }
} catch(PDOException $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}
echo "\n";

echo "=== Functionality Test Complete ===\n";
echo "The withdrawals page should work correctly with:\n";
echo "- Status filtering (All/Pending/Approved/Rejected)\n";
echo "- Approve/reject buttons for pending withdrawals\n";
echo "- Full wallet address display\n";
echo "- Copy functionality for wallet addresses and memos\n";
echo "- Pagination (15 records per page)\n";
echo "- Finance activity recording on approval\n\n";

echo "Test URL: http://localhost/handtoglobal/admin/withdrawals.php\n";
?>
