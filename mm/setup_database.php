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
    
    // Sample products
    $products = [
        ['Laptop', 'High-performance laptop', 999.99, 50, 'Electronics', '2025-12-31'],
        ['Smartphone', 'Latest smartphone model', 599.99, 100, 'Electronics', '2025-12-31'],
        ['Headphones', 'Wireless noise-canceling headphones', 199.99, 75, 'Electronics', '2025-12-31'],
        ['Coffee Maker', 'Automatic coffee machine', 89.99, 30, 'Home & Kitchen', '2025-12-31'],
        ['Running Shoes', 'Comfortable athletic shoes', 129.99, 60, 'Sports', '2025-12-31'],
        ['Backpack', 'Durable school backpack', 49.99, 80, 'Fashion', '2025-12-31'],
        ['Bluetooth Speaker', 'Portable wireless speaker', 79.99, 45, 'Electronics', '2025-12-31'],
        ['Desk Lamp', 'LED desk lamp with adjustable brightness', 39.99, 55, 'Home & Kitchen', '2025-12-31']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO products (name, description, price, stock_quantity, category, expiry_date) VALUES (?, ?, ?, ?, ?, ?)");
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