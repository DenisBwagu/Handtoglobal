<?php
/**
 * Search Users API
 * This script returns users matching the search query in JSON format
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$query = $_GET['query'] ?? '';

if (empty($query)) {
    echo json_encode([]);
    exit;
}

try {
    $conn = getConnection();
    
    // Search users by fullname or email
    $stmt = $conn->prepare("
        SELECT id, fullname, email
        FROM users
        WHERE fullname LIKE ? OR email LIKE ?
        ORDER BY fullname ASC
        LIMIT 50
    ");
    $searchTerm = '%' . $query . '%';
    $stmt->execute([$searchTerm, $searchTerm]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format users as requested
    $formattedUsers = [];
    foreach ($users as $user) {
        $formattedUsers[] = [
            'id' => $user['id'],
            'fullname' => $user['fullname'],
            'email' => $user['email']
        ];
    }
    
    echo json_encode($formattedUsers);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
