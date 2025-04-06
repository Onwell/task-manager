<?php
// Start session for user authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

// Check if task_id was provided
if (!isset($_POST['task_id']) || empty($_POST['task_id'])) {
    echo json_encode(['success' => false, 'message' => 'No task ID provided']);
    exit;
}

$taskId = intval($_POST['task_id']);

// Database connection
$host = 'localhost';
$dbname = 'task_manager_db';
$username = 'root'; // Replace with your database username
$password = ''; // Replace with your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// First check if the task belongs to the current user
$checkStmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
$checkStmt->execute([$taskId, $userId]);

if (!$checkStmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Task not found or you do not have permission to delete it']);
    exit;
}

// Delete the task
try {
    $deleteStmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
    $result = $deleteStmt->execute([$taskId, $userId]);
    
    if ($result && $deleteStmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Task deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete task']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>