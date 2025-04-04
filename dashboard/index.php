<?php
// Start session for user authentication
session_start();

// Mock user data - in a real application, you would retrieve this from a database
$user = [
    'name' => 'Alex',
    'initial' => 'A',
    'tasks' => [
        [
            'id' => 1,
            'title' => 'Complete dashboard design',
            'date' => 'April 1, 2025',
            'time' => '10:00 AM',
            'priority' => 'high',
            'completed' => true,
            'progress' => 100
        ],
        [
            'id' => 2,
            'title' => 'Finalize project proposal',
            'date' => 'April 2, 2025',
            'time' => '2:00 PM',
            'priority' => 'medium',
            'completed' => false,
            'progress' => 75
        ],
        [
            'id' => 3,
            'title' => 'Schedule team meeting',
            'date' => 'April 3, 2025',
            'time' => '9:30 AM',
            'priority' => 'low',
            'completed' => false,
            'progress' => 0
        ],
        [
            'id' => 4,
            'title' => 'Review client feedback',
            'date' => 'April 4, 2025',
            'time' => '11:00 AM',
            'priority' => 'high',
            'completed' => false,
            'progress' => 30
        ]
    ]
];

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
    $taskDate = strtotime($task['date']);
    $tomorrow = strtotime('tomorrow');
    if ($taskDate >= $tomorrow && !$task['completed']) {
        $upcomingTasks++;
    }
}

// Handle form submission for task operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process form submission (implementation would go here)
    // This would handle adding, editing, or deleting tasks
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
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
            <h1><i class="fas fa-check-circle"></i> TaskMaster</h1>
            <div class="user-profile">
                <span>Welcome, <?php echo htmlspecialchars($user['name']); ?></span>
                <div class="user-avatar"><?php echo htmlspecialchars($user['initial']); ?></div>
            </div>
        </header>
        
        <div class="dashboard">
            <div class="sidebar">
                <ul class="main-menu">
                    <li><a href="#" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="#"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li><a href="#"><i class="fas fa-project-diagram"></i> Projects</a></li>
                    <li><a href="#"><i class="fas fa-calendar"></i> Calendar</a></li>
                    <li><a href="#"><i class="fas fa-chart-line"></i> Reports</a></li>
                    <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
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
                                        <span><i class="far fa-calendar"></i> <?php echo htmlspecialchars($task['date']); ?></span>
                                        <span><i class="far fa-clock"></i> <?php echo htmlspecialchars($task['time']); ?></span>
                                    </div>
                                    <?php if ($task['progress'] > 0 && $task['progress'] < 100): ?>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $task['progress']; ?>%; background-color: var(--<?php echo $task['priority']; ?>);"></div>
                                    </div>
                                    <?php endif; ?>
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
    
    <!-- Task form modal would go here in a real application -->
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle checkbox toggling with AJAX
            const checkboxes = document.querySelectorAll('.task-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('click', function() {
                    const taskId = this.getAttribute('data-task-id');
                    this.classList.toggle('checked');
                    
                    // In a real app, you would send an AJAX request to update the task completion status
                    // For example:
                    /*
                    fetch('update_task.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'task_id=' + taskId + '&completed=' + (this.classList.contains('checked') ? 1 : 0)
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Handle response
                    });
                    */
                    
                    // For demo, just update the count
                    updateStats();
                });
            });
            
            function updateStats() {
                const totalTasks = document.querySelectorAll('.task-item').length;
                const completedTasks = document.querySelectorAll('.task-checkbox.checked').length;
                
                document.querySelectorAll('.stat-card h3')[0].textContent = totalTasks;
                document.querySelectorAll('.stat-card h3')[1].textContent = completedTasks;
            }
            
            // Add task button functionality
            document.getElementById('add-task-btn').addEventListener('click', function() {
                // In a real app, show a modal form to add a task
                alert('Add task functionality would go here in a real app!');
                // Then submit the form via AJAX or a normal form submission
            });
            
            // Edit task buttons
            document.querySelectorAll('.edit-task').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const taskId = this.getAttribute('data-task-id');
                    const taskTitle = this.closest('.task-item').querySelector('h4').textContent;
                    
                    // In a real app, show a modal form to edit the task
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
                        // In a real app, send an AJAX request to delete the task
                        /*
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
                            }
                        });
                        */
                        
                        // For demo, just remove the task
                        taskItem.remove();
                        updateStats();
                    }
                });
            });
        });
    </script>
</body>
</html>