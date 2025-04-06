<?php
// Start session for user authentication
session_start();

// Database connection
$host = 'localhost';
$dbname = 'task_manager_db';
$username = 'root'; // Replace with your database username
$password = ''; // Replace with your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => "Database connection failed: " . $e->getMessage()]));
}

// Get user ID from session (assuming user is logged in)
$userId = $_SESSION['user_id'] ?? 1; // Default to 1 if not set (for testing)

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    // Add new task
    try {
        $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, due_date, time, priority, completed, progress) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $_POST['title'],
            $_POST['due_date'],
            $_POST['time'] ?? '00:00:00',
            $_POST['priority'] ?? 'medium',
            $_POST['completed'] ?? 0,
            $_POST['progress'] ?? 0
        ]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => "Error adding task: " . $e->getMessage()]);
    }
} elseif ($action === 'edit') {
    // Edit existing task
    if (!isset($_POST['task_id'])) {
        echo json_encode(['success' => false, 'message' => 'Task ID is required']);
        exit;
    }
    
    $taskId = (int)$_POST['task_id'];
    
    // Verify the task belongs to the current user
    $checkStmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
    $checkStmt->execute([$taskId, $userId]);
    
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Task not found or access denied']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE tasks SET 
                              title = ?, 
                              due_date = ?, 
                              time = ?, 
                              priority = ?, 
                              progress = ? 
                              WHERE id = ? AND user_id = ?");
        $stmt->execute([
            $_POST['title'],
            $_POST['due_date'],
            $_POST['time'] ?? '00:00:00',
            $_POST['priority'] ?? 'medium',
            $_POST['progress'] ?? 0,
            $taskId,
            $userId
        ]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => "Error updating task: " . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}