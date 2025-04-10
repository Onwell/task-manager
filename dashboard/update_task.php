<?php
session_start();
require_once 'activity_logger.php';

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
$completed = $_POST['completed'] ?? '';

if (empty($taskId) || !isset($completed)) {
    die(json_encode(['success' => false, 'message' => 'Task ID and completion status are required']));
}

try {
    // Check if user owns the task or has edit permissions through sharing
    $taskStmt = $pdo->prepare("
        SELECT t.title, t.user_id, st.can_edit 
        FROM tasks t
        LEFT JOIN shared_tasks st ON t.id = st.task_id AND st.shared_with_id = ?
        WHERE t.id = ? AND (t.user_id = ? OR st.shared_with_id = ?)
    ");
    $taskStmt->execute([$userId, $taskId, $userId, $userId]);
    $taskData = $taskStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$taskData) {
        die(json_encode(['success' => false, 'message' => 'Task not found or access denied']));
    }
    
    // If task is shared, check edit permission
    if ($taskData['user_id'] != $userId && !$taskData['can_edit']) {
        die(json_encode(['success' => false, 'message' => 'No permission to edit this task']));
    }
    
    $stmt = $pdo->prepare("UPDATE tasks SET completed = ? WHERE id = ?");
    $result = $stmt->execute([$completed, $taskId]);
    
    if ($result) {
        // Log the activity
        $action = $completed ? 'completed_task' : 'uncompleted_task';
        $details = $completed ? "Completed task: {$taskData['title']}" : "Marked task as incomplete: {$taskData['title']}";
        logActivity($pdo, $userId, $action, $details);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update task status']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>