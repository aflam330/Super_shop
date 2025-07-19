<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config.php';

class OrdersAPI {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Generate unique order number
    private function generateOrderNumber() {
        $prefix = 'ORD';
        $year = date('Y');
        $month = date('m');
        
        // Get the last order number for this month
        $stmt = $this->pdo->prepare("
            SELECT order_number FROM orders 
            WHERE order_number LIKE ? 
            ORDER BY id DESC LIMIT 1
        ");
        $pattern = $prefix . '-' . $year . '-' . $month . '-%';
        $stmt->execute([$pattern]);
        
        if ($stmt->rowCount() > 0) {
            $lastOrder = $stmt->fetch();
            $lastNumber = intval(substr($lastOrder['order_number'], -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . '-' . $year . '-' . $month . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
    
    // Create new order
    public function createOrder() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->error('Method not allowed', 405);
        }
        
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            return $this->error('Not authenticated', 401);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            return $this->error('Invalid JSON data', 400);
        }
        
        $required_fields = ['items', 'payment_method', 'customer_info'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return $this->error("Missing required field: $field", 400);
            }
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Generate unique order number
            $order_number = $this->generateOrderNumber();
            
            // Calculate totals
            $total_amount = 0;
            $tax_amount = 0;
            $discount_amount = $data['discount_amount'] ?? 0;
            
            // Validate items and calculate totals
            foreach ($data['items'] as $item) {
                $stmt = $this->pdo->prepare("SELECT price, stock_quantity FROM products WHERE id = ? AND is_active = 1");
                $stmt->execute([$item['product_id']]);
                
                if ($stmt->rowCount() === 0) {
                    $this->pdo->rollBack();
                    return $this->error("Product not found: " . $item['product_id'], 400);
                }
                
                $product = $stmt->fetch();
                
                if ($product['stock_quantity'] < $item['quantity']) {
                    $this->pdo->rollBack();
                    return $this->error("Insufficient stock for product ID: " . $item['product_id'], 400);
                }
                
                $total_amount += $product['price'] * $item['quantity'];
            }
            
            // Calculate tax (5% of total)
            $tax_amount = $total_amount * 0.05;
            $final_amount = $total_amount + $tax_amount - $discount_amount;
            
            // Create order
            $stmt = $this->pdo->prepare("
                INSERT INTO orders (
                    user_id, order_number, total_amount, tax_amount, discount_amount, final_amount,
                    payment_method, payment_status, customer_phone, customer_name, customer_email,
                    customer_address, order_status, transaction_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $customer_info = $data['customer_info'];
            $payment_status = $data['payment_status'] ?? 'pending';
            $transaction_id = $data['transaction_id'] ?? null;
            
            $stmt->execute([
                $_SESSION['user_id'],
                $order_number,
                $total_amount,
                $tax_amount,
                $discount_amount,
                $final_amount,
                $data['payment_method'],
                $payment_status,
                $customer_info['phone'] ?? null,
                $customer_info['name'] ?? null,
                $customer_info['email'] ?? null,
                $customer_info['address'] ?? null,
                'pending',
                $transaction_id
            ]);
            
            $order_id = $this->pdo->lastInsertId();
            
            // Create order items and update stock
            foreach ($data['items'] as $item) {
                // Insert order item
                $stmt = $this->pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $product_stmt = $this->pdo->prepare("SELECT price FROM products WHERE id = ?");
                $product_stmt->execute([$item['product_id']]);
                $product = $product_stmt->fetch();
                
                $unit_price = $product['price'];
                $total_price = $unit_price * $item['quantity'];
                
                $stmt->execute([
                    $order_id,
                    $item['product_id'],
                    $item['quantity'],
                    $unit_price,
                    $total_price
                ]);
                
                // Update product stock
                $update_stmt = $this->pdo->prepare("
                    UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?
                ");
                $update_stmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            // Log order creation event
            $log_stmt = $this->pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
            $log_data = json_encode([
                'order_id' => $order_id,
                'order_number' => $order_number,
                'user_id' => $_SESSION['user_id'],
                'total_amount' => $final_amount
            ]);
            $log_stmt->execute(['order_created', $log_data]);
            
            $this->pdo->commit();
            
            return $this->success([
                'message' => 'Order created successfully',
                'order_id' => $order_id,
                'order_number' => $order_number,
                'total_amount' => $final_amount
            ]);
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return $this->error('Order creation failed: ' . $e->getMessage(), 500);
        }
    }
    
    // Get order by order number (for tracking)
    public function trackOrder() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->error('Method not allowed', 405);
        }
        
        $order_number = $_GET['order_number'] ?? '';
        
        if (empty($order_number)) {
            return $this->error('Order number is required', 400);
        }
        
        try {
            // Get order details
            $stmt = $this->pdo->prepare("
                SELECT o.*, u.username, u.first_name, u.last_name
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.order_number = ?
            ");
            $stmt->execute([$order_number]);
            
            if ($stmt->rowCount() === 0) {
                return $this->error('Order not found', 404);
            }
            
            $order = $stmt->fetch();
            
            // Get order items
            $stmt = $this->pdo->prepare("
                SELECT oi.*, p.name, p.image_url
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$order['id']]);
            $items = $stmt->fetchAll();
            
            // Get order status history (you can extend this with a status_history table)
            $status_history = [
                [
                    'status' => 'pending',
                    'description' => 'Order placed',
                    'timestamp' => $order['created_at']
                ]
            ];
            
            // Add status updates based on current status
            if ($order['order_status'] !== 'pending') {
                $status_history[] = [
                    'status' => $order['order_status'],
                    'description' => ucfirst($order['order_status']),
                    'timestamp' => $order['updated_at']
                ];
            }
            
            return $this->success([
                'order' => $order,
                'items' => $items,
                'status_history' => $status_history
            ]);
            
        } catch (Exception $e) {
            return $this->error('Error tracking order: ' . $e->getMessage(), 500);
        }
    }
    
    // Get customer order history
    public function getCustomerOrders() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->error('Method not allowed', 405);
        }
        
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            return $this->error('Not authenticated', 401);
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT o.*, 
                       COUNT(oi.id) as total_items,
                       GROUP_CONCAT(CONCAT(p.name, ' (', oi.quantity, ')') SEPARATOR ', ') as items_summary
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE o.user_id = ?
                GROUP BY o.id
                ORDER BY o.created_at DESC
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $orders = $stmt->fetchAll();
            
            return $this->success(['orders' => $orders]);
            
        } catch (Exception $e) {
            return $this->error('Error fetching orders: ' . $e->getMessage(), 500);
        }
    }
    
    // Get order details by ID
    public function getOrderDetails() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->error('Method not allowed', 405);
        }
        
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            return $this->error('Not authenticated', 401);
        }
        
        $order_id = $_GET['order_id'] ?? '';
        
        if (empty($order_id)) {
            return $this->error('Order ID is required', 400);
        }
        
        try {
            // Get order details
            $stmt = $this->pdo->prepare("
                SELECT o.*, u.username, u.first_name, u.last_name
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.id = ? AND o.user_id = ?
            ");
            $stmt->execute([$order_id, $_SESSION['user_id']]);
            
            if ($stmt->rowCount() === 0) {
                return $this->error('Order not found', 404);
            }
            
            $order = $stmt->fetch();
            
            // Get order items
            $stmt = $this->pdo->prepare("
                SELECT oi.*, p.name, p.image_url, p.description
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$order_id]);
            $items = $stmt->fetchAll();
            
            return $this->success([
                'order' => $order,
                'items' => $items
            ]);
            
        } catch (Exception $e) {
            return $this->error('Error fetching order details: ' . $e->getMessage(), 500);
        }
    }
    
    // Update order status (admin/receptionist only)
    public function updateOrderStatus() {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            return $this->error('Method not allowed', 405);
        }
        
        session_start();
        
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'receptionist'])) {
            return $this->error('Access denied', 403);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || empty($data['order_id']) || empty($data['status'])) {
            return $this->error('Order ID and status are required', 400);
        }
        
        $allowed_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        
        if (!in_array($data['status'], $allowed_statuses)) {
            return $this->error('Invalid status', 400);
        }
        
        try {
            $stmt = $this->pdo->prepare("
                UPDATE orders SET order_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?
            ");
            
            if ($stmt->execute([$data['status'], $data['order_id']])) {
                // Log status update
                $log_stmt = $this->pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
                $log_data = json_encode([
                    'order_id' => $data['order_id'],
                    'status' => $data['status'],
                    'updated_by' => $_SESSION['user_id']
                ]);
                $log_stmt->execute(['order_status_updated', $log_data]);
                
                return $this->success(['message' => 'Order status updated successfully']);
            } else {
                return $this->error('Failed to update order status', 500);
            }
            
        } catch (Exception $e) {
            return $this->error('Error updating order status: ' . $e->getMessage(), 500);
        }
    }
    
    private function success($data) {
        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $data]);
    }
    
    private function error($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
    }
}

// Initialize API
$pdo = getDBConnection();
$orders = new OrdersAPI($pdo);

// Route requests
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'create':
        $orders->createOrder();
        break;
    case 'track':
        $orders->trackOrder();
        break;
    case 'history':
        $orders->getCustomerOrders();
        break;
    case 'details':
        $orders->getOrderDetails();
        break;
    case 'update_status':
        $orders->updateOrderStatus();
        break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Action not found']);
        break;
}
?> 