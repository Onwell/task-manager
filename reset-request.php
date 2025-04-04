<?php
// Start session
session_start();

// Database connection parameters
$db_host = "localhost";
$db_user = "root"; // Change to your database username
$db_pass = ""; // Change to your database password
$db_name = "task_manager_db";

// Connect to database
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to generate reset token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Function to sanitize user input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to send password reset email
function sendResetEmail($email, $name, $token) {
    $subject = "Password Reset - Task Manager";
    
    // Create reset link
    $reset_link = "http://" . $_SERVER['HTTP_HOST'] . 
                   dirname($_SERVER['PHP_SELF']) . 
                   "/reset-password-form.php?email=" . urlencode($email) . 
                   "&token=" . urlencode($token);
    
    // Email message
    $message = "
    <html>
    <head>
        <title>Reset Your Password</title>
    </head>
    <body>
        <p>Hello $name,</p>
        <p>We received a request to reset your password. Please click the link below to set a new password:</p>
        <p><a href='$reset_link'>Reset Password</a></p>
        <p>This link will expire in 1 hour for security reasons.</p>
        <p>If you did not request a password reset, please ignore this email.</p>
        <p>Regards,<br>Task Manager Team</p>
        
        <p>If the link doesn't work, copy and paste this URL into your browser:</p>
        <p>$reset_link</p>
    </body>
    </html>
    ";
    
    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@taskmanager.com" . "\r\n";
    
    // Attempt to send email
    $mailSent = mail($email, $subject, $message, $headers);
    
    // Log the email attempt
    error_log("Password reset email attempt to $email: " . ($mailSent ? "Success" : "Failed"));
    
    // For testing purposes: store the reset link in a session variable
    $_SESSION['reset_link'] = $reset_link;
    
    return $mailSent;
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $errors = [];
    
    // Get and sanitize email
    $email = sanitizeInput($_POST['email']);
    
    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email exists in database
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND is_verified = 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            // For security reasons, don't reveal if email exists or not
            // Just show a generic success message later
            $user_exists = false;
        } else {
            $user = $result->fetch_assoc();
            $user_exists = true;
            $user_name = $user['name'];
            $user_id = $user['id'];
        }
        
        $stmt->close();
    }
    
    // If no errors and user exists, proceed with reset request
    if (empty($errors) && $user_exists) {
        // Generate token
        $reset_token = generateToken();
        
        // Set expiration time (1 hour from now)
        $expiry = date('Y-m-d H:i:s', time() + 3600);
        
        // Check if a reset request already exists for this user
        $stmt = $conn->prepare("SELECT * FROM password_resets WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing request
            $update_stmt = $conn->prepare("UPDATE password_resets SET token = ?, expires_at = ? WHERE user_id = ?");
            $update_stmt->bind_param("ssi", $reset_token, $expiry, $user_id);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            // Insert new request
            $insert_stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $insert_stmt->bind_param("iss", $user_id, $reset_token, $expiry);
            $insert_stmt->execute();
            $insert_stmt->close();
        }
        
        $stmt->close();
        
        // Send password reset email
        $emailSent = sendResetEmail($email, $user_name, $reset_token);
        
        // For security, always show success message even if email fails
        // But store actual status in session for debugging
        $_SESSION['reset_success'] = "If the email exists in our system, a password reset link has been sent.";
        $_SESSION['email_sent'] = $emailSent;
        
        // For testing purposes
        if (!$emailSent) {
            $_SESSION['reset_success'] .= " Email sending is currently disabled. For testing purposes, you can use this link: <a href='" . $_SESSION['reset_link'] . "'>Reset Link</a>";
        }
        
        header("Location: forgot-password.php");
        exit();
    } elseif (empty($errors)) {
        // Even if user doesn't exist, show success message for security
        $_SESSION['reset_success'] = "If the email exists in our system, a password reset link has been sent.";
        header("Location: forgot-password.php");
        exit();
    }
    
    // If there are errors, store them in session and redirect back
    if (!empty($errors)) {
        $_SESSION['reset_errors'] = $errors;
        $_SESSION['reset_email'] = $email;
        header("Location: forgot-password.php");
        exit();
    }
}

// Close database connection
$conn->close();

// If accessed directly without form submission, redirect to forgot password page
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: forgot-password.php");
    exit();
}
?>