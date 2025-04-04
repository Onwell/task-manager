<?php
// Start session
session_start();

// Check if token and email exist in session
if (!isset($_SESSION['reset_token']) || !isset($_SESSION['reset_email'])) {
    header("Location: forgot-password.php");
    exit();
}

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

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_SESSION['reset_email'];
    $token = $_SESSION['reset_token'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $errors = [];
    
    // Validate password
    if (empty($new_password)) {
        $errors[] = "New password is required";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    // Validate password confirmation
    if ($new_password != $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // If no errors, verify token again and update password
    if (empty($errors)) {
        // Validate token
        $stmt = $conn->prepare("
            SELECT pr.*, u.id as user_id 
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE u.email = ? AND pr.token = ? AND pr.expires_at > NOW()
        ");
        $stmt->bind_param("ss", $email, $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $reset_data = $result->fetch_assoc();
            $user_id = $reset_data['user_id'];
            
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update user's password
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                // Delete used reset token
                $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $delete_stmt->bind_param("i", $user_id);
                $delete_stmt->execute();
                $delete_stmt->close();
                
                // Set success message
                $_SESSION['success_message'] = "Your password has been updated successfully. You can now login with your new password.";
                
                // Clear reset session variables
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_token']);
                
                // Redirect to login page
                header("Location: index.php");
                exit();
            } else {
                $errors[] = "Failed to update password. Please try again.";
            }
            
            $update_stmt->close();
        } else {
            $errors[] = "Invalid or expired reset token. Please request a new password reset.";
        }
        
        $stmt->close();
    }
    
    // If there are errors, store them in session and redirect back to form
    if (!empty($errors)) {
        $_SESSION['reset_form_errors'] = $errors;
        header("Location: reset-password-form.php?email=" . urlencode($email) . "&token=" . urlencode($token));
        exit();
    }
} else {
    // If accessed directly without form submission, redirect to forgot password page
    header("Location: forgot-password.php");
    exit();
}

// Close database connection
$conn->close();
?>