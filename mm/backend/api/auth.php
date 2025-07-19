<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config.php';

class AuthAPI {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // User Registration (FR1)
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->error('Method not allowed', 405);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            return $this->error('Invalid JSON data', 400);
        }
        
        $required_fields = ['username', 'email', 'password', 'first_name', 'last_name'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return $this->error("Missing required field: $field", 400);
            }
        }
        
        // Validate email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email format', 400);
        }
        
        // Check if username or email already exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$data['username'], $data['email']]);
        
        if ($stmt->rowCount() > 0) {
            return $this->error('Username or email already exists', 409);
        }
        
        // Hash password
        $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $this->pdo->prepare("
            INSERT INTO users (username, email, password_hash, first_name, last_name, phone, address, role) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'customer')
        ");
        $phone = $data['phone'] ?? null;
        $address = $data['address'] ?? null;
        
        if ($stmt->execute([$data['username'], $data['email'], $password_hash, $data['first_name'], $data['last_name'], $phone, $address])) {
            $user_id = $this->pdo->lastInsertId();
            
            // Create session
            session_start();
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $data['username'];
            $_SESSION['role'] = 'customer';
            
            return $this->success([
                'message' => 'User registered successfully',
                'user_id' => $user_id,
                'username' => $data['username'],
                'role' => 'customer'
            ]);
        } else {
            return $this->error('Registration failed', 500);
        }
    }
    
    // User Login (FR1, FR7, FR13)
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->error('Method not allowed', 405);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || empty($data['username']) || empty($data['password'])) {
            return $this->error('Username and password are required', 400);
        }
        
        // Get user by username or email
        $stmt = $this->pdo->prepare("
            SELECT id, username, email, password_hash, first_name, last_name, role, is_active 
            FROM users 
            WHERE (username = ? OR email = ?) AND is_active = 1
        ");
        $stmt->execute([$data['username'], $data['username']]);
        
        if ($stmt->rowCount() === 0) {
            return $this->error('Invalid credentials', 401);
        }
        
        $user = $stmt->fetch();
        
        // Verify password
        if (!password_verify($data['password'], $user['password_hash'])) {
            return $this->error('Invalid credentials', 401);
        }
        
        // Create session
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        
        // Log login event
        $this->logEvent('user_login', [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ]);
        
        return $this->success([
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'role' => $user['role']
            ]
        ]);
    }
    
    // User Logout (FR1, FR7, FR13)
    public function logout() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->error('Method not allowed', 405);
        }
        
        session_start();
        
        if (isset($_SESSION['user_id'])) {
            // Log logout event
            $this->logEvent('user_logout', [
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username']
            ]);
        }
        
        // Destroy session
        session_destroy();
        
        return $this->success(['message' => 'Logout successful']);
    }
    
    // Get current user info
    public function getCurrentUser() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->error('Method not allowed', 405);
        }
        
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            return $this->error('Not authenticated', 401);
        }
        
        $stmt = $this->pdo->prepare("
            SELECT id, username, email, first_name, last_name, phone, address, role, created_at 
            FROM users 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        
        if ($stmt->rowCount() === 0) {
            session_destroy();
            return $this->error('User not found', 404);
        }
        
        $user = $stmt->fetch();
        
        return $this->success(['user' => $user]);
    }
    
    // Update user profile
    public function updateProfile() {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            return $this->error('Method not allowed', 405);
        }
        
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            return $this->error('Not authenticated', 401);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            return $this->error('Invalid JSON data', 400);
        }
        
        $allowed_fields = ['first_name', 'last_name', 'phone', 'address'];
        $update_fields = [];
        $types = '';
        $values = [];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_fields[] = "$field = ?";
                $types .= 's';
                $values[] = $data[$field];
            }
        }
        
        if (empty($update_fields)) {
            return $this->error('No valid fields to update', 400);
        }
        
        $values[] = $_SESSION['user_id'];
        $types .= 'i';
        
        $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        
        if ($stmt->execute($values)) {
            return $this->success(['message' => 'Profile updated successfully']);
        } else {
            return $this->error('Profile update failed', 500);
        }
    }
    
    // Change password
    public function changePassword() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->error('Method not allowed', 405);
        }
        
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            return $this->error('Not authenticated', 401);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || empty($data['current_password']) || empty($data['new_password'])) {
            return $this->error('Current password and new password are required', 400);
        }
        
        // Verify current password
        $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!password_verify($data['current_password'], $user['password_hash'])) {
            return $this->error('Current password is incorrect', 400);
        }
        
        // Update password
        $new_password_hash = password_hash($data['new_password'], PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        
        if ($stmt->execute([$new_password_hash, $_SESSION['user_id']])) {
            return $this->success(['message' => 'Password changed successfully']);
        } else {
            return $this->error('Password change failed', 500);
        }
    }
    
    // Admin: Get all users (FR18)
    public function getAllUsers() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->error('Method not allowed', 405);
        }
        
        session_start();
        
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            return $this->error('Access denied', 403);
        }
        
        $stmt = $this->pdo->prepare("
            SELECT id, username, email, first_name, last_name, phone, role, is_active, created_at 
            FROM users 
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        
        $users = $stmt->fetchAll();
        
        return $this->success(['users' => $users]);
    }
    
    // Admin: Update user role/status (FR18)
    public function updateUser() {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            return $this->error('Method not allowed', 405);
        }
        
        session_start();
        
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            return $this->error('Access denied', 403);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || empty($data['user_id'])) {
            return $this->error('User ID is required', 400);
        }
        
        $allowed_fields = ['role', 'is_active'];
        $update_fields = [];
        $types = '';
        $values = [];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_fields[] = "$field = ?";
                $types .= 's';
                $values[] = $data[$field];
            }
        }
        
        if (empty($update_fields)) {
            return $this->error('No valid fields to update', 400);
        }
        
        $values[] = $data['user_id'];
        $types .= 'i';
        
        $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        
        if ($stmt->execute($values)) {
            return $this->success(['message' => 'User updated successfully']);
        } else {
            return $this->error('User update failed', 500);
        }
    }
    
    // Check if user is authenticated
    public function checkAuth() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->error('Method not allowed', 405);
        }
        
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            return $this->error('Not authenticated', 401);
        }
        
        // Verify user still exists and is active
        $stmt = $this->pdo->prepare("
            SELECT id, username, first_name, last_name, role 
            FROM users 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        
        if ($stmt->rowCount() === 0) {
            session_destroy();
            return $this->error('User not found or inactive', 401);
        }
        
        $user = $stmt->fetch();
        
        return $this->success([
            'authenticated' => true,
            'user' => $user
        ]);
    }

    private function logEvent($event_type, $event_data) {
        $stmt = $this->pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
        $stmt->execute([$event_type, json_encode($event_data)]);
    }
    
    private function success($data) {
        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $data]);
    }
    
    private function error($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
    }
}

// Initialize API
$pdo = getDBConnection();
$auth = new AuthAPI($pdo);

// Route requests
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        $auth->register();
        break;
    case 'login':
        $auth->login();
        break;
    case 'logout':
        $auth->logout();
        break;
    case 'check':
        $auth->checkAuth();
        break;
    case 'profile':
        $auth->getCurrentUser();
        break;
    case 'update_profile':
        $auth->updateProfile();
        break;
    case 'change_password':
        $auth->changePassword();
        break;
    case 'get_users':
        $auth->getAllUsers();
        break;
    case 'update_user':
        $auth->updateUser();
        break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Action not found']);
        break;
}
?> 