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
    die("Database connection failed: " . $e->getMessage());
}

// Get user ID from session
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: login.php');
    exit;
}

// Fetch user data
$userStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

// Get user initial for avatar
$user['initial'] = strtoupper(substr($user['name'], 0, 1));

// Handle search query
$searchQuery = $_GET['search'] ?? '';
$whereClause = "WHERE user_id = ?";
$params = [$userId];

if (!empty($searchQuery)) {
    $whereClause .= " AND (title LIKE ? OR description LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

// Fetch tasks from database
$taskStmt = $pdo->prepare("SELECT * FROM tasks $whereClause ORDER BY 
    CASE 
        WHEN due_date < CURDATE() THEN 0 
        WHEN due_date = CURDATE() THEN 1 
        ELSE 2 
    END, due_date ASC");
$taskStmt->execute($params);
$user['tasks'] = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$totalTasks = count($user['tasks']);
$completedTasks = 0;
$highPriorityTasks = 0;
$mediumPriorityTasks = 0;
$lowPriorityTasks = 0;
$overdueTasks = 0;
$todayTasks = 0;
$upcomingTasks = 0;

foreach ($user['tasks'] as $task) {
    if ($task['completed']) {
        $completedTasks++;
    }
    
    if ($task['priority'] === 'high') {
        $highPriorityTasks++;
    } elseif ($task['priority'] === 'medium') {
        $mediumPriorityTasks++;
    } elseif ($task['priority'] === 'low') {
        $lowPriorityTasks++;
    }
    
    // Calculate due date status
    $dueDate = new DateTime($task['due_date']);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    if (!$task['completed']) {
        if ($dueDate < $today) {
            $overdueTasks++;
        } elseif ($dueDate == $today) {
            $todayTasks++;
        } else {
            $upcomingTasks++;
        }
    }
}

// Get shared tasks count
$sharedStmt = $pdo->prepare("SELECT COUNT(*) as count FROM shared_tasks WHERE shared_with_id = ?");
$sharedStmt->execute([$userId]);
$sharedTasks = $sharedStmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get total users count
$usersStmt = $pdo->query("SELECT COUNT(*) as count FROM users");
$totalUsers = $usersStmt->fetch(PDO::FETCH_ASSOC)['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager / Dashboard</title>
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
        
        .overview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
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
            position: relative;
            overflow: hidden;
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
        
        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .search-input {
            flex-grow: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 14px;
            transition: var(--transition);
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.2);
        }
        
        .search-button {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .search-button:hover {
            background-color: var(--secondary);
        }
        
        .task-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex-grow: 1;
            overflow-y: auto;
            padding-bottom: 20px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .button {
            background-color: var(--primary);
            text-decoration: none;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .button:hover {
            background-color: var(--secondary);
        }
        
        .task-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 20px;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            border-left: 4px solid;
        }
        
        .task-item.overdue {
            border-left-color: var(--danger);
        }
        
        .task-item.today {
            border-left-color: var(--warning);
        }
        
        .task-item.upcoming {
            border-left-color: var(--success);
        }
        
        .task-item.completed {
            opacity: 0.7;
            border-left-color: #ccc;
        }
        
        .task-item.completed h4 {
            text-decoration: line-through;
            color: var(--gray);
        }
        
        .task-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }
        
        .task-left {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-grow: 1;
        }
        
        .task-checkbox {
            min-width: 22px;
            height: 22px;
            border-radius: 6px;
            border: 2px solid var(--gray);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .task-checkbox.checked {
            background-color: var(--success);
            border-color: var(--success);
            position: relative;
        }
        
        .task-checkbox.checked::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 14px;
        }
        
        .task-content {
            flex-grow: 1;
        }
        
        .task-content h4 {
            margin-bottom: 5px;
        }
        
        .task-meta {
            display: flex;
            gap: 10px;
            font-size: 12px;
            color: var(--gray);
        }
        
        .due-date-badge {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
            color: white;
        }
        
        .due-date-badge.overdue {
            background-color: var(--danger);
        }
        
        .due-date-badge.today {
            background-color: var(--warning);
        }
        
        .due-date-badge.upcoming {
            background-color: var(--success);
        }
        
        .tag {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
            color: white;
        }
        
        .tag.high {
            background-color: var(--danger);
        }
        
        .tag.medium {
            background-color: var(--warning);
        }
        
        .tag.low {
            background-color: var(--success);
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background-color: #f0f0f0;
            border-radius: 4px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.6s ease;
        }
        
        .task-item.completed .progress-fill {
            background-color: #ccc !important;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .action-button {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: white;
            transition: var(--transition);
        }
        
        .action-button:hover {
            transform: scale(1.1);
        }
        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            text-align: center;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: white;
            padding: 25px;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .close-modal {
            cursor: pointer;
            font-size: 24px;
            color: var(--gray);
            transition: var(--transition);
        }
        
        .close-modal:hover {
            color: var(--dark);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.2);
        }
        
        .form-group.checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group.checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .sidebar {
                height: auto;
            }
            
            .content {
                height: auto;
                overflow-y: visible;
            }
            
            .overview {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
        
        @media (max-width: 480px) {
            .task-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .action-buttons {
                align-self: flex-end;
            }
            
            header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
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
                    <li><a href="index.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="add_task.php"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li><a href="calendar.php"><i class="fas fa-calendar"></i> Calendar</a></li>
                    <li><a href="report.php"><i class="fas fa-chart-line"></i> Reports</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="activity_log.php"><i class="fas fa-history"></i> Activity Log</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
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
                        <h3><?php echo $overdueTasks; ?></h3>
                        <p>Overdue</p>
                    </div>
                    
                    <div class="card stat-card">
                        <div class="icon" style="background-color: rgba(248, 150, 30, 0.1); color: var(--warning);">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <h3><?php echo $todayTasks; ?></h3>
                        <p>Due Today</p>
                    </div>
                    
                    <div class="card stat-card">
                        <div class="icon" style="background-color: rgba(76, 201, 240, 0.1); color: var(--success);">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3><?php echo $upcomingTasks; ?></h3>
                        <p>Upcoming</p>
                    </div>

                    <div class="card stat-card">
                        <div class="icon" style="background-color: rgba(111, 66, 193, 0.1); color: #6f42c1;">
                            <i class="fas fa-share-alt"></i>
                        </div>
                        <h3><?php echo $sharedTasks; ?></h3>
                        <p>Shared Tasks</p>
                    </div>

                    <div class="card stat-card">
                        <div class="icon" style="background-color: rgba(111, 66, 193, 0.1); color: #6f42c1;">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?php echo $totalUsers; ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                
                <div class="card">
                    <div class="section-header">
                        <h2>Recent Tasks</h2>
                    </div>
                    
                    <div class="task-list">
                        <?php if (empty($user['tasks'])): ?>
                            <div class="empty-state">
                                <i class="far fa-check-circle"></i>
                                <h3>No tasks found</h3>
                                <p><?php echo empty($searchQuery) ? 'Get started by adding your first task' : 'No tasks match your search'; ?></p>
                                <button id="empty-add-btn" class="button">Add Task</button>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_slice($user['tasks'], 0, 5) as $task): 
                                $dueDate = new DateTime($task['due_date']);
                                $today = new DateTime();
                                $today->setTime(0, 0, 0);
                                
                                $dueStatus = '';
                                if (!$task['completed']) {
                                    if ($dueDate < $today) {
                                        $dueStatus = 'overdue';
                                    } elseif ($dueDate == $today) {
                                        $dueStatus = 'today';
                                    } else {
                                        $dueStatus = 'upcoming';
                                    }
                                }
                            ?>
                            <div class="task-item <?php echo $task['completed'] ? 'completed' : $dueStatus; ?>" data-task-id="<?php echo $task['id']; ?>">
                                <div class="task-left">
                                    
                                    <div class="task-content">
                                        <h4><?php echo htmlspecialchars($task['title']); ?></h4>
                                        <div class="task-meta">
                                            <span><i class="far fa-calendar"></i> <?php echo htmlspecialchars(date('M j, Y', strtotime($task['due_date']))); ?></span>
                                            <?php if (!empty($task['time'])): ?>
                                                <span><i class="far fa-clock"></i> <?php echo htmlspecialchars(date('g:i A', strtotime($task['time']))); ?></span>
                                            <?php endif; ?>
                                            
                                            <?php if (!$task['completed'] && $dueStatus): ?>
                                                <span class="due-date-badge <?php echo $dueStatus; ?>">
                                                    <?php echo ucfirst($dueStatus); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="progress-bar">
                                            <div class="progress-fill" 
                                                 style="width: <?php echo $task['completed'] ? '100' : $task['progress']; ?>%;
                                                        background-color: <?php 
                                                            if ($task['priority'] === 'high') echo 'var(--danger)';
                                                            elseif ($task['priority'] === 'medium') echo 'var(--warning)';
                                                            else echo 'var(--success)';
                                                        ?>;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="task-right">
                                    <span class="tag <?php echo $task['priority']; ?>">
                                        <?php echo ucfirst($task['priority']); ?>
                                    </span>
                                    
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <div style="text-align: center; margin-top: 15px;" >
                                <a href="add_task.php" class="button">View All Tasks</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Task Form Modal -->
    <div id="task-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Task</h3>
                <span class="close-modal">&times;</span>
            </div>
            <form id="task-form" method="POST" data-mode="add">
                <input type="hidden" name="task_id" id="task-id">
                <div class="form-group">
                    <label for="task-title">Task Title</label>
                    <input type="text" id="task-title" name="title" required>
                </div>
                <div class="form-group">
                    <label for="task-description">Description</label>
                    <textarea id="task-description" name="description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="task-date">Due Date</label>
                    <input type="date" id="task-date" name="due_date" required>
                </div>
                <div class="form-group">
                    <label for="task-time">Time (optional)</label>
                    <input type="time" id="task-time" name="time">
                </div>
                <div class="form-group">
                    <label for="task-priority">Priority</label>
                    <select id="task-priority" name="priority">
                        <option value="high">High</option>
                        <option value="medium" selected>Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="task-progress">Progress (%)</label>
                    <input type="number" id="task-progress" name="progress" min="0" max="100" value="0">
                </div>
                <div class="form-group checkbox-group">
                    <input type="checkbox" id="task-reminder" name="reminder">
                    <label for="task-reminder">Set Reminder</label>
                </div>
                <button type="submit" class="button" style="width: 100%;">Save Task</button>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle checkbox toggling with AJAX
            document.querySelectorAll('.task-checkbox').forEach(checkbox => {
                checkbox.addEventListener('click', function() {
                    const taskId = this.getAttribute('data-task-id');
                    const isCompleted = !this.classList.contains('checked');
                    const taskItem = this.closest('.task-item');
                    const progressFill = taskItem.querySelector('.progress-fill');
                    
                    // Update UI immediately
                    this.classList.toggle('checked');
                    taskItem.classList.toggle('completed');
                    
                    if (isCompleted) {
                        progressFill.dataset.originalWidth = progressFill.style.width;
                        progressFill.style.width = '100%';
                        progressFill.style.backgroundColor = '#ccc';
                    } else {
                        progressFill.style.width = progressFill.dataset.originalWidth;
                        const priority = taskItem.querySelector('.tag').className.split(' ')[1];
                        progressFill.style.backgroundColor = 
                            priority === 'high' ? 'var(--danger)' :
                            priority === 'medium' ? 'var(--warning)' :
                            'var(--success)';
                    }
                    
                    // Send AJAX request
                    fetch('update_task.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `task_id=${taskId}&completed=${isCompleted ? 1 : 0}`
                    })
                    .then(response => {
                        if (!response.ok) throw new Error('Network error');
                        return response.json();
                    })
                    .then(data => {
                        if (!data.success) {
                            // Revert UI changes if failed
                            this.classList.toggle('checked');
                            taskItem.classList.toggle('completed');
                            if (isCompleted) {
                                progressFill.style.width = progressFill.dataset.originalWidth;
                            } else {
                                progressFill.style.width = '100%';
                            }
                            alert('Failed to update task status');
                        } else {
                            location.reload();
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        this.classList.toggle('checked');
                        taskItem.classList.toggle('completed');
                        if (isCompleted) {
                            progressFill.style.width = progressFill.dataset.originalWidth;
                        } else {
                            progressFill.style.width = '100%';
                        }
                        alert('Error updating task status');
                    });
                });
            });
            
            // Add task buttons
            const addTaskButtons = [document.getElementById('add-task-btn')];
            const emptyAddBtn = document.getElementById('empty-add-btn');
            if (emptyAddBtn) addTaskButtons.push(emptyAddBtn);
            
            addTaskButtons.forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('task-form').reset();
                    document.getElementById('task-form').setAttribute('data-mode', 'add');
                    document.getElementById('task-form').removeAttribute('data-task-id');
                    document.getElementById('task-date').valueAsDate = new Date();
                    document.getElementById('task-modal').style.display = 'flex';
                });
            });
            
            // Close modal
            document.querySelector('.close-modal').addEventListener('click', function() {
                document.getElementById('task-modal').style.display = 'none';
            });
            
            // Close modal when clicking outside
            document.getElementById('task-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
            
            // Edit task buttons
            document.querySelectorAll('.edit-task').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const taskId = this.getAttribute('data-task-id');
                    
                    fetch('get_task.php?task_id=' + taskId)
                        .then(response => response.json())
                        .then(task => {
                            if (task && task.success) {
                                document.getElementById('task-id').value = task.id;
                                document.getElementById('task-title').value = task.title;
                                document.getElementById('task-description').value = task.description || '';
                                document.getElementById('task-date').value = task.due_date;
                                document.getElementById('task-time').value = task.time || '';
                                document.getElementById('task-priority').value = task.priority;
                                document.getElementById('task-progress').value = task.progress;
                                
                                document.querySelector('.modal-header h3').textContent = 'Edit Task';
                                document.getElementById('task-form').setAttribute('data-mode', 'edit');
                                
                                document.getElementById('task-modal').style.display = 'flex';
                            } else {
                                alert(task?.message || 'Failed to load task data');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error loading task data');
                        });
                });
            });
            
            // Delete task buttons
            document.querySelectorAll('.delete-task').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const taskId = this.getAttribute('data-task-id');
                    const taskItem = this.closest('.task-item');
                    
                    if (confirm('Are you sure you want to delete this task?')) {
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                        this.style.pointerEvents = 'none';
                        
                        fetch('delete_task.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `task_id=${taskId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                taskItem.remove();
                                location.reload();
                            } else {
                                alert(data.message || 'Failed to delete task');
                                this.innerHTML = '<i class="fas fa-trash"></i>';
                                this.style.pointerEvents = 'auto';
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error deleting task');
                            this.innerHTML = '<i class="fas fa-trash"></i>';
                            this.style.pointerEvents = 'auto';
                        });
                    }
                });
            });
            
            // Form submission
            document.getElementById('task-form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                
                const formData = new FormData(this);
                const mode = this.getAttribute('data-mode');
                
                fetch('process_task.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Save Task';
                    
                    if (data.success) {
                        document.getElementById('task-modal').style.display = 'none';
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to save task');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Save Task';
                    alert('Error saving task');
                });
            });
        });
    </script>
</body>
</html>
