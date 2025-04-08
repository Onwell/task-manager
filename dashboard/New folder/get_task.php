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

// Check if task ID is provided
if (!isset($_GET['task_id'])) {
    echo json_encode(null);
    exit;
}

$taskId = (int)$_GET['task_id'];

// Fetch task data
try {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->execute([$taskId, $userId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($task) {
        // Format date and time for HTML inputs
        $task['due_date'] = date('Y-m-d', strtotime($task['due_date']));
        $task['time'] = !empty($task['time']) ? date('H:i', strtotime($task['time'])) : '';
        
        echo json_encode($task);
    } else {
        echo json_encode(null);
    }
} catch (PDOException $e) {
    echo json_encode(null);
}