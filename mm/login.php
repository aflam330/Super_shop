<?php
session_start();
require_once 'backend/config.php';

$error_message = '';
$success_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $selected_role = $_POST['role'] ?? 'customer';
    
    if (empty($username) || empty($password)) {
        $error_message = 'Please fill in all fields.';
    } else {
        try {
            $pdo = getDBConnection();
            
            // Get user by username or email
            $stmt = $pdo->prepare("
                SELECT id, username, email, password_hash, first_name, last_name, role, is_active 
                FROM users 
                WHERE (username = ? OR email = ?) AND is_active = 1
            ");
            $stmt->execute([$username, $username]);
            
            if ($stmt->rowCount() === 0) {
                $error_message = 'Invalid credentials.';
            } else {
                $user = $stmt->fetch();
                
                // Verify password
                if (password_verify($password, $user['password_hash'])) {
                    // Check if user role matches selected role (for admin/receptionist)
                    if (($selected_role === 'admin' && $user['role'] !== 'admin') ||
                        ($selected_role === 'receptionist' && $user['role'] !== 'receptionist')) {
                        $error_message = 'You do not have permission to access this role.';
                    } else {
                        // Create session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['first_name'] = $user['first_name'];
                        $_SESSION['last_name'] = $user['last_name'];
                        
                        // Log login event
                        $log_stmt = $pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
                        $log_data = json_encode([
                            'user_id' => $user['id'],
                            'username' => $user['username'],
                            'role' => $user['role']
                        ]);
                        $log_stmt->execute(['user_login', $log_data]);
                        
                        $success_message = 'Login successful! Redirecting...';
                        
                        // Redirect based on role
                        header("refresh:2;url=" . getRedirectUrl($user['role']));
                    }
                } else {
                    $error_message = 'Invalid credentials.';
                }
            }
        } catch (PDOException $e) {
            $error_message = 'Database error. Please try again.';
        }
    }
}

function getRedirectUrl($role) {
    switch ($role) {
        case 'customer':
            return 'index.html';
        case 'receptionist':
            return 'receptionist-dashboard.html';
        case 'admin':
            return 'admin-dashboard.html';
        default:
            return 'index.html';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Super Shop Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .theme-blue { background-color: #3b82f6; }
        .theme-green { background-color: #10b981; }
        .theme-pink { background-color: #ec4899; }
        .theme-yellow { background-color: #f59e42; }
    </style>
</head>
<body class="theme-blue min-h-screen transition-colors duration-500">
    <nav class="flex items-center justify-between p-4 bg-white shadow">
        <div class="text-2xl font-bold text-blue-700">Super Shop SSMS</div>
        <div>
            <button onclick="setTheme('theme-blue')" class="w-6 h-6 bg-blue-500 rounded-full inline-block mx-1" title="Blue Theme"></button>
            <button onclick="setTheme('theme-green')" class="w-6 h-6 bg-green-500 rounded-full inline-block mx-1" title="Green Theme"></button>
            <button onclick="setTheme('theme-pink')" class="w-6 h-6 bg-pink-500 rounded-full inline-block mx-1" title="Pink Theme"></button>
            <button onclick="setTheme('theme-yellow')" class="w-6 h-6 bg-yellow-400 rounded-full inline-block mx-1" title="Yellow Theme"></button>
        </div>
        <div>
            <a href="index.html" class="mx-2 text-gray-700 hover:text-blue-700">Home</a>
            <a href="register.php" class="mx-2 text-gray-700 hover:text-blue-700">Register</a>
            <a href="my-orders.php" class="mx-2 text-gray-700 hover:text-blue-700">History</a>
        </div>
    </nav>

    <main class="max-w-md mx-auto mt-20 p-6">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Welcome Back</h1>
                <p class="text-gray-600">Login to your Super Shop account</p>
            </div>

            <!-- Error/Success Messages -->
            <?php if ($error_message): ?>
                <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-center">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-lg text-center">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="login.php" class="space-y-6">
                <!-- Role Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Login As</label>
                    <div class="grid grid-cols-3 gap-3">
                        <label class="role-btn p-3 border-2 border-gray-200 rounded-lg text-center hover:border-blue-500 focus:outline-none focus:border-blue-500 cursor-pointer">
                            <input type="radio" name="role" value="customer" class="hidden" checked>
                            <div class="text-2xl mb-1">👤</div>
                            <div class="text-sm font-medium">Customer</div>
                        </label>
                        <label class="role-btn p-3 border-2 border-gray-200 rounded-lg text-center hover:border-blue-500 focus:outline-none focus:border-blue-500 cursor-pointer">
                            <input type="radio" name="role" value="receptionist" class="hidden">
                            <div class="text-2xl mb-1">🛎️</div>
                            <div class="text-sm font-medium">Receptionist</div>
                        </label>
                        <label class="role-btn p-3 border-2 border-gray-200 rounded-lg text-center hover:border-blue-500 focus:outline-none focus:border-blue-500 cursor-pointer">
                            <input type="radio" name="role" value="admin" class="hidden">
                            <div class="text-2xl mb-1">⚙️</div>
                            <div class="text-sm font-medium">Admin</div>
                        </label>
                    </div>
                </div>

                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username or Email</label>
                    <input type="text" id="username" name="username" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                           placeholder="Enter your username or email"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" id="password" name="password" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                           placeholder="Enter your password">
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input type="checkbox" id="remember" name="remember" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="remember" class="ml-2 block text-sm text-gray-700">Remember me</label>
                    </div>
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-500">Forgot password?</a>
                </div>

                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                    Login
                </button>
            </form>

            <!-- Demo Credentials -->
            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                <h3 class="text-sm font-medium text-gray-700 mb-2">Demo Credentials:</h3>
                <div class="text-xs text-gray-600 space-y-1">
                    <div><strong>Admin:</strong> admin / admin123</div>
                    <div><strong>Receptionist:</strong> receptionist / receptionist123</div>
                    <div><strong>Customer:</strong> Register a new account</div>
                </div>
            </div>

            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Don't have an account? 
                    <a href="register.php" class="text-blue-600 hover:text-blue-500 font-medium">Register here</a>
                </p>
            </div>
        </div>
    </main>

    <script>
        // Dynamic color theme switcher
        function setTheme(color) {
            document.documentElement.style.setProperty('--tw-bg-opacity', '1');
            document.body.className = color;
        }

        // Role selection styling
        document.querySelectorAll('input[name="role"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Remove active styling from all role buttons
                document.querySelectorAll('.role-btn').forEach(btn => {
                    btn.classList.remove('border-blue-500', 'bg-blue-50');
                    btn.classList.add('border-gray-200');
                });
                
                // Add active styling to selected role button
                const selectedBtn = this.closest('.role-btn');
                selectedBtn.classList.add('border-blue-500', 'bg-blue-50');
            });
        });

        // Initialize with customer role selected
        document.querySelector('input[value="customer"]').closest('.role-btn').classList.add('border-blue-500', 'bg-blue-50');
    </script>
</body>
</html> 