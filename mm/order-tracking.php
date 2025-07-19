<?php
session_start();
require_once 'backend/config.php';

$error_message = '';
$order_data = null;
$tracking_result = null;

// Handle order tracking form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_number = trim($_POST['order_number'] ?? '');
    
    if (empty($order_number)) {
        $error_message = 'Please enter an order number.';
    } else {
        try {
            $pdo = getDBConnection();
            
            // Get order details
            $stmt = $pdo->prepare("
                SELECT o.*, u.username, u.first_name, u.last_name
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.order_number = ?
            ");
            $stmt->execute([$order_number]);
            
            if ($stmt->rowCount() === 0) {
                $error_message = 'Order not found. Please check your order number.';
            } else {
                $order_data = $stmt->fetch();
                
                // Get order items
                $stmt = $pdo->prepare("
                    SELECT oi.*, p.name, p.image_url, p.description
                    FROM order_items oi
                    JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = ?
                ");
                $stmt->execute([$order_data['id']]);
                $order_data['items'] = $stmt->fetchAll();
                
                // Create status history
                $order_data['status_history'] = [
                    [
                        'status' => 'pending',
                        'description' => 'Order placed',
                        'timestamp' => $order_data['created_at'],
                        'completed' => true
                    ]
                ];
                
                // Add status updates based on current status
                if ($order_data['order_status'] !== 'pending') {
                    $order_data['status_history'][] = [
                        'status' => $order_data['order_status'],
                        'description' => ucfirst($order_data['order_status']),
                        'timestamp' => $order_data['updated_at'],
                        'completed' => true
                    ];
                }
                
                // Add future statuses
                $future_statuses = ['processing', 'shipped', 'delivered'];
                $current_status_index = array_search($order_data['order_status'], $future_statuses);
                
                if ($current_status_index === false) {
                    $current_status_index = -1;
                }
                
                for ($i = $current_status_index + 1; $i < count($future_statuses); $i++) {
                    $order_data['status_history'][] = [
                        'status' => $future_statuses[$i],
                        'description' => ucfirst($future_statuses[$i]),
                        'timestamp' => null,
                        'completed' => false
                    ];
                }
                
                $tracking_result = $order_data;
            }
        } catch (PDOException $e) {
            $error_message = 'Database error. Please try again.';
        }
    }
}

function getStatusColor($status) {
    switch ($status) {
        case 'pending': return 'bg-yellow-500';
        case 'processing': return 'bg-blue-500';
        case 'shipped': return 'bg-purple-500';
        case 'delivered': return 'bg-green-500';
        case 'cancelled': return 'bg-red-500';
        default: return 'bg-gray-500';
    }
}

function getStatusIcon($status) {
    switch ($status) {
        case 'pending': return '📋';
        case 'processing': return '⚙️';
        case 'shipped': return '🚚';
        case 'delivered': return '✅';
        case 'cancelled': return '❌';
        default: return '📦';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking - Super Shop Management System</title>
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
            <a href="register.php" class="mx-2 text-gray-700 hover:text-blue-700">Register</a>
            <a href="my-orders.php" class="mx-2 text-gray-700 hover:text-blue-700">History</a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto mt-10 p-6">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-white mb-2">Order Tracking</h1>
            <p class="text-white text-lg">Track your order status and delivery progress</p>
        </div>

        <!-- Tracking Form -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
            <form method="POST" action="order-tracking.php" class="max-w-md mx-auto">
                <div class="mb-6">
                    <label for="order_number" class="block text-sm font-medium text-gray-700 mb-2">Order Number</label>
                    <input type="text" id="order_number" name="order_number" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-lg"
                           placeholder="Enter your order number (e.g., ORD-2024-01-0001)"
                           value="<?php echo htmlspecialchars($_POST['order_number'] ?? ''); ?>">
                    <p class="text-xs text-gray-500 mt-1">You can find your order number in your order confirmation email</p>
                </div>

                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors font-medium text-lg">
                    Track Order
                </button>
            </form>

            <?php if ($error_message): ?>
                <div class="mt-4 p-3 bg-red-100 text-red-700 rounded-lg text-center">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Order Tracking Result -->
        <?php if ($tracking_result): ?>
            <div class="bg-white rounded-lg shadow-lg p-8">
                <!-- Order Header -->
                <div class="border-b border-gray-200 pb-6 mb-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800 mb-2">Order #<?php echo htmlspecialchars($tracking_result['order_number']); ?></h2>
                            <p class="text-gray-600">Placed on <?php echo date('F j, Y \a\t g:i A', strtotime($tracking_result['created_at'])); ?></p>
                        </div>
                        <div class="text-right">
                            <div class="text-3xl font-bold text-blue-600">৳<?php echo number_format($tracking_result['final_amount'], 2); ?></div>
                            <div class="text-sm text-gray-500">Total Amount</div>
                        </div>
                    </div>
                </div>

                <!-- Order Status Timeline -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Order Status</h3>
                    <div class="relative">
                        <?php foreach ($tracking_result['status_history'] as $index => $status): ?>
                            <div class="flex items-center mb-6">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center <?php echo $status['completed'] ? getStatusColor($status['status']) : 'bg-gray-300'; ?> text-white text-lg">
                                        <?php echo getStatusIcon($status['status']); ?>
                                    </div>
                                </div>
                                <div class="ml-4 flex-1">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($status['description']); ?></div>
                                    <?php if ($status['timestamp']): ?>
                                        <div class="text-sm text-gray-500"><?php echo date('F j, Y \a\t g:i A', strtotime($status['timestamp'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($status['completed']): ?>
                                    <div class="text-green-500">
                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($index < count($tracking_result['status_history']) - 1): ?>
                                <div class="ml-5 w-0.5 h-6 bg-gray-300"></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Order Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Order Information</h3>
                        <div class="space-y-2 text-sm">
                            <div><span class="font-medium">Order Number:</span> <?php echo htmlspecialchars($tracking_result['order_number']); ?></div>
                            <div><span class="font-medium">Payment Method:</span> <?php echo ucfirst(htmlspecialchars($tracking_result['payment_method'])); ?></div>
                            <div><span class="font-medium">Payment Status:</span> 
                                <span class="px-2 py-1 rounded text-xs <?php echo $tracking_result['payment_status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo ucfirst(htmlspecialchars($tracking_result['payment_status'])); ?>
                                </span>
                            </div>
                            <?php if ($tracking_result['transaction_id']): ?>
                                <div><span class="font-medium">Transaction ID:</span> <?php echo htmlspecialchars($tracking_result['transaction_id']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Customer Information</h3>
                        <div class="space-y-2 text-sm">
                            <div><span class="font-medium">Name:</span> <?php echo htmlspecialchars($tracking_result['customer_name']); ?></div>
                            <div><span class="font-medium">Email:</span> <?php echo htmlspecialchars($tracking_result['customer_email']); ?></div>
                            <div><span class="font-medium">Phone:</span> <?php echo htmlspecialchars($tracking_result['customer_phone']); ?></div>
                            <?php if ($tracking_result['customer_address']): ?>
                                <div><span class="font-medium">Address:</span> <?php echo htmlspecialchars($tracking_result['customer_address']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Order Items</h3>
                    <div class="space-y-4">
                        <?php foreach ($tracking_result['items'] as $item): ?>
                            <div class="flex items-center p-4 border border-gray-200 rounded-lg">
                                <div class="flex-shrink-0">
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                         class="w-16 h-16 object-cover rounded">
                                </div>
                                <div class="ml-4 flex-1">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <div class="text-sm text-gray-500">Quantity: <?php echo $item['quantity']; ?></div>
                                    <div class="text-sm text-gray-500">Unit Price: ৳<?php echo number_format($item['unit_price'], 2); ?></div>
                                </div>
                                <div class="text-right">
                                    <div class="font-medium text-gray-900">৳<?php echo number_format($item['total_price'], 2); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="mt-8 p-4 bg-gray-50 rounded-lg">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-600">
                            <div>Subtotal: ৳<?php echo number_format($tracking_result['total_amount'], 2); ?></div>
                            <div>Tax: ৳<?php echo number_format($tracking_result['tax_amount'], 2); ?></div>
                            <?php if ($tracking_result['discount_amount'] > 0): ?>
                                <div>Discount: -৳<?php echo number_format($tracking_result['discount_amount'], 2); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-gray-900">৳<?php echo number_format($tracking_result['final_amount'], 2); ?></div>
                            <div class="text-sm text-gray-500">Total Amount</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Help Section -->
        <div class="bg-white rounded-lg shadow-lg p-8 mt-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Need Help?</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Can't find your order number?</h4>
                    <p class="text-sm text-gray-600">Check your order confirmation email or contact our customer support.</p>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Order taking longer than expected?</h4>
                    <p class="text-sm text-gray-600">Contact us at support@supershop.com or call +880 1234-567890</p>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Dynamic color theme switcher
        function setTheme(color) {
            document.documentElement.style.setProperty('--tw-bg-opacity', '1');
            document.body.className = color;
        }
    </script>
</body>
</html> 