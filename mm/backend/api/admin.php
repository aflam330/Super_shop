<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config.php';

class AdminAPI {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // Check admin authentication
    private function checkAdminAuth() {
        session_start();
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            return false;
        }
        return true;
    }
    
    // Get dashboard statistics (FR17)
    public function getDashboardStats() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkAdminAuth()) {
            return $this->error('Access denied', 403);
        }
        
        try {
            // Total products
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total_products FROM products WHERE is_active = 1");
            $stmt->execute();
            $total_products = $stmt->get_result()->fetch_assoc()['total_products'];
            
            // Low stock products (less than 10)
            $stmt = $this->conn->prepare("SELECT COUNT(*) as low_stock FROM products WHERE stock_quantity < 10 AND is_active = 1");
            $stmt->execute();
            $low_stock = $stmt->get_result()->fetch_assoc()['low_stock'];
            
            // Expired products
            $stmt = $this->conn->prepare("SELECT COUNT(*) as expired FROM products WHERE expiry_date < CURDATE() AND expiry_date IS NOT NULL");
            $stmt->execute();
            $expired = $stmt->get_result()->fetch_assoc()['expired'];
            
            // Total orders today
            $stmt = $this->conn->prepare("SELECT COUNT(*) as today_orders FROM orders WHERE DATE(created_at) = CURDATE()");
            $stmt->execute();
            $today_orders = $stmt->get_result()->fetch_assoc()['today_orders'];
            
            // Total sales today
            $stmt = $this->conn->prepare("SELECT COALESCE(SUM(final_amount), 0) as today_sales FROM orders WHERE DATE(created_at) = CURDATE() AND payment_status = 'completed'");
            $stmt->execute();
            $today_sales = $stmt->get_result()->fetch_assoc()['today_sales'];
            
            // Total users
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total_users FROM users WHERE is_active = 1");
            $stmt->execute();
            $total_users = $stmt->get_result()->fetch_assoc()['total_users'];
            
            // Pending returns
            $stmt = $this->conn->prepare("SELECT COUNT(*) as pending_returns FROM returns WHERE return_status = 'pending'");
            $stmt->execute();
            $pending_returns = $stmt->get_result()->fetch_assoc()['pending_returns'];
            
            return $this->success([
                'total_products' => $total_products,
                'low_stock' => $low_stock,
                'expired_products' => $expired,
                'today_orders' => $today_orders,
                'today_sales' => $today_sales,
                'total_users' => $total_users,
                'pending_returns' => $pending_returns
            ]);
        } catch (Exception $e) {
            return $this->error('Failed to get dashboard stats: ' . $e->getMessage(), 500);
        }
    }
    
    // Get sales analytics (FR17)
    public function getSalesAnalytics() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkAdminAuth()) {
            return $this->error('Access denied', 403);
        }
        
        $period = $_GET['period'] ?? '7'; // Default 7 days
        
        try {
            // Sales data for the period
            $stmt = $this->conn->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as orders,
                    SUM(final_amount) as sales,
                    AVG(final_amount) as avg_order_value
                FROM orders 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                AND payment_status = 'completed'
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ");
            $stmt->bind_param("i", $period);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $sales_data = [];
            while ($row = $result->fetch_assoc()) {
                $sales_data[] = $row;
            }
            
            // Top selling products
            $stmt = $this->conn->prepare("
                SELECT 
                    p.name,
                    p.id,
                    SUM(oi.quantity) as total_sold,
                    SUM(oi.total_price) as total_revenue
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                JOIN orders o ON oi.order_id = o.id
                WHERE o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                AND o.payment_status = 'completed'
                GROUP BY p.id, p.name
                ORDER BY total_sold DESC
                LIMIT 10
            ");
            $stmt->bind_param("i", $period);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $top_products = [];
            while ($row = $result->fetch_assoc()) {
                $top_products[] = $row;
            }
            
            // Category-wise sales
            $stmt = $this->conn->prepare("
                SELECT 
                    p.category,
                    SUM(oi.quantity) as total_sold,
                    SUM(oi.total_price) as total_revenue
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                JOIN orders o ON oi.order_id = o.id
                WHERE o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                AND o.payment_status = 'completed'
                GROUP BY p.category
                ORDER BY total_revenue DESC
            ");
            $stmt->bind_param("i", $period);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $category_sales = [];
            while ($row = $result->fetch_assoc()) {
                $category_sales[] = $row;
            }
            
            return $this->success([
                'sales_data' => $sales_data,
                'top_products' => $top_products,
                'category_sales' => $category_sales
            ]);
        } catch (Exception $e) {
            return $this->error('Failed to get sales analytics: ' . $e->getMessage(), 500);
        }
    }
    
    // Get low stock products (FR15)
    public function getLowStockProducts() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkAdminAuth()) {
            return $this->error('Access denied', 403);
        }
        
        $threshold = $_GET['threshold'] ?? 10;
        
        try {
            $stmt = $this->conn->prepare("
                SELECT id, name, stock_quantity, category, price
                FROM products 
                WHERE stock_quantity <= ? AND is_active = 1
                ORDER BY stock_quantity ASC
            ");
            $stmt->bind_param("i", $threshold);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $products = [];
            while ($row = $result->fetch_assoc()) {
                $products[] = $row;
            }
            
            return $this->success(['products' => $products]);
        } catch (Exception $e) {
            return $this->error('Failed to get low stock products: ' . $e->getMessage(), 500);
        }
    }
    
    // Get expired products (FR16)
    public function getExpiredProducts() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkAdminAuth()) {
            return $this->error('Access denied', 403);
        }
        
        try {
            $stmt = $this->conn->prepare("
                SELECT id, name, expiry_date, stock_quantity, category
                FROM products 
                WHERE expiry_date < CURDATE() AND expiry_date IS NOT NULL
                ORDER BY expiry_date ASC
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $products = [];
            while ($row = $result->fetch_assoc()) {
                $products[] = $row;
            }
            
            return $this->success(['products' => $products]);
        } catch (Exception $e) {
            return $this->error('Failed to get expired products: ' . $e->getMessage(), 500);
        }
    }
    
    // Update product stock (FR15)
    public function updateProductStock() {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkAdminAuth()) {
            return $this->error('Access denied', 403);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || empty($data['product_id']) || !isset($data['stock_quantity'])) {
            return $this->error('Product ID and stock quantity are required', 400);
        }
        
        try {
            $stmt = $this->conn->prepare("
                UPDATE products 
                SET stock_quantity = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->bind_param("ii", $data['stock_quantity'], $data['product_id']);
            
            if ($stmt->execute()) {
                // Log stock update event
                $this->logEvent('stock_update', [
                    'product_id' => $data['product_id'],
                    'new_stock' => $data['stock_quantity'],
                    'updated_by' => $_SESSION['user_id']
                ]);
                
                return $this->success(['message' => 'Stock updated successfully']);
            } else {
                return $this->error('Failed to update stock', 500);
            }
        } catch (Exception $e) {
            return $this->error('Failed to update stock: ' . $e->getMessage(), 500);
        }
    }
    
    // Remove expired products (FR16)
    public function removeExpiredProducts() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkAdminAuth()) {
            return $this->error('Access denied', 403);
        }
        
        try {
            // Get expired products before deletion
            $stmt = $this->conn->prepare("
                SELECT id, name, expiry_date, stock_quantity
                FROM products 
                WHERE expiry_date < CURDATE() AND expiry_date IS NOT NULL
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $expired_products = [];
            while ($row = $result->fetch_assoc()) {
                $expired_products[] = $row;
            }
            
            // Delete expired products
            $stmt = $this->conn->prepare("
                DELETE FROM products 
                WHERE expiry_date < CURDATE() AND expiry_date IS NOT NULL
            ");
            $stmt->execute();
            
            $deleted_count = $stmt->affected_rows;
            
            // Log deletion event
            if ($deleted_count > 0) {
                $this->logEvent('expired_products_removed', [
                    'deleted_count' => $deleted_count,
                    'products' => $expired_products,
                    'removed_by' => $_SESSION['user_id']
                ]);
            }
            
            return $this->success([
                'message' => "Removed $deleted_count expired products",
                'deleted_count' => $deleted_count,
                'products' => $expired_products
            ]);
        } catch (Exception $e) {
            return $this->error('Failed to remove expired products: ' . $e->getMessage(), 500);
        }
    }
    
    // Get weekly reports (FR12)
    public function getWeeklyReports() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkAdminAuth()) {
            return $this->error('Access denied', 403);
        }
        
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    wr.*,
                    u.first_name,
                    u.last_name,
                    u.username
                FROM weekly_reports wr
                JOIN users u ON wr.receptionist_id = u.id
                ORDER BY wr.submitted_at DESC
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $reports = [];
            while ($row = $result->fetch_assoc()) {
                $reports[] = $row;
            }
            
            return $this->success(['reports' => $reports]);
        } catch (Exception $e) {
            return $this->error('Failed to get weekly reports: ' . $e->getMessage(), 500);
        }
    }
    
    // Review weekly report (FR12)
    public function reviewWeeklyReport() {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkAdminAuth()) {
            return $this->error('Access denied', 403);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || empty($data['report_id']) || empty($data['review_status'])) {
            return $this->error('Report ID and review status are required', 400);
        }
        
        try {
            $stmt = $this->conn->prepare("
                UPDATE weekly_reports 
                SET review_status = ?, reviewed_by = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sii", $data['review_status'], $_SESSION['user_id'], $data['report_id']);
            
            if ($stmt->execute()) {
                return $this->success(['message' => 'Report reviewed successfully']);
            } else {
                return $this->error('Failed to review report', 500);
            }
        } catch (Exception $e) {
            return $this->error('Failed to review report: ' . $e->getMessage(), 500);
        }
    }
    
    // Get system settings
    public function getSystemSettings() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkAdminAuth()) {
            return $this->error('Access denied', 403);
        }
        
        // For now, return default settings
        // In a real system, these would be stored in a settings table
        return $this->success([
            'settings' => [
                'return_policy_days' => 5,
                'low_stock_threshold' => 10,
                'tax_rate' => 0.10,
                'free_shipping_threshold' => 100,
                'max_return_period' => 5
            ]
        ]);
    }
    
    // Update system settings
    public function updateSystemSettings() {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            return $this->error('Method not allowed', 405);
        }
        
        if (!$this->checkAdminAuth()) {
            return $this->error('Access denied', 403);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            return $this->error('Invalid JSON data', 400);
        }
        
        // In a real system, update settings in database
        // For now, just return success
        return $this->success(['message' => 'Settings updated successfully']);
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
$admin = new AdminAPI($conn);

// Route requests
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'dashboard_stats':
        $admin->getDashboardStats();
        break;
    case 'sales_analytics':
        $admin->getSalesAnalytics();
        break;
    case 'low_stock':
        $admin->getLowStockProducts();
        break;
    case 'expired_products':
        $admin->getExpiredProducts();
        break;
    case 'update_stock':
        $admin->updateProductStock();
        break;
    case 'remove_expired':
        $admin->removeExpiredProducts();
        break;
    case 'weekly_reports':
        $admin->getWeeklyReports();
        break;
    case 'review_report':
        $admin->reviewWeeklyReport();
        break;
    case 'settings':
        $admin->getSystemSettings();
        break;
    case 'update_settings':
        $admin->updateSystemSettings();
        break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Action not found']);
        break;
}
?> 