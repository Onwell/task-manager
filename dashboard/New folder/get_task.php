<?php
// Start session for user authentication
session_start();

// Database connection
$host = 'localhost';
$dbname = 'task_manager_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => "Database connection failed: " . $e->getMessage()]));
}

// Get user ID from session
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'User not authenticated']));
}

$userId = $_SESSION['user_id'];

// Check if task ID is provided
if (!isset($_GET['task_id'])) {
    die(json_encode(['success' => false, 'message' => 'Task ID not provided']));
}

$taskId = (int)$_GET['task_id'];

// Fetch task data including shared tasks with edit permission
try {
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            CASE 
                WHEN t.user_id = :user_id THEN 'owner'
                ELSE 'shared'
            END AS task_type,
            st.can_edit AS can_edit
        FROM tasks t
        LEFT JOIN shared_tasks st ON t.id = st.task_id AND st.shared_with_id = :user_id
        WHERE t.id = :task_id AND (t.user_id = :user_id OR st.shared_with_id = :user_id)
    ");
    
    $stmt->execute([
        ':task_id' => $taskId,
        ':user_id' => $userId
    ]);
    
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($task) {
        // Check if user has permission to edit
        if ($task['task_type'] === 'shared' && !$task['can_edit']) {
            die(json_encode([
                'success' => false,
                'message' => 'You do not have permission to edit this task'
            ]));
        }

        // Format date and time for HTML inputs
        $task['due_date'] = date('Y-m-d', strtotime($task['due_date']));
        $task['time'] = !empty($task['time']) ? date('H:i', strtotime($task['time'])) : '';
        
        // Add success flag
        $task['success'] = true;
        
        header('Content-Type: application/json');
        echo json_encode($task);
    } else {
        die(json_encode([
            'success' => false,
            'message' => 'Task not found or no access permission'
        ]));
    }
} catch (PDOException $e) {
    die(json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]));
}