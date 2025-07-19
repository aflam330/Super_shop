<?php
session_start();

// Log logout event if user is logged in
if (isset($_SESSION['user_id'])) {
    try {
        require_once 'backend/config.php';
        $pdo = getDBConnection();
        
        $log_stmt = $pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
        $log_data = json_encode([
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username']
        ]);
        $log_stmt->execute(['user_logout', $log_data]);
    } catch (Exception $e) {
        // Continue with logout even if logging fails
    }
}

// Destroy session
session_destroy();

// Clear any session cookies
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect to home page
header('Location: index.html');
exit;
?> 