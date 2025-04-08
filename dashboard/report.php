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
$tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$totalTasks = count($tasks);
$completedTasks = 0;
$highPriorityTasks = 0;
$mediumPriorityTasks = 0;
$lowPriorityTasks = 0;

// Group tasks by status and priority
$tasksByStatus = ['completed' => 0, 'pending' => 0];
$tasksByPriority = ['high' => 0, 'medium' => 0, 'low' => 0];
$completionByPriority = ['high' => 0, 'medium' => 0, 'low' => 0];
$progressData = [];

foreach ($tasks as $task) {
    if ($task['completed']) {
        $completedTasks++;
        $tasksByStatus['completed']++;
    } else {
        $tasksByStatus['pending']++;
    }
    
    $tasksByPriority[$task['priority']]++;
    
    if ($task['completed']) {
        $completionByPriority[$task['priority']]++;
    }
    
    $progressData[] = $task['progress'];
}

// Calculate completion rates
$completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
$highPriorityCompletion = $tasksByPriority['high'] > 0 ? round(($completionByPriority['high'] / $tasksByPriority['high']) * 100) : 0;
$mediumPriorityCompletion = $tasksByPriority['medium'] > 0 ? round(($completionByPriority['medium'] / $tasksByPriority['medium']) * 100) : 0;
$lowPriorityCompletion = $tasksByPriority['low'] > 0 ? round(($completionByPriority['low'] / $tasksByPriority['low']) * 100) : 0;

// Calculate average progress
$averageProgress = $totalTasks > 0 ? round(array_sum($progressData) / $totalTasks) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager / Reports</title>
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
        
        .report-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .stat-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 20px;
        }
        
        .stat-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 24px;
        }
        
        .stat-card h3 {
            font-size: 32px;
            margin: 5px 0;
        }
        
        .stat-card p {
            color: var(--gray);
            font-size: 16px;
        }
        
        .progress-ring {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 20px auto;
        }
        
        .progress-ring-circle {
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        
        .progress-ring-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 24px;
            font-weight: bold;
        }
        
        .task-distribution {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        
        .distribution-item {
            text-align: center;
            flex: 1;
        }
        
        .distribution-bar {
            height: 150px;
            width: 30px;
            background-color: #e9ecef;
            margin: 0 auto 10px;
            position: relative;
            border-radius: 5px;
        }
        
        .distribution-fill {
            position: absolute;
            bottom: 0;
            width: 100%;
            border-radius: 5px;
            transition: var(--transition);
        }
        
        .priority-label {
            font-weight: 500;
            color: var(--gray);
        }
        
        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .report-grid {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                height: auto;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <li><a href="add_task.php"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li><a href="calendar.php"><i class="fas fa-calendar"></i> Calendar</a></li>
                    <li><a href="report.php" class="active"><i class="fas fa-chart-line"></i> Reports</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="activity_log.php"><i class="fas fa-history"></i> Activity Log</a></li>
                    <li><a href="http://localhost/task-manager/"><i class="fas fa-lock"></i> Logout</a></li>
                </ul>
            </div>
            
            <div class="content">
                <div class="card">
                    <div class="section-header">
                        <h2><i class="fas fa-chart-pie"></i> Task Statistics</h2>
                    </div>
                    
                    <div class="report-grid">
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
                            <p>Completed Tasks</p>
                        </div>
                        
                        <div class="card stat-card">
                            <div class="progress-ring">
                                <svg width="120" height="120" viewBox="0 0 120 120">
                                    <circle cx="60" cy="60" r="50" stroke="#e9ecef" stroke-width="10" fill="none" />
                                    <circle class="progress-ring-circle" cx="60" cy="60" r="50" stroke="#4361ee" stroke-width="10" 
                                        stroke-dasharray="314" stroke-dashoffset="<?php echo 314 - (314 * $completionRate / 100); ?>" 
                                        fill="none" />
                                </svg>
                                <div class="progress-ring-text"><?php echo $completionRate; ?>%</div>
                            </div>
                            <p>Overall Completion Rate</p>
                        </div>
                        
                        <div class="card stat-card">
                            <div class="progress-ring">
                                <svg width="120" height="120" viewBox="0 0 120 120">
                                    <circle cx="60" cy="60" r="50" stroke="#e9ecef" stroke-width="10" fill="none" />
                                    <circle class="progress-ring-circle" cx="60" cy="60" r="50" stroke="#4cc9f0" stroke-width="10" 
                                        stroke-dasharray="314" stroke-dashoffset="<?php echo 314 - (314 * $averageProgress / 100); ?>" 
                                        fill="none" />
                                </svg>
                                <div class="progress-ring-text"><?php echo $averageProgress; ?>%</div>
                            </div>
                            <p>Average Progress</p>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="section-header">
                        <h2><i class="fas fa-chart-bar"></i> Task Distribution</h2>
                    </div>
                    
                    <div class="task-distribution">
                        <div class="distribution-item">
                            <div class="distribution-bar">
                                <div class="distribution-fill" style="height: <?php echo $tasksByPriority['high'] > 0 ? ($tasksByPriority['high'] / max($tasksByPriority) * 100) : 0; ?>%; background-color: var(--danger);"></div>
                            </div>
                            <div class="priority-label">High Priority</div>
                            <div><?php echo $tasksByPriority['high']; ?> tasks</div>
                            <div><?php echo $highPriorityCompletion; ?>% completed</div>
                        </div>
                        
                        <div class="distribution-item">
                            <div class="distribution-bar">
                                <div class="distribution-fill" style="height: <?php echo $tasksByPriority['medium'] > 0 ? ($tasksByPriority['medium'] / max($tasksByPriority) * 100) : 0; ?>%; background-color: var(--warning);"></div>
                            </div>
                            <div class="priority-label">Medium Priority</div>
                            <div><?php echo $tasksByPriority['medium']; ?> tasks</div>
                            <div><?php echo $mediumPriorityCompletion; ?>% completed</div>
                        </div>
                        
                        <div class="distribution-item">
                            <div class="distribution-bar">
                                <div class="distribution-fill" style="height: <?php echo $tasksByPriority['low'] > 0 ? ($tasksByPriority['low'] / max($tasksByPriority) * 100) : 0; ?>%; background-color: var(--success);"></div>
                            </div>
                            <div class="priority-label">Low Priority</div>
                            <div><?php echo $tasksByPriority['low']; ?> tasks</div>
                            <div><?php echo $lowPriorityCompletion; ?>% completed</div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="section-header">
                        <h2><i class="fas fa-chart-line"></i> Task Status Overview</h2>
                    </div>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                
                <div class="card">
                    <div class="section-header">
                        <h2><i class="fas fa-chart-pie"></i> Priority Distribution</h2>
                    </div>
                    <div class="chart-container">
                        <canvas id="priorityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Task Status Chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            const statusChart = new Chart(statusCtx, {
                type: 'bar',
                data: {
                    labels: ['Completed', 'Pending'],
                    datasets: [{
                        label: 'Tasks by Status',
                        data: [<?php echo $tasksByStatus['completed']; ?>, <?php echo $tasksByStatus['pending']; ?>],
                        backgroundColor: [
                            'rgba(76, 201, 240, 0.7)',
                            'rgba(247, 37, 133, 0.7)'
                        ],
                        borderColor: [
                            'rgba(76, 201, 240, 1)',
                            'rgba(247, 37, 133, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
            
            // Priority Distribution Chart
            const priorityCtx = document.getElementById('priorityChart').getContext('2d');
            const priorityChart = new Chart(priorityCtx, {
                type: 'pie',
                data: {
                    labels: ['High Priority', 'Medium Priority', 'Low Priority'],
                    datasets: [{
                        data: [<?php echo $tasksByPriority['high']; ?>, <?php echo $tasksByPriority['medium']; ?>, <?php echo $tasksByPriority['low']; ?>],
                        backgroundColor: [
                            'rgba(247, 37, 133, 0.7)',
                            'rgba(248, 150, 30, 0.7)',
                            'rgba(76, 201, 240, 0.7)'
                        ],
                        borderColor: [
                            'rgba(247, 37, 133, 1)',
                            'rgba(248, 150, 30, 1)',
                            'rgba(76, 201, 240, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
