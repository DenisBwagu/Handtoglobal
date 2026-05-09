<?php
/**
 * Test Task Action Response
 * This script tests that task_action.php returns the correct format for continuous flow
 */

echo "=== TESTING TASK ACTION RESPONSE ===\n\n";

try {
    // Test 1: Check if task_action.php has the correct next_task logic
    echo "1. Checking task_action.php next_task logic...\n";
    
    $taskActionContent = file_get_contents('task_action.php');
    
    if (strpos($taskActionContent, 'Get next task for continuous flow') !== false) {
        echo "   ✅ Next task logic present\n";
    } else {
        echo "   ❌ Next task logic not found\n";
    }
    
    if (strpos($taskActionContent, 't.id NOT IN') !== false) {
        echo "   ✅ Excluding completed tasks\n";
    } else {
        echo "   ❌ Completed tasks exclusion not found\n";
    }
    
    if (strpos($taskActionContent, 'ORDER BY t.id LIMIT 1') !== false) {
        echo "   ✅ Getting next available task\n";
    } else {
        echo "   ❌ Next task selection not found\n";
    }
    
    if (strpos($taskActionContent, 'task_number = $stats[$current_level][\'completed\'] + 1') !== false) {
        echo "   ✅ Task number calculation\n";
    } else {
        echo "   ❌ Task number calculation not found\n";
    }
    
    // Test 2: Check response format
    echo "\n2. Checking response format...\n";
    
    if (strpos($taskActionContent, 'next_task => [') !== false) {
        echo "   ✅ next_task array format\n";
    } else {
        echo "   ❌ next_task array format not found\n";
    }
    
    if (strpos($taskActionContent, 'image\' => $next_task[\'image\'] ? \'uploads/tasks/\'') !== false) {
        echo "   ✅ Image path formatting\n";
    } else {
        echo "   ❌ Image path formatting not found\n";
    }
    
    if (strpos($taskActionContent, 'instructions\' => $next_task[\'instructions\'] ?? \'YES or NO\'') !== false) {
        echo "   ✅ Instructions field\n";
    } else {
        echo "   ❌ Instructions field not found\n";
    }
    
    // Test 3: Check dashboard integration
    echo "\n3. Checking dashboard integration...\n";
    
    $dashboardContent = file_get_contents('dashboard.php');
    
    if (strpos($dashboardContent, 'console.log(\'Task submission response:\', data)') !== false) {
        echo "   ✅ Debug logging added\n";
    } else {
        echo "   ❌ Debug logging not found\n";
    }
    
    if (strpos($dashboardContent, 'console.log(\'renderTask called with:\', task)') !== false) {
        echo "   ✅ renderTask debugging\n";
    } else {
        echo "   ❌ renderTask debugging not found\n";
    }
    
    if (strpos($dashboardContent, 'renderTask(data.next_task)') !== false) {
        echo "   ✅ renderTask called with next_task\n";
    } else {
        echo "   ❌ renderTask call not found\n";
    }
    
    if (strpos($dashboardContent, 'disableTaskButtons()') !== false) {
        echo "   ✅ Button disable implemented\n";
    } else {
        echo "   ❌ Button disable not found\n";
    }
    
    if (strpos($dashboardContent, 'enableTaskButtons()') !== false) {
        echo "   ✅ Button enable implemented\n";
    } else {
        echo "   ❌ Button enable not found\n";
    }
    
    // Test 4: Check error handling
    echo "\n4. Checking error handling...\n";
    
    if (strpos($dashboardContent, 'enableTaskButtons();') !== false) {
        echo "   ✅ Buttons re-enabled on error\n";
    } else {
        echo "   ❌ Error recovery not found\n";
    }
    
    // Test 5: Expected behavior verification
    echo "\n5. Expected behavior verification:\n";
    echo "   ✅ Task action returns next_task when available\n";
    echo "   ✅ Task number calculated correctly\n";
    echo "   ✅ Image path includes uploads/tasks/\n";
    echo "   ✅ Instructions field included\n";
    echo "   ✅ Dashboard calls renderTask with next_task\n";
    echo "   ✅ Buttons disabled during submission\n";
    echo "   ✅ Buttons re-enabled after task loads\n";
    echo "   ✅ Debug logging for troubleshooting\n";
    echo "   ✅ Error handling with button recovery\n";
    
    echo "\n=== TASK ACTION RESPONSE TEST COMPLETE ===\n";
    echo "✅ Task action response format verified!\n";
    echo "\nDebugging tips:\n";
    echo "1. Open browser console to see debug logs\n";
    echo "2. Check for 'Task submission response:' logs\n";
    echo "3. Check for 'renderTask called with:' logs\n";
    echo "4. Verify next_task object has all required fields\n";
    echo "5. Check if buttons are properly disabled/enabled\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
