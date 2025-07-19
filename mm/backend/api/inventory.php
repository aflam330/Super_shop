<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $input['action'] ?? '';

try {
    $pdo = getDBConnection();
    
    switch ($action) {
        case 'getProducts':
            handleGetProducts($pdo, $_GET);
            break;
            
        case 'getProduct':
            handleGetProduct($pdo, $_GET);
            break;
            
        case 'addProduct':
            handleAddProduct($pdo, $input);
            break;
            
        case 'updateProduct':
            handleUpdateProduct($pdo, $input);
            break;
            
        case 'deleteProduct':
            handleDeleteProduct($pdo, $input);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

function handleGetProducts($pdo, $params) {
    $search = $params['search'] ?? '';
    $category = $params['category'] ?? '';
    
    $whereConditions = ['is_active = 1'];
    $queryParams = [];
    
    if (!empty($search)) {
        $whereConditions[] = '(name LIKE ? OR description LIKE ?)';
        $queryParams[] = "%$search%";
        $queryParams[] = "%$search%";
    }
    
    if (!empty($category)) {
        $whereConditions[] = 'category = ?';
        $queryParams[] = $category;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $sql = "SELECT * FROM products WHERE $whereClause ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $products = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'products' => $products
    ]);
}

function handleGetProduct($pdo, $params) {
    $id = $params['id'] ?? 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Product ID is required']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'product' => $product
    ]);
}

function handleAddProduct($pdo, $data) {
    // Validate required fields
    $requiredFields = ['name', 'category', 'price', 'stock_quantity'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            return;
        }
    }
    
    // Validate price and stock
    if (!is_numeric($data['price']) || $data['price'] < 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Price must be a positive number']);
        return;
    }
    
    if (!is_numeric($data['stock_quantity']) || $data['stock_quantity'] < 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Stock quantity must be a positive number']);
        return;
    }
    
    // Validate expiry date if provided
    if (!empty($data['expiry_date'])) {
        $expiryDate = DateTime::createFromFormat('Y-m-d', $data['expiry_date']);
        if (!$expiryDate || $expiryDate->format('Y-m-d') !== $data['expiry_date']) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid expiry date format']);
            return;
        }
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO products (name, description, category, price, stock_quantity, expiry_date, image_url, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        
        $stmt->execute([
            $data['name'],
            $data['description'] ?? '',
            $data['category'],
            $data['price'],
            $data['stock_quantity'],
            $data['expiry_date'] ?: null,
            $data['image_url'] ?? ''
        ]);
        
        $productId = $pdo->lastInsertId();
        
        // Log the event
        $log_stmt = $pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
        $log_data = json_encode([
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'product_id' => $productId,
            'product_name' => $data['name']
        ]);
        $log_stmt->execute(['product_added', $log_data]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Product added successfully',
            'product_id' => $productId
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error adding product: ' . $e->getMessage()]);
    }
}

function handleUpdateProduct($pdo, $data) {
    // Validate required fields
    $requiredFields = ['id', 'name', 'category', 'price', 'stock_quantity'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            return;
        }
    }
    
    // Validate price and stock
    if (!is_numeric($data['price']) || $data['price'] < 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Price must be a positive number']);
        return;
    }
    
    if (!is_numeric($data['stock_quantity']) || $data['stock_quantity'] < 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Stock quantity must be a positive number']);
        return;
    }
    
    // Validate expiry date if provided
    if (!empty($data['expiry_date'])) {
        $expiryDate = DateTime::createFromFormat('Y-m-d', $data['expiry_date']);
        if (!$expiryDate || $expiryDate->format('Y-m-d') !== $data['expiry_date']) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid expiry date format']);
            return;
        }
    }
    
    try {
        // Check if product exists
        $check_stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1");
        $check_stmt->execute([$data['id']]);
        
        if ($check_stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
            return;
        }
        
        $stmt = $pdo->prepare("
            UPDATE products 
            SET name = ?, description = ?, category = ?, price = ?, stock_quantity = ?, 
                expiry_date = ?, image_url = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['name'],
            $data['description'] ?? '',
            $data['category'],
            $data['price'],
            $data['stock_quantity'],
            $data['expiry_date'] ?: null,
            $data['image_url'] ?? '',
            $data['id']
        ]);
        
        // Log the event
        $log_stmt = $pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
        $log_data = json_encode([
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'product_id' => $data['id'],
            'product_name' => $data['name']
        ]);
        $log_stmt->execute(['product_updated', $log_data]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Product updated successfully'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error updating product: ' . $e->getMessage()]);
    }
}

function handleDeleteProduct($pdo, $data) {
    $id = $data['id'] ?? 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Product ID is required']);
        return;
    }
    
    try {
        // Check if product exists and get its name for logging
        $check_stmt = $pdo->prepare("SELECT name FROM products WHERE id = ? AND is_active = 1");
        $check_stmt->execute([$id]);
        
        if ($check_stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
            return;
        }
        
        $product = $check_stmt->fetch();
        
        // Soft delete - set is_active to 0
        $stmt = $pdo->prepare("UPDATE products SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$id]);
        
        // Log the event
        $log_stmt = $pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
        $log_data = json_encode([
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'product_id' => $id,
            'product_name' => $product['name']
        ]);
        $log_stmt->execute(['product_deleted', $log_data]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error deleting product: ' . $e->getMessage()]);
    }
}
?> 