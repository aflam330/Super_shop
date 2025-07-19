<?php
require_once '../config.php';

setCORSHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

switch ($method) {
    case 'GET':
        // Get feedback with optional filtering
        $productId = $_GET['product_id'] ?? null;
        $limit = intval($_GET['limit'] ?? 50);
        $offset = intval($_GET['offset'] ?? 0);
        
        $sql = "SELECT f.*, p.name as product_name FROM feedback f LEFT JOIN products p ON f.product_id = p.id";
        $params = [];
        $conditions = [];
        
        if ($productId) {
            $conditions[] = "f.product_id = ?";
            $params[] = intval($productId);
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY f.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $feedback = $stmt->fetchAll();
            
            // Get total count for pagination
            $countSql = "SELECT COUNT(*) as total FROM feedback f";
            if (!empty($conditions)) {
                $countSql .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $stmt = $pdo->prepare($countSql);
            $stmt->execute(array_slice($params, 0, -2)); // Remove limit and offset
            $total = $stmt->fetch()['total'];
            
            sendResponse([
                'feedback' => $feedback,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (PDOException $e) {
            sendError('Failed to fetch feedback: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'POST':
        // Create new feedback
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['customer_name']) || !isset($input['message'])) {
            sendError('Customer name and message are required');
        }
        
        $customerName = validateInput($input['customer_name']);
        $productId = isset($input['product_id']) ? intval($input['product_id']) : null;
        $message = validateInput($input['message']);
        $rating = isset($input['rating']) ? intval($input['rating']) : null;
        
        // Validate rating if provided
        if ($rating !== null && ($rating < 1 || $rating > 5)) {
            sendError('Rating must be between 1 and 5');
        }
        
        // Validate product exists if product_id provided
        if ($productId) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
                $stmt->execute([$productId]);
                if (!$stmt->fetch()) {
                    sendError('Product not found', 404);
                }
            } catch (PDOException $e) {
                sendError('Failed to validate product: ' . $e->getMessage(), 500);
            }
        }
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO feedback (customer_name, product_id, message, rating)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$customerName, $productId, $message, $rating]);
            
            $feedbackId = $pdo->lastInsertId();
            
            // Get the created feedback with product info
            $stmt = $pdo->prepare("
                SELECT f.*, p.name as product_name 
                FROM feedback f 
                LEFT JOIN products p ON f.product_id = p.id 
                WHERE f.id = ?
            ");
            $stmt->execute([$feedbackId]);
            $newFeedback = $stmt->fetch();
            
            // Add real-time event for new feedback
            $eventData = [
                'type' => 'new_feedback',
                'feedback' => [
                    'id' => $feedbackId,
                    'customer_name' => $customerName,
                    'message' => $message,
                    'rating' => $rating,
                    'product_id' => $productId,
                    'product_name' => $newFeedback['product_name'],
                    'created_at' => $newFeedback['created_at']
                ]
            ];
            
            $stmt = $pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
            $stmt->execute(['new_review', json_encode($eventData)]);
            
            sendResponse([
                'id' => $feedbackId,
                'feedback' => $newFeedback,
                'message' => 'Feedback submitted successfully'
            ], 201);
            
        } catch (PDOException $e) {
            sendError('Failed to submit feedback: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?> 