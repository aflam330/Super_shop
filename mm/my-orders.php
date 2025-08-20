<?php
session_start();
require_once 'backend/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error_message = '';
$success_message = '';
$orders = [];

try {
    $pdo = getDBConnection();
    
    // Get all orders for the current user with complete order items details
    $stmt = $pdo->prepare("
        SELECT o.*, 
               COUNT(oi.id) as total_items,
               SUM(oi.quantity) as total_quantity,
               GROUP_CONCAT(CONCAT(p.name, ' (', oi.quantity, ')') SEPARATOR ', ') as items_summary
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE o.user_id = ? AND o.order_status != 'deleted'
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $orders = $stmt->fetchAll();
    
    // Get total purchase statistics
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            SUM(o.final_amount) as total_spent,
            COUNT(oi.id) as total_products_purchased,
            SUM(oi.quantity) as total_items_purchased
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ? AND o.order_status != 'deleted'
    ");
    $stats_stmt->execute([$_SESSION['user_id']]);
    $purchase_stats = $stats_stmt->fetch();
    
} catch (PDOException $e) {
    $error_message = 'Database error. Please try again.';
}

// Handle order deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    $order_id = $_POST['order_id'];
    
    try {
        // Check if order belongs to user and is deletable
        $stmt = $pdo->prepare("
            SELECT order_status, order_number 
            FROM orders 
            WHERE id = ? AND user_id = ? AND order_status IN ('pending', 'cancelled')
        ");
        $stmt->execute([$order_id, $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            $order = $stmt->fetch();
            
            // Soft delete the order
            $update_stmt = $pdo->prepare("
                UPDATE orders 
                SET order_status = 'deleted', updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $update_stmt->execute([$order_id]);
            
            // Log the deletion event
            $log_stmt = $pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
            $log_data = json_encode([
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'order_id' => $order_id,
                'order_number' => $order['order_number']
            ]);
            $log_stmt->execute(['order_deleted', $log_data]);
            
            $success_message = 'Order deleted successfully!';
            
            // Refresh the page to show updated data
            header("refresh:2;url=my-orders.php");
        } else {
            $error_message = 'Order cannot be deleted or does not exist.';
        }
    } catch (PDOException $e) {
        $error_message = 'Error deleting order. Please try again.';
    }
}

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $order_id = $_POST['order_id'];
    $cancel_reason = trim($_POST['cancel_reason']);
    
    if (empty($cancel_reason)) {
        $error_message = 'Please provide a reason for cancellation.';
    } else {
        try {
            // Check if order belongs to user and is cancellable
            $stmt = $pdo->prepare("
                SELECT order_status, order_number 
                FROM orders 
                WHERE id = ? AND user_id = ? AND order_status IN ('pending', 'processing')
            ");
            $stmt->execute([$order_id, $_SESSION['user_id']]);
            
            if ($stmt->rowCount() > 0) {
                $order = $stmt->fetch();
                
                // Update order status to cancelled
                $update_stmt = $pdo->prepare("
                    UPDATE orders 
                    SET order_status = 'cancelled', updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $update_stmt->execute([$order_id]);
                
                // Log the cancellation event
                $log_stmt = $pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
                $log_data = json_encode([
                    'user_id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username'],
                    'order_id' => $order_id,
                    'order_number' => $order['order_number'],
                    'cancel_reason' => $cancel_reason
                ]);
                $log_stmt->execute(['order_cancelled', $log_data]);
                
                $success_message = 'Order cancelled successfully!';
                
                // Refresh the page to show updated data
                header("refresh:2;url=my-orders.php");
            } else {
                $error_message = 'Order cannot be cancelled or does not exist.';
            }
        } catch (PDOException $e) {
            $error_message = 'Error cancelling order. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Purchase History - Super Shop</title>
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
            <a href="my-orders.php" class="mx-2 text-blue-700 font-semibold">History</a>
            <a href="logout.php" class="mx-2 text-gray-700 hover:text-blue-700">Logout</a>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-8 px-4">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">My Complete Purchase History</h1>
            <p class="text-white text-opacity-80">Welcome back, <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>!</p>
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

        <!-- Purchase Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white bg-opacity-10 backdrop-blur-sm p-6 rounded-lg border border-white border-opacity-20">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-500 text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-white text-opacity-80">Total Orders</p>
                        <p class="text-2xl font-semibold text-white"><?php echo $purchase_stats['total_orders'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white bg-opacity-10 backdrop-blur-sm p-6 rounded-lg border border-white border-opacity-20">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-500 text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-white text-opacity-80">Total Spent</p>
                        <p class="text-2xl font-semibold text-white">৳<?php echo number_format($purchase_stats['total_spent'] ?? 0, 2); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white bg-opacity-10 backdrop-blur-sm p-6 rounded-lg border border-white border-opacity-20">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-500 text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-white text-opacity-80">Products Bought</p>
                        <p class="text-2xl font-semibold text-white"><?php echo $purchase_stats['total_products_purchased'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white bg-opacity-10 backdrop-blur-sm p-6 rounded-lg border border-white border-opacity-20">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-500 text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-white text-opacity-80">Total Items</p>
                        <p class="text-2xl font-semibold text-white"><?php echo $purchase_stats['total_items_purchased'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mb-6 flex flex-wrap gap-4">
            <a href="index.html" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors">
                🛒 Continue Shopping
            </a>
            <a href="order-tracking.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                📍 Track New Order
            </a>
            <button onclick="showReorderModal()" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition-colors">
                🔄 Reorder Items
            </button>
            <button onclick="exportPurchaseHistory()" class="bg-yellow-600 text-white px-6 py-3 rounded-lg hover:bg-yellow-700 transition-colors">
                📄 Export History
            </button>
        </div>

        <!-- Complete Purchase History -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b">
                <h2 class="text-xl font-semibold text-gray-800">All My Purchases - Complete Product History</h2>
                <p class="text-sm text-gray-600">Total Orders: <?php echo count($orders); ?> | Total Products: <?php echo $purchase_stats['total_products_purchased'] ?? 0; ?></p>
            </div>
            
            <?php if (empty($orders)): ?>
                <div class="p-8 text-center">
                    <div class="text-gray-500 mb-4">
                        <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No purchases yet</h3>
                    <p class="text-gray-500">Start shopping to see your complete purchase history here.</p>
                    <a href="index.html" class="mt-4 inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        Start Shopping
                    </a>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($orders as $order): ?>
                        <div class="p-6">
                            <!-- Order Header with Order Number -->
                            <div class="flex items-center justify-between mb-6">
                                <div>
                                    <div class="flex items-center space-x-3 mb-2">
                                        <h3 class="text-2xl font-bold text-gray-900">
                                            Order #<?php echo htmlspecialchars($order['order_number']); ?>
                                        </h3>
                                        <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo getStatusBadgeClass($order['order_status']); ?>">
                                            <?php echo ucfirst($order['order_status']); ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600">
                                        Purchased on <?php echo date('F j, Y \a\t g:i A', strtotime($order['created_at'])); ?>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        <?php echo $order['total_items']; ?> different products, <?php echo $order['total_quantity']; ?> total items
                                    </p>
                                </div>
                                <div class="text-right">
                                    <div class="text-3xl font-bold text-blue-600">
                                        ৳<?php echo number_format($order['final_amount'], 2); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">Total Paid</div>
                                </div>
                            </div>

                            <!-- Complete Product List - What I Bought -->
                            <div class="mb-6">
                                <h4 class="text-xl font-bold text-gray-800 mb-4">📦 Complete Product List - What I Bought:</h4>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <?php
                                    // Get detailed order items with complete information
                                    $items_stmt = $pdo->prepare("
                                        SELECT oi.*, p.name, p.price, p.image_url, p.category, p.description
                                        FROM order_items oi
                                        JOIN products p ON oi.product_id = p.id
                                        WHERE oi.order_id = ?
                                        ORDER BY p.name
                                    ");
                                    $items_stmt->execute([$order['id']]);
                                    $order_items = $items_stmt->fetchAll();
                                    ?>
                                    
                                    <div class="space-y-4">
                                        <?php foreach ($order_items as $index => $item): ?>
                                            <div class="flex items-center justify-between p-4 bg-white rounded-lg border shadow-sm">
                                                <div class="flex items-center space-x-4">
                                                    <div class="w-20 h-20 bg-gray-200 rounded-lg flex items-center justify-center flex-shrink-0">
                                                        <?php if ($item['image_url']): ?>
                                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                                 class="w-full h-full object-cover rounded-lg">
                                                        <?php else: ?>
                                                            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                                            </svg>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="flex-1">
                                                        <div class="flex items-center space-x-2 mb-1">
                                                            <h5 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($item['name']); ?></h5>
                                                            <span class="px-2 py-1 rounded text-xs bg-blue-100 text-blue-800"><?php echo ucfirst($item['category']); ?></span>
                                                        </div>
                                                        <?php if ($item['description']): ?>
                                                            <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($item['description']); ?></p>
                                                        <?php endif; ?>
                                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                                            <div>
                                                                <span class="font-medium text-gray-700">Quantity:</span>
                                                                <span class="text-gray-900 font-bold"><?php echo $item['quantity']; ?></span>
                                                            </div>
                                                            <div>
                                                                <span class="font-medium text-gray-700">Unit Price:</span>
                                                                <span class="text-gray-900 font-bold">৳<?php echo number_format($item['price'], 2); ?></span>
                                                            </div>
                                                            <div>
                                                                <span class="font-medium text-gray-700">Item Total:</span>
                                                                <span class="text-gray-900 font-bold">৳<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                                            </div>
                                                            <div>
                                                                <span class="font-medium text-gray-700">Product ID:</span>
                                                                <span class="text-gray-900">#<?php echo $item['product_id']; ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Order Summary with Complete Breakdown -->
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-gray-800 mb-3">💳 Payment Information:</h4>
                                    <div class="space-y-2 text-sm">
                                        <div><span class="font-medium">Method:</span> <?php echo ucfirst($order['payment_method']); ?></div>
                                        <div><span class="font-medium">Status:</span> 
                                            <span class="px-2 py-1 rounded text-xs <?php echo $order['payment_status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                <?php echo ucfirst($order['payment_status']); ?>
                                            </span>
                                        </div>
                                        <?php if ($order['transaction_id']): ?>
                                            <div><span class="font-medium">Transaction ID:</span> <?php echo htmlspecialchars($order['transaction_id']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="bg-green-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-gray-800 mb-3">💰 Order Summary:</h4>
                                    <div class="space-y-2 text-sm">
                                        <div><span class="font-medium">Subtotal:</span> ৳<?php echo number_format($order['total_amount'], 2); ?></div>
                                        <div><span class="font-medium">Tax:</span> ৳<?php echo number_format($order['tax_amount'], 2); ?></div>
                                        <?php if ($order['discount_amount'] > 0): ?>
                                            <div><span class="font-medium">Discount:</span> -৳<?php echo number_format($order['discount_amount'], 2); ?></div>
                                        <?php endif; ?>
                                        <div class="font-bold text-lg text-green-600 border-t pt-2">৳<?php echo number_format($order['final_amount'], 2); ?></div>
                                    </div>
                                </div>

                                <div class="bg-purple-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-gray-800 mb-3">📊 Order Statistics:</h4>
                                    <div class="space-y-2 text-sm">
                                        <div><span class="font-medium">Products:</span> <?php echo $order['total_items']; ?> different items</div>
                                        <div><span class="font-medium">Total Items:</span> <?php echo $order['total_quantity']; ?> pieces</div>
                                        <div><span class="font-medium">Average Price:</span> ৳<?php echo number_format($order['final_amount'] / $order['total_quantity'], 2); ?> per item</div>
                                        <div><span class="font-medium">Order Date:</span> <?php echo date('M j, Y', strtotime($order['created_at'])); ?></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Order Actions -->
                            <div class="flex flex-wrap gap-3">
                                <button onclick="trackOrder('<?php echo htmlspecialchars($order['order_number']); ?>')" 
                                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
                                    📍 Track Order
                                </button>
                                
                                <button onclick="viewOrderDetails(<?php echo $order['id']; ?>)" 
                                        class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm">
                                    👁️ View Details
                                </button>
                                
                                <button onclick="reorderItems(<?php echo $order['id']; ?>)" 
                                        class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 text-sm">
                                    🔄 Reorder Items
                                </button>
                                
                                <?php if ($order['order_status'] === 'delivered'): ?>
                                    <button onclick="requestReturn(<?php echo $order['id']; ?>)" 
                                            class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 text-sm">
                                        🔄 Request Return
                                    </button>
                                <?php endif; ?>
                                
                                <?php if (in_array($order['order_status'], ['pending', 'processing'])): ?>
                                    <button onclick="showCancelModal(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>')" 
                                            class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm">
                                        ❌ Cancel Order
                                    </button>
                                <?php endif; ?>
                                
                                <?php if (in_array($order['order_status'], ['pending', 'cancelled'])): ?>
                                    <button onclick="showDeleteModal(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>')" 
                                            class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 text-sm">
                                        🗑️ Delete Order
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cancel Order Modal -->
    <div id="cancelModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Cancel Order</h3>
                <form method="POST" action="my-orders.php">
                    <input type="hidden" id="cancelOrderId" name="order_id">
                    <div class="mb-4">
                        <label for="cancel_reason" class="block text-sm font-medium text-gray-700 mb-2">
                            Reason for Cancellation *
                        </label>
                        <textarea id="cancel_reason" name="cancel_reason" rows="4" required
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                  placeholder="Please provide a reason for cancelling this order..."></textarea>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="hideCancelModal()" 
                                class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">
                            Cancel
                        </button>
                        <button type="submit" name="cancel_order" 
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                            Confirm Cancellation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Order Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Delete Order</h3>
                <p class="text-gray-600 mb-6">Are you sure you want to delete this order? This action cannot be undone.</p>
                <form method="POST" action="my-orders.php">
                    <input type="hidden" id="deleteOrderId" name="order_id">
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="hideDeleteModal()" 
                                class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">
                            Cancel
                        </button>
                        <button type="submit" name="delete_order" 
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                            Delete Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reorder Modal -->
    <div id="reorderModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-2xl w-full p-6 max-h-screen overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-900">Reorder Items</h3>
                    <button onclick="hideReorderModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div id="reorderContent">
                    <!-- Reorder items will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="orderDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-2xl w-full p-6 max-h-screen overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Order Details</h3>
                    <button onclick="hideOrderDetailsModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div id="orderDetailsContent">
                    <!-- Order details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        function setTheme(color) {
            document.body.className = color;
        }

        function trackOrder(orderNumber) {
            window.open(`order-tracking.php?order_number=${orderNumber}`, '_blank');
        }

        function viewOrderDetails(orderId) {
            fetch(`backend/api/orders.php?action=getOrderDetails&order_id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('orderDetailsContent').innerHTML = formatOrderDetails(data.order);
                        document.getElementById('orderDetailsModal').classList.remove('hidden');
                    } else {
                        alert('Error loading order details: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading order details');
                });
        }

        function formatOrderDetails(order) {
            return `
                <div class="space-y-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-900 mb-2">Order Information</h4>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div><strong>Order Number:</strong> ${order.order_number}</div>
                            <div><strong>Status:</strong> <span class="px-2 py-1 rounded text-xs ${getStatusBadgeClass(order.order_status)}">${order.order_status}</span></div>
                            <div><strong>Date:</strong> ${new Date(order.created_at).toLocaleString()}</div>
                            <div><strong>Total Amount:</strong> ৳${parseFloat(order.final_amount).toFixed(2)}</div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-900 mb-2">Items</h4>
                        <div class="space-y-2">
                            ${order.items.map(item => `
                                <div class="flex justify-between items-center">
                                    <div>
                                        <div class="font-medium">${item.product_name}</div>
                                        <div class="text-sm text-gray-600">Qty: ${item.quantity}</div>
                                    </div>
                                    <div class="text-right">
                                        <div>৳${parseFloat(item.price).toFixed(2)}</div>
                                        <div class="text-sm text-gray-600">Total: ৳${(parseFloat(item.price) * item.quantity).toFixed(2)}</div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            `;
        }

        function getStatusBadgeClass(status) {
            switch (status) {
                case 'pending': return 'bg-yellow-100 text-yellow-800';
                case 'processing': return 'bg-blue-100 text-blue-800';
                case 'shipped': return 'bg-purple-100 text-purple-800';
                case 'delivered': return 'bg-green-100 text-green-800';
                case 'cancelled': return 'bg-red-100 text-red-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        }

        function showCancelModal(orderId, orderNumber) {
            document.getElementById('cancelOrderId').value = orderId;
            document.getElementById('cancelModal').classList.remove('hidden');
        }

        function hideCancelModal() {
            document.getElementById('cancelModal').classList.add('hidden');
            document.getElementById('cancel_reason').value = '';
        }

        function showDeleteModal(orderId, orderNumber) {
            document.getElementById('deleteOrderId').value = orderId;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        function showReorderModal() {
            document.getElementById('reorderModal').classList.remove('hidden');
            loadReorderItems();
        }

        function hideReorderModal() {
            document.getElementById('reorderModal').classList.add('hidden');
        }

        function hideOrderDetailsModal() {
            document.getElementById('orderDetailsModal').classList.add('hidden');
        }

        function reorderItems(orderId) {
            fetch(`backend/api/orders.php?action=getOrderDetails&order_id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const order = data.order;
                        const reorderContent = document.getElementById('reorderContent');
                        
                        reorderContent.innerHTML = `
                            <div class="mb-4">
                                <h4 class="font-semibold text-gray-900 mb-2">Items from Order #${order.order_number}</h4>
                                <p class="text-sm text-gray-600">Select items to reorder:</p>
                            </div>
                            
                            <div class="space-y-3 mb-6">
                                ${order.items.map(item => `
                                    <div class="flex items-center justify-between p-3 border rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <input type="checkbox" id="item_${item.product_id}" value="${item.product_id}" 
                                                   data-name="${item.product_name}" data-price="${item.price}" 
                                                   class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500">
                                            <div>
                                                <div class="font-medium">${item.product_name}</div>
                                                <div class="text-sm text-gray-600">৳${parseFloat(item.price).toFixed(2)} each</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <label class="text-sm">Qty:</label>
                                            <input type="number" min="1" value="${item.quantity}" 
                                                   class="w-16 px-2 py-1 border rounded text-sm"
                                                   data-product-id="${item.product_id}">
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                            
                            <div class="flex justify-end space-x-3">
                                <button onclick="hideReorderModal()" 
                                        class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">
                                    Cancel
                                </button>
                                <button onclick="addToCartFromReorder()" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Add to Cart
                                </button>
                            </div>
                        `;
                        
                        document.getElementById('reorderModal').classList.remove('hidden');
                    } else {
                        alert('Error loading order items: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading order items');
                });
        }

        function loadReorderItems() {
            // This will be populated when reorderItems is called
        }

        function addToCartFromReorder() {
            const selectedItems = [];
            const checkboxes = document.querySelectorAll('#reorderContent input[type="checkbox"]:checked');
            
            checkboxes.forEach(checkbox => {
                const productId = checkbox.value;
                const productName = checkbox.dataset.name;
                const productPrice = checkbox.dataset.price;
                const quantityInput = document.querySelector(`input[data-product-id="${productId}"]`);
                const quantity = parseInt(quantityInput.value) || 1;
                
                selectedItems.push({
                    id: productId,
                    name: productName,
                    price: parseFloat(productPrice),
                    quantity: quantity
                });
            });
            
            if (selectedItems.length === 0) {
                alert('Please select at least one item to add to cart.');
                return;
            }
            
            // Add items to cart (localStorage)
            let cart = JSON.parse(localStorage.getItem('cart') || '[]');
            
            selectedItems.forEach(item => {
                const existingItem = cart.find(cartItem => cartItem.id == item.id);
                if (existingItem) {
                    existingItem.quantity += item.quantity;
                } else {
                    cart.push(item);
                }
            });
            
            localStorage.setItem('cart', JSON.stringify(cart));
            
            alert(`${selectedItems.length} item(s) added to cart successfully!`);
            hideReorderModal();
            
            // Redirect to cart or home page
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 1000);
        }

        function exportPurchaseHistory() {
            // Create a simple export of purchase history
            const orders = <?php echo json_encode($orders); ?>;
            let csvContent = "Order Number,Date,Status,Total Amount,Products\n";
            
            orders.forEach(order => {
                csvContent += `"${order.order_number}","${order.created_at}","${order.order_status}","${order.final_amount}","${order.items_summary}"\n`;
            });
            
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'purchase_history.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        function requestReturn(orderId) {
            alert('Return request feature will be implemented soon!');
        }

        // Close modals when clicking outside
        document.getElementById('cancelModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideCancelModal();
            }
        });

        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideDeleteModal();
            }
        });

        document.getElementById('reorderModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideReorderModal();
            }
        });

        document.getElementById('orderDetailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideOrderDetailsModal();
            }
        });
    </script>
</body>
</html>

<?php
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'processing': return 'bg-blue-100 text-blue-800';
        case 'shipped': return 'bg-purple-100 text-purple-800';
        case 'delivered': return 'bg-green-100 text-green-800';
        case 'cancelled': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}
?> 