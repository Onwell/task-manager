<?php
session_start();
header('Content-Type: application/json');

// Database connection
$host = 'localhost';
$dbname = 'task_manager_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Get input
$taskId = $_POST['task_id'] ?? null;
$email = $_POST['email'] ?? null;
$canEdit = isset($_POST['can_edit']) ? (int)$_POST['can_edit'] : 0;
$userId = $_SESSION['user_id'] ?? null;

if (!$taskId || !$email || !$userId) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

// Check if task exists and belongs to user
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
$stmt->execute([$taskId, $userId]);
$task = $stmt->fetch();

if (!$task) {
    echo json_encode(['success' => false, 'message' => 'Task not found or not authorized']);
    exit;
}

// Check if user exists to share with
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
$sharedUser = $stmt->fetch();

if (!$sharedUser) {
    echo json_encode(['success' => false, 'message' => 'User with this email not found']);
    exit;
}

if ($sharedUser['id'] == $userId) {
    echo json_encode(['success' => false, 'message' => 'Cannot share task with yourself']);
    exit;
}

// Check if task is already shared with this user
$stmt = $pdo->prepare("SELECT * FROM shared_tasks WHERE task_id = ? AND shared_with_id = ?");
$stmt->execute([$taskId, $sharedUser['id']]);
$existingShare = $stmt->fetch();

if ($existingShare) {
    echo json_encode(['success' => false, 'message' => 'Task is already shared with this user']);
    exit;
}

// Share the task
try {
    $stmt = $pdo->prepare("INSERT INTO shared_tasks (task_id, owner_id, shared_with_id, can_edit, shared_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$taskId, $userId, $sharedUser['id'], $canEdit]);
    
    echo json_encode(['success' => true, 'message' => 'Task shared successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
