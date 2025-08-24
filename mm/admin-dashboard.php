<?php
session_start();
require_once 'backend/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: admin-login.php');
    exit;
}

// Get admin user info
$admin_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$admin_username = $_SESSION['username'];

// Get dashboard statistics
try {
    $pdo = getDBConnection();
    
    // Total products
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM products WHERE is_active = 1");
    $stmt->execute();
    $total_products = $stmt->fetch()['total'];
    
    // Low stock products (less than 20)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM products WHERE stock_quantity < 20 AND is_active = 1");
    $stmt->execute();
    $low_stock = $stmt->fetch()['total'];
    
    // Expired products
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM products WHERE expiry_date < CURDATE() AND is_active = 1");
    $stmt->execute();
    $expired_products = $stmt->fetch()['total'];
    
    // Today's sales
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(final_amount), 0) as total FROM orders WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $today_sales = $stmt->fetch()['total'];
    
    // Total users
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
    $stmt->execute();
    $total_users = $stmt->fetch()['total'];
    
    // Recent orders
    $stmt = $pdo->prepare("
        SELECT o.*, u.first_name, u.last_name 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_orders = $stmt->fetchAll();
    
    // Recent activity
    $stmt = $pdo->prepare("
        SELECT event_type, event_data, created_at 
        FROM realtime_events 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recent_activity = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = 'Database error. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Super Shop Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .admin-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .sidebar-gradient {
            background: linear-gradient(180deg, #2d3748 0%, #1a202c 100%);
        }
    </style>
</head>
<body class="admin-gradient min-h-screen">
    <!-- Top Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <h1 class="text-2xl font-bold text-purple-600">Super Shop SSMS</h1>
                    </div>
                    <div class="ml-10 flex items-baseline space-x-4">
                        <span class="text-gray-500">Admin Panel</span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-sm text-gray-700">
                        Welcome, <span class="font-semibold"><?php echo htmlspecialchars($admin_name); ?></span>
                    </div>
                    <div class="text-xs text-gray-500">
                        <?php echo htmlspecialchars($admin_username); ?>
                    </div>
                    <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-64 sidebar-gradient min-h-screen">
            <div class="p-6">
                <div class="flex items-center mb-8">
                    <div class="w-10 h-10 bg-purple-600 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h2 class="ml-3 text-xl font-semibold text-white">Admin Panel</h2>
                </div>
                
                <nav class="space-y-2">
                    <button onclick="showSection('dashboard')" class="nav-btn w-full text-left px-4 py-3 rounded-lg hover:bg-purple-600 hover:text-white transition-colors text-white">
                        🏠 Dashboard
                    </button>
                    <button onclick="showSection('inventory')" class="nav-btn w-full text-left px-4 py-3 rounded-lg hover:bg-purple-600 hover:text-white transition-colors text-white">
                        📦 Inventory
                    </button>
                    <button onclick="showSection('orders')" class="nav-btn w-full text-left px-4 py-3 rounded-lg hover:bg-purple-600 hover:text-white transition-colors text-white">
                        🛒 Orders
                    </button>
                    <a href="receptionist-dashboard.html" class="nav-btn w-full text-left px-4 py-3 rounded-lg hover:bg-purple-600 hover:text-white transition-colors text-white block">
                        💳 Generate Bill
                    </a>
                    <a href="product-recommendations.php" class="nav-btn w-full text-left px-4 py-3 rounded-lg hover:bg-purple-600 hover:text-white transition-colors text-white block">
                        ⭐ Product Recommendations
                    </a>
                    <a href="return-policy.php" class="nav-btn w-full text-left px-4 py-3 rounded-lg hover:bg-purple-600 hover:text-white transition-colors text-white block">
                        🔄 Process Returns
                    </a>
                    <a href="weekly-reports.php" class="nav-btn w-full text-left px-4 py-3 rounded-lg hover:bg-purple-600 hover:text-white transition-colors text-white block">
                        📋 Weekly Reports
                    </a>
                    <a href="return-policy.php" class="nav-btn w-full text-left px-4 py-3 rounded-lg hover:bg-purple-600 hover:text-white transition-colors text-white block">
                        📜 Return Policy
                    </a>
                    <button onclick="showSection('users')" class="nav-btn w-full text-left px-4 py-3 rounded-lg hover:bg-purple-600 hover:text-white transition-colors text-white">
                        👥 Users
                    </button>
                    <button onclick="showSection('analytics')" class="nav-btn w-full text-left px-4 py-3 rounded-lg hover:bg-purple-600 hover:text-white transition-colors text-white">
                        📈 Analytics
                    </button>
                    <button onclick="showSection('reports')" class="nav-btn w-full text-left px-4 py-3 rounded-lg hover:bg-purple-600 hover:text-white transition-colors text-white">
                        📊 Reports
                    </button>
                    <button onclick="showSection('settings')" class="nav-btn w-full text-left px-4 py-3 rounded-lg hover:bg-purple-600 hover:text-white transition-colors text-white">
                        ⚙️ Settings
                    </button>
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <!-- Dashboard Section -->
            <div id="dashboard-section" class="section">
                <div class="mb-8">
                    <h1 class="text-4xl font-bold text-white mb-2">Admin Dashboard</h1>
                    <p class="text-white text-opacity-80">Welcome back, <?php echo htmlspecialchars($admin_name); ?>!</p>
                </div>
                
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="glass-effect p-6 rounded-lg">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-500 text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-white text-opacity-80">Total Products</p>
                                <p class="text-2xl font-semibold text-white"><?php echo $total_products; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="glass-effect p-6 rounded-lg">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-500 text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-white text-opacity-80">Low Stock</p>
                                <p class="text-2xl font-semibold text-white"><?php echo $low_stock; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="glass-effect p-6 rounded-lg">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-500 text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-white text-opacity-80">Expired Products</p>
                                <p class="text-2xl font-semibold text-white"><?php echo $expired_products; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="glass-effect p-6 rounded-lg">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-500 text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-white text-opacity-80">Today's Sales</p>
                                <p class="text-2xl font-semibold text-white">৳<?php echo number_format($today_sales, 2); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions and Recent Activity -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Quick Actions -->
                    <div class="glass-effect p-6 rounded-lg">
                        <h3 class="text-xl font-semibold text-white mb-4">Quick Actions</h3>
                        <div class="space-y-3">
                            <button onclick="showSection('inventory')" class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                                📦 Manage Inventory
                            </button>
                            <button onclick="removeExpiredProducts()" class="w-full bg-red-600 text-white px-4 py-3 rounded-lg hover:bg-red-700 transition-colors">
                                ⚠️ Remove Expired Products
                            </button>
                            <button onclick="showSection('users')" class="w-full bg-green-600 text-white px-4 py-3 rounded-lg hover:bg-green-700 transition-colors">
                                👥 Manage Users
                            </button>
                            <button onclick="showSection('orders')" class="w-full bg-purple-600 text-white px-4 py-3 rounded-lg hover:bg-purple-700 transition-colors">
                                🛒 View Orders
                            </button>
                        </div>
                    </div>
                    
                    <!-- Recent Activity -->
                    <div class="glass-effect p-6 rounded-lg">
                        <h3 class="text-xl font-semibold text-white mb-4">Recent Activity</h3>
                        <div class="space-y-3 max-h-64 overflow-y-auto">
                            <?php if (!empty($recent_activity)): ?>
                                <?php foreach ($recent_activity as $activity): ?>
                                    <div class="flex items-center text-sm text-white text-opacity-80">
                                        <div class="w-2 h-2 bg-green-400 rounded-full mr-3"></div>
                                        <div class="flex-1">
                                            <div class="font-medium"><?php echo ucfirst(str_replace('_', ' ', $activity['event_type'])); ?></div>
                                            <div class="text-xs text-white text-opacity-60">
                                                <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-white text-opacity-60">No recent activity</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="mt-8">
                    <div class="glass-effect p-6 rounded-lg">
                        <h3 class="text-xl font-semibold text-white mb-4">Recent Orders</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="text-white text-opacity-80">
                                        <th class="px-4 py-2 text-left">Order #</th>
                                        <th class="px-4 py-2 text-left">Customer</th>
                                        <th class="px-4 py-2 text-left">Amount</th>
                                        <th class="px-4 py-2 text-left">Status</th>
                                        <th class="px-4 py-2 text-left">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recent_orders)): ?>
                                        <?php foreach ($recent_orders as $order): ?>
                                            <tr class="text-white text-opacity-80 border-t border-white border-opacity-20">
                                                <td class="px-4 py-2"><?php echo htmlspecialchars($order['order_number']); ?></td>
                                                <td class="px-4 py-2">
                                                    <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                                                </td>
                                                <td class="px-4 py-2">৳<?php echo number_format($order['final_amount'], 2); ?></td>
                                                <td class="px-4 py-2">
                                                    <span class="px-2 py-1 rounded text-xs <?php echo getStatusBadgeClass($order['order_status']); ?>">
                                                        <?php echo ucfirst($order['order_status']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-2 text-sm">
                                                    <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="px-4 py-2 text-center text-white text-opacity-60">No recent orders</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Management Section -->
            <div id="inventory-section" class="section hidden">
                <div class="mb-8">
                    <h1 class="text-4xl font-bold text-white mb-2">Inventory Management</h1>
                    <p class="text-white text-opacity-80">Manage your product inventory, add new products, and track stock levels.</p>
                </div>

                <!-- Inventory Stats -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="glass-effect p-6 rounded-lg">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-500 text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-white text-opacity-80">Total Products</p>
                                <p class="text-2xl font-semibold text-white"><?php echo $total_products; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="glass-effect p-6 rounded-lg">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-500 text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-white text-opacity-80">Low Stock</p>
                                <p class="text-2xl font-semibold text-white"><?php echo $low_stock; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="glass-effect p-6 rounded-lg">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-500 text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-white text-opacity-80">Expired</p>
                                <p class="text-2xl font-semibold text-white"><?php echo $expired_products; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="glass-effect p-6 rounded-lg">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-500 text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-white text-opacity-80">In Stock</p>
                                <p class="text-2xl font-semibold text-white"><?php echo $total_products - $low_stock - $expired_products; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inventory Actions -->
                <div class="glass-effect p-6 rounded-lg mb-8">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-semibold text-white mb-2">Product Management</h3>
                            <p class="text-white text-opacity-80">Add, edit, and manage your product inventory</p>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <button onclick="showAddProductModal()" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors">
                                ➕ Add New Product
                            </button>
                            <button onclick="loadInventoryData()" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                                🔄 Refresh
                            </button>
                            <button onclick="removeExpiredProducts()" class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition-colors">
                                ⚠️ Remove Expired
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Inventory Table -->
                <div class="glass-effect p-6 rounded-lg">
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                        <h3 class="text-xl font-semibold text-white">Product Inventory</h3>
                        <div class="flex gap-3">
                            <input type="text" id="inventorySearch" placeholder="Search products..." 
                                   class="px-4 py-2 bg-white bg-opacity-20 text-white placeholder-white placeholder-opacity-70 rounded-lg focus:outline-none focus:ring-2 focus:ring-white">
                            <select id="categoryFilter" class="px-4 py-2 bg-white bg-opacity-20 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-white">
                                <option value="">All Categories</option>
                                <option value="electronics">Electronics</option>
                                <option value="fashion">Fashion</option>
                                <option value="home">Home & Garden</option>
                                <option value="sports">Sports</option>
                                <option value="books">Books</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="text-white text-opacity-80 border-b border-white border-opacity-20">
                                    <th class="px-4 py-3 text-left">Product</th>
                                    <th class="px-4 py-3 text-left">Category</th>
                                    <th class="px-4 py-3 text-left">Price</th>
                                    <th class="px-4 py-3 text-left">Stock</th>
                                    <th class="px-4 py-3 text-left">Expiry</th>
                                    <th class="px-4 py-3 text-left">Status</th>
                                    <th class="px-4 py-3 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="inventoryTableBody">
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-white text-opacity-60">
                                        <div class="flex flex-col items-center">
                                            <svg class="w-12 h-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                            </svg>
                                            <p>Loading inventory data...</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="orders-section" class="section hidden">
                <div class="glass-effect p-6 rounded-lg">
                    <h2 class="text-2xl font-bold text-white mb-4">Order Management</h2>
                    <p class="text-white text-opacity-80">Order management section will be implemented here.</p>
                </div>
            </div>

            <div id="users-section" class="section hidden">
                <div class="glass-effect p-6 rounded-lg">
                    <h2 class="text-2xl font-bold text-white mb-4">User Management</h2>
                    <p class="text-white text-opacity-80">User management section will be implemented here.</p>
                </div>
            </div>

            <div id="analytics-section" class="section hidden">
                <div class="glass-effect p-6 rounded-lg">
                    <h2 class="text-2xl font-bold text-white mb-4">Sales Analytics</h2>
                    <p class="text-white text-opacity-80">Analytics section will be implemented here.</p>
                </div>
            </div>

            <div id="reports-section" class="section hidden">
                <div class="glass-effect p-6 rounded-lg">
                    <h2 class="text-2xl font-bold text-white mb-4">Reports</h2>
                    <p class="text-white text-opacity-80">Reports section will be implemented here.</p>
                </div>
            </div>

            <div id="settings-section" class="section hidden">
                <div class="glass-effect p-6 rounded-lg">
                    <h2 class="text-2xl font-bold text-white mb-4">System Settings</h2>
                    <p class="text-white text-opacity-80">Settings section will be implemented here.</p>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Product Modal -->
    <div id="addProductModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-2xl w-full p-6 max-h-screen overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-900">Add New Product</h3>
                    <button onclick="hideAddProductModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form id="addProductForm" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="productName" class="block text-sm font-medium text-gray-700 mb-2">Product Name *</label>
                            <input type="text" id="productName" name="name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Enter product name">
                        </div>
                        
                        <div>
                            <label for="productCategory" class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                            <select id="productCategory" name="category" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select Category</option>
                                <option value="electronics">Electronics</option>
                                <option value="fashion">Fashion</option>
                                <option value="home">Home & Garden</option>
                                <option value="sports">Sports</option>
                                <option value="books">Books</option>
                                <option value="food">Food & Beverages</option>
                                <option value="health">Health & Beauty</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="productPrice" class="block text-sm font-medium text-gray-700 mb-2">Price (৳) *</label>
                            <input type="number" id="productPrice" name="price" step="0.01" min="0" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="0.00">
                        </div>
                        
                        <div>
                            <label for="productStock" class="block text-sm font-medium text-gray-700 mb-2">Stock Quantity *</label>
                            <input type="number" id="productStock" name="stock_quantity" min="0" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="0">
                        </div>
                        
                        <div>
                            <label for="productExpiry" class="block text-sm font-medium text-gray-700 mb-2">Expiry Date</label>
                            <input type="date" id="productExpiry" name="expiry_date"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label for="productImage" class="block text-sm font-medium text-gray-700 mb-2">Image URL</label>
                            <input type="url" id="productImage" name="image_url"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="https://example.com/image.jpg">
                        </div>
                    </div>
                    
                    <div>
                        <label for="productDescription" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea id="productDescription" name="description" rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                  placeholder="Enter product description..."></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="hideAddProductModal()" 
                                class="px-6 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                            Add Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editProductModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-2xl w-full p-6 max-h-screen overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-900">Edit Product</h3>
                    <button onclick="hideEditProductModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form id="editProductForm" class="space-y-6">
                    <input type="hidden" id="editProductId" name="id">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="editProductName" class="block text-sm font-medium text-gray-700 mb-2">Product Name *</label>
                            <input type="text" id="editProductName" name="name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label for="editProductCategory" class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                            <select id="editProductCategory" name="category" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="electronics">Electronics</option>
                                <option value="fashion">Fashion</option>
                                <option value="home">Home & Garden</option>
                                <option value="sports">Sports</option>
                                <option value="books">Books</option>
                                <option value="food">Food & Beverages</option>
                                <option value="health">Health & Beauty</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="editProductPrice" class="block text-sm font-medium text-gray-700 mb-2">Price (৳) *</label>
                            <input type="number" id="editProductPrice" name="price" step="0.01" min="0" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label for="editProductStock" class="block text-sm font-medium text-gray-700 mb-2">Stock Quantity *</label>
                            <input type="number" id="editProductStock" name="stock_quantity" min="0" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label for="editProductExpiry" class="block text-sm font-medium text-gray-700 mb-2">Expiry Date</label>
                            <input type="date" id="editProductExpiry" name="expiry_date"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label for="editProductImage" class="block text-sm font-medium text-gray-700 mb-2">Image URL</label>
                            <input type="url" id="editProductImage" name="image_url"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <div>
                        <label for="editProductDescription" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea id="editProductDescription" name="description" rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="hideEditProductModal()" 
                                class="px-6 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Update Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Navigation functionality
        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('.section').forEach(section => {
                section.classList.add('hidden');
            });
            
            // Show selected section
            document.getElementById(sectionName + '-section').classList.remove('hidden');
            
            // Update navigation buttons
            document.querySelectorAll('.nav-btn').forEach(btn => {
                btn.classList.remove('bg-purple-600');
            });
            event.target.classList.add('bg-purple-600');
        }

        // Remove expired products
        function removeExpiredProducts() {
            if (confirm('Are you sure you want to remove all expired products? This action cannot be undone.')) {
                // Implement expired products removal
                alert('Expired products removal feature will be implemented soon.');
            }
        }

        // Auto-refresh dashboard data every 30 seconds
        setInterval(() => {
            // Refresh dashboard statistics
            location.reload();
        }, 30000);

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Set active navigation
            document.querySelector('.nav-btn').classList.add('bg-purple-600');
            
            // Load inventory data when inventory section is shown
            document.querySelector('[onclick="showSection(\'inventory\')"]').addEventListener('click', function() {
                setTimeout(loadInventoryData, 100);
            });
        });

        // Inventory Management Functions
        function showAddProductModal() {
            document.getElementById('addProductModal').classList.remove('hidden');
            document.getElementById('addProductForm').reset();
        }

        function hideAddProductModal() {
            document.getElementById('addProductModal').classList.add('hidden');
        }

        function showEditProductModal(productId) {
            // Load product data and populate form
            fetch(`backend/api/inventory.php?action=getProduct&id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const product = data.product;
                        document.getElementById('editProductId').value = product.id;
                        document.getElementById('editProductName').value = product.name;
                        document.getElementById('editProductCategory').value = product.category;
                        document.getElementById('editProductPrice').value = product.price;
                        document.getElementById('editProductStock').value = product.stock_quantity;
                        document.getElementById('editProductExpiry').value = product.expiry_date || '';
                        document.getElementById('editProductImage').value = product.image_url || '';
                        document.getElementById('editProductDescription').value = product.description || '';
                        
                        document.getElementById('editProductModal').classList.remove('hidden');
                    } else {
                        alert('Error loading product: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading product data');
                });
        }

        function hideEditProductModal() {
            document.getElementById('editProductModal').classList.add('hidden');
        }

        function loadInventoryData() {
            const searchTerm = document.getElementById('inventorySearch').value;
            const categoryFilter = document.getElementById('categoryFilter').value;
            
            fetch(`backend/api/inventory.php?action=getProducts&search=${encodeURIComponent(searchTerm)}&category=${encodeURIComponent(categoryFilter)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayInventoryTable(data.products);
                    } else {
                        console.error('Error loading inventory:', data.error);
                        displayInventoryTable([]);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    displayInventoryTable([]);
                });
        }

        function displayInventoryTable(products) {
            const tbody = document.getElementById('inventoryTableBody');
            
            if (products.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-white text-opacity-60">
                            <div class="flex flex-col items-center">
                                <svg class="w-12 h-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                                <p>No products found</p>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = products.map(product => `
                <tr class="text-white text-opacity-80 border-t border-white border-opacity-20 hover:bg-white hover:bg-opacity-10">
                    <td class="px-4 py-3">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-gray-200 rounded-lg flex items-center justify-center">
                                ${product.image_url ? 
                                    `<img src="${product.image_url}" alt="${product.name}" class="w-full h-full object-cover rounded-lg">` :
                                    `<svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>`
                                }
                            </div>
                            <div>
                                <div class="font-medium">${product.name}</div>
                                <div class="text-sm text-white text-opacity-60">${product.description || 'No description'}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded text-xs bg-blue-100 text-blue-800">${product.category}</span>
                    </td>
                    <td class="px-4 py-3">৳${parseFloat(product.price).toFixed(2)}</td>
                    <td class="px-4 py-3">
                        <span class="${product.stock_quantity < 10 ? 'text-red-400' : 'text-green-400'}">
                            ${product.stock_quantity}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        ${product.expiry_date ? 
                            `<span class="${new Date(product.expiry_date) < new Date() ? 'text-red-400' : 'text-white'}">${product.expiry_date}</span>` :
                            'No expiry'
                        }
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded text-xs ${getProductStatusClass(product)}">
                            ${getProductStatusText(product)}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex space-x-2">
                            <button onclick="showEditProductModal(${product.id})" 
                                    class="text-blue-400 hover:text-blue-300" title="Edit">
                                ✏️
                            </button>
                            <button onclick="deleteProduct(${product.id}, '${product.name}')" 
                                    class="text-red-400 hover:text-red-300" title="Delete">
                                🗑️
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function getProductStatusClass(product) {
            if (product.stock_quantity === 0) return 'bg-red-100 text-red-800';
            if (product.stock_quantity < 10) return 'bg-yellow-100 text-yellow-800';
            if (product.expiry_date && new Date(product.expiry_date) < new Date()) return 'bg-red-100 text-red-800';
            return 'bg-green-100 text-green-800';
        }

        function getProductStatusText(product) {
            if (product.stock_quantity === 0) return 'Out of Stock';
            if (product.stock_quantity < 10) return 'Low Stock';
            if (product.expiry_date && new Date(product.expiry_date) < new Date()) return 'Expired';
            return 'In Stock';
        }

        function deleteProduct(productId, productName) {
            if (confirm(`Are you sure you want to delete "${productName}"? This action cannot be undone.`)) {
                fetch('backend/api/inventory.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'deleteProduct',
                        id: productId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Product deleted successfully!');
                        loadInventoryData();
                    } else {
                        alert('Error deleting product: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting product');
                });
            }
        }

        // Form submissions
        document.getElementById('addProductForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const productData = Object.fromEntries(formData.entries());
            
            fetch('backend/api/inventory.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'addProduct',
                    ...productData
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Product added successfully!');
                    hideAddProductModal();
                    loadInventoryData();
                } else {
                    alert('Error adding product: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding product');
            });
        });

        document.getElementById('editProductForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const productData = Object.fromEntries(formData.entries());
            
            fetch('backend/api/inventory.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'updateProduct',
                    ...productData
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Product updated successfully!');
                    hideEditProductModal();
                    loadInventoryData();
                } else {
                    alert('Error updating product: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating product');
            });
        });

        // Search and filter functionality
        document.getElementById('inventorySearch').addEventListener('input', function() {
            loadInventoryData();
        });

        document.getElementById('categoryFilter').addEventListener('change', function() {
            loadInventoryData();
        });

        // Close modals when clicking outside
        document.getElementById('addProductModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideAddProductModal();
            }
        });

        document.getElementById('editProductModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideEditProductModal();
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