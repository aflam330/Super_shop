<?php
// Automatic database fix script for Super Shop Management System
require_once 'backend/config.php';

echo "<h2>🔧 Automatic Database Fix for Super Shop Analytics</h2>";

try {
    // Connect to MySQL without specifying database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p>✅ Database '" . DB_NAME . "' created/verified</p>";
    
    // Connect to the specific database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // Read and execute SQL schema
    $sql = file_get_contents('backend/database.sql');
    $pdo->exec($sql);
    echo "<p>✅ Database tables created successfully</p>";
    
    // Insert sample data if not exists
    echo "<h3>📊 Adding Sample Data for Analytics...</h3>";
    
    // Sample admin user
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password_hash, first_name, last_name, role, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@supershop.com', $admin_password, 'Admin', 'User', 'admin', 1]);
    echo "<p>✅ Admin user created/verified</p>";
    
    // Sample receptionist user
    $receptionist_password = password_hash('receptionist123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password_hash, first_name, last_name, role, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['receptionist', 'receptionist@supershop.com', $receptionist_password, 'Receptionist', 'User', 'receptionist', 1]);
    echo "<p>✅ Receptionist user created/verified</p>";
    
    // Sample products
    $products = [
        ['Smart Watch', 'Advanced fitness tracking smartwatch', 99.99, 15, 'Electronics', '2025-12-31'],
        ['Wireless Earbuds', 'Noise-canceling wireless earbuds', 49.99, 25, 'Electronics', '2025-12-31'],
        ['Laptop', 'High-performance gaming laptop', 899.99, 8, 'Electronics', '2025-12-31'],
        ['Bluetooth Speaker', 'Portable wireless speaker', 89.99, 20, 'Electronics', '2025-12-31'],
        ['Smartphone', 'Latest smartphone model', 599.99, 12, 'Electronics', '2025-12-31'],
        ['Tablet', '10-inch tablet with high resolution', 299.99, 18, 'Electronics', '2025-12-31'],
        ['Running Shoes', 'Comfortable athletic shoes', 79.99, 30, 'Sports', '2025-12-31'],
        ['Denim Jacket', 'Classic denim jacket', 69.99, 22, 'Fashion', '2025-12-31'],
        ['Leather Bag', 'Premium leather handbag', 129.99, 15, 'Fashion', '2025-12-31'],
        ['Sunglasses', 'Stylish sunglasses', 39.99, 35, 'Fashion', '2025-12-31'],
        ['Wristwatch', 'Elegant wristwatch', 159.99, 10, 'Fashion', '2025-12-31'],
        ['Coffee Maker', 'Automatic coffee machine', 129.99, 12, 'Home & Kitchen', '2025-12-31'],
        ['Bedside Lamp', 'LED bedside lamp', 45.99, 28, 'Home & Kitchen', '2025-12-31'],
        ['Kitchen Mixer', 'Professional kitchen mixer', 199.99, 8, 'Home & Kitchen', '2025-12-31'],
        ['Wall Clock', 'Modern wall clock', 29.99, 40, 'Home & Kitchen', '2025-12-31'],
        ['Throw Pillow', 'Comfortable throw pillow', 24.99, 50, 'Home & Kitchen', '2025-12-31'],
        ['Yoga Mat', 'Non-slip yoga mat', 34.99, 25, 'Sports', '2025-12-31'],
        ['Dumbbells Set', 'Weight training dumbbells', 149.99, 6, 'Sports', '2025-12-31'],
        ['Basketball', 'Official size basketball', 49.99, 20, 'Sports', '2025-12-31'],
        ['Programming Book', 'Advanced programming guide', 39.99, 15, 'Books', '2025-12-31'],
        ['Notebook Set', 'Premium notebook set', 19.99, 30, 'Books', '2025-12-31']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO products (name, description, price, stock_quantity, category, expiry_date) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($products as $product) {
        $stmt->execute($product);
    }
    echo "<p>✅ Sample products created/verified</p>";
    
    // Create sample orders for analytics
    echo "<h3>📈 Creating Sample Orders for Analytics...</h3>";
    
    // Get admin user ID
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
    $stmt->execute();
    $admin_user = $stmt->fetch();
    $admin_id = $admin_user['id'];
    
    // Get product IDs
    $stmt = $pdo->prepare("SELECT id, price FROM products WHERE is_active = 1");
    $stmt->execute();
    $all_products = $stmt->fetchAll();
    
    // Create sample orders for the last 7 days
    for ($day = 6; $day >= 0; $day--) {
        $order_date = date('Y-m-d H:i:s', strtotime("-$day days"));
        $num_orders = rand(3, 8); // 3-8 orders per day
        
        for ($order = 0; $order < $num_orders; $order++) {
            // Create order
            $order_number = 'ORD' . date('Ymd') . rand(1000, 9999);
            $total_amount = 0;
            $tax_amount = 0;
            $discount_amount = rand(0, 20);
            $final_amount = 0;
            
            // Insert order
            $stmt = $pdo->prepare("
                INSERT INTO orders (user_id, order_number, total_amount, tax_amount, discount_amount, final_amount, 
                                  payment_method, payment_status, customer_phone, customer_name, customer_email, order_status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $admin_id,
                $order_number,
                0, // Will update after items
                0,
                $discount_amount,
                0, // Will update after items
                'card',
                'completed',
                '+8801' . rand(100000000, 999999999),
                'Customer ' . rand(1, 100),
                'customer' . rand(1, 100) . '@example.com',
                'delivered',
                $order_date
            ]);
            
            $order_id = $pdo->lastInsertId();
            
            // Add 1-3 items to each order
            $num_items = rand(1, 3);
            $selected_products = array_rand($all_products, min($num_items, count($all_products)));
            if (!is_array($selected_products)) {
                $selected_products = [$selected_products];
            }
            
            foreach ($selected_products as $product_index) {
                $product = $all_products[$product_index];
                $quantity = rand(1, 3);
                $unit_price = $product['price'];
                $item_total = $quantity * $unit_price;
                $total_amount += $item_total;
                
                // Insert order item
                $stmt = $pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$order_id, $product['id'], $quantity, $unit_price, $item_total]);
            }
            
            // Update order with final amounts
            $tax_amount = $total_amount * 0.15; // 15% tax
            $final_amount = $total_amount + $tax_amount - $discount_amount;
            
            $stmt = $pdo->prepare("
                UPDATE orders SET total_amount = ?, tax_amount = ?, final_amount = ?
                WHERE id = ?
            ");
            $stmt->execute([$total_amount, $tax_amount, $final_amount, $order_id]);
        }
    }
    
    echo "<p>✅ Sample orders created for analytics</p>";
    
    // Verify analytics data
    echo "<h3>🔍 Verifying Analytics Data...</h3>";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE payment_status = 'completed'");
    $stmt->execute();
    $order_count = $stmt->fetch()['total_orders'];
    echo "<p>✅ Total completed orders: $order_count</p>";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_products FROM products WHERE is_active = 1");
    $stmt->execute();
    $product_count = $stmt->fetch()['total_products'];
    echo "<p>✅ Total active products: $product_count</p>";
    
    $stmt = $pdo->prepare("SELECT SUM(final_amount) as total_sales FROM orders WHERE payment_status = 'completed'");
    $stmt->execute();
    $total_sales = $stmt->fetch()['total_sales'];
    echo "<p>✅ Total sales amount: ৳" . number_format($total_sales, 2) . "</p>";
    
    echo "<h3>🎉 Database Fix Completed!</h3>";
    echo "<p><strong>Test Accounts:</strong></p>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> username=admin, password=admin123</li>";
    echo "<li><strong>Receptionist:</strong> username=receptionist, password=receptionist123</li>";
    echo "</ul>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Login to admin dashboard: <a href='admin-dashboard.html'>Admin Dashboard</a></li>";
    echo "<li>Go to Sales Analytics section</li>";
    echo "<li>Click 'Refresh Now' to load the analytics data</li>";
    echo "<li>The analytics should now display real data instead of loading messages</li>";
    echo "</ol>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database fix failed: " . $e->getMessage() . "</p>";
    echo "<p>Please make sure:</p>";
    echo "<ul>";
    echo "<li>MySQL server is running</li>";
    echo "<li>Database credentials in backend/config.php are correct</li>";
    echo "<li>User has permission to create databases</li>";
    echo "</ul>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
h2, h3 { color: #333; }
p { margin: 10px 0; }
ul, ol { margin: 10px 0; padding-left: 20px; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
</style> 