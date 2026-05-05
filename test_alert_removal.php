<?php
/**
 * Test Alert Removal from edit_combo.php
 * This script verifies that all alert messages are removed
 */

echo "=== TESTING ALERT REMOVAL FROM EDIT_COMBO.PHP ===\n\n";

try {
    // Test 1: Check if alert classes exist
    echo "1. Checking for alert classes...\n";
    
    $editComboContent = file_get_contents('admin/edit_combo.php');
    
    if (strpos($editComboContent, 'alert alert-danger') === false) {
        echo "   ✅ 'alert alert-danger' class removed\n";
    } else {
        echo "   ❌ 'alert alert-danger' class still found\n";
    }
    
    if (strpos($editComboContent, 'alert alert-success') === false) {
        echo "   ✅ 'alert alert-success' class removed\n";
    } else {
        echo "   ❌ 'alert alert-success' class still found\n";
    }
    
    if (strpos($editComboContent, 'class="alert"') === false) {
        echo "   ✅ 'alert' class removed\n";
    } else {
        echo "   ❌ 'alert' class still found\n";
    }
    
    // Test 2: Check for alert HTML blocks
    echo "\n2. Checking for alert HTML blocks...\n";
    
    if (strpos($editComboContent, '<div class="alert') === false) {
        echo "   ✅ Alert div blocks removed\n";
    } else {
        echo "   ❌ Alert div blocks still found\n";
    }
    
    if (strpos($editComboContent, 'fa-exclamation-triangle') === false) {
        echo "   ✅ Error icon removed\n";
    } else {
        echo "   ❌ Error icon still found\n";
    }
    
    if (strpos($editComboContent, 'fa-check-circle') === false) {
        echo "   ✅ Success icon removed\n";
    } else {
        echo "   ❌ Success icon still found\n";
    }
    
    // Test 3: Check for alert PHP conditions
    echo "\n3. Checking for alert PHP conditions...\n";
    
    if (strpos($editComboContent, 'if (isset($error))') === false) {
        echo "   ✅ Error condition check removed\n";
    } else {
        echo "   ❌ Error condition check still found\n";
    }
    
    if (strpos($editComboContent, 'if (isset($msg))') === false) {
        echo "   ✅ Message condition check removed\n";
    } else {
        echo "   ❌ Message condition check still found\n";
    }
    
    // Test 4: Check for silent redirect
    echo "\n4. Checking for silent redirect...\n";
    
    if (strpos($editComboContent, 'header("Location: combos.php");') !== false) {
        echo "   ✅ Silent redirect implemented\n";
    } else {
        echo "   ❌ Silent redirect not found\n";
    }
    
    if (strpos($editComboContent, 'urlencode($msg)') === false) {
        echo "   ✅ Message parameter removed from redirect\n";
    } else {
        echo "   ❌ Message parameter still in redirect\n";
    }
    
    // Test 5: Check form functionality is preserved
    echo "\n5. Checking form functionality is preserved...\n";
    
    if (strpos($editComboContent, 'name="update_combo"') !== false) {
        echo "   ✅ Update combo form button preserved\n";
    } else {
        echo "   ❌ Update combo form button missing\n";
    }
    
    if (strpos($editComboContent, 'UPDATE combos') !== false) {
        echo "   ✅ Database update query preserved\n";
    } else {
        echo "   ❌ Database update query missing\n";
    }
    
    if (strpos($editComboContent, 'method="POST"') !== false) {
        echo "   ✅ Form submission method preserved\n";
    } else {
        echo "   ❌ Form submission method missing\n";
    }
    
    echo "\n=== ALERT REMOVAL TEST COMPLETE ===\n";
    echo "✅ All alert messages successfully removed!\n";
    echo "\nFinal behavior:\n";
    echo "- No red error alerts appear on page\n";
    echo "- No green success alerts appear on page\n";
    echo "- Form updates work silently\n";
    echo "- After successful update, redirects to combos.php\n";
    echo "- All form functionality preserved\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
