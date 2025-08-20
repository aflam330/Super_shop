<?php
session_start();
require_once 'backend/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: admin-login.php');
    exit;
}

$admin_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$error_message = '';
$success_message = '';

// Get date range for weekly report
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));

try {
    $pdo = getDBConnection();
    
    // Weekly sales data
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as order_count,
            SUM(final_amount) as total_sales,
            AVG(final_amount) as avg_order_value
        FROM orders 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute([$week_start, $week_end]);
    $weekly_sales = $stmt->fetchAll();
    
    // Top selling products
    $stmt = $pdo->prepare("
        SELECT 
            p.name,
            p.category,
            SUM(oi.quantity) as total_sold,
            SUM(oi.total_price) as total_revenue,
            p.stock_quantity as current_stock
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY p.id
        ORDER BY total_sold DESC
        LIMIT 10
    ");
    $stmt->execute([$week_start, $week_end]);
    $top_products = $stmt->fetchAll();
    
    // Customer statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT o.user_id) as unique_customers,
            COUNT(*) as total_orders,
            SUM(o.final_amount) as total_revenue,
            AVG(o.final_amount) as avg_order_value
        FROM orders o
        WHERE DATE(o.created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$week_start, $week_end]);
    $customer_stats = $stmt->fetch();
    
    // Payment method breakdown
    $stmt = $pdo->prepare("
        SELECT 
            payment_method,
            COUNT(*) as order_count,
            SUM(final_amount) as total_amount
        FROM orders
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY payment_method
    ");
    $stmt->execute([$week_start, $week_end]);
    $payment_breakdown = $stmt->fetchAll();
    
    // Category performance
    $stmt = $pdo->prepare("
        SELECT 
            p.category,
            COUNT(DISTINCT o.id) as order_count,
            SUM(oi.quantity) as items_sold,
            SUM(oi.total_price) as revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY p.category
        ORDER BY revenue DESC
    ");
    $stmt->execute([$week_start, $week_end]);
    $category_performance = $stmt->fetchAll();
    
    // Low stock alerts
    $stmt = $pdo->prepare("
        SELECT name, category, stock_quantity, price
        FROM products
        WHERE stock_quantity < 10 AND is_active = 1
        ORDER BY stock_quantity ASC
    ");
    $stmt->execute();
    $low_stock_items = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = 'Database error. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Reports - Super Shop Management System</title>
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
                        <span class="text-gray-500">Weekly Reports</span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-sm text-gray-700">
                        Welcome, <span class="font-semibold"><?php echo htmlspecialchars($admin_name); ?></span>
                    </div>
                    <a href="admin-dashboard.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        Back to Dashboard
                    </a>
                    <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-8 px-4">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-white mb-2">📋 Weekly Reports</h1>
            <p class="text-white text-opacity-80">
                Report Period: <?php echo date('F j', strtotime($week_start)); ?> - <?php echo date('F j, Y', strtotime($week_end)); ?>
            </p>
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

        <!-- Quick Actions -->
        <div class="mb-6 flex flex-wrap gap-4">
            <button onclick="exportReport()" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors">
                📄 Export Report
            </button>
            <button onclick="printReport()" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                🖨️ Print Report
            </button>
            <button onclick="generatePDF()" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition-colors">
                📊 Generate PDF
            </button>
        </div>

        <!-- Summary Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="glass-effect p-6 rounded-lg">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-500 text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-white text-opacity-80">Total Revenue</p>
                        <p class="text-2xl font-semibold text-white">৳<?php echo number_format($customer_stats['total_revenue'] ?? 0, 2); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="glass-effect p-6 rounded-lg">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-500 text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-white text-opacity-80">Total Orders</p>
                        <p class="text-2xl font-semibold text-white"><?php echo $customer_stats['total_orders'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="glass-effect p-6 rounded-lg">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-500 text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-white text-opacity-80">Unique Customers</p>
                        <p class="text-2xl font-semibold text-white"><?php echo $customer_stats['unique_customers'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="glass-effect p-6 rounded-lg">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-500 text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-white text-opacity-80">Avg Order Value</p>
                        <p class="text-2xl font-semibold text-white">৳<?php echo number_format($customer_stats['avg_order_value'] ?? 0, 2); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Daily Sales Chart -->
            <div class="glass-effect p-6 rounded-lg">
                <h3 class="text-xl font-semibold text-white mb-4">📈 Daily Sales Trend</h3>
                <canvas id="dailySalesChart" width="400" height="200"></canvas>
            </div>
            
            <!-- Payment Method Chart -->
            <div class="glass-effect p-6 rounded-lg">
                <h3 class="text-xl font-semibold text-white mb-4">💳 Payment Method Breakdown</h3>
                <canvas id="paymentChart" width="400" height="200"></canvas>
            </div>
        </div>

        <!-- Top Products Table -->
        <div class="glass-effect p-6 rounded-lg mb-8">
            <h3 class="text-xl font-semibold text-white mb-4">🏆 Top Selling Products</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-white">
                    <thead>
                        <tr class="border-b border-white border-opacity-20">
                            <th class="text-left py-3 px-4">Product</th>
                            <th class="text-left py-3 px-4">Category</th>
                            <th class="text-left py-3 px-4">Units Sold</th>
                            <th class="text-left py-3 px-4">Revenue</th>
                            <th class="text-left py-3 px-4">Current Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_products as $product): ?>
                            <tr class="border-b border-white border-opacity-10">
                                <td class="py-3 px-4"><?php echo htmlspecialchars($product['name']); ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($product['category']); ?></td>
                                <td class="py-3 px-4"><?php echo $product['total_sold']; ?></td>
                                <td class="py-3 px-4">৳<?php echo number_format($product['total_revenue'], 2); ?></td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-1 rounded text-xs <?php echo $product['current_stock'] < 10 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                        <?php echo $product['current_stock']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Category Performance -->
        <div class="glass-effect p-6 rounded-lg mb-8">
            <h3 class="text-xl font-semibold text-white mb-4">📊 Category Performance</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach ($category_performance as $category): ?>
                    <div class="bg-white bg-opacity-10 p-4 rounded-lg">
                        <h4 class="font-semibold text-white"><?php echo htmlspecialchars($category['category']); ?></h4>
                        <div class="text-sm text-white text-opacity-80 mt-2">
                            <div>Orders: <?php echo $category['order_count']; ?></div>
                            <div>Items Sold: <?php echo $category['items_sold']; ?></div>
                            <div>Revenue: ৳<?php echo number_format($category['revenue'], 2); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Low Stock Alerts -->
        <div class="glass-effect p-6 rounded-lg">
            <h3 class="text-xl font-semibold text-white mb-4">⚠️ Low Stock Alerts</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-white">
                    <thead>
                        <tr class="border-b border-white border-opacity-20">
                            <th class="text-left py-3 px-4">Product</th>
                            <th class="text-left py-3 px-4">Category</th>
                            <th class="text-left py-3 px-4">Current Stock</th>
                            <th class="text-left py-3 px-4">Price</th>
                            <th class="text-left py-3 px-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($low_stock_items as $item): ?>
                            <tr class="border-b border-white border-opacity-10">
                                <td class="py-3 px-4"><?php echo htmlspecialchars($item['name']); ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($item['category']); ?></td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-1 rounded text-xs bg-red-100 text-red-800">
                                        <?php echo $item['stock_quantity']; ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4">৳<?php echo number_format($item['price'], 2); ?></td>
                                <td class="py-3 px-4">
                                    <button onclick="restockProduct(<?php echo $item['id']; ?>)" class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                                        Restock
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Daily Sales Chart
        const dailySalesCtx = document.getElementById('dailySalesChart').getContext('2d');
        new Chart(dailySalesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($weekly_sales, 'date')); ?>,
                datasets: [{
                    label: 'Daily Sales (৳)',
                    data: <?php echo json_encode(array_column($weekly_sales, 'total_sales')); ?>,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        labels: {
                            color: 'white'
                        }
                    }
                },
                scales: {
                    y: {
                        ticks: {
                            color: 'white'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: 'white'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    }
                }
            }
        });

        // Payment Method Chart
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($payment_breakdown, 'payment_method')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($payment_breakdown, 'total_amount')); ?>,
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        labels: {
                            color: 'white'
                        }
                    }
                }
            }
        });

        function exportReport() {
            // Create CSV export
            const data = <?php echo json_encode($weekly_sales); ?>;
            let csvContent = "Date,Orders,Total Sales,Average Order Value\n";
            
            data.forEach(row => {
                csvContent += `"${row.date}","${row.order_count}","${row.total_sales}","${row.avg_order_value}"\n`;
            });
            
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'weekly_report.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        function printReport() {
            window.print();
        }

        function generatePDF() {
            alert('PDF generation feature will be implemented soon!');
        }

        function restockProduct(productId) {
            const quantity = prompt('Enter restock quantity:');
            if (quantity && !isNaN(quantity)) {
                // Call API to restock product
                fetch('backend/api/inventory.php?action=updateStock', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        stock_quantity: parseInt(quantity)
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Product restocked successfully!');
                        location.reload();
                    } else {
                        alert('Error restocking product: ' + data.error);
                    }
                })
                .catch(error => {
                    alert('Error restocking product');
                });
            }
        }
    </script>
</body>
</html> 