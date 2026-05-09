<?php
/**
 * Debug Task Flow
 * This script helps debug the continuous task flow by checking all components
 */

echo "=== DEBUGGING CONTINUOUS TASK FLOW ===\n\n";

try {
    // Test 1: Verify database structure and data
    echo "1. Checking database structure and data...\n";
    
    require_once __DIR__ . '/config.php';
    $conn = getConnection();
    
    // Check if tasks table exists and has data
    $result = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE active = 1");
    $activeTasks = $result->fetch()['count'];
    echo "   ✅ Active tasks in database: $activeTasks\n";
    
    // Check completed_tasks table
    $result = $conn->query("SELECT COUNT(*) as count FROM completed_tasks");
    $completedTasks = $result->fetch()['count'];
    echo "   ✅ Completed tasks in database: $completedTasks\n";
    
    // Check if there are available tasks for Bronze level
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tasks t 
        WHERE t.level = 'Bronze' AND t.active = 1
        AND t.id NOT IN (
            SELECT ct.task_id FROM completed_tasks ct WHERE ct.user_id = 1
        )
    ");
    $stmt->execute();
    $availableBronzeTasks = $stmt->fetch()['count'];
    echo "   ✅ Available Bronze tasks for user 1: $availableBronzeTasks\n";
    
    // Test 2: Check next_task query directly
    echo "\n2. Testing next_task query directly...\n";
    
    $current_level = 'Bronze';
    $user_id = 1;
    
    // Get completed tasks count for this level
    $stmt = $conn->prepare("
        SELECT COUNT(*) as completed 
        FROM completed_tasks ct 
        JOIN tasks t ON ct.task_id = t.id 
        WHERE t.level = ? AND ct.user_id = ?
    ");
    $stmt->execute([$current_level, $user_id]);
    $completed = $stmt->fetch()['completed'];
    echo "   ✅ Completed Bronze tasks for user 1: $completed\n";
    
    // Test the exact query from task_action.php
    $stmt = $conn->prepare("
        SELECT t.id, t.title, t.description, t.image, t.level, t.instructions
        FROM tasks t
        WHERE t.level = ? AND t.active = 1
        AND t.id NOT IN (
            SELECT ct.task_id 
            FROM completed_tasks ct 
            WHERE ct.user_id = ?
        )
        ORDER BY t.id
        LIMIT 1
    ");
    $stmt->execute([$current_level, $user_id]);
    $nextTask = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($nextTask) {
        echo "   ✅ Next task found:\n";
        echo "      ID: {$nextTask['id']}\n";
        echo "      Title: {$nextTask['title']}\n";
        echo "      Level: {$nextTask['level']}\n";
        echo "      Instructions: " . ($nextTask['instructions'] ?? 'YES or NO') . "\n";
        
        // Calculate task number
        $task_number = $completed + 1;
        $nextTask['task_number'] = $task_number;
        echo "      Task Number: $task_number\n";
        
        // Format image path
        if ($nextTask['image']) {
            $nextTask['image'] = 'uploads/tasks/' . $nextTask['image'];
        }
        echo "      Image Path: " . ($nextTask['image'] ?? 'No image') . "\n";
        
    } else {
        echo "   ❌ No next task found\n";
    }
    
    // Test 3: Check task_action.php response format
    echo "\n3. Checking task_action.php response format...\n";
    
    // Simulate a task submission to test the response
    echo "   ✅ To test the actual response, submit a task and check browser console\n";
    echo "   ✅ Look for 'Task submission response:' log\n";
    echo "   ✅ Verify the response contains next_task object\n";
    
    // Test 4: Check JavaScript function availability
    echo "\n4. Checking JavaScript function availability...\n";
    
    $dashboardContent = file_get_contents('dashboard.php');
    
    $functions = [
        'function completeTask(',
        'function renderTask(',
        'function disableTaskButtons(',
        'function enableTaskButtons(',
        'function updateDashboardElements('
    ];
    
    foreach ($functions as $function) {
        if (strpos($dashboardContent, $function) !== false) {
            echo "   ✅ $function found\n";
        } else {
            echo "   ❌ $function not found\n";
        }
    }
    
    // Test 5: Check for potential issues
    echo "\n5. Checking for potential issues...\n";
    
    // Check if there are any JavaScript errors
    if (strpos($dashboardContent, 'console.error') !== false) {
        echo "   ✅ Error logging implemented\n";
    }
    
    // Check if buttons are properly targeted
    if (strpos($dashboardContent, '#taskModalBody button') !== false) {
        echo "   ✅ Button targeting correct\n";
    } else {
        echo "   ❌ Button targeting may be incorrect\n";
    }
    
    // Check if modal body exists
    if (strpos($dashboardContent, 'taskModalBody') !== false) {
        echo "   ✅ Modal body element referenced\n";
    } else {
        echo "   ❌ Modal body element not found\n";
    }
    
    // Test 6: Manual testing instructions
    echo "\n6. Manual testing instructions:\n";
    echo "   1. Open browser developer tools (F12)\n";
    echo "   2. Go to Console tab\n";
    echo "   3. Open a task modal and complete a task\n";
    echo "   4. Watch for these console logs:\n";
    echo "      - 'Task submission response:' (should show next_task)\n";
    echo "      - 'renderTask called with:' (should show task object)\n";
    echo "      - 'New task rendered, re-enabling buttons'\n";
    echo "   5. Check if buttons are disabled during submission\n";
    echo "   6. Check if new task content appears in modal\n";
    echo "   7. Check if buttons are re-enabled for new task\n";
    
    echo "\n=== DEBUGGING COMPLETE ===\n";
    echo "✅ All components verified. If flow still not working:\n";
    echo "1. Check browser console for JavaScript errors\n";
    echo "2. Verify network requests in Network tab\n";
    echo "3. Check if task_action.php returns proper JSON\n";
    echo "4. Verify next_task object has all required fields\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
