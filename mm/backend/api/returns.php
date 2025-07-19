<?php
require_once '../config.php';

setCORSHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

switch ($method) {
    case 'GET':
        // Get returns with optional filtering
        $returnId = $_GET['return_id'] ?? null;
        $orderId = $_GET['order_id'] ?? null;
        $status = $_GET['status'] ?? null;
        
        if ($returnId) {
            // Get specific return
            try {
                $stmt = $pdo->prepare("
                    SELECT r.*, o.order_id as original_order_id, o.customer_name as order_customer_name
                    FROM returns r
                    LEFT JOIN orders o ON r.order_id = o.id
                    WHERE r.return_id = ?
                ");
                $stmt->execute([$returnId]);
                $return = $stmt->fetch();
                
                if (!$return) {
                    sendError('Return not found', 404);
                }
                
                sendResponse(['return' => $return]);
            } catch (PDOException $e) {
                sendError('Failed to fetch return: ' . $e->getMessage(), 500);
            }
        } else {
            // Get all returns with optional filters
            $sql = "SELECT r.*, o.order_id as original_order_id FROM returns r LEFT JOIN orders o ON r.order_id = o.id";
            $params = [];
            $conditions = [];
            
            if ($orderId) {
                $conditions[] = "o.order_id = ?";
                $params[] = validateInput($orderId);
            }
            
            if ($status) {
                $conditions[] = "r.status = ?";
                $params[] = validateInput($status);
            }
            
            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $sql .= " ORDER BY r.created_at DESC";
            
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $returns = $stmt->fetchAll();
                
                sendResponse(['returns' => $returns]);
            } catch (PDOException $e) {
                sendError('Failed to fetch returns: ' . $e->getMessage(), 500);
            }
        }
        break;
        
    case 'POST':
        // Create new return request
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['order_id']) || !isset($input['customer_name']) || !isset($input['reason'])) {
            sendError('Order ID, customer name, and reason are required');
        }
        
        $orderId = validateInput($input['order_id']);
        $customerName = validateInput($input['customer_name']);
        $customerEmail = validateInput($input['customer_email'] ?? '');
        $reason = validateInput($input['reason']);
        $receiptFile = validateInput($input['receipt_file'] ?? '');
        
        try {
            // Verify order exists
            $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            
            if (!$order) {
                sendError('Order not found', 404);
            }
            
            // Check if return already exists for this order
            $stmt = $pdo->prepare("SELECT id FROM returns WHERE order_id = ?");
            $stmt->execute([$order['id']]);
            $existingReturn = $stmt->fetch();
            
            if ($existingReturn) {
                sendError('Return request already exists for this order', 400);
            }
            
            // Generate unique return ID
            $returnId = 'RET' . strtoupper(substr(uniqid(), -6));
            
            // Create return request
            $stmt = $pdo->prepare("
                INSERT INTO returns (return_id, order_id, customer_name, customer_email, reason, receipt_file)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$returnId, $order['id'], $customerName, $customerEmail, $reason, $receiptFile]);
            
            // Add real-time event for new return
            $eventData = [
                'type' => 'new_return',
                'return' => [
                    'return_id' => $returnId,
                    'order_id' => $orderId,
                    'customer_name' => $customerName,
                    'status' => 'pending'
                ]
            ];
            
            $stmt = $pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
            $stmt->execute(['order_status', json_encode($eventData)]);
            
            sendResponse([
                'return_id' => $returnId,
                'message' => 'Return request created successfully'
            ], 201);
            
        } catch (PDOException $e) {
            sendError('Failed to create return request: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'PUT':
        // Update return status
        $input = json_decode(file_get_contents('php://input'), true);
        $returnId = $_GET['return_id'] ?? null;
        
        if (!$returnId || !isset($input['status'])) {
            sendError('Return ID and status are required');
        }
        
        $status = validateInput($input['status']);
        $validStatuses = ['pending', 'under_review', 'approved', 'rejected'];
        
        if (!in_array($status, $validStatuses)) {
            sendError('Invalid status. Must be one of: ' . implode(', ', $validStatuses));
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE returns SET status = ? WHERE return_id = ?");
            $stmt->execute([$status, $returnId]);
            
            if ($stmt->rowCount() === 0) {
                sendError('Return not found', 404);
            }
            
            // Add real-time event for status update
            $eventData = [
                'type' => 'return_status_update',
                'return' => [
                    'return_id' => $returnId,
                    'status' => $status
                ]
            ];
            
            $stmt = $pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
            $stmt->execute(['order_status', json_encode($eventData)]);
            
            sendResponse(['message' => 'Return status updated successfully']);
        } catch (PDOException $e) {
            sendError('Failed to update return status: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?> 