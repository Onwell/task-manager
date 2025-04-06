<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Check if required parameters exist
if (!isset($_POST['task_id']) || !isset($_POST['completed'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$userId = $_SESSION['user_id'];
$taskId = (int)$_POST['task_id'];
$completed = $_POST['completed'] === '1' ? 1 : 0;

// Database connection
$host = 'localhost';
$dbname = 'task_manager_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Verify task belongs to user
$stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
$stmt->execute([$taskId, $userId]);

if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Task not found or access denied']);
    exit;
}

// Update task status
try {
    $updateStmt = $pdo->prepare("UPDATE tasks SET completed = ? WHERE id = ?");
    $updateStmt->execute([$completed, $taskId]);
    
    echo json_encode(['success' => true, 'message' => 'Task updated successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>