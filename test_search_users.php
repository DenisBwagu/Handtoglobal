<?php
// Test the search_users.php API
$_GET['query'] = 'test';

// Simulate admin session
session_start();
$_SESSION['admin_id'] = 1;

ob_start();
include 'admin/search_users.php';
$response = ob_get_clean();

echo "API Response for 'test' query:\n";
echo $response . "\n\n";

// Test JSON parsing
$data = json_decode($response, true);
if (is_array($data)) {
    echo "✅ Valid JSON response\n";
    echo "Found " . count($data) . " users\n";
    if (!empty($data)) {
        $firstUser = $data[0];
        echo "First user format:\n";
        echo "- ID: " . $firstUser['id'] . "\n";
        echo "- Full Name: " . $firstUser['fullname'] . "\n";
        echo "- Email: " . $firstUser['email'] . "\n";
    }
} else {
    echo "❌ Invalid JSON response\n";
}
?>
