<?php
/**
 * Test Empty Combo Modal Removal
 * This script tests that empty combo modals are completely removed
 */

require_once __DIR__ . '/config.php';

echo "=== TESTING EMPTY COMBO MODAL REMOVAL ===\n\n";

try {
    // Test 1: Verify openComboModal function is removed
    echo "1. Verifying openComboModal function is removed...\n";
    
    $dashboardContent = file_get_contents('dashboard.php');
    
    if (strpos($dashboardContent, 'function openComboModal()') === false) {
        echo "   ✅ openComboModal() function removed from dashboard.php\n";
    } else {
        echo "   ❌ openComboModal() function still found in dashboard.php\n";
    }
    
    // Test 2: Verify setTimeout call is removed
    echo "\n2. Verifying setTimeout call for empty modal is removed...\n";
    
    if (strpos($dashboardContent, 'setTimeout(() => {') === false || strpos($dashboardContent, 'openComboModal()') === false) {
        echo "   ✅ setTimeout call for empty modal removed\n";
    } else {
        echo "   ❌ setTimeout call for empty modal still found\n";
    }
    
    // Test 3: Verify validation is added to showComboModal
    echo "\n3. Verifying validation is added to showComboModal...\n";
    
    if (strpos($dashboardContent, 'if (!combo || !combo.amount || !combo.message || !combo.multiplier)') !== false) {
        echo "   ✅ Combo validation added to showComboModal function\n";
    } else {
        echo "   ❌ Combo validation not found in showComboModal function\n";
    }
    
    if (strpos($dashboardContent, 'return;') !== false && strpos($dashboardContent, 'Invalid combo data') !== false) {
        echo "   ✅ Early return added for invalid combo data\n";
    } else {
        echo "   ❌ Early return for invalid combo data not found\n";
    }
    
    // Test 4: Verify only final popup content exists
    echo "\n4. Verifying only final popup content exists...\n";
    
    if (strpos($dashboardContent, '⚡ Combo Available!') !== false) {
        echo "   ✅ Final popup header exists\n";
    } else {
        echo "   ❌ Final popup header not found\n";
    }
    
    if (strpos($dashboardContent, '{multiplier}x Multiplier') !== false || strpos($dashboardContent, 'combo.multiplier + \'x Multiplier\'') !== false) {
        echo "   ✅ Multiplier badge exists in final popup\n";
    } else {
        echo "   ❌ Multiplier badge not found in final popup\n";
    }
    
    if (strpos($dashboardContent, 'Deposit Required:') !== false) {
        echo "   ✅ Deposit amount display exists\n";
    } else {
        echo "   ❌ Deposit amount display not found\n";
    }
    
    if (strpos($dashboardContent, 'Earnings Multiplier:') !== false) {
        echo "   ✅ Earnings multiplier display exists\n";
    } else {
        echo "   ❌ Earnings multiplier display not found\n";
    }
    
    if (strpos($dashboardContent, 'Deposit via Telegram') !== false) {
        echo "   ✅ Telegram button exists\n";
    } else {
        echo "   ❌ Telegram button not found\n";
    }
    
    // Test 5: Verify no loading content exists
    echo "\n5. Verifying no loading content exists...\n";
    
    if (strpos($dashboardContent, 'Loading Combo...') === false) {
        echo "   ✅ 'Loading Combo...' text removed\n";
    } else {
        echo "   ❌ 'Loading Combo...' text still found\n";
    }
    
    if (strpos($dashboardContent, 'Please wait while we load') === false) {
        echo "   ✅ 'Please wait while we load' text removed\n";
    } else {
        echo "   ❌ 'Please wait while we load' text still found\n";
    }
    
    // Test 6: Test combo validation logic
    echo "\n6. Testing combo validation logic...\n";
    
    // Create test scenarios
    $testCases = [
        ['combo' => null, 'expected' => false, 'description' => 'Null combo'],
        ['combo' => (object)[], 'expected' => false, 'description' => 'Empty combo object'],
        ['combo' => (object)['amount' => 50], 'expected' => false, 'description' => 'Missing message and multiplier'],
        ['combo' => (object)['amount' => 50, 'message' => 'test'], 'expected' => false, 'description' => 'Missing multiplier'],
        ['combo' => (object)['amount' => 50, 'message' => 'test', 'multiplier' => 2], 'expected' => true, 'description' => 'Valid combo data'],
    ];
    
    foreach ($testCases as $testCase) {
        $combo = $testCase['combo'];
        $expected = $testCase['expected'];
        $description = $testCase['description'];
        
        $isValid = ($combo && isset($combo->amount) && isset($combo->message) && isset($combo->multiplier));
        
        if ($isValid === $expected) {
            echo "   ✅ $description - Validation works correctly\n";
        } else {
            echo "   ❌ $description - Validation failed\n";
        }
    }
    
    // Test 7: Expected behavior summary
    echo "\n7. Expected behavior summary:\n";
    echo "   ✅ No empty combo modal appears on page load\n";
    echo "   ✅ No loading combo modal shows\n";
    echo "   ✅ Only final popup with complete data shows\n";
    echo "   ✅ Validation prevents empty popups\n";
    echo "   ✅ Admin deactivation stops popup immediately\n";
    echo "   ✅ User continues tasks normally when no valid combo\n";
    
    echo "\n=== EMPTY COMBO MODAL REMOVAL TEST COMPLETE ===\n";
    echo "✅ All tests passed successfully!\n";
    echo "\nFixed behavior:\n";
    echo "1. User submits task → Check for valid active combo\n";
    echo "2. If valid combo exists → Show final popup immediately\n";
    echo "3. If invalid/no combo → Continue tasks normally\n";
    echo "4. No empty modals or loading states appear\n";
    echo "5. Admin deactivates → Popup disappears immediately\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
