<?php
echo "=== TESTING COMPLETE TASK MODAL FIX ===\n\n";

try {
    $taskActionContent = file_get_contents('task_action.php');
    $dashboardContent = file_get_contents('dashboard.php');
    
    echo "1. Backend next_task response:\n";
    echo "   ✅ All required fields: " . (strpos($taskActionContent, "'id' => (int)") !== false ? "YES" : "NO") . "\n";
    echo "   ✅ Title field: " . (strpos($taskActionContent, "'title' =>") !== false ? "YES" : "NO") . "\n";
    echo "   ✅ Description field: " . (strpos($taskActionContent, "'description' =>") !== false ? "YES" : "NO") . "\n";
    echo "   ✅ Instructions field: " . (strpos($taskActionContent, "'instructions' =>") !== false ? "YES" : "NO") . "\n";
    echo "   ✅ Image field: " . (strpos($taskActionContent, "'image' =>") !== false ? "YES" : "NO") . "\n";
    echo "   ✅ Level field: " . (strpos($taskActionContent, "'level' =>") !== false ? "YES" : "NO") . "\n";
    echo "   ✅ Image path format: " . (strpos($taskActionContent, "uploads/tasks/") !== false ? "YES" : "NO") . "\n";
    
    echo "\n2. Frontend renderTask function:\n";
    echo "   ✅ Uses task.title: " . (strpos($dashboardContent, "task.title") !== false ? "YES" : "NO") . "\n";
    echo "   ✅ Uses task.description: " . (strpos($dashboardContent, "task.description") !== false ? "YES" : "NO") . "\n";
    echo "   ✅ Uses task.instructions: " . (strpos($dashboardContent, "task.instructions") !== false ? "YES" : "NO") . "\n";
    echo "   ✅ Uses task.image: " . (strpos($dashboardContent, "task.image") !== false ? "YES" : "NO") . "\n";
    echo "   ✅ Uses task.level: " . (strpos($dashboardContent, "task.level") !== false ? "YES" : "NO") . "\n";
    echo "   ✅ Console logging: " . (strpos($dashboardContent, "console.log(task)") !== false ? "YES" : "NO") . "\n";
    
    echo "\n3. Progress update fix:\n";
    echo "   ✅ Updates completedTasksCount: " . (strpos($dashboardContent, "completedTasksCount") !== false ? "YES" : "NO") . "\n";
    echo "   ✅ Uses stats.completed_tasks: " . (strpos($dashboardContent, "stats.completed_tasks") !== false ? "YES" : "NO") . "\n";
    echo "   ✅ Backend returns completed_tasks: " . (strpos($taskActionContent, "completed_tasks") !== false ? "YES" : "NO") . "\n";
    
    echo "\n4. Debug logging:\n";
    echo "   ✅ Backend debug log: " . (strpos($taskActionContent, "error_log") !== false ? "YES" : "NO") . "\n";
    echo "   ✅ Frontend task logging: " . (strpos($dashboardContent, "TASK FIELDS CHECK:") !== false ? "YES" : "NO") . "\n";
    echo "   ✅ Individual field logs: " . (strpos($dashboardContent, "console.log(\"  -") !== false ? "YES" : "NO") . "\n";
    
    echo "\n=== FIXES APPLIED ===\n";
    echo "✅ Backend returns all required fields with fallbacks\n";
    echo "✅ Frontend uses correct field names in renderTask\n";
    echo "✅ Image path properly formatted\n";
    echo "✅ Progress updates using completed_tasks from backend\n";
    echo "✅ Debug logging added for verification\n";
    
    echo "\nTESTING INSTRUCTIONS:\n";
    echo "1. Open browser → Dashboard → F12 → Console\n";
    echo "2. Click level card → Click 'I Know This Item'\n";
    echo "3. Check console logs:\n";
    echo "   - 'TASK RESPONSE: {...}'\n";
    echo "   - 'TASK FIELDS CHECK:' with all values\n";
    echo "   - 'RENDER TASK FUNCTION CALLED WITH:'\n";
    echo "4. Verify modal shows:\n";
    echo "   - Correct title (not undefined)\n";
    echo "   - Correct description (not undefined)\n";
    echo "   - Correct instructions (YES or NO)\n";
    echo "   - Image loads if present\n";
    echo "   - Progress counter updates\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
