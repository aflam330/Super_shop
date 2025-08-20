<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once dirname(__FILE__) . '/../config.php';

class ReceptionistAPI {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Check receptionist authentication
    private function checkReceptionistAuth() {
        // First try session-based auth
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'receptionist') {
            return true;
        }
        
        // Fallback to header-based auth for frontend requests
        $userId = $_SERVER['HTTP_X_USER_ID'] ?? null;
        $userRole = $_SERVER['HTTP_X_USER_ROLE'] ?? null;
        
        if ($userId && $userRole === 'receptionist') {
            // Set session for future requests
            $_SESSION['user_id'] = $userId;
            $_SESSION['role'] = $userRole;
            return true;
        }
        
        return false;
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
            $this->pdo->beginTransaction();
            
            // Calculate totals
            $subtotal = 0;
            $tax_rate = 0.10; // 10% tax
            $discount_rate = 0.05; // 5% discount for orders above ৳100
            
            foreach ($data['items'] as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }
            
            $tax_amount = $subtotal * $tax_rate;
            $discount_amount = $subtotal > 100 ? $subtotal * $discount_rate : 0;
            $final_amount = $subtotal + $tax_amount - $discount_amount;
            
            // Generate order number
            $order_number = 'ORD-' . date('Y-m-d') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Create order
            $stmt = $this->pdo->prepare("
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
            
            $stmt->execute([
                $user_id, $order_number, $subtotal, $tax_amount, $discount_amount, 
                $final_amount, $payment_method, $payment_status, $customer_name, 
                $customer_email, $customer_phone, $customer_address
            ]);
            
            $order_id = $this->pdo->lastInsertId();
            
            // Add order items
            $stmt = $this->pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($data['items'] as $item) {
                $total_price = $item['price'] * $item['quantity'];
                $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price'], $total_price]);
                
                // Update product stock
                $update_stmt = $this->pdo->prepare("
                    UPDATE products 
                    SET stock_quantity = stock_quantity - ? 
                    WHERE id = ?
                ");
                $update_stmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            $this->pdo->commit();
            
            // Log bill generation event
            $this->logEvent('bill_generated', [
                'order_id' => $order_id,
                'order_number' => $order_number,
                'receptionist_id' => $_SESSION['user_id'],
                'total_amount' => $final_amount
            ]);
            
            return $this->success([
                'message' => 'Bill generated successfully',
                'order_id' => $order_id,
                'order_number' => $order_number,
                'total_amount' => $final_amount
            ]);
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
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
                'return_period' => 30,
                'conditions' => [
                    'Product must be in original condition',
                    'Original packaging and tags intact',
                    'No signs of use or damage',
                    'Proof of purchase required',
                    'Return shipping label provided by us'
                ],
                'process' => [
                    '1. Submit return request with order details',
                    '2. Get approval within 24 hours',
                    '3. Pack item securely and ship using provided label',
                    '4. Receive refund within 3-5 business days'
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
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_orders,
                    COALESCE(SUM(final_amount), 0) as total_sales
                FROM orders 
                WHERE DATE(created_at) BETWEEN ? AND ?
                AND payment_status = 'completed'
            ");
            $stmt->execute([$data['week_start_date'], $data['week_end_date']]);
            $sales_data = $stmt->fetch();
            
            // Get top selling products
            $stmt = $this->pdo->prepare("
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
            $stmt->execute([$data['week_start_date'], $data['week_end_date']]);
            $top_products_result = $stmt->fetchAll();
            
            $top_products = array_column($top_products_result, 'name');
            
            // Insert weekly report
            $stmt = $this->pdo->prepare("
                INSERT INTO weekly_reports (
                    receptionist_id, week_start_date, week_end_date, total_sales, 
                    total_orders, top_selling_products, customer_feedback_summary, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $top_products_str = implode(', ', $top_products);
            $feedback_summary = $data['customer_feedback_summary'] ?? 'Overall positive feedback';
            $notes = $data['notes'] ?? '';
            
            $stmt->execute([
                $_SESSION['user_id'], $data['week_start_date'], $data['week_end_date'], 
                $sales_data['total_sales'], $sales_data['total_orders'], $top_products_str, 
                $feedback_summary, $notes
            ]);
            
            $report_id = $this->pdo->lastInsertId();
            
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
            
        } catch (Exception $e) {
            return $this->error('Failed to submit weekly report: ' . $e->getMessage(), 500);
        }
    }
    
    // Get customer assistance data (FR8)
    public function getCustomerAssistanceData() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkReceptionistAuth()) {
            return $this->error('Access denied', 403);
        }
        
        try {
            // Get recent orders
            $stmt = $this->pdo->prepare("
                SELECT o.order_number, o.customer_name, o.final_amount, o.order_status, o.created_at
                FROM orders o
                WHERE o.order_status != 'deleted'
                ORDER BY o.created_at DESC
                LIMIT 5
            ");
            $stmt->execute();
            $recentOrders = $stmt->fetchAll();
            
            // Get pending returns - Using correct column names (return_date, not created_at)
            $stmt = $this->pdo->prepare("
                SELECT r.id, r.return_reason, r.return_date, o.order_number, p.name as product_name
                FROM returns r
                JOIN orders o ON r.order_id = o.id
                JOIN products p ON r.product_id = p.id
                WHERE r.return_status = 'pending'
                ORDER BY r.return_date DESC
                LIMIT 5
            ");
            $stmt->execute();
            $pendingReturns = $stmt->fetchAll();
            
            // Get low stock products
            $stmt = $this->pdo->prepare("
                SELECT id, name, stock_quantity, category
                FROM products
                WHERE stock_quantity <= 10 AND is_active = 1
                ORDER BY stock_quantity ASC
                LIMIT 5
            ");
            $stmt->execute();
            $lowStockProducts = $stmt->fetchAll();
            
            return $this->success([
                'recent_orders' => $recentOrders,
                'pending_returns' => $pendingReturns,
                'low_stock_products' => $lowStockProducts
            ]);
            
        } catch (Exception $e) {
            return $this->error('Failed to get customer assistance data: ' . $e->getMessage(), 500);
        }
    }

    // Get dashboard statistics (New function)
    public function getDashboardStats() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkReceptionistAuth()) {
            return $this->error('Access denied', 403);
        }
        
        try {
            // Get recent orders count
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM orders
                WHERE order_status != 'deleted'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute();
            $recentOrders = $stmt->fetch()['count'];
            
            // Get pending returns count
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM returns
                WHERE return_status = 'pending'
            ");
            $stmt->execute();
            $pendingReturns = $stmt->fetch()['count'];
            
            // Get low stock items count
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM products
                WHERE stock_quantity <= 10 AND is_active = 1
            ");
            $stmt->execute();
            $lowStockItems = $stmt->fetch()['count'];
            
            // Get today's sales
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(final_amount), 0) as total
                FROM orders
                WHERE DATE(created_at) = CURDATE()
                AND order_status != 'cancelled'
            ");
            $stmt->execute();
            $todaySales = $stmt->fetch()['total'];
            
            return $this->success([
                'recent_orders' => $recentOrders,
                'pending_returns' => $pendingReturns,
                'low_stock_items' => $lowStockItems,
                'today_sales' => number_format($todaySales, 2)
            ]);
            
        } catch (Exception $e) {
            return $this->error('Failed to get dashboard stats: ' . $e->getMessage(), 500);
        }
    }
    
    // Process return (FR11)
    public function processReturn() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkReceptionistAuth()) {
            return $this->error('Access denied', 403);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $returnId = $input['return_id'] ?? null;
        $action = $input['action'] ?? null; // 'approve' or 'reject'
        $notes = $input['notes'] ?? '';
        
        if (!$returnId || !$action) {
            return $this->error('Missing required fields', 400);
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Get return details
            $stmt = $this->pdo->prepare("
                SELECT r.id, r.order_id, r.user_id, r.product_id, r.return_reason, r.return_status, 
                       r.refund_amount, r.return_date, r.processed_by, r.notes,
                       o.order_number, p.name as product_name, u.first_name, u.last_name
                FROM returns r
                JOIN orders o ON r.order_id = o.id
                JOIN products p ON r.product_id = p.id
                LEFT JOIN users u ON r.user_id = u.id
                WHERE r.id = ? AND r.return_status = 'pending'
            ");
            $stmt->execute([$returnId]);
            $return = $stmt->fetch();
            
            if (!$return) {
                return $this->error('Return not found or already processed', 404);
            }
            
            $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
            $refundAmount = ($action === 'approve') ? $return['refund_amount'] : 0;
            
            // Update return status
            $stmt = $this->pdo->prepare("
                UPDATE returns 
                SET return_status = ?, processed_by = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$newStatus, $_SESSION['user_id'], $notes, $returnId]);
            
            // If approved, update product stock (assuming quantity of 1 for returns)
            if ($action === 'approve') {
                $stmt = $this->pdo->prepare("
                    UPDATE products 
                    SET stock_quantity = stock_quantity + 1 
                    WHERE id = ?
                ");
                $stmt->execute([$return['product_id']]);
            }
            
            // Log event
            $customerName = ($return['first_name'] && $return['last_name']) 
                ? $return['first_name'] . ' ' . $return['last_name'] 
                : 'Unknown Customer';
            
            $eventData = [
                'return_id' => $returnId,
                'action' => $action,
                'customer_name' => $customerName,
                'product_name' => $return['product_name'],
                'order_number' => $return['order_number']
            ];
            $this->logEvent('return_processed', $eventData);
            
            $this->pdo->commit();
            
            return $this->success([
                'message' => 'Return ' . $action . 'ed successfully',
                'return_id' => $returnId,
                'status' => $newStatus
            ]);
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return $this->error('Failed to process return: ' . $e->getMessage(), 500);
        }
    }

    // Get orders for management (New function)
    public function getOrders() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkReceptionistAuth()) {
            return $this->error('Access denied', 403);
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    o.id,
                    o.order_number,
                    o.customer_name,
                    o.customer_email,
                    o.customer_phone,
                    o.customer_address,
                    o.total_amount,
                    o.tax_amount,
                    o.discount_amount,
                    o.final_amount,
                    o.payment_method,
                    o.payment_status,
                    o.order_status,
                    o.created_at,
                    o.updated_at,
                    COUNT(oi.id) as item_count
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.order_status != 'deleted'
                GROUP BY o.id, o.order_number, o.customer_name, o.customer_email, 
                         o.customer_phone, o.customer_address, o.total_amount, o.tax_amount, 
                         o.discount_amount, o.final_amount, o.payment_method, o.payment_status, 
                         o.order_status, o.created_at, o.updated_at
                ORDER BY o.created_at DESC
            ");
            $stmt->execute();
            $orders = $stmt->fetchAll();
            
            // Get order items for each order
            foreach ($orders as &$order) {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        oi.id,
                        oi.product_id,
                        oi.quantity,
                        oi.unit_price,
                        oi.total_price,
                        p.name as product_name,
                        p.image_url
                    FROM order_items oi
                    JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = ?
                ");
                $stmt->execute([$order['id']]);
                $order['items'] = $stmt->fetchAll();
            }
            
            return $this->success(['orders' => $orders]);
            
        } catch (Exception $e) {
            return $this->error('Failed to get orders: ' . $e->getMessage(), 500);
        }
    }

    // Accept order (New function)
    public function acceptOrder() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkReceptionistAuth()) {
            return $this->error('Access denied', 403);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $orderId = $input['order_id'] ?? null;
        
        if (!$orderId) {
            return $this->error('Order ID is required', 400);
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Check if order exists and is pending
            $stmt = $this->pdo->prepare("
                SELECT o.*, u.first_name, u.last_name
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.id = ? AND o.order_status = 'pending'
            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            
            if (!$order) {
                return $this->error('Order not found or not pending', 404);
            }
            
            // Update order status to processing (approved)
            $stmt = $this->pdo->prepare("
                UPDATE orders 
                SET order_status = 'processing', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            
            // Log event
            $eventData = [
                'order_id' => $orderId,
                'order_number' => $order['order_number'],
                'customer_name' => $order['customer_name'],
                'action' => 'accepted',
                'processed_by' => $_SESSION['user_id']
            ];
            $this->logEvent('order_accepted', $eventData);
            
            $this->pdo->commit();
            
            return $this->success([
                'message' => 'Order accepted successfully',
                'order_id' => $orderId,
                'order_number' => $order['order_number']
            ]);
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return $this->error('Failed to accept order: ' . $e->getMessage(), 500);
        }
    }

    // Reject order (New function)
    public function rejectOrder() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkReceptionistAuth()) {
            return $this->error('Access denied', 403);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $orderId = $input['order_id'] ?? null;
        $rejectionReason = $input['rejection_reason'] ?? '';
        
        if (!$orderId) {
            return $this->error('Order ID is required', 400);
        }
        
        if (!$rejectionReason) {
            return $this->error('Rejection reason is required', 400);
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Check if order exists and is pending
            $stmt = $this->pdo->prepare("
                SELECT o.*, u.first_name, u.last_name
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.id = ? AND o.order_status = 'pending'
            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            
            if (!$order) {
                return $this->error('Order not found or not pending', 404);
            }
            
            // Update order status to cancelled (rejected)
            $stmt = $this->pdo->prepare("
                UPDATE orders 
                SET order_status = 'cancelled', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            
            // Restore product stock
            $stmt = $this->pdo->prepare("
                UPDATE products p
                JOIN order_items oi ON p.id = oi.product_id
                SET p.stock_quantity = p.stock_quantity + oi.quantity
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$orderId]);
            
            // Log event
            $eventData = [
                'order_id' => $orderId,
                'order_number' => $order['order_number'],
                'customer_name' => $order['customer_name'],
                'action' => 'rejected',
                'reason' => $rejectionReason,
                'processed_by' => $_SESSION['user_id']
            ];
            $this->logEvent('order_rejected', $eventData);
            
            $this->pdo->commit();
            
            return $this->success([
                'message' => 'Order rejected successfully',
                'order_id' => $orderId,
                'order_number' => $order['order_number']
            ]);
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return $this->error('Failed to reject order: ' . $e->getMessage(), 500);
        }
    }
    
    // Helper method to log events
    private function logEvent($event_type, $event_data) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
            $json_data = json_encode($event_data);
            $stmt->execute([$event_type, $json_data]);
        } catch (Exception $e) {
            // Log event might fail, but don't break the main functionality
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
$receptionist = new ReceptionistAPI($pdo);

// Route requests
$action = $_GET['action'] ?? '';

switch ($action) {
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
    case 'dashboard_stats':
        $receptionist->getDashboardStats();
        break;
    case 'process_return':
        $receptionist->processReturn();
        break;
    case 'get_orders':
        $receptionist->getOrders();
        break;
    case 'accept_order':
        $receptionist->acceptOrder();
        break;
    case 'reject_order':
        $receptionist->rejectOrder();
        break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Action not found']);
        break;
}
?> 