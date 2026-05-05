<?php
/**
 * Setup Test Combos
 * This script creates sample combos for testing the complete system
 */

require_once 'config.php';

echo "=== SETTING UP TEST COMBOS ===\n\n";

try {
    $conn = getConnection();
    
    // Get Test User ID
    $stmt = $conn->prepare("SELECT id, fullname FROM users WHERE email = 'testuser@handtoglobal.com'");
    $stmt->execute();
    $testUser = $stmt->fetch();
    
    if (!$testUser) {
        echo "❌ Test user not found. Please create test user first.\n";
        exit;
    }
    
    echo "Found test user: {$testUser['fullname']} (ID: {$testUser['id']})\n\n";
    
    // Create test combos
    $testCombos = [
        [
            'level' => 'Bronze',
            'start_task' => 1,
            'end_task' => 1,
            'amount' => 25,
            'multiplier' => 2,
            'user_id' => null, // Global combo
            'message' => 'Bronze Starter Combo - Double your earnings!',
            'status' => 'active'
        ],
        [
            'level' => 'Bronze',
            'start_task' => 3,
            'end_task' => 3,
            'amount' => 50,
            'multiplier' => 3,
            'user_id' => $testUser['id'], // User-specific combo
            'message' => 'Special Test User Combo - Triple earnings!',
            'status' => 'active'
        ],
        [
            'level' => 'Silver',
            'start_task' => 1,
            'end_task' => 2,
            'amount' => 75,
            'multiplier' => 1.5,
            'user_id' => null, // Global combo
            'message' => 'Silver Multi-Task Combo - 1.5x earnings for 2 tasks!',
            'status' => 'active'
        ],
        [
            'level' => 'Bronze',
            'start_task' => 5,
            'end_task' => 5,
            'amount' => 100,
            'multiplier' => 5,
            'user_id' => null, // Global combo (for testing deactivate)
            'message' => 'High Value Bronze Combo - 5x multiplier!',
            'status' => 'inactive' // Start inactive for testing activate
        ]
    ];
    
    $createdCombos = [];
    
    foreach ($testCombos as $comboData) {
        $stmt = $conn->prepare("
            INSERT INTO combos (level, start_task, end_task, amount, multiplier, user_id, message, status, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        $stmt->execute([
            $comboData['level'],
            $comboData['start_task'],
            $comboData['end_task'],
            $comboData['amount'],
            $comboData['multiplier'],
            $comboData['user_id'],
            $comboData['message'],
            $comboData['status']
        ]);
        
        $comboId = $conn->lastInsertId();
        $createdCombos[] = array_merge($comboData, ['id' => $comboId]);
        
        echo "✅ Created combo ID $comboId: {$comboData['level']} Task {$comboData['start_task']} - \${$comboData['amount']} ({$comboData['multiplier']}x)\n";
    }
    
    echo "\n=== TEST COMBOS CREATED ===\n";
    echo "Total combos created: " . count($createdCombos) . "\n\n";
    
    echo "=== TESTING SCENARIOS ===\n";
    echo "1. GLOBAL BRONZE COMBO (ID: {$createdCombos[0]['id']})\n";
    echo "   - Any user reaching Bronze Task 1 will see popup\n";
    echo "   - Popup shows: 2x Multiplier, \$25 deposit, 'Bronze Starter Combo'\n";
    echo "   - Test: Login as any user, complete Bronze Task 1\n\n";
    
    echo "2. USER-SPECIFIC BRONZE COMBO (ID: {$createdCombos[1]['id']})\n";
    echo "   - Only Test User (ID: {$testUser['id']}) will see popup for Bronze Task 3\n";
    echo "   - Popup shows: 3x Multiplier, \$50 deposit, 'Special Test User Combo'\n";
    echo "   - Test: Login as Test User, complete Bronze Task 3\n";
    echo "   - Test: Login as different user, complete Bronze Task 3 (should NOT see popup)\n\n";
    
    echo "3. GLOBAL SILVER COMBO (ID: {$createdCombos[2]['id']})\n";
    echo "   - Any user with Silver access reaching Task 1-2 will see popup\n";
    echo "   - Popup shows: 1.5x Multiplier, \$75 deposit, 'Silver Multi-Task Combo'\n";
    echo "   - Test: Unlock Silver, complete Silver Task 1\n\n";
    
    echo "4. INACTIVE BRONZE COMBO (ID: {$createdCombos[3]['id']})\n";
    echo "   - Currently inactive, no popup will appear\n";
    echo "   - Test: Admin activate this combo, then test Bronze Task 5\n";
    echo "   - Test: Admin deactivate this combo, verify popup disappears\n\n";
    
    echo "=== ADMIN TESTING ===\n";
    echo "1. Go to admin/combos.php\n";
    echo "2. Test Edit button on any combo\n";
    echo "3. Test Activate/Deactivate buttons\n";
    echo "4. Test user targeting in dropdown\n\n";
    
    echo "=== USER TESTING ===\n";
    echo "Login credentials:\n";
    echo "Email: testuser@handtoglobal.com\n";
    echo "Password: password123\n\n";
    
    echo "Ready for complete system testing!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
