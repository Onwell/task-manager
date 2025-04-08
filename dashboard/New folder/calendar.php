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
$userId = $_SESSION['user_id'] ?? 1;

// Fetch user data
$userStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

// Get user initial for avatar
$user['initial'] = strtoupper(substr($user['name'], 0, 1));

// Fetch all tasks
$taskStmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = ?");
$taskStmt->execute([$userId]);
$tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare tasks for FullCalendar
$calendarTasks = [];
foreach ($tasks as $task) {
    $calendarTasks[] = [
        'id' => $task['id'],
        'title' => $task['title'],
        'description' => $task['description'],
        'start' => $task['due_date'] . ($task['time'] ? 'T' . $task['time'] : ''),
        'allDay' => empty($task['time']),
        'priority' => $task['priority'],
        'completed' => (bool)$task['completed'],
        'progress' => $task['progress'],
        'reminder' => (bool)$task['reminder'],
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
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .button {
            background-color: var(--primary);
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
        
        #calendar {
            width: 100%;
            margin: 0 auto;
        }
        
        .fc-event {
            cursor: pointer;
        }
        
        .fc-event.completed {
            opacity: 0.7;
            text-decoration: line-through;
        }
        
        .fc-day.has-tasks {
            background-color: rgba(67, 97, 238, 0.05);
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
        
        .form-group.checkbox-group label {
            margin-bottom: 0;
            font-weight: normal;
            cursor: pointer;
        }
        
        .form-group.checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .task-detail-content {
            margin-bottom: 20px;
        }
        
        .task-detail-content p {
            margin-bottom: 10px;
        }
        
        .task-detail-description {
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: var(--border-radius);
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
        
        .task-detail-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
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
            
            .task-detail-actions {
                flex-direction: column;
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
                    <li><a href="http://localhost/task-manager/dashboard/"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="add_task.php"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li><a href="#"><i class="fas fa-project-diagram"></i> Projects</a></li>
                    <li><a href="calendar.php" class="active"><i class="fas fa-calendar"></i> Calendar</a></li>
                    <li><a href="report.php"><i class="fas fa-chart-line"></i> Reports</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="activity_log.php"><i class="fas fa-history"></i> Activity Log</a></li>
                    <li><a href="http://localhost/task-manager/"><i class="fas fa-lock"></i> Logout</a></li>
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
    
    <!-- Task Detail Modal -->
    <div id="task-detail-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="detail-task-title"></h3>
                <span class="close-detail-modal">&times;</span>
            </div>
            <div class="task-detail-content">
                <div class="task-detail-description" id="detail-task-description"></div>
                <p><strong>Date:</strong> <span id="detail-task-date"></span></p>
                <p><strong>Time:</strong> <span id="detail-task-time"></span></p>
                <p><strong>Priority:</strong> <span id="detail-task-priority" class="tag"></span></p>
                <p><strong>Status:</strong> <span id="detail-task-status"></span></p>
                <p><strong>Progress:</strong> <span id="detail-task-progress"></span>%</p>
            </div>
            <div class="task-detail-actions">
                <button class="button" id="edit-task-btn" style="flex: 1;"><i class="fas fa-edit"></i> Edit</button>
                <button class="button" id="delete-task-btn" style="background-color: var(--danger); flex: 1;"><i class="fas fa-trash"></i> Delete</button>
                <button class="button" id="complete-task-btn" style="flex: 1;"><i class="fas fa-check"></i> Toggle Complete</button>
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
                    if (arg.event.extendedProps.completed) {
                        arg.el.classList.add('completed');
                    }
                    
                    const dayEl = document.querySelector(`.fc-day[data-date="${arg.event.startStr.split('T')[0]}"]`);
                    if (dayEl) {
                        dayEl.classList.add('has-tasks');
                    }
                },
                datesSet: function(info) {
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
                    const task = info.event;
                    document.getElementById('detail-task-title').textContent = task.title;
                    document.getElementById('detail-task-description').textContent = 
                        task.extendedProps.description || 'No description provided';
                    document.getElementById('detail-task-date').textContent = task.start ? task.start.toLocaleDateString() : '';
                    document.getElementById('detail-task-time').textContent = task.start && !task.allDay ? 
                        task.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : 'All day';
                    
                    const priorityEl = document.getElementById('detail-task-priority');
                    priorityEl.textContent = task.extendedProps.priority.charAt(0).toUpperCase() + 
                                           task.extendedProps.priority.slice(1);
                    priorityEl.className = 'tag ' + task.extendedProps.priority;
                    
                    document.getElementById('detail-task-status').textContent = 
                        task.extendedProps.completed ? 'Completed' : 'Pending';
                    document.getElementById('detail-task-progress').textContent = task.extendedProps.progress;
                    
                    const completeBtn = document.getElementById('complete-task-btn');
                    completeBtn.innerHTML = `<i class="fas fa-check"></i> ${task.extendedProps.completed ? 'Mark Incomplete' : 'Mark Complete'}`;
                    completeBtn.style.backgroundColor = task.extendedProps.completed ? 'var(--success)' : 'var(--primary)';
                    
                    // Set up button actions
                    document.getElementById('edit-task-btn').onclick = function() {
                        document.getElementById('task-detail-modal').style.display = 'none';
                        
                        fetch('get_task.php?task_id=' + task.id)
                            .then(response => response.json())
                            .then(taskData => {
                                if (taskData) {
                                    document.getElementById('task-title').value = taskData.title;
                                    document.getElementById('task-description').value = taskData.description || '';
                                    document.getElementById('task-date').value = taskData.due_date;
                                    document.getElementById('task-time').value = taskData.time || '';
                                    document.getElementById('task-priority').value = taskData.priority;
                                    document.getElementById('task-progress').value = taskData.progress;
                                    document.getElementById('task-reminder').checked = taskData.reminder == 1;
                                    
                                    const form = document.getElementById('task-form');
                                    form.setAttribute('data-task-id', task.id);
                                    form.setAttribute('data-mode', 'edit');
                                    document.querySelector('.modal-header h3').textContent = 'Edit Task';
                                    document.getElementById('task-modal').style.display = 'flex';
                                } else {
                                    alert('Failed to load task data');
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('Error loading task data');
                            });
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
                        const completeBtn = this;
                        
                        fetch('update_task.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `task_id=${task.id}&completed=${newStatus ? 1 : 0}`
                        })
                        .then(response => {
                            if (!response.ok) throw new Error('Network response was not ok');
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                task.setExtendedProp('completed', newStatus);
                                const eventEl = document.querySelector(`.fc-event[data-event-id="${task.id}"]`);
                                if (eventEl) eventEl.classList.toggle('completed', newStatus);
                                document.getElementById('detail-task-status').textContent = newStatus ? 'Completed' : 'Pending';
                                completeBtn.innerHTML = `<i class="fas fa-check"></i> ${newStatus ? 'Mark Incomplete' : 'Mark Complete'}`;
                                completeBtn.style.backgroundColor = newStatus ? 'var(--success)' : 'var(--primary)';
                                document.getElementById('task-detail-modal').style.display = 'none';
                            } else {
                                alert(data.message || 'Failed to update task status');
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
                    document.getElementById('task-date').value = info.dateStr;
                    document.getElementById('task-modal').style.display = 'flex';
                }
            });
            calendar.render();
            
            function updateDayHighlights() {
                document.querySelectorAll('.fc-day.has-tasks').forEach(day => {
                    day.classList.remove('has-tasks');
                });
                
                const events = calendar.getEvents();
                const daysWithTasks = new Set();
                
                events.forEach(event => {
                    const dateStr = event.startStr.split('T')[0];
                    daysWithTasks.add(dateStr);
                });
                
                daysWithTasks.forEach(dateStr => {
                    const dayEl = document.querySelector(`.fc-day[data-date="${dateStr}"]`);
                    if (dayEl) dayEl.classList.add('has-tasks');
                });
            }
            
            // Modal functionality
            const taskModal = document.getElementById('task-modal');
            const taskDetailModal = document.getElementById('task-detail-modal');
            
            document.getElementById('add-task-btn').addEventListener('click', function() {
                document.getElementById('task-form').reset();
                document.getElementById('task-form').setAttribute('data-mode', 'add');
                document.getElementById('task-form').removeAttribute('data-task-id');
                document.querySelector('.modal-header h3').textContent = 'Add New Task';
                document.getElementById('task-date').valueAsDate = new Date();
                document.getElementById('task-progress').value = 0;
                taskModal.style.display = 'flex';
            });
            
            document.querySelector('.close-modal').addEventListener('click', function() {
                taskModal.style.display = 'none';
            });
            
            document.querySelector('.close-detail-modal').addEventListener('click', function() {
                taskDetailModal.style.display = 'none';
            });
            
            taskModal.addEventListener('click', function(e) {
                if (e.target === this) this.style.display = 'none';
            });
            
            taskDetailModal.addEventListener('click', function(e) {
                if (e.target === this) this.style.display = 'none';
            });
            
            document.getElementById('task-form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const mode = this.getAttribute('data-mode');
                const taskId = this.getAttribute('data-task-id');
                
                formData.append('action', mode);
                if (mode === 'edit' && taskId) {
                    formData.append('task_id', taskId);
                }
                
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                
                fetch('process_task.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Save Task';
                    
                    if (data.success) {
                        taskModal.style.display = 'none';
                        calendar.refetchEvents();
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
