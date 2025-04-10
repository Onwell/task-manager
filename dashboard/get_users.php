<?php
session_start();
require_once 'db_connection.php'; // Your database connection file

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    header('HTTP/1.1 401 Unauthorized');
    exit(json_encode(['error' => 'Unauthorized']));
}

try {
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id != ?");
    $stmt->execute([$userId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($users);
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database error']);
}
?>