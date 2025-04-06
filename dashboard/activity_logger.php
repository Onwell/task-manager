<?php
function logActivity($pdo, $userId, $action, $details = null) {
    try {
        // Validate parameters
        if (!$pdo instanceof PDO) {
            error_log("ERROR: Invalid PDO connection object");
            return false;
        }
        
        if (empty($userId)) {
            error_log("ERROR: User ID is required for activity logging");
            return false;
        }
        
        if (empty($action)) {
            error_log("ERROR: Action is required for activity logging");
            return false;
        }

        // Debug logging
        error_log("Activity log - User: $userId, Action: $action, Details: " . ($details ?? 'none'));
        
        // Prepare and execute the query
        $sql = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        
        if (!$stmt) {
            error_log("ERROR: Failed to prepare activity log SQL statement: " . print_r($pdo->errorInfo(), true));
            return false;
        }
        
        $result = $stmt->execute([$userId, $action, $details]);
        
        if (!$result) {
            error_log("ERROR: Failed to execute activity log SQL statement: " . print_r($stmt->errorInfo(), true));
            return false;
        }
        
        $newId = $pdo->lastInsertId();
        error_log("SUCCESS: Activity logged with ID: $newId");
        return true;
        
    } catch (Exception $e) {
        error_log("EXCEPTION in logActivity: " . $e->getMessage());
        return false;
    }
}
?>