<?php
session_start();
require_once 'backend/config.php';

$error_message = '';
$success_message = '';

// Redirect if already logged in as admin
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header('Location: admin-dashboard.html');
    exit;
}

// Handle admin login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $security_code = trim($_POST['security_code'] ?? '');
    
    if (empty($username) || empty($password)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (empty($security_code)) {
        $error_message = 'Security code is required for admin access.';
    } else {
        try {
            $pdo = getDBConnection();
            
            // Get admin user by username or email
            $stmt = $pdo->prepare("
                SELECT id, username, email, password_hash, first_name, last_name, role, is_active 
                FROM users 
                WHERE (username = ? OR email = ?) AND role = 'admin' AND is_active = 1
            ");
            $stmt->execute([$username, $username]);
            
            if ($stmt->rowCount() === 0) {
                $error_message = 'Invalid admin credentials.';
            } else {
                $user = $stmt->fetch();
                
                // Verify password
                if (password_verify($password, $user['password_hash'])) {
                    // Verify security code (you can customize this)
                    $valid_security_codes = ['ADMIN2024', 'SUPERSHOP', 'SECURE123'];
                    
                    if (in_array(strtoupper($security_code), $valid_security_codes)) {
                        // Create admin session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['first_name'] = $user['first_name'];
                        $_SESSION['last_name'] = $user['last_name'];
                        $_SESSION['is_admin'] = true;
                        
                        // Log admin login event
                        $log_stmt = $pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
                        $log_data = json_encode([
                            'user_id' => $user['id'],
                            'username' => $user['username'],
                            'role' => $user['role'],
                            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                        ]);
                        $log_stmt->execute(['admin_login', $log_data]);
                        
                        $success_message = 'Admin login successful! Redirecting to dashboard...';
                        
                        // Redirect to admin dashboard
                        header("refresh:2;url=admin-dashboard.html");
                    } else {
                        $error_message = 'Invalid security code.';
                    }
                } else {
                    $error_message = 'Invalid admin credentials.';
                }
            }
        } catch (PDOException $e) {
            $error_message = 'Database error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Super Shop Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .admin-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="admin-gradient min-h-screen">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <div class="mx-auto h-16 w-16 bg-white rounded-full flex items-center justify-center mb-4">
                    <svg class="h-8 w-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold text-white mb-2">Admin Access</h2>
                <p class="text-white text-opacity-80">Super Shop Management System</p>
            </div>

            <!-- Login Form -->
            <div class="glass-effect rounded-lg p-8">
                <!-- Error/Success Messages -->
                <?php if ($error_message): ?>
                    <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded-lg text-center">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded-lg text-center">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="admin-login.php" class="space-y-6">
                    <div>
                        <label for="username" class="block text-sm font-medium text-white mb-2">Admin Username or Email</label>
                        <input type="text" id="username" name="username" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                               placeholder="Enter admin username or email"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-white mb-2">Admin Password</label>
                        <input type="password" id="password" name="password" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                               placeholder="Enter admin password">
                    </div>

                    <div>
                        <label for="security_code" class="block text-sm font-medium text-white mb-2">Security Code</label>
                        <input type="text" id="security_code" name="security_code" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                               placeholder="Enter security code"
                               maxlength="10">
                        <p class="text-xs text-white text-opacity-70 mt-1">Additional security verification required for admin access</p>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input type="checkbox" id="remember" name="remember" class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                            <label for="remember" class="ml-2 block text-sm text-white">Remember this device</label>
                        </div>
                        <a href="#" class="text-sm text-white hover:text-purple-200">Forgot password?</a>
                    </div>

                    <button type="submit" 
                            class="w-full bg-purple-600 text-white py-3 px-4 rounded-lg hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition-colors font-medium text-lg">
                        🔐 Access Admin Panel
                    </button>
                </form>

                <!-- Admin Credentials Info -->
                <div class="mt-6 p-4 bg-white bg-opacity-10 rounded-lg">
                    <h3 class="text-sm font-medium text-white mb-2">Demo Admin Credentials:</h3>
                    <div class="text-xs text-white text-opacity-80 space-y-1">
                        <div><strong>Username:</strong> admin</div>
                        <div><strong>Password:</strong> admin123</div>
                        <div><strong>Security Code:</strong> ADMIN2024</div>
                    </div>
                </div>

                <!-- Navigation Links -->
                <div class="mt-6 text-center space-y-2">
                    <a href="index.html" class="block text-sm text-white hover:text-purple-200">← Back to Home</a>
                    <a href="login.php" class="block text-sm text-white hover:text-purple-200">← Regular User Login</a>
                    <a href="my-orders.php" class="block text-sm text-white hover:text-purple-200">📋 Order History</a>
                </div>
            </div>

            <!-- Security Notice -->
            <div class="text-center">
                <p class="text-xs text-white text-opacity-60">
                    🔒 Secure admin access. Unauthorized access attempts will be logged.
                </p>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus on username field
        document.getElementById('username').focus();

        // Security code input formatting
        document.getElementById('security_code').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const securityCode = document.getElementById('security_code').value.trim();

            if (!username || !password || !securityCode) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
        });

        // Show/hide password toggle (optional enhancement)
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const type = passwordField.type === 'password' ? 'text' : 'password';
            passwordField.type = type;
        }
    </script>
</body>
</html> 