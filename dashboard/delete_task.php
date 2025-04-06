<?php
session_start();
require_once 'activity_logger.php'; // Include the activity logger

// Database connection
$host = 'localhost';
$dbname = 'task_manager_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not logged in']));
}

$userId = $_SESSION['user_id'];
$taskId = $_POST['task_id'] ?? '';

if (empty($taskId)) {
    die(json_encode(['success' => false, 'message' => 'Task ID is required']));
}

try {
    // Get task details for the log before deleting
    $taskStmt = $pdo->prepare("SELECT title FROM tasks WHERE id = ? AND user_id = ?");
    $taskStmt->execute([$taskId, $userId]);
    $taskData = $taskStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$taskData) {
        die(json_encode(['success' => false, 'message' => 'Task not found or access denied']));
    }
    
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
    $result = $stmt->execute([$taskId, $userId]);
    
    if ($result) {
        // Log the activity
        logActivity($pdo, $userId, 'deleted_task', "Deleted task: {$taskData['title']}");
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete task']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>