<?php
session_start();
require_once 'backend/config.php';

$error_message = '';
$success_message = '';

// Handle customer service requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_type = $_POST['service_type'] ?? '';
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($customer_name) || empty($description)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        try {
            $pdo = getDBConnection();
            
            // Create customer service request
            $request_id = 'CSR-' . date('Y-m-d') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $stmt = $pdo->prepare("
                INSERT INTO customer_service_requests (
                    request_id, customer_name, customer_email, customer_phone,
                    service_type, description, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP)
            ");
            
            if ($stmt->execute([$request_id, $customer_name, $customer_email, $customer_phone, $service_type, $description])) {
                // Log service request
                $log_stmt = $pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
                $log_data = json_encode([
                    'request_id' => $request_id,
                    'service_type' => $service_type,
                    'customer_name' => $customer_name
                ]);
                $log_stmt->execute(['customer_service_request', $log_data]);
                
                $success_message = "Service request submitted successfully! Your Request ID is: $request_id";
            } else {
                $error_message = 'Error submitting service request. Please try again.';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error. Please try again.';
        }
    }
}

// Get service statistics
try {
    $pdo = getDBConnection();
    
    // Get recent orders for customer
    $recent_orders = [];
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("
            SELECT order_number, final_amount, order_status, created_at
            FROM orders
            WHERE user_id = ? AND order_status != 'deleted'
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $recent_orders = $stmt->fetchAll();
    }
    
    // Get popular products for recommendations
    $stmt = $pdo->prepare("
        SELECT p.*, COUNT(oi.id) as times_ordered
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        WHERE p.is_active = 1
        GROUP BY p.id
        ORDER BY times_ordered DESC
        LIMIT 6
    ");
    $stmt->execute();
    $popular_products = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = 'Database error. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Service - Super Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .theme-blue { background-color: #3b82f6; }
        .theme-green { background-color: #10b981; }
        .theme-pink { background-color: #ec4899; }
        .theme-yellow { background-color: #f59e42; }
    </style>
</head>
<body class="theme-blue min-h-screen">
    <nav class="flex items-center justify-between p-4 bg-white shadow">
        <button onclick="window.location.href='index.html'" class="text-2xl font-bold text-blue-700 hover:text-blue-800 transition-colors cursor-pointer">Super Shop</button>
        <div>
            <button onclick="setTheme('theme-blue')" class="w-6 h-6 bg-blue-500 rounded-full inline-block mx-1" title="Blue Theme"></button>
            <button onclick="setTheme('theme-green')" class="w-6 h-6 bg-green-500 rounded-full inline-block mx-1" title="Green Theme"></button>
            <button onclick="setTheme('theme-pink')" class="w-6 h-6 bg-pink-500 rounded-full inline-block mx-1" title="Pink Theme"></button>
            <button onclick="setTheme('theme-yellow')" class="w-6 h-6 bg-yellow-400 rounded-full inline-block mx-1" title="Yellow Theme"></button>
        </div>
        <div>
            <a href="index.html" class="mx-2 text-gray-700 hover:text-blue-700">Home</a>
            <a href="catalog.html" class="mx-2 text-gray-700 hover:text-blue-700">Catalog</a>
            <a href="cart.html" class="mx-2 text-gray-700 hover:text-blue-700">Cart</a>
            <a href="my-orders.php" class="mx-2 text-gray-700 hover:text-blue-700">History</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="logout.php" class="mx-2 text-gray-700 hover:text-blue-700">Logout</a>
            <?php else: ?>
                <a href="login.php" class="mx-2 text-gray-700 hover:text-blue-700">Login</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-8 px-4">
        <!-- Header -->
        <div class="mb-8 text-center">
            <h1 class="text-4xl font-bold text-white mb-4">🎧 Customer Service</h1>
            <p class="text-white text-opacity-80 text-lg">We're here to help you with all your shopping needs</p>
        </div>

        <!-- Messages -->
        <?php if ($error_message): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <!-- Service Features Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <!-- Generate Bill -->
            <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow">
                <div class="text-center mb-4">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <span class="text-2xl">💳</span>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800">Generate Bill</h3>
                </div>
                <p class="text-gray-600 mb-4">Need a bill for your purchase? Our receptionist can help you generate one quickly.</p>
                <button onclick="showServiceModal('bill')" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                    Request Bill
                </button>
            </div>

            <!-- Product Recommendations -->
            <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow">
                <div class="text-center mb-4">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <span class="text-2xl">⭐</span>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800">Product Recommendations</h3>
                </div>
                <p class="text-gray-600 mb-4">Get personalized product recommendations based on your preferences and history.</p>
                <a href="product-recommendations.php" class="block w-full bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition-colors text-center">
                    View Recommendations
                </a>
            </div>

            <!-- Process Returns -->
            <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow">
                <div class="text-center mb-4">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <span class="text-2xl">🔄</span>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800">Process Returns</h3>
                </div>
                <p class="text-gray-600 mb-4">Need to return a product? We'll help you process your return quickly and efficiently.</p>
                <a href="return-policy.php" class="block w-full bg-red-600 text-white py-2 px-4 rounded-lg hover:bg-red-700 transition-colors text-center">
                    Process Return
                </a>
            </div>

            <!-- Weekly Reports -->
            <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow">
                <div class="text-center mb-4">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <span class="text-2xl">📋</span>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800">Weekly Reports</h3>
                </div>
                <p class="text-gray-600 mb-4">Access detailed weekly reports about sales, trends, and product performance.</p>
                <button onclick="showServiceModal('reports')" class="w-full bg-purple-600 text-white py-2 px-4 rounded-lg hover:bg-purple-700 transition-colors">
                    Request Report
                </button>
            </div>

            <!-- Return Policy -->
            <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow">
                <div class="text-center mb-4">
                    <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <span class="text-2xl">📜</span>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800">Return Policy</h3>
                </div>
                <p class="text-gray-600 mb-4">Learn about our customer-friendly return and refund policy.</p>
                <a href="return-policy.php" class="block w-full bg-yellow-600 text-white py-2 px-4 rounded-lg hover:bg-yellow-700 transition-colors text-center">
                    View Policy
                </a>
            </div>

            <!-- General Support -->
            <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow">
                <div class="text-center mb-4">
                    <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <span class="text-2xl">🎧</span>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800">General Support</h3>
                </div>
                <p class="text-gray-600 mb-4">Have a question or need assistance? Our customer service team is here to help.</p>
                <button onclick="showServiceModal('support')" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-lg hover:bg-indigo-700 transition-colors">
                    Get Help
                </button>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Recent Orders (if logged in) -->
            <?php if (isset($_SESSION['user_id']) && !empty($recent_orders)): ?>
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">📦 Your Recent Orders</h3>
                    <div class="space-y-3">
                        <?php foreach ($recent_orders as $order): ?>
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <div class="font-medium"><?php echo htmlspecialchars($order['order_number']); ?></div>
                                    <div class="text-sm text-gray-500">৳<?php echo number_format($order['final_amount'], 2); ?></div>
                                </div>
                                <div class="text-right">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $order['order_status'] === 'delivered' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo ucfirst($order['order_status']); ?>
                                    </span>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4">
                        <a href="my-orders.php" class="text-blue-600 hover:text-blue-800 font-medium">View All Orders →</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Popular Products -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">🔥 Popular Products</h3>
                <div class="grid grid-cols-2 gap-3">
                    <?php foreach ($popular_products as $product): ?>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <div class="font-medium text-sm"><?php echo htmlspecialchars($product['name']); ?></div>
                            <div class="text-sm text-gray-500">৳<?php echo number_format($product['price'], 2); ?></div>
                            <div class="text-xs text-gray-400"><?php echo $product['times_ordered']; ?> orders</div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4">
                    <a href="product-recommendations.php" class="text-blue-600 hover:text-blue-800 font-medium">View All Recommendations →</a>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">📞 Contact Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-1">Phone Support</h4>
                    <p class="text-gray-600">+880 1712-345678</p>
                    <p class="text-sm text-gray-500">Mon-Fri: 9AM-6PM</p>
                </div>
                
                <div class="text-center">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-1">Email Support</h4>
                    <p class="text-gray-600">support@supershop.com</p>
                    <p class="text-sm text-gray-500">24/7 Support</p>
                </div>
                
                <div class="text-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-1">Live Chat</h4>
                    <p class="text-gray-600">Available on website</p>
                    <p class="text-sm text-gray-500">Instant Support</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Service Request Modal -->
    <div id="serviceModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Service Request</h3>
                    
                    <form method="POST" action="customer-service.php" class="space-y-4">
                        <input type="hidden" id="serviceType" name="service_type" value="">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                            <input type="text" name="customer_name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                                   placeholder="Your full name">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                            <input type="email" name="customer_email"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                                   placeholder="your.email@example.com">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                            <input type="tel" name="customer_phone"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                                   placeholder="01XXXXXXXXX">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                            <textarea name="description" rows="4" required
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                                      placeholder="Please describe your request or issue..."></textarea>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="hideServiceModal()" 
                                    class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                Submit Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function setTheme(color) {
            document.body.className = color;
        }

        function showServiceModal(serviceType) {
            document.getElementById('serviceType').value = serviceType;
            document.getElementById('serviceModal').classList.remove('hidden');
        }

        function hideServiceModal() {
            document.getElementById('serviceModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('serviceModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideServiceModal();
            }
        });
    </script>
</body>
</html> 