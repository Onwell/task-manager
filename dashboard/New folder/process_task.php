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
$action = $_POST['action'] ?? '';

// Process add task
if ($action === 'add') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? ''; // Added this line
    $dueDate = $_POST['due_date'] ?? '';
    $time = $_POST['time'] ?? null;
    $priority = $_POST['priority'] ?? 'medium';
    $progress = $_POST['progress'] ?? 0;
    $reminder = isset($_POST['reminder']) ? 1 : 0; // Added this line
    
    if (empty($title) || empty($dueDate)) {
        die(json_encode(['success' => false, 'message' => 'Title and due date are required']));
    }
    
    try {
        // Updated query to include description and reminder
        $stmt = $pdo->prepare("INSERT INTO tasks 
            (user_id, title, description, due_date, time, priority, progress, reminder, completed) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
        $result = $stmt->execute([
            $userId, 
            $title, 
            $description, 
            $dueDate, 
            $time, 
            $priority, 
            $progress, 
            $reminder
        ]);
        
        if ($result) {
            // Log the activity
            $taskId = $pdo->lastInsertId();
            logActivity($pdo, $userId, 'added_task', "Added task: $title");
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add task']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
// Process edit task
else if ($action === 'edit') {
    $taskId = $_POST['task_id'] ?? '';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? ''; // Added this line
    $dueDate = $_POST['due_date'] ?? '';
    $time = $_POST['time'] ?? null;
    $priority = $_POST['priority'] ?? 'medium';
    $progress = $_POST['progress'] ?? 0;
    $reminder = isset($_POST['reminder']) ? 1 : 0; // Added this line
    
    if (empty($taskId) || empty($title) || empty($dueDate)) {
        die(json_encode(['success' => false, 'message' => 'Task ID, title, and due date are required']));
    }
    
    try {
        // Verify task belongs to the user
        $checkStmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
        $checkStmt->execute([$taskId, $userId]);
        if (!$checkStmt->fetch()) {
            die(json_encode(['success' => false, 'message' => 'Task not found or access denied']));
        }
        
        // Updated query to include description and reminder
        $stmt = $pdo->prepare("UPDATE tasks SET 
            title = ?, 
            description = ?, 
            due_date = ?, 
            time = ?, 
            priority = ?, 
            progress = ?, 
            reminder = ? 
            WHERE id = ? AND user_id = ?");
        $result = $stmt->execute([
            $title, 
            $description, 
            $dueDate, 
            $time, 
            $priority, 
            $progress, 
            $reminder, 
            $taskId, 
            $userId
        ]);
        
        if ($result) {
            // Log the activity
            logActivity($pdo, $userId, 'updated_task', "Updated task: $title");
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update task']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
// Invalid action
else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
