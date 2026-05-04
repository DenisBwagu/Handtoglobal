<?php
/**
 * Debug Login As Issue
 * This script helps debug why admin is being redirected back
 */

require_once 'config.php';

echo "=== DEBUGGING LOGIN AS ISSUE ===\n\n";

// Start session
session_start();

echo "Current session data:\n";
print_r($_SESSION);

echo "\n=== CHECKING AUTHENTICATION FUNCTIONS ===\n";

// Test isLoggedIn function
$loggedIn = isLoggedIn();
echo "isLoggedIn(): " . ($loggedIn ? 'true' : 'false') . "\n";

// Test isAdminLoggedIn function
$adminLoggedIn = isAdminLoggedIn();
echo "isAdminLoggedIn(): " . ($adminLoggedIn ? 'true' : 'false') . "\n";

echo "\n=== SIMULATING LOGIN AS USER ===\n";

// Test user ID 3
$userId = 3;
$conn = getConnection();
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($user) {
    echo "Found user: {$user['fullname']}\n";
    
    // Clear session completely
    session_destroy();
    session_start();
    
    echo "Session destroyed and restarted\n";
    
    // Set user session exactly like login_as_user.php
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user'] = $user;
    $_SESSION['role'] = 'user';
    $_SESSION['is_impersonating'] = true;
    
    echo "User session set:\n";
    echo "- user_id: {$_SESSION['user_id']}\n";
    echo "- role: {$_SESSION['role']}\n";
    echo "- is_impersonating: {$_SESSION['is_impersonating']}\n";
    
    // Test authentication again
    $loggedIn = isLoggedIn();
    $adminLoggedIn = isAdminLoggedIn();
    
    echo "\nAfter setting user session:\n";
    echo "isLoggedIn(): " . ($loggedIn ? 'true' : 'false') . "\n";
    echo "isAdminLoggedIn(): " . ($adminLoggedIn ? 'true' : 'false') . "\n";
    
    if ($loggedIn) {
        echo "✅ User should be able to access dashboard\n";
    } else {
        echo "❌ User will be redirected to login.php\n";
    }
    
    if ($adminLoggedIn) {
        echo "⚠️  Admin is still logged in - this might cause issues\n";
    } else {
        echo "✅ Admin is not logged in\n";
    }
    
} else {
    echo "❌ User not found\n";
}

echo "\n=== CHECKING FOR REDIRECTS ===\n";

// Check if there are any redirect conditions
echo "Current URL: " . ($_SERVER['REQUEST_URI'] ?? 'unknown') . "\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'unknown') . "\n";

// Check if we're in admin area
$isAdmin = strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/') !== false;
echo "Is admin area: " . ($isAdmin ? 'true' : 'false') . "\n";

echo "\n=== RECOMMENDATIONS ===\n";
echo "1. Make sure session is completely cleared before setting user session\n";
echo "2. Check if any admin session variables are interfering\n";
echo "3. Verify the dashboard.php authentication logic\n";
echo "4. Test the actual login_as_user.php redirect\n";

echo "\n=== DEBUG COMPLETE ===\n";
?>
