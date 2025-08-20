<?php
// Database setup script for Super Shop Management System
require_once 'backend/config.php';

echo "Setting up Super Shop Management System Database...\n";

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
    echo "✓ Database '" . DB_NAME . "' created/verified\n";
    
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
    echo "✓ Database tables created successfully\n";
    
    // Insert sample data
    echo "Inserting sample data...\n";
    
    // Sample admin user
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password_hash, first_name, last_name, role, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@supershop.com', $admin_password, 'Admin', 'User', 'admin', 1]);
    
    // Sample receptionist user
    $receptionist_password = password_hash('receptionist123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password_hash, first_name, last_name, role, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['receptionist', 'receptionist@supershop.com', $receptionist_password, 'Receptionist', 'User', 'receptionist', 1]);
    
    // Sample products with image URLs
    $products = [
        ['Laptop', 'High-performance laptop with latest specifications', 999.99, 50, 'Electronics', '2025-12-31', 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=300&h=300&fit=crop'],
        ['Smartphone', 'Latest smartphone model with advanced features', 599.99, 100, 'Electronics', '2025-12-31', 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=300&h=300&fit=crop'],
        ['Headphones', 'Wireless noise-canceling headphones', 199.99, 75, 'Electronics', '2025-12-31', 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=300&h=300&fit=crop'],
        ['Coffee Maker', 'Automatic coffee machine for perfect brew', 89.99, 30, 'Home & Kitchen', '2025-12-31', 'https://images.unsplash.com/photo-1517668808822-9ebb02f2a0e6?w=300&h=300&fit=crop'],
        ['Running Shoes', 'Comfortable athletic shoes for daily use', 129.99, 60, 'Sports', '2025-12-31', 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=300&h=300&fit=crop'],
        ['Backpack', 'Durable school backpack with multiple compartments', 49.99, 80, 'Fashion', '2025-12-31', 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=300&h=300&fit=crop'],
        ['Bluetooth Speaker', 'Portable wireless speaker with great sound', 79.99, 45, 'Electronics', '2025-12-31', 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=300&h=300&fit=crop'],
        ['Desk Lamp', 'LED desk lamp with adjustable brightness', 39.99, 55, 'Home & Kitchen', '2025-12-31', 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=300&h=300&fit=crop'],
        ['Yoga Mat', 'Premium yoga mat for home workouts', 34.99, 40, 'Sports', '2025-12-31', 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?w=300&h=300&fit=crop'],
        ['Sunglasses', 'Stylish sunglasses with UV protection', 59.99, 70, 'Fashion', '2025-12-31', 'https://images.unsplash.com/photo-1572635196237-14b3f281503f?w=300&h=300&fit=crop'],
        ['Smart Watch', 'Fitness tracking smartwatch', 299.99, 25, 'Electronics', '2025-12-31', 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=300&h=300&fit=crop'],
        ['Kitchen Mixer', 'Professional kitchen mixer for baking', 199.99, 15, 'Home & Kitchen', '2025-12-31', 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=300&h=300&fit=crop']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO products (name, description, price, stock_quantity, category, expiry_date, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($products as $product) {
        $stmt->execute($product);
    }
    
    echo "✓ Sample data inserted successfully\n";
    echo "\n🎉 Database setup completed!\n\n";
    echo "Test Accounts:\n";
    echo "Admin: username=admin, password=admin123\n";
    echo "Receptionist: username=receptionist, password=receptionist123\n";
    echo "\nYou can now access your Super Shop Management System at: http://localhost:8000\n";
    
} catch (PDOException $e) {
    echo "❌ Database setup failed: " . $e->getMessage() . "\n";
    echo "Please make sure:\n";
    echo "1. MySQL server is running\n";
    echo "2. Database credentials in backend/config.php are correct\n";
    echo "3. User has permission to create databases\n";
}
?> 