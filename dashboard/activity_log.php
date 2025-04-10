<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Database connection
$host = 'localhost';
$dbname = 'task_manager_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Delete activities older than 30 days
try {
    $deleteStmt = $pdo->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $deleteStmt->execute();
    $deletedCount = $deleteStmt->rowCount();
    
    if ($deletedCount > 0) {
        error_log("Deleted $deletedCount old activity logs");
    }
} catch (PDOException $e) {
    error_log("Failed to delete old activity logs: " . $e->getMessage());
}

// Fetch user data
$userStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$userStmt->execute([$_SESSION['user_id']]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

// Get user initial for avatar
$user['initial'] = strtoupper(substr($user['name'], 0, 1));

// Fetch activity logs
try {
    $logsStmt = $pdo->prepare("
        SELECT al.*, u.name as user_name 
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        WHERE al.user_id = ?
        ORDER BY al.created_at DESC
        LIMIT 50
    ");
    $logsStmt->execute([$_SESSION['user_id']]);
    $logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Failed to fetch activity logs: " . $e->getMessage());
    $logs = [];
}

// Function to format action text
function formatAction($action) {
    $actions = [
        'added_task' => 'added a task',
        'updated_task' => 'updated a task',
        'deleted_task' => 'deleted a task',
        'completed_task' => 'completed a task',
        'uncompleted_task' => 'marked a task as incomplete',
        'shared_task' => 'shared a task',
        'login' => 'logged in',
        'logout' => 'logged out'
    ];
    
    return $actions[$action] ?? $action;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager / Activity Log</title>
    <style>
:root {
    --primary: #E5A624;  /* Primary orange */
    --secondary: #2E5C8A; /* Primary blue */
    --success: #4cc9f0;
    --danger: #D99000;   /* Dark orange */
    --warning: #FFF0D3;  /* Light orange */
    --light: #D1E0F0;    /* Light blue */
    --dark: #1A3A5F;     /* Dark blue */
    --gray: #666666;     /* Light text */
    --border-radius: 10px;
    --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    --transition: all 0.3s ease;
}
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        html, body {
            height: 100%;
            width: 100%;
            background-color: #f5f7fb;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            width: 100%;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 10px 0;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .dashboard {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 20px;
            min-height: calc(100% - 70px);
            flex-grow: 1;
        }
        
        .sidebar {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            height: 100%;
            overflow-y: auto;
        }
        
        .main-menu {
            list-style: none;
        }
        
        .main-menu li {
            margin-bottom: 15px;
        }
        
        .main-menu a {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: var(--gray);
            padding: 10px;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .main-menu a:hover, .main-menu a.active {
            background-color: var(--primary);
            color: white;
        }
        
        .main-menu i {
            margin-right: 10px;
        }
        
        .content {
            display: flex;
            flex-direction: column;
            gap: 20px;
            height: auto;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .activity-log {
            margin-top: 20px;
        }
        
        .log-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 10px;
        }
        
        .log-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .log-content {
            flex-grow: 1;
        }
        
        .log-time {
            color: var(--gray);
            font-size: 12px;
            margin-top: 5px;
        }
        
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .alert-info {
            background-color: rgba(76, 201, 240, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        
        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-check-circle"></i> Task Manager</h1>
            <div class="user-profile">
                <span>Welcome, <?php echo htmlspecialchars($user['name']); ?></span>
                <div class="user-avatar"><?php echo htmlspecialchars($user['initial']); ?></div>
            </div>
        </header>
        
        <div class="dashboard">
            <div class="sidebar">
                <ul class="main-menu">
                    <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="add_task.php"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li><a href="calendar.php"><i class="fas fa-calendar"></i> Calendar</a></li>
                    <li><a href="report.php"><i class="fas fa-chart-line"></i> Reports</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="activity_log.php" class="active"><i class="fas fa-history"></i> Activity Log</a></li>
                    <li><a href="http://localhost/task-manager/"><i class="fas fa-lock"></i> Logout</a></li>
                </ul>
            </div>
            
            <div class="content">
                <div class="card">
                    <div class="section-header">
                        <h2><i class="fas fa-history"></i> Activity Log</h2>
                    </div>
                    
                    <div class="activity-log">
                        <?php if (empty($logs)): ?>
                            <div class="alert alert-info">No activities found yet. Activities older than 30 days are automatically deleted.</div>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <div class="log-item">
                                    <div class="log-icon">
                                        <?php
                                        $icon = 'fa-user';
                                        if (strpos($log['action'], 'task') !== false) {
                                            $icon = 'fa-tasks';
                                        }
                                        if (strpos($log['action'], 'completed') !== false) {
                                            $icon = 'fa-check-circle';
                                        }
                                        if (strpos($log['action'], 'shared') !== false) {
                                            $icon = 'fa-share-alt';
                                        }
                                        if (strpos($log['action'], 'login') !== false || strpos($log['action'], 'logout') !== false) {
                                            $icon = 'fa-sign-in-alt';
                                        }
                                        ?>
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="log-content">
                                        <strong><?php echo htmlspecialchars($log['user_name']); ?></strong> 
                                        <?php echo formatAction($log['action']); ?>
                                        <?php if (!empty($log['details'])): ?>
                                            <div class="log-details"><?php echo htmlspecialchars($log['details']); ?></div>
                                        <?php endif; ?>
                                        <div class="log-time">
                                            <?php 
                                            try {
                                                $date = new DateTime($log['created_at']);
                                                echo $date->format('M j, Y g:i A');
                                            } catch (Exception $e) {
                                                echo htmlspecialchars($log['created_at']);
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
