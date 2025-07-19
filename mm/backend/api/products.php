<?php
require_once '../config.php';

setCORSHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

switch ($method) {
    case 'GET':
        // Get products with optional filtering
        $category = $_GET['category'] ?? null;
        $search = $_GET['search'] ?? null;
        
        $sql = "SELECT * FROM products WHERE 1=1";
        $params = [];
        
        if ($category && $category !== 'all') {
            $sql .= " AND category = ?";
            $params[] = validateInput($category);
        }
        
        if ($search) {
            $sql .= " AND (name LIKE ? OR description LIKE ?)";
            $searchTerm = '%' . validateInput($search) . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll();
            
            // Add real-time stock update event
            $eventData = [
                'type' => 'stock_update',
                'products' => array_map(function($product) {
                    return [
                        'id' => $product['id'],
                        'name' => $product['name'],
                        'stock' => $product['stock_quantity']
                    ];
                }, $products)
            ];
            
            $stmt = $pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
            $stmt->execute(['stock_update', json_encode($eventData)]);
            
            sendResponse(['products' => $products]);
        } catch (PDOException $e) {
            sendError('Failed to fetch products: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'POST':
        // Create new product
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['name']) || !isset($input['price'])) {
            sendError('Name and price are required');
        }
        
        $name = validateInput($input['name']);
        $description = validateInput($input['description'] ?? '');
        $price = floatval($input['price']);
        $stock = intval($input['stock_quantity'] ?? 0);
        $category = validateInput($input['category'] ?? '');
        $imageUrl = validateInput($input['image_url'] ?? '');
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO products (name, description, price, stock_quantity, category, image_url)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $price, $stock, $category, $imageUrl]);
            
            $productId = $pdo->lastInsertId();
            
            // Add real-time event for new product
            $eventData = [
                'type' => 'new_product',
                'product' => [
                    'id' => $productId,
                    'name' => $name,
                    'price' => $price,
                    'stock' => $stock
                ]
            ];
            
            $stmt = $pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
            $stmt->execute(['new_offer', json_encode($eventData)]);
            
            sendResponse(['id' => $productId, 'message' => 'Product created successfully'], 201);
        } catch (PDOException $e) {
            sendError('Failed to create product: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'PUT':
        // Update product
        $input = json_decode(file_get_contents('php://input'), true);
        $productId = $_GET['id'] ?? null;
        
        if (!$productId) {
            sendError('Product ID is required');
        }
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            if (!$product) {
                sendError('Product not found', 404);
            }
            
            $name = validateInput($input['name'] ?? $product['name']);
            $description = validateInput($input['description'] ?? $product['description']);
            $price = floatval($input['price'] ?? $product['price']);
            $stock = intval($input['stock_quantity'] ?? $product['stock_quantity']);
            $category = validateInput($input['category'] ?? $product['category']);
            $imageUrl = validateInput($input['image_url'] ?? $product['image_url']);
            
            $stmt = $pdo->prepare("
                UPDATE products 
                SET name = ?, description = ?, price = ?, stock_quantity = ?, category = ?, image_url = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $price, $stock, $category, $imageUrl, $productId]);
            
            // Add real-time event for stock update
            if ($stock != $product['stock_quantity']) {
                $eventData = [
                    'type' => 'stock_update',
                    'product' => [
                        'id' => $productId,
                        'name' => $name,
                        'stock' => $stock,
                        'old_stock' => $product['stock_quantity']
                    ]
                ];
                
                $stmt = $pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
                $stmt->execute(['stock_update', json_encode($eventData)]);
            }
            
            sendResponse(['message' => 'Product updated successfully']);
        } catch (PDOException $e) {
            sendError('Failed to update product: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'DELETE':
        // Delete product
        $productId = $_GET['id'] ?? null;
        
        if (!$productId) {
            sendError('Product ID is required');
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            
            if ($stmt->rowCount() === 0) {
                sendError('Product not found', 404);
            }
            
            sendResponse(['message' => 'Product deleted successfully']);
        } catch (PDOException $e) {
            sendError('Failed to delete product: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?> 