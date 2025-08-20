<?php
// Script to update existing products with image URLs
require_once 'backend/config.php';

echo "Updating product images in database...\n";

try {
    $pdo = getDBConnection();
    
    // Get all products that don't have image URLs
    $stmt = $pdo->prepare("SELECT * FROM products WHERE image_url IS NULL OR image_url = ''");
    $stmt->execute();
    $products = $stmt->fetchAll();
    
    if (empty($products)) {
        echo "✓ All products already have image URLs\n";
        exit;
    }
    
    echo "Found " . count($products) . " products without images. Updating...\n";
    
    // Image URL mappings for different product categories
    $imageMappings = [
        'Electronics' => [
            'laptop' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=300&h=300&fit=crop',
            'smartphone' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=300&h=300&fit=crop',
            'headphones' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=300&h=300&fit=crop',
            'bluetooth speaker' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=300&h=300&fit=crop',
            'smart watch' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=300&h=300&fit=crop',
            'tablet' => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=300&h=300&fit=crop',
            'camera' => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=300&h=300&fit=crop',
            'default' => 'https://images.unsplash.com/photo-1498049794561-7780e7231661?w=300&h=300&fit=crop'
        ],
        'Fashion' => [
            'shoes' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=300&h=300&fit=crop',
            'backpack' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=300&h=300&fit=crop',
            'sunglasses' => 'https://images.unsplash.com/photo-1572635196237-14b3f281503f?w=300&h=300&fit=crop',
            'watch' => 'https://images.unsplash.com/photo-1524592094714-0f0654e20314?w=300&h=300&fit=crop',
            'bag' => 'https://images.unsplash.com/photo-1590874103328-eac38a683ce7?w=300&h=300&fit=crop',
            'jacket' => 'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=300&h=300&fit=crop',
            'default' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=300&h=300&fit=crop'
        ],
        'Home & Kitchen' => [
            'coffee maker' => 'https://images.unsplash.com/photo-1517668808822-9ebb02f2a0e6?w=300&h=300&fit=crop',
            'lamp' => 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=300&h=300&fit=crop',
            'mixer' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=300&h=300&fit=crop',
            'clock' => 'https://images.unsplash.com/photo-1563861826100-9cb868fdbe1c?w=300&h=300&fit=crop',
            'pillow' => 'https://images.unsplash.com/photo-1584100936595-c0654b55a2e2?w=300&h=300&fit=crop',
            'default' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=300&h=300&fit=crop'
        ],
        'Sports' => [
            'yoga mat' => 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?w=300&h=300&fit=crop',
            'dumbbells' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=300&h=300&fit=crop',
            'basketball' => 'https://images.unsplash.com/photo-1546519638-68e109498ffc?w=300&h=300&fit=crop',
            'default' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=300&h=300&fit=crop'
        ],
        'Books' => [
            'book' => 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=300&h=300&fit=crop',
            'notebook' => 'https://images.unsplash.com/photo-1531346680769-a1d79b57de5c?w=300&h=300&fit=crop',
            'default' => 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=300&h=300&fit=crop'
        ]
    ];
    
    $updateStmt = $pdo->prepare("UPDATE products SET image_url = ? WHERE id = ?");
    $updatedCount = 0;
    
    foreach ($products as $product) {
        $category = $product['category'];
        $name = strtolower($product['name']);
        $imageUrl = null;
        
        // Find matching image based on category and product name
        if (isset($imageMappings[$category])) {
            $categoryImages = $imageMappings[$category];
            
            // Try to find a specific match
            foreach ($categoryImages as $keyword => $url) {
                if ($keyword !== 'default' && strpos($name, $keyword) !== false) {
                    $imageUrl = $url;
                    break;
                }
            }
            
            // Use default if no specific match found
            if (!$imageUrl && isset($categoryImages['default'])) {
                $imageUrl = $categoryImages['default'];
            }
        }
        
        // Use a generic image if no category mapping found
        if (!$imageUrl) {
            $imageUrl = 'https://images.unsplash.com/photo-1498049794561-7780e7231661?w=300&h=300&fit=crop';
        }
        
        // Update the product
        $updateStmt->execute([$imageUrl, $product['id']]);
        $updatedCount++;
        
        echo "✓ Updated {$product['name']} with image URL\n";
    }
    
    echo "\n🎉 Successfully updated $updatedCount products with image URLs!\n";
    
} catch (PDOException $e) {
    echo "❌ Error updating product images: " . $e->getMessage() . "\n";
}
?>
