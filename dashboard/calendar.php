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

// Fetch all tasks (not just current month)
$taskStmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = ?");
$taskStmt->execute([$userId]);
$tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare tasks for FullCalendar
$calendarTasks = [];
foreach ($tasks as $task) {
    $calendarTasks[] = [
        'id' => $task['id'],
        'title' => $task['title'],
        'start' => $task['due_date'] . ($task['time'] ? 'T' . $task['time'] : ''),
        'allDay' => empty($task['time']),
        'priority' => $task['priority'],
        'completed' => (bool)$task['completed'],
        'progress' => $task['progress'],
        'color' => $task['priority'] === 'high' ? '#f72585' : 
                  ($task['priority'] === 'medium' ? '#f8961e' : '#4cc9f0')
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager / Calendar</title>
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
        
        #calendar {
            width: 100%;
            height: 700px;
        }
        
        .fc-event {
            cursor: pointer;
            border-radius: 4px;
            padding: 2px 5px;
            font-size: 0.85em;
            border: none !important;
        }
        
        .fc-event.completed {
            opacity: 0.7;
            text-decoration: line-through;
        }
        
        .fc-daygrid-event-dot {
            display: none;
        }
        
        .fc-toolbar-title {
            font-size: 1.5em;
            color: var(--dark);
        }
        
        .fc-button {
            background-color: var(--primary) !important;
            border: none !important;
            color: white !important;
            text-transform: capitalize !important;
            border-radius: 5px !important;
            padding: 6px 12px !important;
        }
        
        .fc-button:hover {
            background-color: var(--secondary) !important;
        }
        
        .fc-button-active {
            background-color: var(--secondary) !important;
        }
        
        .fc-daygrid-day-number {
            color: var(--dark);
            font-weight: 500;
        }
        
        .fc-day-today {
            background-color: rgba(67, 97, 238, 0.1) !important;
        }
        
        .fc-daygrid-day-events {
            margin-top: 2px;
        }
        
        .fc-daygrid-event {
            margin-bottom: 2px;
        }
        
        .fc-event-time {
            font-weight: 500;
        }
        
        /* New styles for task day highlighting */
        .fc-daygrid-day.has-tasks {
            background-color: rgba(76, 201, 240, 0.1);
            position: relative;
        }
        
        .fc-daygrid-day.has-tasks::after {
            content: '';
            position: absolute;
            bottom: 5px;
            left: 50%;
            transform: translateX(-50%);
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: var(--primary);
        }
        
        .fc-day-today.has-tasks {
            background-color: rgba(67, 97, 238, 0.2);
        }
        
        .fc-day-today.has-tasks::after {
            background-color: var(--danger);
            width: 8px;
            height: 8px;
        }
        
        .fc-daygrid-day-number {
            position: relative;
            z-index: 1;
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

        .close-modal, .close-detail-modal {
            cursor: pointer;
            font-size: 24px;
            color: var(--gray);
            transition: var(--transition);
        }

        .close-modal:hover, .close-detail-modal:hover {
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
        
        .tag {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
            color: white;
            display: inline-block;
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
        
        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .sidebar {
                height: auto;
            }
            
            #calendar {
                height: 500px;
            }
        }
        
        @media (max-width: 576px) {
            #calendar {
                height: 400px;
            }
            
            .fc-toolbar {
                flex-direction: column;
                gap: 10px;
            }
            
            .fc-toolbar-chunk {
                display: flex;
                justify-content: center;
                width: 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
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
                    <li><a href="dashboard/index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="add_task.php"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li><a href="#"><i class="fas fa-project-diagram"></i> Projects</a></li>
                    <li><a href="calendar.php" class="active"><i class="fas fa-calendar"></i> Calendar</a></li>
                    <li><a href="report.php"><i class="fas fa-chart-line"></i> Reports</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                </ul>
            </div>
            
            <div class="content">
                <div class="card">
                    <div class="section-header">
                        <h2><i class="fas fa-calendar"></i> Task Calendar</h2>
                        <button class="button" id="add-task-btn"><i class="fas fa-plus"></i> Add Task</button>
                    </div>
                    <div id="calendar"></div>
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
                <button type="submit" class="button" style="width: 100%;">Save Task</button>
            </form>
        </div>
    </div>
    
    <!-- Task Detail Modal -->
    <div id="task-detail-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="detail-task-title"></h3>
                <span class="close-detail-modal">&times;</span>
            </div>
            <div class="task-detail-content">
                <p><strong>Date:</strong> <span id="detail-task-date"></span></p>
                <p><strong>Time:</strong> <span id="detail-task-time"></span></p>
                <p><strong>Priority:</strong> <span id="detail-task-priority" class="tag"></span></p>
                <p><strong>Status:</strong> <span id="detail-task-status"></span></p>
                <p><strong>Progress:</strong> <span id="detail-task-progress"></span>%</p>
            </div>
            <div class="task-detail-actions" style="margin-top: 20px; display: flex; gap: 10px;">
                <button class="button" id="edit-task-btn" style="flex: 1;"><i class="fas fa-edit"></i> Edit</button>
                <button class="button" id="delete-task-btn" style="background-color: var(--danger); flex: 1;"><i class="fas fa-trash"></i> Delete</button>
                <button class="button" id="complete-task-btn" style="background-color: var(--success); flex: 1;"><i class="fas fa-check"></i> Toggle Complete</button>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize calendar
            const calendarEl = document.getElementById('calendar');
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: <?php echo json_encode($calendarTasks); ?>,
                eventContent: function(arg) {
                    // Custom event content
                    const timeEl = arg.event.start ? document.createElement('div') : null;
                    if (timeEl) {
                        timeEl.className = 'fc-event-time';
                        timeEl.textContent = arg.timeText;
                    }
                    
                    const titleEl = document.createElement('div');
                    titleEl.className = 'fc-event-title';
                    titleEl.textContent = arg.event.title;
                    
                    const container = document.createElement('div');
                    if (timeEl) container.appendChild(timeEl);
                    container.appendChild(titleEl);
                    
                    return { domNodes: [container] };
                },
                eventDidMount: function(arg) {
                    // Add completed class if task is completed
                    if (arg.event.extendedProps.completed) {
                        arg.el.classList.add('completed');
                    }
                    
                    // Highlight the day cell if it has tasks
                    const dayEl = document.querySelector(`.fc-day[data-date="${arg.event.startStr.split('T')[0]}"]`);
                    if (dayEl) {
                        dayEl.classList.add('has-tasks');
                    }
                },
                datesSet: function(info) {
                    // After calendar renders, highlight days with tasks
                    setTimeout(() => {
                        const events = calendar.getEvents();
                        const daysWithTasks = new Set();
                        
                        events.forEach(event => {
                            const dateStr = event.startStr.split('T')[0];
                            daysWithTasks.add(dateStr);
                        });
                        
                        daysWithTasks.forEach(dateStr => {
                            const dayEl = document.querySelector(`.fc-day[data-date="${dateStr}"]`);
                            if (dayEl) {
                                dayEl.classList.add('has-tasks');
                            }
                        });
                    }, 100);
                },
                eventClick: function(info) {
                    // Show task details when clicked
                    const task = info.event;
                    document.getElementById('detail-task-title').textContent = task.title;
                    document.getElementById('detail-task-date').textContent = task.start ? task.start.toLocaleDateString() : '';
                    document.getElementById('detail-task-time').textContent = task.start && !task.allDay ? task.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : 'All day';
                    
                    const priorityEl = document.getElementById('detail-task-priority');
                    priorityEl.textContent = task.extendedProps.priority.charAt(0).toUpperCase() + task.extendedProps.priority.slice(1);
                    priorityEl.className = 'tag ' + task.extendedProps.priority;
                    
                    document.getElementById('detail-task-status').textContent = task.extendedProps.completed ? 'Completed' : 'Pending';
                    document.getElementById('detail-task-progress').textContent = task.extendedProps.progress;
                    
                    // Set up button actions
                    document.getElementById('edit-task-btn').onclick = function() {
                        alert('Edit functionality would go here');
                    };
                    
                    document.getElementById('delete-task-btn').onclick = function() {
                        if (confirm('Are you sure you want to delete this task?')) {
                            fetch('delete_task.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'task_id=' + task.id
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    task.remove();
                                    document.getElementById('task-detail-modal').style.display = 'none';
                                    updateDayHighlights();
                                } else {
                                    alert('Failed to delete task');
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('Error deleting task');
                            });
                        }
                    };
                    
                    document.getElementById('complete-task-btn').onclick = function() {
                        const newStatus = !task.extendedProps.completed;
                        fetch('update_task.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'task_id=' + task.id + '&completed=' + (newStatus ? 1 : 0)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                task.setExtendedProp('completed', newStatus);
                                const eventEl = document.querySelector(`.fc-event[data-event-id="${task.id}"]`);
                                if (eventEl) {
                                    if (newStatus) {
                                        eventEl.classList.add('completed');
                                    } else {
                                        eventEl.classList.remove('completed');
                                    }
                                }
                                document.getElementById('detail-task-status').textContent = newStatus ? 'Completed' : 'Pending';
                            } else {
                                alert('Failed to update task status');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error updating task status');
                        });
                    };
                    
                    document.getElementById('task-detail-modal').style.display = 'flex';
                },
                dateClick: function(info) {
                    // Set date when clicking on calendar
                    document.getElementById('task-date').value = info.dateStr;
                    document.getElementById('task-modal').style.display = 'flex';
                }
            });
            calendar.render();
            
            function updateDayHighlights() {
                // Remove all existing highlights
                document.querySelectorAll('.fc-day.has-tasks').forEach(day => {
                    day.classList.remove('has-tasks');
                });
                
                // Re-highlight days with remaining tasks
                const events = calendar.getEvents();
                const daysWithTasks = new Set();
                
                events.forEach(event => {
                    const dateStr = event.startStr.split('T')[0];
                    daysWithTasks.add(dateStr);
                });
                
                daysWithTasks.forEach(dateStr => {
                    const dayEl = document.querySelector(`.fc-day[data-date="${dateStr}"]`);
                    if (dayEl) {
                        dayEl.classList.add('has-tasks');
                    }
                });
            }
            
            // Modal functionality
            const taskModal = document.getElementById('task-modal');
            const taskDetailModal = document.getElementById('task-detail-modal');
            
            // Add task button
            document.getElementById('add-task-btn').addEventListener('click', function() {
                document.getElementById('task-date').valueAsDate = new Date();
                taskModal.style.display = 'flex';
            });
            
            // Close modals
            document.querySelector('.close-modal').addEventListener('click', function() {
                taskModal.style.display = 'none';
            });
            
            document.querySelector('.close-detail-modal').addEventListener('click', function() {
                taskDetailModal.style.display = 'none';
            });
            
            // Close modals when clicking outside
            taskModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
            
            taskDetailModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
            
            // Form submission
            document.getElementById('task-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('add_task.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Failed to add task');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error adding task');
                });
            });
        });
    </script>
</body>
</html>
