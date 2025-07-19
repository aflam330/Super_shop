<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config.php';

class ReceptionistAPI {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // Check receptionist authentication
    private function checkReceptionistAuth() {
        session_start();
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'receptionist') {
            return false;
        }
        return true;
    }
    
    // Get top-rated products (FR10)
    public function getTopRatedProducts() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkReceptionistAuth()) {
            return $this->error('Access denied', 403);
        }
        
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    p.id,
                    p.name,
                    p.price,
                    p.stock_quantity,
                    p.category,
                    p.image_url,
                    AVG(r.rating) as avg_rating,
                    COUNT(r.id) as review_count
                FROM products p
                LEFT JOIN reviews r ON p.id = r.product_id
                WHERE p.is_active = 1
                GROUP BY p.id, p.name, p.price, p.stock_quantity, p.category, p.image_url
                HAVING avg_rating >= 4.0
                ORDER BY avg_rating DESC, review_count DESC
                LIMIT 10
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $products = [];
            while ($row = $result->fetch_assoc()) {
                $products[] = $row;
            }
            
            return $this->success(['products' => $products]);
        } catch (Exception $e) {
            return $this->error('Failed to get top-rated products: ' . $e->getMessage(), 500);
        }
    }
    
    // Get trending products (FR10)
    public function getTrendingProducts() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkReceptionistAuth()) {
            return $this->error('Access denied', 403);
        }
        
        $period = $_GET['period'] ?? '7'; // Default 7 days
        
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    p.id,
                    p.name,
                    p.price,
                    p.stock_quantity,
                    p.category,
                    p.image_url,
                    SUM(oi.quantity) as total_sold
                FROM products p
                JOIN order_items oi ON p.id = oi.product_id
                JOIN orders o ON oi.order_id = o.id
                WHERE o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                AND o.payment_status = 'completed'
                AND p.is_active = 1
                GROUP BY p.id, p.name, p.price, p.stock_quantity, p.category, p.image_url
                ORDER BY total_sold DESC
                LIMIT 10
            ");
            $stmt->bind_param("i", $period);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $products = [];
            while ($row = $result->fetch_assoc()) {
                $products[] = $row;
            }
            
            return $this->success(['products' => $products]);
        } catch (Exception $e) {
            return $this->error('Failed to get trending products: ' . $e->getMessage(), 500);
        }
    }
    
    // Generate bill (FR9)
    public function generateBill() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkReceptionistAuth()) {
            return $this->error('Access denied', 403);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || empty($data['items']) || empty($data['customer_info'])) {
            return $this->error('Items and customer info are required', 400);
        }
        
        try {
            $this->conn->begin_transaction();
            
            // Calculate totals
            $subtotal = 0;
            $tax_rate = 0.10; // 10% tax
            $discount_rate = 0.05; // 5% discount for orders above $100
            
            foreach ($data['items'] as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }
            
            $tax_amount = $subtotal * $tax_rate;
            $discount_amount = $subtotal > 100 ? $subtotal * $discount_rate : 0;
            $final_amount = $subtotal + $tax_amount - $discount_amount;
            
            // Generate order number
            $order_number = 'ORD-' . date('Y-m-d') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Create order
            $stmt = $this->conn->prepare("
                INSERT INTO orders (
                    user_id, order_number, total_amount, tax_amount, discount_amount, 
                    final_amount, payment_method, payment_status, customer_name, 
                    customer_email, customer_phone, customer_address, order_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'processing')
            ");
            
            $user_id = $data['customer_info']['user_id'] ?? null;
            $payment_method = $data['payment_method'] ?? 'cash';
            $payment_status = 'completed'; // Default to completed for receptionist-generated bills
            $customer_name = $data['customer_info']['name'];
            $customer_email = $data['customer_info']['email'] ?? null;
            $customer_phone = $data['customer_info']['phone'] ?? null;
            $customer_address = $data['customer_info']['address'] ?? null;
            
            $stmt->bind_param("isddddssssss", 
                $user_id, $order_number, $subtotal, $tax_amount, $discount_amount, 
                $final_amount, $payment_method, $payment_status, $customer_name, 
                $customer_email, $customer_phone, $customer_address
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to create order');
            }
            
            $order_id = $stmt->insert_id;
            
            // Add order items
            $stmt = $this->conn->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($data['items'] as $item) {
                $total_price = $item['price'] * $item['quantity'];
                $stmt->bind_param("iiidd", $order_id, $item['product_id'], $item['quantity'], $item['price'], $total_price);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to add order item');
                }
                
                // Update product stock
                $update_stmt = $this->conn->prepare("
                    UPDATE products 
                    SET stock_quantity = stock_quantity - ? 
                    WHERE id = ?
                ");
                $update_stmt->bind_param("ii", $item['quantity'], $item['product_id']);
                $update_stmt->execute();
            }
            
            $this->conn->commit();
            
            // Log order creation event
            $this->logEvent('order_created', [
                'order_id' => $order_id,
                'order_number' => $order_number,
                'total_amount' => $final_amount,
                'created_by' => $_SESSION['user_id']
            ]);
            
            return $this->success([
                'message' => 'Bill generated successfully',
                'order' => [
                    'id' => $order_id,
                    'order_number' => $order_number,
                    'subtotal' => $subtotal,
                    'tax_amount' => $tax_amount,
                    'discount_amount' => $discount_amount,
                    'final_amount' => $final_amount,
                    'items' => $data['items']
                ]
            ]);
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return $this->error('Failed to generate bill: ' . $e->getMessage(), 500);
        }
    }
    
    // Get return policy info (FR11)
    public function getReturnPolicy() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkReceptionistAuth()) {
            return $this->error('Access denied', 403);
        }
        
        return $this->success([
            'return_policy' => [
                'return_period' => 5,
                'conditions' => [
                    'Product must be in original condition',
                    'Original packaging required',
                    'Valid purchase receipt needed',
                    'No returns on perishable items',
                    'Return within 5 days of purchase'
                ],
                'process' => [
                    '1. Bring product and receipt to store',
                    '2. Receptionist will verify purchase',
                    '3. Product condition will be checked',
                    '4. Refund will be processed',
                    '5. Return will be logged in system'
                ]
            ]
        ]);
    }
    
    // Submit weekly report (FR12)
    public function submitWeeklyReport() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkReceptionistAuth()) {
            return $this->error('Access denied', 403);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || empty($data['week_start_date']) || empty($data['week_end_date'])) {
            return $this->error('Week start and end dates are required', 400);
        }
        
        try {
            // Calculate sales data for the week
            $stmt = $this->conn->prepare("
                SELECT 
                    COUNT(*) as total_orders,
                    COALESCE(SUM(final_amount), 0) as total_sales
                FROM orders 
                WHERE DATE(created_at) BETWEEN ? AND ?
                AND payment_status = 'completed'
            ");
            $stmt->bind_param("ss", $data['week_start_date'], $data['week_end_date']);
            $stmt->execute();
            $sales_data = $stmt->get_result()->fetch_assoc();
            
            // Get top selling products
            $stmt = $this->conn->prepare("
                SELECT 
                    p.name,
                    SUM(oi.quantity) as total_sold
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                JOIN orders o ON oi.order_id = o.id
                WHERE DATE(o.created_at) BETWEEN ? AND ?
                AND o.payment_status = 'completed'
                GROUP BY p.id, p.name
                ORDER BY total_sold DESC
                LIMIT 5
            ");
            $stmt->bind_param("ss", $data['week_start_date'], $data['week_end_date']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $top_products = [];
            while ($row = $result->fetch_assoc()) {
                $top_products[] = $row['name'];
            }
            
            // Insert weekly report
            $stmt = $this->conn->prepare("
                INSERT INTO weekly_reports (
                    receptionist_id, week_start_date, week_end_date, total_sales, 
                    total_orders, top_selling_products, customer_feedback_summary, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $top_products_str = implode(', ', $top_products);
            $feedback_summary = $data['customer_feedback_summary'] ?? 'Overall positive feedback';
            $notes = $data['notes'] ?? '';
            
            $stmt->bind_param("issdssss", 
                $_SESSION['user_id'], $data['week_start_date'], $data['week_end_date'], 
                $sales_data['total_sales'], $sales_data['total_orders'], $top_products_str, 
                $feedback_summary, $notes
            );
            
            if ($stmt->execute()) {
                $report_id = $stmt->insert_id;
                
                // Log report submission
                $this->logEvent('weekly_report_submitted', [
                    'report_id' => $report_id,
                    'receptionist_id' => $_SESSION['user_id'],
                    'week_start' => $data['week_start_date'],
                    'week_end' => $data['week_end_date']
                ]);
                
                return $this->success([
                    'message' => 'Weekly report submitted successfully',
                    'report_id' => $report_id
                ]);
            } else {
                return $this->error('Failed to submit weekly report', 500);
            }
            
        } catch (Exception $e) {
            return $this->error('Failed to submit weekly report: ' . $e->getMessage(), 500);
        }
    }
    
    // Get customer assistance data
    public function getCustomerAssistanceData() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkReceptionistAuth()) {
            return $this->error('Access denied', 403);
        }
        
        try {
            // Get recent orders
            $stmt = $this->conn->prepare("
                SELECT 
                    o.id, o.order_number, o.customer_name, o.final_amount, 
                    o.order_status, o.created_at
                FROM orders o
                ORDER BY o.created_at DESC
                LIMIT 10
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $recent_orders = [];
            while ($row = $result->fetch_assoc()) {
                $recent_orders[] = $row;
            }
            
            // Get pending returns
            $stmt = $this->conn->prepare("
                SELECT 
                    r.id, r.return_reason, r.return_date,
                    o.order_number, o.customer_name,
                    p.name as product_name
                FROM returns r
                JOIN orders o ON r.order_id = o.id
                JOIN products p ON r.product_id = p.id
                WHERE r.return_status = 'pending'
                ORDER BY r.return_date DESC
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $pending_returns = [];
            while ($row = $result->fetch_assoc()) {
                $pending_returns[] = $row;
            }
            
            // Get low stock products
            $stmt = $this->conn->prepare("
                SELECT id, name, stock_quantity, category, price
                FROM products 
                WHERE stock_quantity < 10 AND is_active = 1
                ORDER BY stock_quantity ASC
                LIMIT 10
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $low_stock = [];
            while ($row = $result->fetch_assoc()) {
                $low_stock[] = $row;
            }
            
            return $this->success([
                'recent_orders' => $recent_orders,
                'pending_returns' => $pending_returns,
                'low_stock_products' => $low_stock
            ]);
            
        } catch (Exception $e) {
            return $this->error('Failed to get customer assistance data: ' . $e->getMessage(), 500);
        }
    }
    
    // Process return (FR11)
    public function processReturn() {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkReceptionistAuth()) {
            return $this->error('Access denied', 403);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || empty($data['return_id']) || empty($data['action'])) {
            return $this->error('Return ID and action are required', 400);
        }
        
        try {
            $this->conn->begin_transaction();
            
            // Get return details
            $stmt = $this->conn->prepare("
                SELECT r.*, o.order_number, p.name as product_name, p.stock_quantity
                FROM returns r
                JOIN orders o ON r.order_id = o.id
                JOIN products p ON r.product_id = p.id
                WHERE r.id = ?
            ");
            $stmt->bind_param("i", $data['return_id']);
            $stmt->execute();
            $return = $stmt->get_result()->fetch_assoc();
            
            if (!$return) {
                return $this->error('Return not found', 404);
            }
            
            $new_status = $data['action'] === 'approve' ? 'approved' : 'rejected';
            $notes = $data['notes'] ?? '';
            
            // Update return status
            $stmt = $this->conn->prepare("
                UPDATE returns 
                SET return_status = ?, processed_by = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sisi", $new_status, $_SESSION['user_id'], $notes, $data['return_id']);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update return status');
            }
            
            // If approved, update product stock and set refund amount
            if ($new_status === 'approved') {
                // Update product stock
                $stmt = $this->conn->prepare("
                    UPDATE products 
                    SET stock_quantity = stock_quantity + 1
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $return['product_id']);
                $stmt->execute();
                
                // Set refund amount (full price for simplicity)
                $stmt = $this->conn->prepare("
                    UPDATE returns 
                    SET refund_amount = (
                        SELECT unit_price 
                        FROM order_items 
                        WHERE order_id = ? AND product_id = ?
                        LIMIT 1
                    )
                    WHERE id = ?
                ");
                $stmt->bind_param("iii", $return['order_id'], $return['product_id'], $data['return_id']);
                $stmt->execute();
            }
            
            $this->conn->commit();
            
            // Log return processing event
            $this->logEvent('return_processed', [
                'return_id' => $data['return_id'],
                'action' => $new_status,
                'processed_by' => $_SESSION['user_id'],
                'notes' => $notes
            ]);
            
            return $this->success([
                'message' => "Return $new_status successfully",
                'return_status' => $new_status
            ]);
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return $this->error('Failed to process return: ' . $e->getMessage(), 500);
        }
    }
    
    // Helper method to log events
    private function logEvent($event_type, $event_data) {
        $stmt = $this->conn->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
        $json_data = json_encode($event_data);
        $stmt->bind_param("ss", $event_type, $json_data);
        $stmt->execute();
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
$receptionist = new ReceptionistAPI($conn);

// Route requests
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'top_rated':
        $receptionist->getTopRatedProducts();
        break;
    case 'trending':
        $receptionist->getTrendingProducts();
        break;
    case 'generate_bill':
        $receptionist->generateBill();
        break;
    case 'return_policy':
        $receptionist->getReturnPolicy();
        break;
    case 'submit_report':
        $receptionist->submitWeeklyReport();
        break;
    case 'assistance_data':
        $receptionist->getCustomerAssistanceData();
        break;
    case 'process_return':
        $receptionist->processReturn();
        break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Action not found']);
        break;
}
?> 