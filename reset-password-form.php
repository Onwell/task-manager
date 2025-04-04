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

// Initialize variables
$valid_token = false;
$email = "";
$token = "";
$error = "";

// Check if email and token are provided in URL
if (isset($_GET['email']) && isset($_GET['token'])) {
    $email = $_GET['email'];
    $token = $_GET['token'];
    
    // Validate token
    $stmt = $conn->prepare("
        SELECT pr.*, u.email 
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE u.email = ? AND pr.token = ? AND pr.expires_at > NOW()
    ");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $valid_token = true;
    } else {
        $error = "Invalid or expired password reset link. Please request a new one.";
    }
    
    $stmt->close();
} else {
    $error = "Invalid password reset link.";
}

// Store token and email in session for form processing
if ($valid_token) {
    $_SESSION['reset_email'] = $email;
    $_SESSION['reset_token'] = $token;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Task Manager | Reset Password</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<!--===============================================================================================-->  
    <link rel="icon" type="image/png" href="images/icons/favicon.ico"/>
<!--===============================================================================================-->
    <link rel="stylesheet" type="text/css" href="vendor/bootstrap/css/bootstrap.min.css">
<!--===============================================================================================-->
    <link rel="stylesheet" type="text/css" href="fonts/font-awesome-4.7.0/css/font-awesome.min.css">
<!--===============================================================================================-->
    <link rel="stylesheet" type="text/css" href="vendor/animate/animate.css">
<!--===============================================================================================-->  
    <link rel="stylesheet" type="text/css" href="vendor/css-hamburgers/hamburgers.min.css">
<!--===============================================================================================-->
    <link rel="stylesheet" type="text/css" href="vendor/select2/select2.min.css">
<!--===============================================================================================-->
    <link rel="stylesheet" type="text/css" href="css/util.css">
    <link rel="stylesheet" type="text/css" href="css/main.css">
<!--===============================================================================================-->
</head>
<body>
    
    <div class="limiter">
        <div class="container-login100">
            <div class="wrap-login100">
                <div class="login100-pic js-tilt" data-tilt>
                    <img src="images/img-01.png" alt="IMG">
                </div>

                <?php if ($valid_token): ?>
                <form class="login100-form validate-form" action="update-password.php" method="POST">
                    <span class="login100-form-title">
                        Reset Password
                    </span>

                    <?php if(isset($_SESSION['reset_form_errors'])): ?>
                    <div class="alert alert-danger text-center">
                        <?php 
                            foreach($_SESSION['reset_form_errors'] as $error) {
                                echo $error . "<br>";
                            }
                            unset($_SESSION['reset_form_errors']); 
                        ?>
                    </div>
                    <?php endif; ?>

                    <div class="wrap-input100 validate-input" data-validate = "Password is required">
                        <input class="input100" type="password" name="new_password" placeholder="New Password">
                        <span class="focus-input100"></span>
                        <span class="symbol-input100">
                            <i class="fa fa-lock" aria-hidden="true"></i>
                        </span>
                    </div>

                    <div class="wrap-input100 validate-input" data-validate = "Password confirmation is required">
                        <input class="input100" type="password" name="confirm_password" placeholder="Confirm New Password">
                        <span class="focus-input100"></span>
                        <span class="symbol-input100">
                            <i class="fa fa-lock" aria-hidden="true"></i>
                        </span>
                    </div>
                    
                    <div class="container-login100-form-btn">
                        <button class="login100-form-btn">
                            Update Password
                        </button>
                    </div>

                    <div class="text-center p-t-12">
                        <span class="txt1">
                            Remember your password?
                        </span>
                        <a class="txt2" href="index.php">
                            Sign In
                        </a>
                    </div>
                </form>
                <?php else: ?>
                <div class="login100-form">
                    <span class="login100-form-title">
                        Reset Password
                    </span>

                    <div class="alert alert-danger text-center">
                        <?php echo $error; ?>
                    </div>

                    <div class="container-login100-form-btn mt-4">
                        <a href="forgot-password.php" class="login100-form-btn">
                            Request New Reset Link
                        </a>
                    </div>

                    <div class="text-center p-t-12">
                        <span class="txt1">
                            Remember your password?
                        </span>
                        <a class="txt2" href="index.php">
                            Sign In
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
<!--===============================================================================================-->  
    <script src="vendor/jquery/jquery-3.2.1.min.js"></script>
<!--===============================================================================================-->
    <script src="vendor/bootstrap/js/popper.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
<!--===============================================================================================-->
    <script src="vendor/select2/select2.min.js"></script>
<!--===============================================================================================-->
    <script src="vendor/tilt/tilt.jquery.min.js"></script>
    <script >
        $('.js-tilt').tilt({
            scale: 1.1
        })
    </script>
<!--===============================================================================================-->
    <script src="js/main.js"></script>

</body>
</html>