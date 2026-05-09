<?php
/**
 * Clear All Combos
 * This script removes all existing combos to start fresh
 */

require_once __DIR__ . '/config.php';

echo "=== CLEARING ALL COMBOS ===\n\n";

try {
    $conn = getConnection();
    
    // Clear user combo status first
    $stmt = $conn->prepare("DELETE FROM user_combo_status");
    $stmt->execute();
    $userStatusCleared = $stmt->rowCount();
    
    // Clear all combos
    $stmt = $conn->prepare("DELETE FROM combos");
    $stmt->execute();
    $combosCleared = $stmt->rowCount();
    
    echo "✅ Database cleared successfully:\n";
    echo "- User combo status records cleared: $userStatusCleared\n";
    echo "- Combo records cleared: $combosCleared\n";
    
    echo "\n=== ALL COMBOS CLEARED ===\n";
    echo "Ready for fresh combo testing!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
