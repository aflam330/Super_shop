<?php
// Script to add more sample products with automatic image assignment
require_once 'backend/config.php';

echo "Adding sample products with automatic image assignment...\n";

try {
    $pdo = getDBConnection();
    
    // Additional sample products (these will get automatic images)
    $additionalProducts = [
        // Electronics
        ['Gaming Laptop', 'High-performance gaming laptop with RGB keyboard', 1299.99, 15, 'Electronics'],
        ['Wireless Mouse', 'Ergonomic wireless mouse for productivity', 29.99, 100, 'Electronics'],
        ['Mechanical Keyboard', 'RGB mechanical keyboard with blue switches', 89.99, 45, 'Electronics'],
        ['Webcam', 'HD webcam for video conferencing', 49.99, 60, 'Electronics'],
        ['USB-C Hub', 'Multi-port USB-C hub for laptops', 39.99, 80, 'Electronics'],
        
        // Fashion
        ['Leather Wallet', 'Genuine leather wallet with card slots', 24.99, 120, 'Fashion'],
        ['Denim Jeans', 'Classic blue denim jeans', 59.99, 75, 'Fashion'],
        ['Cotton T-Shirt', 'Comfortable cotton t-shirt', 19.99, 200, 'Fashion'],
        ['Summer Dress', 'Lightweight summer dress', 45.99, 50, 'Fashion'],
        ['Winter Jacket', 'Warm winter jacket with hood', 89.99, 30, 'Fashion'],
        
        // Home & Kitchen
        ['Toaster', '2-slice toaster with bagel setting', 34.99, 40, 'Home & Kitchen'],
        ['Microwave', 'Countertop microwave oven', 79.99, 25, 'Home & Kitchen'],
        ['Rice Cooker', 'Automatic rice cooker with timer', 44.99, 35, 'Home & Kitchen'],
        ['Air Fryer', 'Digital air fryer for healthy cooking', 69.99, 20, 'Home & Kitchen'],
        ['Blender', 'High-speed blender for smoothies', 54.99, 30, 'Home & Kitchen'],
        
        // Sports
        ['Tennis Racket', 'Professional tennis racket', 89.99, 25, 'Sports'],
        ['Soccer Ball', 'Official size soccer ball', 34.99, 40, 'Sports'],
        ['Swimming Goggles', 'Anti-fog swimming goggles', 19.99, 60, 'Sports'],
        ['Resistance Bands', 'Set of 5 resistance bands', 24.99, 80, 'Sports'],
        ['Jump Rope', 'Adjustable jump rope for cardio', 14.99, 100, 'Sports'],
        
        // Books
        ['Cookbook', 'Collection of easy recipes', 29.99, 45, 'Books'],
        ['Novel', 'Bestselling fiction novel', 19.99, 60, 'Books'],
        ['Planner', '2024 daily planner', 15.99, 80, 'Books'],
        ['Coloring Book', 'Adult coloring book for relaxation', 12.99, 70, 'Books'],
        ['Puzzle Book', 'Crossword and word search book', 9.99, 90, 'Books']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO products (name, description, price, stock_quantity, category) VALUES (?, ?, ?, ?, ?)");
    $addedCount = 0;
    
    foreach ($additionalProducts as $product) {
        try {
            $stmt->execute($product);
            if ($stmt->rowCount() > 0) {
                $addedCount++;
                echo "✓ Added {$product[0]} to {$product[4]} category\n";
            }
        } catch (PDOException $e) {
            // Product might already exist, skip
            echo "- Skipped {$product[0]} (may already exist)\n";
        }
    }
    
    echo "\n🎉 Successfully added $addedCount new products!\n";
    echo "All products will automatically get appropriate images based on their category and name.\n";
    
    // Now update all products without images
    echo "\nUpdating products without images...\n";
    
    $updateStmt = $pdo->prepare("UPDATE products SET image_url = ? WHERE id = ?");
    $stmt = $pdo->prepare("SELECT * FROM products WHERE image_url IS NULL OR image_url = ''");
    $stmt->execute();
    $productsWithoutImages = $stmt->fetchAll();
    
    $updatedCount = 0;
    foreach ($productsWithoutImages as $product) {
        $imageUrl = generateProductImageUrl($product['name'], $product['category']);
        $updateStmt->execute([$imageUrl, $product['id']]);
        $updatedCount++;
        echo "✓ Updated {$product['name']} with auto-generated image\n";
    }
    
    echo "\n🎉 Successfully updated $updatedCount products with automatic images!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// Function to automatically generate image URLs for products
function generateProductImageUrl($productName, $category) {
    $name = strtolower($productName);
    $category = strtolower($category);
    
    // Comprehensive image mappings for different product categories
    $imageMappings = [
        'electronics' => [
            'laptop' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=300&h=300&fit=crop',
            'computer' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=300&h=300&fit=crop',
            'smartphone' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=300&h=300&fit=crop',
            'phone' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=300&h=300&fit=crop',
            'headphones' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=300&h=300&fit=crop',
            'earbuds' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=300&h=300&fit=crop',
            'speaker' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=300&h=300&fit=crop',
            'bluetooth' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=300&h=300&fit=crop',
            'watch' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=300&h=300&fit=crop',
            'smartwatch' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=300&h=300&fit=crop',
            'tablet' => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=300&h=300&fit=crop',
            'camera' => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=300&h=300&fit=crop',
            'tv' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=300&h=300&fit=crop',
            'television' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=300&h=300&fit=crop',
            'gaming' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?w=300&h=300&fit=crop',
            'console' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?w=300&h=300&fit=crop',
            'mouse' => 'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=300&h=300&fit=crop',
            'keyboard' => 'https://images.unsplash.com/photo-1541140532154-b024d705b90a?w=300&h=300&fit=crop',
            'webcam' => 'https://images.unsplash.com/photo-1593642632823-8f785ba67e45?w=300&h=300&fit=crop',
            'hub' => 'https://images.unsplash.com/photo-1593642632823-8f785ba67e45?w=300&h=300&fit=crop',
            'default' => 'https://images.unsplash.com/photo-1498049794561-7780e7231661?w=300&h=300&fit=crop'
        ],
        'fashion' => [
            'shoes' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=300&h=300&fit=crop',
            'sneakers' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=300&h=300&fit=crop',
            'backpack' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=300&h=300&fit=crop',
            'bag' => 'https://images.unsplash.com/photo-1590874103328-eac38a683ce7?w=300&h=300&fit=crop',
            'sunglasses' => 'https://images.unsplash.com/photo-1572635196237-14b3f281503f?w=300&h=300&fit=crop',
            'glasses' => 'https://images.unsplash.com/photo-1572635196237-14b3f281503f?w=300&h=300&fit=crop',
            'watch' => 'https://images.unsplash.com/photo-1524592094714-0f0654e20314?w=300&h=300&fit=crop',
            'jacket' => 'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=300&h=300&fit=crop',
            'shirt' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=300&h=300&fit=crop',
            'dress' => 'https://images.unsplash.com/photo-1515372039744-b8f02a3ae446?w=300&h=300&fit=crop',
            'jeans' => 'https://images.unsplash.com/photo-1542272604-787c3835535d?w=300&h=300&fit=crop',
            'pants' => 'https://images.unsplash.com/photo-1542272604-787c3835535d?w=300&h=300&fit=crop',
            'hat' => 'https://images.unsplash.com/photo-1521369909029-2afed882baee?w=300&h=300&fit=crop',
            'cap' => 'https://images.unsplash.com/photo-1521369909029-2afed882baee?w=300&h=300&fit=crop',
            'wallet' => 'https://images.unsplash.com/photo-1627123424574-724758594e93?w=300&h=300&fit=crop',
            't-shirt' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=300&h=300&fit=crop',
            'default' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=300&h=300&fit=crop'
        ],
        'home & kitchen' => [
            'coffee' => 'https://images.unsplash.com/photo-1517668808822-9ebb02f2a0e6?w=300&h=300&fit=crop',
            'maker' => 'https://images.unsplash.com/photo-1517668808822-9ebb02f2a0e6?w=300&h=300&fit=crop',
            'lamp' => 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=300&h=300&fit=crop',
            'light' => 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=300&h=300&fit=crop',
            'mixer' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=300&h=300&fit=crop',
            'blender' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=300&h=300&fit=crop',
            'clock' => 'https://images.unsplash.com/photo-1563861826100-9cb868fdbe1c?w=300&h=300&fit=crop',
            'pillow' => 'https://images.unsplash.com/photo-1584100936595-c0654b55a2e2?w=300&h=300&fit=crop',
            'cushion' => 'https://images.unsplash.com/photo-1584100936595-c0654b55a2e2?w=300&h=300&fit=crop',
            'chair' => 'https://images.unsplash.com/photo-1567538096630-e0c55bd6374c?w=300&h=300&fit=crop',
            'table' => 'https://images.unsplash.com/photo-1533090481720-856c6e3c1fdc?w=300&h=300&fit=crop',
            'sofa' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=300&h=300&fit=crop',
            'bed' => 'https://images.unsplash.com/photo-1505693314120-0d443867891c?w=300&h=300&fit=crop',
            'toaster' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=300&h=300&fit=crop',
            'microwave' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=300&h=300&fit=crop',
            'rice' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=300&h=300&fit=crop',
            'fryer' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=300&h=300&fit=crop',
            'default' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=300&h=300&fit=crop'
        ],
        'sports' => [
            'yoga' => 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?w=300&h=300&fit=crop',
            'mat' => 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?w=300&h=300&fit=crop',
            'dumbbells' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=300&h=300&fit=crop',
            'weights' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=300&h=300&fit=crop',
            'basketball' => 'https://images.unsplash.com/photo-1546519638-68e109498ffc?w=300&h=300&fit=crop',
            'football' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=300&h=300&fit=crop',
            'tennis' => 'https://images.unsplash.com/photo-1551698618-1dfe5d97d256?w=300&h=300&fit=crop',
            'gym' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=300&h=300&fit=crop',
            'fitness' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=300&h=300&fit=crop',
            'bike' => 'https://images.unsplash.com/photo-1532298229144-0ec0c57515c7?w=300&h=300&fit=crop',
            'bicycle' => 'https://images.unsplash.com/photo-1532298229144-0ec0c57515c7?w=300&h=300&fit=crop',
            'racket' => 'https://images.unsplash.com/photo-1551698618-1dfe5d97d256?w=300&h=300&fit=crop',
            'soccer' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=300&h=300&fit=crop',
            'swimming' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=300&h=300&fit=crop',
            'goggles' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=300&h=300&fit=crop',
            'resistance' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=300&h=300&fit=crop',
            'jump' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=300&h=300&fit=crop',
            'default' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=300&h=300&fit=crop'
        ],
        'books' => [
            'book' => 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=300&h=300&fit=crop',
            'notebook' => 'https://images.unsplash.com/photo-1531346680769-a1d79b57de5c?w=300&h=300&fit=crop',
            'pen' => 'https://images.unsplash.com/photo-1583485088034-697b5bc36b35?w=300&h=300&fit=crop',
            'pencil' => 'https://images.unsplash.com/photo-1583485088034-697b5bc36b35?w=300&h=300&fit=crop',
            'stationery' => 'https://images.unsplash.com/photo-1531346680769-a1d79b57de5c?w=300&h=300&fit=crop',
            'cookbook' => 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=300&h=300&fit=crop',
            'novel' => 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=300&h=300&fit=crop',
            'planner' => 'https://images.unsplash.com/photo-1531346680769-a1d79b57de5c?w=300&h=300&fit=crop',
            'coloring' => 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=300&h=300&fit=crop',
            'puzzle' => 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=300&h=300&fit=crop',
            'default' => 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=300&h=300&fit=crop'
        ]
    ];
    
    // Find matching image based on category and product name
    if (isset($imageMappings[$category])) {
        $categoryImages = $imageMappings[$category];
        
        // Try to find a specific match
        foreach ($categoryImages as $keyword => $url) {
            if ($keyword !== 'default' && strpos($name, $keyword) !== false) {
                return $url;
            }
        }
        
        // Use default if no specific match found
        if (isset($categoryImages['default'])) {
            return $categoryImages['default'];
        }
    }
    
    // Use a generic image if no category mapping found
    return 'https://images.unsplash.com/photo-1498049794561-7780e7231661?w=300&h=300&fit=crop';
}
?>
