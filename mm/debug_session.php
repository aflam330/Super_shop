<?php
session_start();
require_once 'backend/config.php';

echo "<h1>Session Debug</h1>";

echo "<h2>Session Data:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Cookies:</h2>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";

echo "<h2>Session ID:</h2>";
echo "Session ID: " . session_id() . "<br>";

echo "<h2>Database Connection Test:</h2>";
try {
    $pdo = getDBConnection();
    echo "✅ Database connection successful<br>";
    
    // Test a simple query
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "✅ Orders count: " . $result['count'] . "<br>";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

echo "<h2>API Test:</h2>";
try {
    // Simulate admin session
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    
    echo "✅ Admin session set<br>";
    
    // Test the sales analytics query
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as orders,
            SUM(final_amount) as sales
        FROM orders 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND payment_status = 'completed'
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $stmt->execute();
    $sales_data = $stmt->fetchAll();
    
    echo "✅ Sales data query successful<br>";
    echo "Sales data count: " . count($sales_data) . "<br>";
    echo "<pre>";
    print_r($sales_data);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "❌ API test error: " . $e->getMessage() . "<br>";
}
?> 