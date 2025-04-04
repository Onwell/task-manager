<?php
// Start session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Function to generate verification token
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

// Alternative email function using PHPMailer if available
// If not available, will fall back to the regular mail() function
function sendVerificationEmail($email, $name, $token) {
    $subject = "Email Verification - Task Manager";
    
    // Create verification link
    $verification_link = "http://" . $_SERVER['HTTP_HOST'] . 
                          dirname($_SERVER['PHP_SELF']) . 
                          "/verify.php?email=" . urlencode($email) . 
                          "&token=" . urlencode($token);
    
    // Email message
    $message = "
    <html>
    <head>
        <title>Verify Your Email Address</title>
    </head>
    <body>
        <p>Hello $name,</p>
        <p>Thank you for registering with Task Manager. Please click the link below to verify your email address:</p>
        <p><a href='$verification_link'>Verify Email Address</a></p>
        <p>If you did not create an account, no further action is required.</p>
        <p>Regards,<br>Task Manager Team</p>
        
        <p>If the link doesn't work, copy and paste this URL into your browser:</p>
        <p>$verification_link</p>
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
    error_log("Email sending attempt to $email: " . ($mailSent ? "Success" : "Failed"));
    
    // For testing purposes: store the verification link in a session variable
    $_SESSION['verification_link'] = $verification_link;
    
    return $mailSent;
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $errors = [];
    
    // Get form data and sanitize inputs
    $name = sanitizeInput($_POST['text']); // Note: The name field is named 'text' in the form
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['pass'];
    $confirm_password = $_POST['pass2'];
    
    // Validate name
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Email already exists";
        }
        $stmt->close();
    }
    
    // Validate password
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    // Validate confirm password
    if ($password != $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate verification token
        $verification_token = generateToken();
        
        // Current timestamp
        $created_at = date('Y-m-d H:i:s');
        
        // Default is_verified to 0 (false)
        $is_verified = 0;
        
        // Prepare SQL statement
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, verification_token, is_verified, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssis", $name, $email, $hashed_password, $verification_token, $is_verified, $created_at);
        
        // Execute statement
        if ($stmt->execute()) {
            // Try to send verification email
            $emailSent = sendVerificationEmail($email, $name, $verification_token);
            
            // Even if email fails, we'll proceed but with a different message
            // This allows testing on environments where email sending is not set up
            if ($emailSent) {
                $_SESSION['success_message'] = "Registration successful! Please check your email to verify your account.";
            } else {
                // For testing purposes only - in production, you would not want to show the verification link directly
                $_SESSION['success_message'] = "Registration successful! Email sending is currently disabled. For testing purposes, you can verify your account manually through this link: <a href='" . $_SESSION['verification_link'] . "'>Verification Link</a>";
            }
            
            header("Location: index.php");
            exit();
        } else {
            $errors[] = "Registration failed: " . $stmt->error;
        }
        
        $stmt->close();
    }
    
    // If there are errors, store them in session and redirect back to registration form
    if (!empty($errors)) {
        $_SESSION['reg_errors'] = $errors;
        $_SESSION['form_data'] = ['name' => $name, 'email' => $email];
        header("Location: create-account.php");
        exit();
    }
}

// Close database connection
$conn->close();

// If accessed directly without form submission, redirect to registration page
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: create-account.php");
    exit();
}
?>