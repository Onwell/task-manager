<?php

echo "<h1>Task Manager Configuration Test</h1>";

// Test database connection
echo "<h2>Database Connection Test</h2>";
$db_host = "localhost";
$db_user = "root"; // Change to your database username
$db_pass = ""; // Change to your database password
$db_name = "task_manager_db";

try {
    $conn = new mysqli($db_host, $db_user, $db_pass);
    echo "<p>✅ Connected to MySQL server successfully!</p>";
    
    // Check if database exists
    $result = $conn->query("SHOW DATABASES LIKE '$db_name'");
    if ($result->num_rows > 0) {
        echo "<p>✅ Database '$db_name' exists</p>";
        
        // Select the database
        $conn->select_db($db_name);
        
        // Check if users table exists
        $result = $conn->query("SHOW TABLES LIKE 'users'");
        if ($result->num_rows > 0) {
            echo "<p>✅ Table 'users' exists</p>";
            
            // Check table structure
            $result = $conn->query("DESCRIBE users");
            echo "<p>Table structure:</p><ul>";
            while ($row = $result->fetch_assoc()) {
                echo "<li>{$row['Field']} - {$row['Type']}</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>❌ Table 'users' does not exist!</p>";
            echo "<p>Creating 'users' table...</p>";
            
            // Create users table
            $sql = "CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                verification_token VARCHAR(255),
                is_verified TINYINT(1) DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            
            if ($conn->query($sql) === TRUE) {
                echo "<p>✅ Table 'users' created successfully</p>";
            } else {
                echo "<p>❌ Error creating table: " . $conn->error . "</p>";
            }
        }
    } else {
        echo "<p>❌ Database '$db_name' does not exist!</p>";
        echo "<p>Creating database...</p>";
        
        // Create database
        if ($conn->query("CREATE DATABASE $db_name") === TRUE) {
            echo "<p>✅ Database created successfully</p>";
        } else {
            echo "<p>❌ Error creating database: " . $conn->error . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

// Test PHP mail functionality
echo "<h2>Email Function Test</h2>";

if (function_exists('mail')) {
    echo "<p>✅ The mail() function exists</p>";
    
    // Check PHP mail configuration
    $mailconf = ini_get('sendmail_path') ? ini_get('sendmail_path') : 'Not set';
    echo "<p>sendmail_path: $mailconf</p>";
    
    // Test sending an email to yourself
    echo "<form method='post'>
        Test email: <input type='email' name='test_email' required>
        <input type='submit' name='send_test' value='Send Test Email'>
    </form>";
    
    if (isset($_POST['send_test'])) {
        $to = $_POST['test_email'];
        $subject = "Task Manager Email Test";
        $message = "<html><body><h1>Email Test</h1><p>This is a test email from your Task Manager application.</p></body></html>";
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: noreply@taskmanager.com" . "\r\n";
        
        if (mail($to, $subject, $message, $headers)) {
            echo "<p>✅ Test email sent to $to. Please check your inbox (and spam folder).</p>";
        } else {
            echo "<p>❌ Failed to send test email. Check your server's mail configuration.</p>";
        }
    }
} else {
    echo "<p>❌ The mail() function is not available</p>";
    echo "<p>Alternative solutions:</p>
    <ul>
        <li>Configure PHP to enable the mail() function</li>
        <li>Use a third-party library like PHPMailer or Swift Mailer</li>
        <li>Use an email API service like SendGrid, Mailgun, etc.</li>
    </ul>";
}

echo "<h2>System Information</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Operating System: " . PHP_OS . "</p>";

?>