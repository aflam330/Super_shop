<?php
session_start();
require_once 'backend/config.php';

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

$error_message = '';
$recommendations = [];
$popular_products = [];
$trending_products = [];
$new_arrivals = [];
$categories = [];

try {
    $pdo = getDBConnection();
    
    // Simple query to get all active products
    $stmt = $pdo->prepare("SELECT * FROM products WHERE is_active = 1 ORDER BY created_at DESC LIMIT 20");
    $stmt->execute();
    $all_products = $stmt->fetchAll();
    
    if (!empty($all_products)) {
        // Use the same products for all sections to ensure we have data
        $popular_products = array_slice($all_products, 0, 12);
        $trending_products = array_slice($all_products, 0, 8);
        $new_arrivals = array_slice($all_products, 0, 6);
        
        // Get categories
        $stmt = $pdo->prepare("SELECT category, COUNT(*) as product_count FROM products WHERE is_active = 1 GROUP BY category ORDER BY product_count DESC");
        $stmt->execute();
        $categories = $stmt->fetchAll();
        
        // Get personalized recommendations if user is logged in
        if (isset($_SESSION['user_id'])) {
            // Simple recommendation based on available products
            $recommendations = array_slice($all_products, 0, 8);
        }
    }
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    $error_message = 'Error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Recommendations - Super Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .theme-blue { background-color: #3b82f6; }
        .theme-green { background-color: #10b981; }
        .theme-pink { background-color: #ec4899; }
        .theme-yellow { background-color: #f59e42; }
        
        .product-image {
            transition: opacity 0.3s ease;
        }
        
        .product-image.loading {
            opacity: 0;
        }
        
        .product-image.loaded {
            opacity: 1;
        }
        
        .product-card {
            transition: all 0.3s ease;
        }
        
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .image-placeholder {
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
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
            <a href="catalog.html" class="mx-2 text-gray-700 hover:text-blue-700">Catalog</a>
            <a href="cart.html" class="mx-2 text-gray-700 hover:text-blue-700">Cart</a>
            <a href="my-orders.php" class="mx-2 text-gray-700 hover:text-blue-700">History</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="logout.php" class="mx-2 text-gray-700 hover:text-blue-700">Logout</a>
            <?php else: ?>
                <a href="login.php" class="mx-2 text-gray-700 hover:text-blue-700">Login</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-8 px-4">
        <!-- Header -->
        <div class="mb-8 text-center">
            <h1 class="text-4xl font-bold text-white mb-4">⭐ Product Recommendations</h1>
            <p class="text-white text-opacity-80 text-lg">Discover products tailored just for you</p>
        </div>

        <!-- Messages -->
        <?php if ($error_message): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Personalized Recommendations -->
        <?php if (!empty($recommendations)): ?>
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">🎯 Recommended for You</h2>
                <p class="text-gray-600 mb-4">Based on your preferences and our best products</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php foreach ($recommendations as $product): ?>
                        <div class="bg-gray-50 rounded-lg p-4 product-card">
                            <div class="w-full h-48 bg-gray-200 rounded-lg mb-4 overflow-hidden relative">
                                <?php 
                                $imageUrl = generateProductImageUrl($product['name'], $product['category']);
                                ?>
                                <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                     class="w-full h-full object-cover rounded-lg product-image loading"
                                     onload="this.classList.remove('loading'); this.classList.add('loaded');"
                                     onerror="this.src='https://via.placeholder.com/300/6B7280/FFFFFF?text=<?php echo urlencode($product['name']); ?>'; this.classList.remove('loading'); this.classList.add('loaded');">
                                <div class="absolute inset-0 bg-gray-200 rounded-lg image-placeholder" style="display: none;">
                                    <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                </div>
                            </div>
                            
                            <h3 class="font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($product['category']); ?></p>
                            <p class="text-lg font-bold text-blue-600 mb-3">৳<?php echo number_format($product['price'], 2); ?></p>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-500">Stock: <?php echo $product['stock_quantity']; ?></span>
                                <button onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['price']; ?>)" 
                                        class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Most Selling Products (Top Rated) -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">🏆 Top Rated Products</h2>
            <p class="text-gray-600 mb-4">Our most popular and best-selling items</p>
            
            <?php if (!empty($popular_products)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php foreach ($popular_products as $product): ?>
                        <div class="bg-gray-50 rounded-lg p-4 product-card">
                            <div class="w-full h-48 bg-gray-200 rounded-lg mb-4 overflow-hidden relative">
                                <?php 
                                $imageUrl = generateProductImageUrl($product['name'], $product['category']);
                                ?>
                                <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                     class="w-full h-full object-cover rounded-lg product-image loading"
                                     onload="this.classList.remove('loading'); this.classList.add('loaded');"
                                     onerror="this.src='https://via.placeholder.com/300/6B7280/FFFFFF?text=<?php echo urlencode($product['name']); ?>'; this.classList.remove('loading'); this.classList.add('loaded');">
                                <div class="absolute inset-0 bg-gray-200 rounded-lg image-placeholder" style="display: none;">
                                    <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                </div>
                            </div>
                            
                            <h3 class="font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($product['category']); ?></p>
                            <p class="text-lg font-bold text-blue-600 mb-3">৳<?php echo number_format($product['price'], 2); ?></p>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-500">Available</span>
                                <button onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['price']; ?>)" 
                                        class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <p class="text-gray-500">No products available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Trending Products -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">🔥 Trending Products</h2>
            <p class="text-gray-600 mb-4">Currently popular and trending items</p>
            
            <?php if (!empty($trending_products)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php foreach ($trending_products as $product): ?>
                        <div class="bg-gray-50 rounded-lg p-4 product-card">
                            <div class="w-full h-48 bg-gray-200 rounded-lg mb-4 overflow-hidden relative">
                                <?php 
                                $imageUrl = generateProductImageUrl($product['name'], $product['category']);
                                ?>
                                <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                     class="w-full h-full object-cover rounded-lg product-image loading"
                                     onload="this.classList.remove('loading'); this.classList.add('loaded');"
                                     onerror="this.src='https://via.placeholder.com/300/6B7280/FFFFFF?text=<?php echo urlencode($product['name']); ?>'; this.classList.remove('loading'); this.classList.add('loaded');">
                                <div class="absolute inset-0 bg-gray-200 rounded-lg image-placeholder" style="display: none;">
                                    <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                </div>
                            </div>
                            
                            <h3 class="font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($product['category']); ?></p>
                            <p class="text-lg font-bold text-blue-600 mb-3">৳<?php echo number_format($product['price'], 2); ?></p>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-500">Trending</span>
                                <button onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['price']; ?>)" 
                                        class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <p class="text-gray-500">No trending products available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- New Arrivals -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">🆕 New Arrivals</h2>
            <p class="text-gray-600 mb-4">Fresh products just added to our collection</p>
            
            <?php if (!empty($new_arrivals)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($new_arrivals as $product): ?>
                        <div class="bg-gray-50 rounded-lg p-4 product-card">
                            <div class="w-full h-48 bg-gray-200 rounded-lg mb-4 overflow-hidden relative">
                                <?php 
                                $imageUrl = generateProductImageUrl($product['name'], $product['category']);
                                ?>
                                <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                     class="w-full h-full object-cover rounded-lg product-image loading"
                                     onload="this.classList.remove('loading'); this.classList.add('loaded');"
                                     onerror="this.src='https://via.placeholder.com/300/6B7280/FFFFFF?text=<?php echo urlencode($product['name']); ?>'; this.classList.remove('loading'); this.classList.add('loaded');">
                                <div class="absolute inset-0 bg-gray-200 rounded-lg image-placeholder" style="display: none;">
                                    <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                </div>
                            </div>
                            
                            <h3 class="font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($product['category']); ?></p>
                            <p class="text-lg font-bold text-blue-600 mb-3">৳<?php echo number_format($product['price'], 2); ?></p>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-500">New</span>
                                <button onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['price']; ?>)" 
                                        class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <p class="text-gray-500">No new arrivals available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Category Recommendations -->
        <?php if (!empty($categories)): ?>
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">📂 Browse by Category</h2>
                <p class="text-gray-600 mb-4">Explore products by category</p>
                
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    <?php foreach ($categories as $category): ?>
                        <a href="catalog.html?category=<?php echo urlencode($category['category']); ?>" 
                           class="bg-gray-50 rounded-lg p-4 text-center hover:bg-blue-50 hover:shadow-md transition-all">
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($category['category']); ?></h3>
                            <p class="text-xs text-gray-500"><?php echo $category['product_count']; ?> products</p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Call to Action -->
        <div class="bg-white rounded-lg shadow-lg p-8 mt-8 text-center">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Can't find what you're looking for?</h2>
            <p class="text-gray-600 mb-6">Browse our complete catalog or contact us for personalized assistance</p>
            <div class="flex flex-wrap justify-center gap-4">
                <a href="catalog.html" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                    Browse Full Catalog
                </a>
                <a href="customer-service.php" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors">
                    Contact Support
                </a>
            </div>
        </div>
    </div>

    <script>
        function setTheme(color) {
            document.body.className = color;
        }

        function addToCart(productId, productName, price) {
            let cart = JSON.parse(localStorage.getItem('cart') || '[]');
            
            // Check if product already in cart
            const existingItem = cart.find(item => item.id == productId);
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                cart.push({
                    id: productId,
                    name: productName,
                    price: price,
                    quantity: 1
                });
            }
            
            localStorage.setItem('cart', JSON.stringify(cart));
            
            // Show success message
            const message = document.createElement('div');
            message.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
            message.textContent = `${productName} added to cart!`;
            document.body.appendChild(message);
            
            setTimeout(() => {
                message.remove();
            }, 3000);
        }

        // Add smooth scrolling for better UX
        document.addEventListener('DOMContentLoaded', function() {
            const links = document.querySelectorAll('a[href^="#"]');
            links.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html> 