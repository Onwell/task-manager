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
    die("Database connection failed: " . $e->getMessage());
}

// Get user ID from session (assuming user is logged in)
$userId = $_SESSION['user_id'] ?? 1; // Default to 1 if not set (for testing)

// Fetch user data from database
$userStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

// Get user initial for avatar
$user['initial'] = strtoupper(substr($user['name'], 0, 1));

// Fetch tasks from database
$taskStmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = ?");
$taskStmt->execute([$userId]);
$user['tasks'] = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$totalTasks = count($user['tasks']);
$completedTasks = 0;
$highPriorityTasks = 0;
$upcomingTasks = 0;

foreach ($user['tasks'] as $task) {
    if ($task['completed']) {
        $completedTasks++;
    }
    
    if ($task['priority'] === 'high') {
        $highPriorityTasks++;
    }
    
    // Consider tasks for tomorrow or later as upcoming
    $taskDate = strtotime($task['due_date']); // Assuming the field is named due_date
    $tomorrow = strtotime('tomorrow');
    if ($taskDate >= $tomorrow && !$task['completed']) {
        $upcomingTasks++;
    }
}

// Handle form submission for task operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process form submission
    if (isset($_POST['title']) && isset($_POST['due_date'])) {
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
            
            // Redirect to prevent form resubmission
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $e) {
            die("Error adding task: " . $e->getMessage());
        }
    }
    
    // Handle other operations (delete, update) as needed
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fancy Task Manager</title>
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
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
            overflow: hidden;
        }
        
        .container {
            width: 100%;
            height: 100vh;
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
            height: calc(100% - 70px);
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
            height: 100%;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .overview {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        
        .card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
        }
        
        .stat-card {
            display: flex;
            flex-direction: column;
        }
        
        .stat-card .icon {
            align-self: flex-end;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }
        
        .stat-card h3 {
            font-size: 28px;
            margin: 5px 0;
        }
        
        .stat-card p {
            color: var(--gray);
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .overview {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .sidebar {
                height: auto;
            }
            
            .content {
                height: auto;
            }
        }
        
        @media (max-width: 480px) {
            .overview {
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
                <li><a href="dashboard/index.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="add_task.php"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li><a href="#"><i class="fas fa-project-diagram"></i> Projects</a></li>
                    <li><a href="calendar.php"><i class="fas fa-calendar"></i> Calendar</a></li>
                    <li><a href="report.php"><i class="fas fa-chart-line"></i> Reports</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="activity_log.php"><i class="fas fa-history"></i> Activity Log</a></li>
                    <li><a href="http://localhost/task-manager/"><i class="fas fa-lock"></i> Logout</a></li>
                </ul>
            </div>
            
            <div class="content">
                <div class="overview">
                    <div class="card stat-card">
                        <div class="icon" style="background-color: rgba(67, 97, 238, 0.1); color: var(--primary);">
                            <i class="fas fa-list"></i>
                        </div>
                        <h3><?php echo $totalTasks; ?></h3>
                        <p>Total Tasks</p>
                    </div>
                    
                    <div class="card stat-card">
                        <div class="icon" style="background-color: rgba(76, 201, 240, 0.1); color: var(--success);">
                            <i class="fas fa-check"></i>
                        </div>
                        <h3><?php echo $completedTasks; ?></h3>
                        <p>Completed</p>
                    </div>
                    
                    <div class="card stat-card">
                        <div class="icon" style="background-color: rgba(247, 37, 133, 0.1); color: var(--danger);">
                            <i class="fas fa-exclamation"></i>
                        </div>
                        <h3><?php echo $highPriorityTasks; ?></h3>
                        <p>High Priority</p>
                    </div>
                    
                    <div class="card stat-card">
                        <div class="icon" style="background-color: rgba(248, 150, 30, 0.1); color: var(--warning);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3><?php echo $upcomingTasks; ?></h3>
                        <p>Upcoming</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
