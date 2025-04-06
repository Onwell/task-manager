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
$mediumPriorityTasks = 0;
$lowPriorityTasks = 0;

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
            /* Removed overflow: hidden */
        }
        
        .container {
            width: 100%;
            min-height: 100vh; /* Changed from height to min-height */
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
            min-height: calc(100% - 70px); /* Changed from height to min-height */
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
            height: auto; /* Changed from height: 100% */
            overflow-y: auto;
            padding-right: 10px;
            -webkit-overflow-scrolling: touch; /* For smooth scrolling on iOS */
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
        
        .task-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            flex-grow: 1;
            overflow-y: auto;
            padding-bottom: 20px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .button {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
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
            padding: 15px;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
        }
        
        .task-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }
        
        .task-left {
            display: flex;
            align-items: center;
            gap: 15px;
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
        
        .task-content h4 {
            margin-bottom: 5px;
        }
        
        .task-meta {
            display: flex;
            gap: 10px;
            font-size: 12px;
            color: var(--gray);
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
            height: 10px;
            background-color: #e9ecef;
            border-radius: 5px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            transition: var(--transition);
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
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.2);
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
                overflow-y: visible; /* Allow natural scrolling */
            }
        }
        
        @media (max-width: 480px) {
            .overview {
                grid-template-columns: 1fr;
            }
            
            .task-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .action-buttons {
                align-self: flex-end;
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
                            <i class="fas fa-tasks"></i>
                        </div>
                        <h3><?php echo $mediumPriorityTasks; ?></h3>
                        <p>Medium Priority</p>
                    </div>
                    
                    <div class="card stat-card">
                        <div class="icon" style="background-color: rgba(76, 201, 240, 0.1); color: var(--success);">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <h3><?php echo $lowPriorityTasks; ?></h3>
                        <p>Low Priority</p>
                    </div>
                </div>
                
                <div class="card" style="flex-grow: 1;">
                    <div class="section-header">
                        <h2>My Tasks</h2>
                        <button class="button" id="add-task-btn"><i class="fas fa-plus"></i> Add Task</button>
                    </div>
                    
                    <div class="task-list">
                        <?php foreach ($user['tasks'] as $task): ?>
                        <div class="task-item" data-task-id="<?php echo $task['id']; ?>">
                            <div class="task-left">
                                <div class="task-checkbox <?php echo $task['completed'] ? 'checked' : ''; ?>" data-task-id="<?php echo $task['id']; ?>"></div>
                                <div class="task-content">
                                    <h4><?php echo htmlspecialchars($task['title']); ?></h4>
                                    <div class="task-meta">
                                        <span><i class="far fa-calendar"></i> <?php echo htmlspecialchars(date('F j, Y', strtotime($task['due_date']))); ?></span>
                                        <?php if (!empty($task['time'])): ?>
                                        <span><i class="far fa-clock"></i> <?php echo htmlspecialchars(date('h:i A', strtotime($task['time']))); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $task['progress']; ?>%; background-color: 
                                            <?php 
                                                if ($task['priority'] === 'high') echo 'var(--danger)';
                                                elseif ($task['priority'] === 'medium') echo 'var(--warning)';
                                                else echo 'var(--success)';
                                            ?>;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="task-right">
                                <span class="tag <?php echo $task['priority']; ?>"><?php echo ucfirst($task['priority']); ?></span>
                                <div class="action-buttons">
                                    <div class="action-button edit-task" style="background-color: var(--primary);" data-task-id="<?php echo $task['id']; ?>">
                                        <i class="fas fa-pen"></i>
                                    </div>
                                    <div class="action-button delete-task" style="background-color: var(--danger);" data-task-id="<?php echo $task['id']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
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
            <form id="task-form" method="POST">
                <div class="form-group">
                    <label for="task-title">Task Title</label>
                    <input type="text" id="task-title" name="title" required>
                </div>
                <div class="form-group">
                    <label for="task-date">Date</label>
                    <input type="date" id="task-date" name="due_date" required>
                </div>
                <div class="form-group">
                    <label for="task-time">Time</label>
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
                <button type="submit" class="button" style="width: 100%;">Save Task</button>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle checkbox toggling with AJAX
            const checkboxes = document.querySelectorAll('.task-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('click', function() {
                    const taskId = this.getAttribute('data-task-id');
                    const isCompleted = !this.classList.contains('checked');
                    this.classList.toggle('checked');
                    
                    // Send AJAX request to update task status
                    fetch('update_task.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'task_id=' + taskId + '&completed=' + (isCompleted ? 1 : 0)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            this.classList.toggle('checked'); // Revert if failed
                        } else {
                            updateStats();
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        this.classList.toggle('checked'); // Revert if error
                    });
                });
            });
            
            function updateStats() {
                // Refresh the page to get updated stats
                location.reload();
            }
            
            // Add task button functionality
            document.getElementById('add-task-btn').addEventListener('click', function() {
                document.getElementById('task-modal').style.display = 'flex';
                // Set today's date as default
                document.getElementById('task-date').valueAsDate = new Date();
            });
            
            // Close modal when clicking X
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
                    const taskItem = this.closest('.task-item');
                    const taskTitle = taskItem.querySelector('h4').textContent;
                    
                    // In a real app, show a modal form to edit the task
                    // For now, we'll just show an alert
                    alert('Edit task: ' + taskTitle);
                });
            });
            
            // Delete task buttons
            document.querySelectorAll('.delete-task').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const taskId = this.getAttribute('data-task-id');
                    const taskItem = this.closest('.task-item');
                    
                    if (confirm('Are you sure you want to delete this task?')) {
                        // Send AJAX request to delete the task
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
                                updateStats();
                            } else {
                                alert('Failed to delete task');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error deleting task');
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>
