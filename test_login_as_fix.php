<?php
/**
 * Test Login As User Fix
 * This script tests the login as functionality
 */

require_once __DIR__ . '/config.php';

echo "=== TESTING LOGIN AS USER FIX ===\n\n";

try {
    $conn = getConnection();
    echo "✅ Database connected\n";
    
    // Test with a real user
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([3]); // Test user ID 3
    $testUser = $stmt->fetch();
    
    if ($testUser) {
        echo "✅ Found test user: {$testUser['fullname']} (ID: {$testUser['id']})\n";
        echo "   Email: {$testUser['email']}\n";
        echo "   Balance: \\${$testUser['balance']}\n";
        
        // Simulate the login_as_user.php process
        echo "\n=== SIMULATING LOGIN AS USER ===\n";
        
        // Start fresh session
        session_start();
        
        // Save admin session if exists
        if (isset($_SESSION['admin_id'])) {
            echo "✅ Admin session found: {$_SESSION['admin_id']}\n";
            $_SESSION['original_admin_id'] = $_SESSION['admin_id'];
        } else {
            echo "⚠️  No admin session found (this is ok after removal)\n";
        }
        
        // Clear session
        session_unset();
        echo "✅ Session cleared\n";
        
        // Set user session
        $_SESSION['user_id'] = $testUser['id'];
        $_SESSION['user'] = $testUser;
        $_SESSION['is_impersonating'] = true;
        
        echo "✅ User session set:\n";
        echo "   - User ID: {$_SESSION['user_id']}\n";
        echo "   - User Name: {$_SESSION['user']['fullname']}\n";
        echo "   - Impersonating: {$_SESSION['is_impersonating']}\n";
        
        // Test user authentication function
        require_once __DIR__ . '/config.php';
        if (function_exists('isUserLoggedIn')) {
            $isLoggedIn = isUserLoggedIn();
            echo "✅ isUserLoggedIn(): " . ($isLoggedIn ? 'true' : 'false') . "\n";
        }
        
        // Test getting current user
        if (function_exists('getCurrentUser')) {
            $currentUser = getCurrentUser();
            if ($currentUser) {
                echo "✅ Current user: {$currentUser['fullname']}\n";
            } else {
                echo "❌ getCurrentUser() returned null\n";
            }
        }
        
        echo "\n=== EXPECTED BEHAVIOR ===\n";
        echo "1. Admin clicks 'Login As' on user page\n";
        echo "2. Goes to login_as_user.php?user_id=3\n";
        echo "3. Session cleared and user session set\n";
        echo "4. Redirects to ../dashboard.php\n";
        echo "5. User should be logged in as test user\n";
        echo "6. Orange 'Return to Admin' bar should appear\n";
        
        echo "\n=== TROUBLESHOOTING ===\n";
        echo "If still not working, check:\n";
        echo "1. dashboard.php authentication logic\n";
        echo "2. requireLogin() function\n";
        echo "3. Session path/cookie settings\n";
        echo "4. User data structure in session\n";
        
        // Clean up
        session_unset();
        
    } else {
        echo "❌ No test user found with ID 3\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>
