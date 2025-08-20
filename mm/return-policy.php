<?php
session_start();
require_once 'backend/config.php';

$error_message = '';
$success_message = '';

// Handle return request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_return'])) {
    $order_number = trim($_POST['order_number']);
    $customer_name = trim($_POST['customer_name']);
    $customer_email = trim($_POST['customer_email']);
    $reason = trim($_POST['reason']);
    $return_type = $_POST['return_type'];
    
    if (empty($order_number) || empty($customer_name) || empty($reason)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        try {
            $pdo = getDBConnection();
            
            // Verify order exists and is eligible for return
            $stmt = $pdo->prepare("
                SELECT o.*, u.first_name, u.last_name, u.email
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.order_number = ? AND o.order_status = 'delivered'
            ");
            $stmt->execute([$order_number]);
            
            if ($stmt->rowCount() === 0) {
                $error_message = 'Order not found or not eligible for return. Only delivered orders can be returned.';
            } else {
                $order = $stmt->fetch();
                
                // Check if return already exists
                $stmt = $pdo->prepare("SELECT id FROM returns WHERE order_id = ?");
                $stmt->execute([$order['id']]);
                
                if ($stmt->rowCount() > 0) {
                    $error_message = 'Return request already exists for this order.';
                } else {
                    // Create return request
                    $return_id = 'RET-' . date('Y-m-d') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO returns (
                            order_id, user_id, product_id, return_reason, return_status
                        ) VALUES (?, ?, ?, ?, 'pending')
                    ");
                    
                    if ($stmt->execute([$order['id'], null, 1, $reason])) {
                        // Log return request
                        $log_stmt = $pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
                        $log_data = json_encode([
                            'return_id' => $return_id,
                            'order_number' => $order_number,
                            'customer_name' => $customer_name,
                            'reason' => $reason
                        ]);
                        $log_stmt->execute(['return_requested', $log_data]);
                        
                        $success_message = "Return request submitted successfully! Your Return ID is: $return_id";
                    } else {
                        $error_message = 'Error submitting return request. Please try again.';
                    }
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
    <title>Return Policy - Super Shop</title>
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
            <a href="order-tracking.php" class="mx-2 text-gray-700 hover:text-blue-700">Track Order</a>
            <a href="my-orders.php" class="mx-2 text-gray-700 hover:text-blue-700">History</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="logout.php" class="mx-2 text-gray-700 hover:text-blue-700">Logout</a>
            <?php else: ?>
                <a href="login.php" class="mx-2 text-gray-700 hover:text-blue-700">Login</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="max-w-6xl mx-auto py-8 px-4">
        <!-- Header -->
        <div class="mb-8 text-center">
            <h1 class="text-4xl font-bold text-white mb-4">📜 Return Policy</h1>
            <p class="text-white text-opacity-80 text-lg">Our customer-friendly return and refund policy</p>
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

        <!-- Return Policy Information -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Policy Details -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">🔄 Return & Refund Policy</h2>
                
                <div class="space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">📅 Return Window</h3>
                        <ul class="list-disc list-inside text-gray-600 space-y-2">
                            <li><strong>30 Days:</strong> Standard return window for most products</li>
                            <li><strong>14 Days:</strong> Electronics and gadgets</li>
                            <li><strong>7 Days:</strong> Perishable items and food products</li>
                            <li><strong>No Returns:</strong> Personal care items, undergarments, and custom products</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">✅ Return Conditions</h3>
                        <ul class="list-disc list-inside text-gray-600 space-y-2">
                            <li>Product must be in original condition</li>
                            <li>Original packaging and tags intact</li>
                            <li>No signs of use or damage</li>
                            <li>Proof of purchase required</li>
                            <li>Return shipping label provided by us</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">💰 Refund Process</h3>
                        <ul class="list-disc list-inside text-gray-600 space-y-2">
                            <li><strong>Full Refund:</strong> Product in perfect condition</li>
                            <li><strong>Partial Refund:</strong> Minor wear and tear (up to 20% deduction)</li>
                            <li><strong>No Refund:</strong> Damaged or used products</li>
                            <li><strong>Processing Time:</strong> 3-5 business days after receiving return</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">🚚 Return Shipping</h3>
                        <ul class="list-disc list-inside text-gray-600 space-y-2">
                            <li>Free return shipping for defective products</li>
                            <li>Customer pays return shipping for change of mind</li>
                            <li>Return label provided via email</li>
                            <li>Track your return shipment online</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Return Request Form -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">📝 Request a Return</h2>
                
                <form method="POST" action="return-policy.php" class="space-y-4">
                    <div>
                        <label for="order_number" class="block text-sm font-medium text-gray-700 mb-2">
                            Order Number *
                        </label>
                        <input type="text" id="order_number" name="order_number" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="e.g., ORD-2024-01-0001">
                    </div>
                    
                    <div>
                        <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Full Name *
                        </label>
                        <input type="text" id="customer_name" name="customer_name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Your full name">
                    </div>
                    
                    <div>
                        <label for="customer_email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email Address
                        </label>
                        <input type="email" id="customer_email" name="customer_email"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="your.email@example.com">
                    </div>
                    
                    <div>
                        <label for="return_type" class="block text-sm font-medium text-gray-700 mb-2">
                            Return Type *
                        </label>
                        <select id="return_type" name="return_type" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select return type</option>
                            <option value="defective">Defective Product</option>
                            <option value="wrong_item">Wrong Item Received</option>
                            <option value="damaged">Damaged in Transit</option>
                            <option value="change_mind">Change of Mind</option>
                            <option value="size_issue">Size/Fit Issue</option>
                            <option value="quality_issue">Quality Issue</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="reason" class="block text-sm font-medium text-gray-700 mb-2">
                            Reason for Return *
                        </label>
                        <textarea id="reason" name="reason" rows="4" required
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                  placeholder="Please provide detailed reason for return..."></textarea>
                    </div>
                    
                    <button type="submit" name="submit_return" 
                            class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 font-medium transition-colors">
                        📤 Submit Return Request
                    </button>
                </form>
            </div>
        </div>

        <!-- Return Process Steps -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">🔄 Return Process</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="text-center">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-bold text-blue-600">1</span>
                    </div>
                    <h3 class="font-semibold text-gray-800 mb-2">Submit Request</h3>
                    <p class="text-sm text-gray-600">Fill out the return form with order details and reason</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-bold text-green-600">2</span>
                    </div>
                    <h3 class="font-semibold text-gray-800 mb-2">Get Approval</h3>
                    <p class="text-sm text-gray-600">We'll review your request and send approval within 24 hours</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-bold text-yellow-600">3</span>
                    </div>
                    <h3 class="font-semibold text-gray-800 mb-2">Ship Back</h3>
                    <p class="text-sm text-gray-600">Pack the item securely and ship using provided label</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-bold text-purple-600">4</span>
                    </div>
                    <h3 class="font-semibold text-gray-800 mb-2">Receive Refund</h3>
                    <p class="text-sm text-gray-600">Get your refund processed within 3-5 business days</p>
                </div>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">❓ Frequently Asked Questions</h2>
            
            <div class="space-y-4">
                <div class="border-b border-gray-200 pb-4">
                    <h3 class="font-semibold text-gray-800 mb-2">How long does the return process take?</h3>
                    <p class="text-gray-600">The entire process typically takes 7-10 business days from request to refund.</p>
                </div>
                
                <div class="border-b border-gray-200 pb-4">
                    <h3 class="font-semibold text-gray-800 mb-2">Can I return items without original packaging?</h3>
                    <p class="text-gray-600">Original packaging is required for full refund. Returns without packaging may receive partial refund.</p>
                </div>
                
                <div class="border-b border-gray-200 pb-4">
                    <h3 class="font-semibold text-gray-800 mb-2">What if my item arrives damaged?</h3>
                    <p class="text-gray-600">Contact us within 48 hours of delivery. We'll provide free return shipping and full refund.</p>
                </div>
                
                <div class="border-b border-gray-200 pb-4">
                    <h3 class="font-semibold text-gray-800 mb-2">Do I need to pay for return shipping?</h3>
                    <p class="text-gray-600">Free return shipping for defective/damaged items. Customer pays for change of mind returns.</p>
                </div>
                
                <div class="border-b border-gray-200 pb-4">
                    <h3 class="font-semibold text-gray-800 mb-2">How will I receive my refund?</h3>
                    <p class="text-gray-600">Refunds are processed to the original payment method used for the purchase.</p>
                </div>
                
                <div>
                    <h3 class="font-semibold text-gray-800 mb-2">Can I exchange an item instead of returning?</h3>
                    <p class="text-gray-600">Yes, you can request an exchange. Please specify the desired replacement item in your return request.</p>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="bg-white rounded-lg shadow-lg p-6 mt-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">📞 Need Help?</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 mb-1">Phone Support</h3>
                    <p class="text-gray-600">+880 1712-345678</p>
                    <p class="text-sm text-gray-500">Mon-Fri: 9AM-6PM</p>
                </div>
                
                <div class="text-center">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 mb-1">Email Support</h3>
                    <p class="text-gray-600">returns@supershop.com</p>
                    <p class="text-sm text-gray-500">24/7 Support</p>
                </div>
                
                <div class="text-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 mb-1">Live Chat</h3>
                    <p class="text-gray-600">Available on website</p>
                    <p class="text-sm text-gray-500">Instant Support</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function setTheme(color) {
            document.body.className = color;
        }
    </script>
</body>
</html> 