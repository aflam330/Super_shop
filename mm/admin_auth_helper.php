<?php
// Set proper headers for JSON response before any output
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

// Check if admin is already logged in
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    echo json_encode([
        'success' => true,
        'message' => 'Admin already logged in',
        'user_id' => $_SESSION['user_id'],
        'role' => $_SESSION['role'],
        'session_id' => session_id()
    ]);
    exit;
}

// Simulate admin login for testing
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

echo json_encode([
    'success' => true,
    'message' => 'Admin session created successfully',
    'session_id' => session_id(),
    'user_id' => $_SESSION['user_id'],
    'role' => $_SESSION['role']
]);
?> 