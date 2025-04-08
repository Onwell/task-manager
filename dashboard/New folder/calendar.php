<?php
session_start();

// 1. Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'task_manager_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// 2. Database Connection
try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// 3. User Authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];

// 4. Get User Data
$stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found");
}

// 5. Get User's Tasks (Owned + Shared)
$stmt = $pdo->prepare("
    SELECT 
        t.*,
        u.name AS owner_name,
        CASE 
            WHEN t.user_id = :user_id THEN 'owner'
            ELSE 'shared'
        END AS task_ownership,
        st.can_edit AS shared_permission
    FROM tasks t
    LEFT JOIN shared_tasks st ON t.id = st.task_id AND st.shared_with_id = :user_id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE t.user_id = :user_id OR st.shared_with_id = :user_id
    ORDER BY t.due_date ASC
");
$stmt->execute([':user_id' => $userId]);
$tasks = $stmt->fetchAll();

// 6. Format Tasks for FullCalendar
$calendarEvents = [];
foreach ($tasks as $task) {
    $start = (new DateTime($task['due_date']))->format('Y-m-d');
    if (!empty($task['time'])) {
        $time = new DateTime($task['time']);
        $start .= 'T' . $time->format('H:i:s');
    }

    $calendarEvents[] = [
        'id' => $task['id'],
        'title' => $task['title'],
        'start' => $start,
        'allDay' => empty($task['time']),
        'color' => $task['priority'] === 'high' ? '#f72585' : 
                  ($task['priority'] === 'medium' ? '#f8961e' : '#4cc9f0'),
        'extendedProps' => [
            'description' => $task['description'],
            'priority' => $task['priority'],
            'completed' => (bool)$task['completed'],
            'progress' => $task['progress'],
            'ownership' => $task['task_ownership'],
            'permission' => $task['shared_permission'],
            'owner_name' => $task['owner_name']
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Calendar | Task Manager</title>
    
    <!-- CSS -->
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
            --border-radius: 8px;
            --box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            --transition: all 0.2s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fb;
            color: var(--dark);
            line-height: 1.6;
        }

        .container {
            width: 100%;
            min-height: 100vh;
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
            flex-grow: 1;
        }

        .sidebar {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
        }

        .main-menu {
            list-style: none;
        }

        .main-menu li {
            margin-bottom: 10px;
        }

        .main-menu a {
            display: flex;
            align-items: center;
            padding: 10px;
            color: var(--gray);
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .main-menu a:hover, .main-menu a.active {
            background: var(--primary);
            color: white;
        }

        .main-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .card {
            background: white;
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

        .button {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .button:hover {
            background: var(--secondary);
        }

        .button i {
            font-size: 14px;
        }

        #calendar {
            width: 100%;
        }

        /* Modal Styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 500px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .close-modal {
            font-size: 24px;
            cursor: pointer;
            color: var(--gray);
            transition: var(--transition);
        }

        .close-modal:hover {
            color: var(--dark);
        }

        /* Task Detail Modal */
        .task-detail-content {
            margin-bottom: 20px;
        }

        .task-detail-content p {
            margin-bottom: 10px;
        }

        .task-detail-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .task-detail-actions .button {
            flex: 1;
            min-width: 120px;
            justify-content: center;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .task-detail-actions .button {
                min-width: 100%;
            }
        }
    </style>
    
    <!-- External CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-calendar-alt"></i> Task Calendar</h1>
            <div class="user-profile">
                <span><?= htmlspecialchars($user['name']) ?></span>
                <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            </div>
        </header>
        
        <div class="dashboard">
            <div class="sidebar">
                <ul class="main-menu">
                    <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="add_task.php"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li><a href="calendar.php" class="active"><i class="fas fa-calendar"></i> Calendar</a></li>
                    <li><a href="report.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="activity_log.php"><i class="fas fa-history"></i> Activity Log</a></li>
                    <li><a href="http://localhost/task-manager/"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
            
            <div class="content">
                <div class="card">
                    <div class="section-header">
                        <h2>My Tasks</h2>
                    </div>
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
        
        <!-- Add/Edit Task Modal -->
        <div id="task-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modal-title">Add New Task</h3>
                    <span class="close-modal">&times;</span>
                </div>
                <form id="task-form">
                    <input type="hidden" name="task_id" id="task-id">
                    <input type="hidden" name="action" id="form-action" value="add">
                    
                    <div class="form-group">
                        <label for="task-title">Title</label>
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
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="button" style="width: 100%;">
                        <i class="fas fa-save"></i> Save Task
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Task Detail Modal -->
        <div id="task-detail-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="detail-task-title">Task Details</h3>
                    <span class="close-modal">&times;</span>
                </div>
                
                <div class="task-detail-content">
                    <p><strong>Description:</strong> <span id="detail-task-description"></span></p>
                    <p><strong>Due Date:</strong> <span id="detail-task-date"></span></p>
                    <p><strong>Time:</strong> <span id="detail-task-time"></span></p>
                    <p><strong>Priority:</strong> <span id="detail-task-priority"></span></p>
                    <p><strong>Status:</strong> <span id="detail-task-status"></span></p>
                    <p><strong>Owner:</strong> <span id="detail-task-owner"></span></p>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    
    <!-- Main JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Calendar
            const calendarEl = document.getElementById('calendar');
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: <?= json_encode($calendarEvents) ?>,
                eventClick: function(info) {
                    const event = info.event;
                    const modal = document.getElementById('task-detail-modal');
                    
                    // Populate modal
                    document.getElementById('detail-task-title').textContent = event.title;
                    document.getElementById('detail-task-description').textContent = 
                        event.extendedProps.description || 'No description';
                    
                    // Format date
                    const dueDate = event.start ? new Date(event.start) : null;
                    document.getElementById('detail-task-date').textContent = 
                        dueDate ? dueDate.toLocaleDateString() : 'No date set';
                    
                    // Format time
                    document.getElementById('detail-task-time').textContent = 
                        !event.allDay && event.start ? event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : 'All day';
                    
                    // Set priority
                    const priority = event.extendedProps.priority;
                    const priorityEl = document.getElementById('detail-task-priority');
                    priorityEl.textContent = priority.charAt(0).toUpperCase() + priority.slice(1);
                    priorityEl.className = '';
                    priorityEl.classList.add('priority-' + priority);
                    
                    // Set status
                    document.getElementById('detail-task-status').textContent = 
                        event.extendedProps.completed ? 'Completed' : 'Pending';
                    
                    // Set owner
                    document.getElementById('detail-task-owner').textContent = 
                        event.extendedProps.owner_name || 'You';
                    
                    // Store task ID
                    modal.dataset.taskId = event.id;
                    
                    // Show modal
                    modal.style.display = 'flex';
                }
            });
            
            // Render calendar
            calendar.render();
            
            // Modal functionality
            const modals = document.querySelectorAll('.modal');
            const closeButtons = document.querySelectorAll('.close-modal');
            
            // Explicit close handlers for each modal
            document.getElementById('task-modal').querySelector('.close-modal').addEventListener('click', function() {
                document.getElementById('task-modal').style.display = 'none';
            });
            
            document.getElementById('task-detail-modal').querySelector('.close-modal').addEventListener('click', function() {
                document.getElementById('task-detail-modal').style.display = 'none';
            });
            
            // Close modals when clicking outside
            modals.forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.style.display = 'none';
                    }
                });
            });
            
        });
    </script>
</body>
</html>