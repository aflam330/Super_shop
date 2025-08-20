<?php
// Helper script to set up receptionist authentication
session_start();

// Set receptionist session
$_SESSION['user_id'] = 2; // Assuming receptionist user ID is 2
$_SESSION['role'] = 'receptionist';

// Set session cookie
setcookie('PHPSESSID', session_id(), time() + 3600, '/');

echo json_encode([
    'success' => true,
    'message' => 'Receptionist session established',
    'user_id' => $_SESSION['user_id'],
    'role' => $_SESSION['role'],
    'session_id' => session_id()
]);
?> 