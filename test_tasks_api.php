<?php
// Test the get_tasks_by_level.php API
$_GET['level'] = 'Bronze';

// Simulate admin session
session_start();
$_SESSION['admin_id'] = 1;

ob_start();
include 'admin/get_tasks_by_level.php';
$response = ob_get_clean();

echo "API Response for Bronze level:\n";
echo $response . "\n\n";

// Test JSON parsing
$data = json_decode($response, true);
if (is_array($data)) {
    echo "✅ Valid JSON response\n";
    echo "Found " . count($data) . " tasks\n";
    if (!empty($data)) {
        $firstTask = $data[0];
        echo "First task format:\n";
        echo "- ID: " . $firstTask['id'] . "\n";
        echo "- Title: " . $firstTask['title'] . "\n";
        echo "- Level: " . $firstTask['level'] . "\n";
    }
} else {
    echo "❌ Invalid JSON response\n";
}
?>
