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

// Get user ID from session
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId = $_SESSION['user_id'];

// Fetch user data from database
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

// Initialize variables
$errors = [];
$success = false;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Change username
    if (isset($_POST['change_username'])) {
        $newUsername = trim($_POST['new_username']);
        
        if (empty($newUsername)) {
            $errors['username'] = "Username cannot be empty";
        } elseif (strlen($newUsername) < 3) {
            $errors['username'] = "Username must be at least 3 characters";
        } else {
            // Check if username already exists
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE name = ? AND id != ?");
            $checkStmt->execute([$newUsername, $userId]);
            
            if ($checkStmt->fetch()) {
                $errors['name'] = "Username already taken";
            } else {
                $updateStmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
                if ($updateStmt->execute([$newUsername, $userId])) {
                    $success = "Username updated successfully";
                    $user['name'] = $newUsername;
                } else {
                    $errors['name'] = "Failed to update username";
                }
            }
        }
    }
    
    // Change email
    if (isset($_POST['change_email'])) {
        $newEmail = trim($_POST['new_email']);
        
        if (empty($newEmail)) {
            $errors['email'] = "Email cannot be empty";
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Invalid email format";
        } else {
            // Check if email already exists
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $checkStmt->execute([$newEmail, $userId]);
            
            if ($checkStmt->fetch()) {
                $errors['email'] = "Email already in use";
            } else {
                $updateStmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                if ($updateStmt->execute([$newEmail, $userId])) {
                    $success = "Email updated successfully";
                    $user['email'] = $newEmail;
                } else {
                    $errors['email'] = "Failed to update email";
                }
            }
        }
    }
    
    // Change password
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Verify current password
        if (!password_verify($currentPassword, $user['password'])) {
            $errors['password'] = "Current password is incorrect";
        } elseif (empty($newPassword)) {
            $errors['password'] = "New password cannot be empty";
        } elseif (strlen($newPassword) < 8) {
            $errors['password'] = "Password must be at least 8 characters";
        } elseif ($newPassword !== $confirmPassword) {
            $errors['password'] = "Passwords do not match";
        } else {
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($updateStmt->execute([$hashedPassword, $userId])) {
                $success = "Password updated successfully";
            } else {
                $errors['password'] = "Failed to update password";
            }
        }
    }
}

// Get user initial for avatar
$user['initial'] = strtoupper(substr($user['name'], 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager / Settings</title>
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
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
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
        
        .error {
            color: var(--danger);
            font-size: 14px;
            margin-top: 5px;
        }
        
        .success {
            color: var(--success);
            font-size: 14px;
            margin-bottom: 15px;
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
                <li><a href="http://localhost/task-manager/dashboard/"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="add_task.php"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li><a href="calendar.php"><i class="fas fa-calendar"></i> Calendar</a></li>
                    <li><a href="report.php"><i class="fas fa-chart-line"></i> Reports</a></li>
                    <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="activity_log.php"><i class="fas fa-history"></i> Activity Log</a></li>
                    <li><a href="http://localhost/task-manager/"><i class="fas fa-lock"></i> Logout</a></li>
                </ul>
            </div>
            
            <div class="content">
                <div class="card">
                    <h2 class="section-header">Account Settings</h2>
                    
                    <?php if ($success): ?>
                        <div class="success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="current_username">Current Username</label>
                            <input type="text" id="current_username" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_username">New Username</label>
                            <input type="text" id="new_username" name="new_username" placeholder="Enter new username">
                            <?php if (isset($errors['username'])): ?>
                                <div class="error"><?php echo $errors['username']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" name="change_username" class="button">Change Username</button>
                    </form>
                </div>
                
                <div class="card">
                    <form method="POST">
                        <div class="form-group">
                            <label for="current_email">Current Email</label>
                            <input type="email" id="current_email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_email">New Email</label>
                            <input type="email" id="new_email" name="new_email" placeholder="Enter new email">
                            <?php if (isset($errors['email'])): ?>
                                <div class="error"><?php echo $errors['email']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" name="change_email" class="button">Change Email</button>
                    </form>
                </div>
                
                <div class="card">
                    <form method="POST">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" placeholder="Enter current password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                            <?php if (isset($errors['password'])): ?>
                                <div class="error"><?php echo $errors['password']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" name="change_password" class="button">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
