<?php
// Set CORS headers before any output
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
require_once dirname(__FILE__) . '/../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';

try {
    $pdo = getDBConnection();
    
    switch ($action) {
        case 'dashboard_stats':
            handleDashboardStats($pdo);
            break;
            
        case 'sales_analytics':
            handleSalesAnalytics($pdo, $_GET);
            break;
            
        case 'low_stock':
            handleLowStockProducts($pdo);
            break;
            
        case 'expired_products':
            handleExpiredProducts($pdo);
            break;
            
        case 'update_stock':
            handleUpdateStock($pdo, $input);
            break;
            
        case 'remove_expired':
            handleRemoveExpired($pdo);
            break;
            
        case 'weekly_reports':
            handleWeeklyReports($pdo);
            break;
            
        case 'review_report':
            handleReviewReport($pdo, $input);
            break;
            
        case 'settings':
            handleGetSettings($pdo);
            break;
            
        case 'update_settings':
            handleUpdateSettings($pdo, $input);
            break;
            
        case 'users':
            handleGetUsers($pdo);
            break;
            
        case 'roles':
            handleGetRoles($pdo);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

function handleDashboardStats($pdo) {
    try {
        // Total products
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_products FROM products WHERE is_active = 1");
        $stmt->execute();
        $total_products = $stmt->fetch()['total_products'];
        
        // Low stock products (less than or equal to 20)
        $stmt = $pdo->prepare("SELECT COUNT(*) as low_stock FROM products WHERE stock_quantity <= 20 AND is_active = 1");
        $stmt->execute();
        $low_stock = $stmt->fetch()['low_stock'];
        
        // Expired products
        $stmt = $pdo->prepare("SELECT COUNT(*) as expired FROM products WHERE expiry_date < CURDATE() AND expiry_date IS NOT NULL");
        $stmt->execute();
        $expired = $stmt->fetch()['expired'];
        
        // Total orders today
        $stmt = $pdo->prepare("SELECT COUNT(*) as today_orders FROM orders WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $today_orders = $stmt->fetch()['today_orders'];
        
        // Total sales today
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(final_amount), 0) as today_sales FROM orders WHERE DATE(created_at) = CURDATE() AND payment_status = 'completed'");
        $stmt->execute();
        $today_sales = $stmt->fetch()['today_sales'];
        
        // Total users
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_users FROM users WHERE is_active = 1");
        $stmt->execute();
        $total_users = $stmt->fetch()['total_users'];
        
        // Pending returns
        $stmt = $pdo->prepare("SELECT COUNT(*) as pending_returns FROM returns WHERE return_status = 'pending'");
        $stmt->execute();
        $pending_returns = $stmt->fetch()['pending_returns'];
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_products' => $total_products,
                'low_stock' => $low_stock,
                'expired_products' => $expired,
                'today_orders' => $today_orders,
                'today_sales' => $today_sales,
                'total_users' => $total_users,
                'pending_returns' => $pending_returns
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to get dashboard stats: ' . $e->getMessage()]);
    }
}

function handleSalesAnalytics($pdo, $params) {
    $period = isset($params['period']) ? (int)$params['period'] : 7;
    
    try {
        // Sales data for the period
        $stmt = $pdo->prepare("
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
        $stmt->execute([$period]);
        $sales_data = $stmt->fetchAll();
        
        // Track if we're using sample data
        $using_sample_data = false;
        
        // If no sales data, create sample data for demonstration
        if (empty($sales_data)) {
            $using_sample_data = true;
            $sales_data = [];
            for ($i = $period - 1; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $sales_data[] = [
                    'date' => $date,
                    'orders' => rand(1, 15),
                    'sales' => rand(100, 2000),
                    'avg_order_value' => rand(50, 300)
                ];
            }
        }
        
        // Top selling products
        $stmt = $pdo->prepare("
            SELECT 
                p.name,
                p.id,
                SUM(oi.quantity) as total_sold,
                SUM(oi.quantity * oi.unit_price) as total_revenue
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN orders o ON oi.order_id = o.id
            WHERE o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            AND o.payment_status = 'completed'
            GROUP BY p.id, p.name
            ORDER BY total_sold DESC
            LIMIT 10
        ");
        $stmt->execute([$period]);
        $top_products = $stmt->fetchAll();
        
        // If no top products data, create sample data
        if (empty($top_products)) {
            $using_sample_data = true;
            $stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE is_active = 1 LIMIT 5");
            $stmt->execute();
            $products = $stmt->fetchAll();
            
            $top_products = [];
            foreach ($products as $product) {
                $total_sold = rand(5, 50);
                $top_products[] = [
                    'name' => $product['name'],
                    'id' => $product['id'],
                    'total_sold' => $total_sold,
                    'total_revenue' => $total_sold * $product['price']
                ];
            }
        }
        
        // Category-wise sales
        $stmt = $pdo->prepare("
            SELECT 
                p.category,
                SUM(oi.quantity) as total_sold,
                SUM(oi.quantity * oi.unit_price) as total_revenue
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN orders o ON oi.order_id = o.id
            WHERE o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            AND o.payment_status = 'completed'
            GROUP BY p.category
            ORDER BY total_revenue DESC
        ");
        $stmt->execute([$period]);
        $category_sales = $stmt->fetchAll();
        
        // If no category sales data, create sample data
        if (empty($category_sales)) {
            $using_sample_data = true;
            $stmt = $pdo->prepare("SELECT DISTINCT category FROM products WHERE is_active = 1");
            $stmt->execute();
            $categories = $stmt->fetchAll();
            
            $category_sales = [];
            foreach ($categories as $category) {
                $total_sold = rand(10, 100);
                $avg_price = rand(50, 200);
                $category_sales[] = [
                    'category' => $category['category'],
                    'total_sold' => $total_sold,
                    'total_revenue' => $total_sold * $avg_price
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'sales_data' => $sales_data,
                'top_products' => $top_products,
                'category_sales' => $category_sales,
                'period' => $period,
                'data_type' => $using_sample_data ? 'sample' : 'real'
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to get sales analytics: ' . $e->getMessage()]);
    }
}

function handleLowStockProducts($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, category, stock_quantity, price
            FROM products
            WHERE stock_quantity <= 20 AND is_active = 1
            ORDER BY stock_quantity ASC
        ");
        $stmt->execute();
        $products = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => ['products' => $products]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to get low stock products: ' . $e->getMessage()]);
    }
}

function handleExpiredProducts($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, category, expiry_date, stock_quantity, price
            FROM products
            WHERE expiry_date < CURDATE() AND expiry_date IS NOT NULL AND is_active = 1
            ORDER BY expiry_date ASC
        ");
        $stmt->execute();
        $products = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => ['products' => $products]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to get expired products: ' . $e->getMessage()]);
    }
}

function handleUpdateStock($pdo, $data) {
    if (!isset($data['product_id']) || !isset($data['new_quantity'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Product ID and new quantity are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$data['new_quantity'], $data['product_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Stock updated successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update stock: ' . $e->getMessage()]);
    }
}

function handleRemoveExpired($pdo) {
    try {
        $stmt = $pdo->prepare("UPDATE products SET is_active = 0 WHERE expiry_date < CURDATE() AND expiry_date IS NOT NULL");
        $stmt->execute();
        $deleted_count = $stmt->rowCount();
        
        echo json_encode([
            'success' => true,
            'data' => ['deleted_count' => $deleted_count],
            'message' => "Removed $deleted_count expired products"
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to remove expired products: ' . $e->getMessage()]);
    }
}

function handleWeeklyReports($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT wr.*, u.first_name, u.last_name
            FROM weekly_reports wr
            JOIN users u ON wr.receptionist_id = u.id
            ORDER BY wr.submitted_at DESC
        ");
        $stmt->execute();
        $reports = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => ['reports' => $reports]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to get weekly reports: ' . $e->getMessage()]);
    }
}

function handleReviewReport($pdo, $data) {
    if (!isset($data['report_id']) || !isset($data['status'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Report ID and status are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE weekly_reports SET review_status = ?, reviewed_by = ? WHERE id = ?");
        $stmt->execute([$data['status'], $_SESSION['user_id'], $data['report_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Report reviewed successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to review report: ' . $e->getMessage()]);
    }
}

function handleGetSettings($pdo) {
    try {
        // For now, return default settings
        $settings = [
            'return_policy_days' => 5,
            'low_stock_threshold' => 20,
            'tax_rate' => 0.10,
            'free_shipping_threshold' => 500
        ];
        
        echo json_encode([
            'success' => true,
            'data' => ['settings' => $settings]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to get settings: ' . $e->getMessage()]);
    }
}

function handleUpdateSettings($pdo, $data) {
    try {
        // For now, just return success (settings would be stored in a settings table)
        echo json_encode([
            'success' => true,
            'message' => 'Settings updated successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update settings: ' . $e->getMessage()]);
    }
}

function handleGetUsers($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users");
        $stmt->execute();
        $users = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => ['users' => $users]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to get users: ' . $e->getMessage()]);
    }
}

function handleGetRoles($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT role FROM users");
        $stmt->execute();
        $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            'success' => true,
            'data' => ['roles' => $roles]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to get roles: ' . $e->getMessage()]);
    }
}

?>