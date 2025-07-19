<?php
session_start();
require_once 'backend/config.php';

$error_message = '';
$success_message = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms = isset($_POST['terms']);
    
    // Validation
    $errors = [];
    
    if (empty($first_name)) $errors[] = 'First name is required.';
    if (empty($last_name)) $errors[] = 'Last name is required.';
    if (empty($username)) $errors[] = 'Username is required.';
    if (empty($email)) $errors[] = 'Email is required.';
    if (empty($password)) $errors[] = 'Password is required.';
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    if (!$terms) {
        $errors[] = 'You must agree to the terms and conditions.';
    }
    
    if (empty($errors)) {
        try {
            $pdo = getDBConnection();
            
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->rowCount() > 0) {
                $error_message = 'Username or email already exists.';
            } else {
                // Hash password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password_hash, first_name, last_name, phone, address, role, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'customer', 1)
                ");
                
                if ($stmt->execute([$username, $email, $password_hash, $first_name, $last_name, $phone, $address])) {
                    $user_id = $pdo->lastInsertId();
                    
                    // Create session
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = 'customer';
                    $_SESSION['first_name'] = $first_name;
                    $_SESSION['last_name'] = $last_name;
                    
                    // Log registration event
                    $log_stmt = $pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
                    $log_data = json_encode([
                        'user_id' => $user_id,
                        'username' => $username,
                        'email' => $email
                    ]);
                    $log_stmt->execute(['user_registration', $log_data]);
                    
                    $success_message = 'Registration successful! Welcome to Super Shop. Redirecting...';
                    header("refresh:3;url=index.html");
                } else {
                    $error_message = 'Registration failed. Please try again.';
                }
            }
        } catch (PDOException $e) {
            $error_message = 'Database error. Please try again.';
        }
    } else {
        $error_message = implode(' ', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Super Shop Management System</title>
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
            <a href="login.php" class="mx-2 text-gray-700 hover:text-blue-700">Login</a>
            <a href="my-orders.php" class="mx-2 text-gray-700 hover:text-blue-700">History</a>
        </div>
    </nav>

    <main class="max-w-2xl mx-auto mt-10 p-6">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Create Account</h1>
                <p class="text-gray-600">Join Super Shop for the best shopping experience</p>
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

            <!-- Registration Form -->
            <form method="POST" action="register.php" class="space-y-6">
                <!-- Personal Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                               placeholder="Enter your first name"
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                               placeholder="Enter your last name"
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username *</label>
                        <input type="text" id="username" name="username" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                               placeholder="Choose a username"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        <p class="text-xs text-gray-500 mt-1">Username must be unique</p>
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                        <input type="email" id="email" name="email" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                               placeholder="Enter your email address"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                    <input type="tel" id="phone" name="phone" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                           placeholder="Enter your phone number"
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>

                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                    <textarea id="address" name="address" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                              placeholder="Enter your full address"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                </div>

                <!-- Password Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                        <input type="password" id="password" name="password" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                               placeholder="Create a strong password">
                        <div class="password-strength mt-2">
                            <div class="text-xs text-gray-500">Password strength: <span id="strength-text">Weak</span></div>
                            <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                                <div id="strength-bar" class="bg-red-500 h-2 rounded-full transition-all duration-300" style="width: 25%"></div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                               placeholder="Confirm your password">
                        <div id="password-match" class="text-xs mt-1"></div>
                    </div>
                </div>

                <!-- Terms and Conditions -->
                <div class="flex items-start">
                    <input type="checkbox" id="terms" name="terms" required 
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mt-1"
                           <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>>
                    <label for="terms" class="ml-2 block text-sm text-gray-700">
                        I agree to the 
                        <a href="#" class="text-blue-600 hover:text-blue-500">Terms and Conditions</a> 
                        and 
                        <a href="#" class="text-blue-600 hover:text-blue-500">Privacy Policy</a>
                    </label>
                </div>

                <div class="flex items-start">
                    <input type="checkbox" id="newsletter" name="newsletter" 
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mt-1"
                           <?php echo isset($_POST['newsletter']) ? 'checked' : ''; ?>>
                    <label for="newsletter" class="ml-2 block text-sm text-gray-700">
                        Subscribe to our newsletter for updates and special offers
                    </label>
                </div>

                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors font-medium">
                    Create Account
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Already have an account? 
                    <a href="login.php" class="text-blue-600 hover:text-blue-500 font-medium">Login here</a>
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

        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            let feedback = [];

            if (password.length >= 8) strength += 25;
            if (/[a-z]/.test(password)) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;

            const strengthBar = document.getElementById('strength-bar');
            const strengthText = document.getElementById('strength-text');

            strengthBar.style.width = strength + '%';

            if (strength <= 25) {
                strengthBar.className = 'bg-red-500 h-2 rounded-full transition-all duration-300';
                strengthText.textContent = 'Weak';
            } else if (strength <= 50) {
                strengthBar.className = 'bg-orange-500 h-2 rounded-full transition-all duration-300';
                strengthText.textContent = 'Fair';
            } else if (strength <= 75) {
                strengthBar.className = 'bg-yellow-500 h-2 rounded-full transition-all duration-300';
                strengthText.textContent = 'Good';
            } else {
                strengthBar.className = 'bg-green-500 h-2 rounded-full transition-all duration-300';
                strengthText.textContent = 'Strong';
            }
        }

        // Password confirmation checker
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('password-match');

            if (confirmPassword === '') {
                matchDiv.textContent = '';
                matchDiv.className = 'text-xs mt-1';
            } else if (password === confirmPassword) {
                matchDiv.textContent = 'Passwords match ✓';
                matchDiv.className = 'text-xs mt-1 text-green-600';
            } else {
                matchDiv.textContent = 'Passwords do not match ✗';
                matchDiv.className = 'text-xs mt-1 text-red-600';
            }
        }

        // Event listeners
        document.getElementById('password').addEventListener('input', function() {
            checkPasswordStrength(this.value);
            checkPasswordMatch();
        });

        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
    </script>
</body>
</html> 