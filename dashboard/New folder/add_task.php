<?php
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
$userId = $_SESSION['user_id'] ?? 1;

// Fetch user data
$userStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

$user['initial'] = strtoupper(substr($user['name'], 0, 1));

// Handle search query and date filter
$searchQuery = $_GET['search'] ?? '';
$dateFilter = $_GET['date_filter'] ?? 'all'; // all, today, week, month, overdue
$params = [':user_id' => $userId];

// Build the task query
$taskQuery = "
    SELECT 
        t.*,
        u.name AS owner_name,
        CASE 
            WHEN t.user_id = :user_id THEN 'owned'
            ELSE 'shared'
        END AS task_type,
        st.can_edit AS can_edit,
        st.shared_at AS shared_at
    FROM tasks t
    LEFT JOIN shared_tasks st ON t.id = st.task_id AND st.shared_with_id = :user_id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE (t.user_id = :user_id OR st.shared_with_id = :user_id)
";

if (!empty($searchQuery)) {
    $taskQuery .= " AND (t.title LIKE :search OR t.description LIKE :search)";
    $params[':search'] = "%$searchQuery%";
}

// Add date filtering
switch ($dateFilter) {
    case 'today':
        $taskQuery .= " AND t.due_date = CURDATE()";
        break;
    case 'week':
        $taskQuery .= " AND t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $taskQuery .= " AND t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 MONTH)";
        break;
    case 'overdue':
        $taskQuery .= " AND t.due_date < CURDATE() AND t.completed = 0";
        break;
    // 'all' shows all tasks
}

$taskQuery .= "
    ORDER BY 
        CASE 
            WHEN t.due_date < CURDATE() AND t.completed = 0 THEN 0
            WHEN t.due_date = CURDATE() AND t.completed = 0 THEN 1
            ELSE 2
        END,
        t.due_date ASC,
        t.priority DESC
";

$taskStmt = $pdo->prepare($taskQuery);
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

// Count shared tasks (tasks shared with the current user)
$sharedTasksStmt = $pdo->prepare("
    SELECT COUNT(*) as shared_count 
    FROM shared_tasks 
    WHERE shared_with_id = :user_id
");
$sharedTasksStmt->execute([':user_id' => $userId]);
$sharedTasks = $sharedTasksStmt->fetch(PDO::FETCH_ASSOC)['shared_count'];


// Count total users
$userCountStmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
$totalUsers = $userCountStmt->fetch(PDO::FETCH_ASSOC)['total_users'];


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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager / Add Task</title>
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

        .form-group.checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group.checkbox-group label {
            margin-bottom: 0;
            font-weight: normal;
            cursor: pointer;
        }

        .form-group.checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
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
            color: white;
            border: none;
            height: 40px;
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
        
        .tag.shared {
            background-color: var(--secondary);
            margin-left: 8px;
            font-size: 10px;
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
        
        .action-button.disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
        
        .task-owner {
            font-size: 12px;
            color: var(--gray);
            margin-top: 3px;
            font-style: italic;
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
        
        /* Filter styles */
        .filter-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .filter-select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            background-color: white;
            font-size: 14px;
            cursor: pointer;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        /* Add this to your existing CSS */
        .stat-card .icon.purple {
            background-color: rgba(111, 66, 193, 0.1);
            color: #6f42c1;
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
                    <li><a href="http://localhost/task-manager/dashboard/"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="add_task.php" class="active"><i class="fas fa-tasks"></i> Tasks</a></li>
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
                        <div class="icon purple">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?php echo $totalUsers; ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                
                <div class="card" style="flex-grow: 1;">
                    <div class="section-header">
                        <h2>My Tasks</h2>
                        <div style="display: flex; gap: 10px;">
                            <form method="GET" action="" class="search-bar">
                                <input type="text" name="search" class="search-input" placeholder="Search tasks..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                                <button type="submit" class="search-button"><i class="fas fa-search"></i></button>
                            </form>
                            <button class="button" id="add-task-btn"><i class="fas fa-plus"></i> Add Task</button>
                        </div>
                    </div>
                    
                    <!-- Date Filter Dropdown -->
                    <div class="filter-container">
                        <form method="GET" action="" style="display: flex; gap: 10px;">
                            <?php if (!empty($searchQuery)): ?>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>">
                            <?php endif; ?>
                            <select name="date_filter" class="filter-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>All Dates</option>
                                <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="month" <?php echo $dateFilter === 'month' ? 'selected' : ''; ?>>This Month</option>
                                <option value="overdue" <?php echo $dateFilter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                            </select>
                        </form>
                    </div>
                    
                    <div class="task-list">
                        <?php if (empty($user['tasks'])): ?>
                            <div class="empty-state">
                                <i class="far fa-check-circle"></i>
                                <h3>No tasks found</h3>
                                <p><?php echo (empty($searchQuery) && $dateFilter === 'all' ? 'Get started by adding your first task' : 'No tasks match your criteria'); ?></p>
                                <button class="button" id="empty-add-btn">Add Task</button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($user['tasks'] as $task): 
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
                                
                                $isShared = ($task['task_type'] === 'shared');
                                $canEditTask = (!$isShared || ($isShared && $task['can_edit']));
                            ?>
                            <div class="task-item <?php echo $task['completed'] ? 'completed' : $dueStatus; ?>" data-task-id="<?php echo $task['id']; ?>">
                                <div class="task-left">
                                    <div class="task-checkbox <?php echo $task['completed'] ? 'checked' : ''; ?>"
                                         data-task-id="<?php echo $task['id']; ?>"
                                         <?php if (!$canEditTask) echo 'style="opacity:0.5;cursor:not-allowed" title="No edit permission"'; ?>>
                                    </div>
                                    
                                    <div class="task-content">
                                        <h4>
                                            <?php echo htmlspecialchars($task['title']); ?>
                                            <?php if ($isShared): ?>
                                                <span class="tag shared" title="Shared by <?php echo htmlspecialchars($task['owner_name']); ?>">
                                                    Shared
                                                </span>
                                            <?php endif; ?>
                                        </h4>
                                        
                                        <?php if ($isShared): ?>
                                            <div class="task-owner">Shared by: <?php echo htmlspecialchars($task['owner_name']); ?></div>
                                        <?php endif; ?>
                                        
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
                                    
                                    <div class="action-buttons">
                                        <?php if ($canEditTask): ?>
                                            <div class="action-button edit-task" 
                                                 style="background-color: var(--primary);" 
                                                 data-task-id="<?php echo $task['id']; ?>">
                                                <i class="fas fa-pen"></i>
                                            </div>
                                            
                                            <div class="action-button delete-task" 
                                                 style="background-color: var(--danger);" 
                                                 data-task-id="<?php echo $task['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="action-button disabled" title="No edit permission">
                                                <i class="fas fa-pen"></i>
                                            </div>
                                            <div class="action-button disabled" title="No delete permission">
                                                <i class="fas fa-trash"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!$isShared): ?>
                                            <div class="action-button share-task" 
                                                 style="background-color: var(--secondary);" 
                                                 data-task-id="<?php echo $task['id']; ?>">
                                                <i class="fas fa-share-alt"></i>
                                            </div>
                                        <?php endif; ?>
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
    
    <!-- Task Form Modal -->
    <div id="task-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Task</h3>
                <span class="close-modal">&times;</span>
            </div>
            <form id="task-form" method="POST" data-mode="add">
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
                    if (this.style.opacity === '0.5' && this.style.cursor === 'not-allowed') {
                        return; // Skip if no edit permission
                    }
                    
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
                        const priority = taskItem.querySelector('.tag:not(.shared)').className.split(' ')[1];
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
                    document.querySelector('.modal-header h3').textContent = 'Add New Task';
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
                            if (task) {
                                document.getElementById('task-title').value = task.title;
                                document.getElementById('task-description').value = task.description || '';
                                document.getElementById('task-date').value = task.due_date;
                                document.getElementById('task-time').value = task.time || '';
                                document.getElementById('task-priority').value = task.priority;
                                document.getElementById('task-progress').value = task.progress;
                                
                                document.querySelector('.modal-header h3').textContent = 'Edit Task';
                                document.getElementById('task-form').setAttribute('data-mode', 'edit');
                                document.getElementById('task-form').setAttribute('data-task-id', taskId);
                                
                                document.getElementById('task-modal').style.display = 'flex';
                            } else {
                                alert('Failed to load task data');
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
                            body: 'task_id=' + taskId
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                taskItem.remove();
                                location.reload();
                            } else {
                                alert('Failed to delete task');
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
            
            // Share task buttons
            document.querySelectorAll('.share-task').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const taskId = this.getAttribute('data-task-id');
                    
                    // Create a share modal
                    const shareModal = document.createElement('div');
                    shareModal.className = 'modal';
                    shareModal.id = 'share-modal';
                    shareModal.innerHTML = `
                        <div class="modal-content" style="max-width: 400px;">
                            <div class="modal-header">
                                <h3>Share Task</h3>
                                <span class="close-share-modal">&times;</span>
                            </div>
                            <div class="form-group">
                                <label for="share-email">Email Address</label>
                                <input type="email" id="share-email" placeholder="Enter user's email" required>
                            </div>
                            <div class="form-group checkbox-group">
                                <input type="checkbox" id="can-edit" name="can_edit">
                                <label for="can-edit">Allow editing</label>
                            </div>
                            <button id="confirm-share" class="button" style="width: 100%;">Share Task</button>
                        </div>
                    `;
                    document.body.appendChild(shareModal);
                    shareModal.style.display = 'flex';

                    // Close modal
                    document.querySelector('.close-share-modal').addEventListener('click', function() {
                        shareModal.remove();
                    });

                    // Close when clicking outside
                    shareModal.addEventListener('click', function(e) {
                        if (e.target === this) {
                            this.remove();
                        }
                    });

                    // Handle share confirmation
                    document.getElementById('confirm-share').addEventListener('click', function() {
                        const email = document.getElementById('share-email').value;
                        const canEdit = document.getElementById('can-edit').checked;
                        
                        if (!email) {
                            alert('Please enter an email address');
                            return;
                        }

                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sharing...';

                        fetch('share_task.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `task_id=${taskId}&email=${encodeURIComponent(email)}&can_edit=${canEdit ? 1 : 0}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Task shared successfully!');
                                shareModal.remove();
                            } else {
                                alert('Failed to share task: ' + (data.message || 'Unknown error'));
                                this.disabled = false;
                                this.innerHTML = 'Share Task';
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error sharing task');
                            this.disabled = false;
                            this.innerHTML = 'Share Task';
                        });
                    });
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
                
                if (mode === 'edit') {
                    const taskId = this.getAttribute('data-task-id');
                    formData.append('task_id', taskId);
                    formData.append('action', 'edit');
                } else {
                    formData.append('action', 'add');
                }
                
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
                        alert('Failed to save task: ' + (data.message || 'Unknown error'));
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
