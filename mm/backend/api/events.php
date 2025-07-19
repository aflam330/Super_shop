<?php
require_once '../config.php';

setCORSHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

switch ($method) {
    case 'GET':
        // Get recent real-time events
        $eventType = $_GET['type'] ?? null;
        $limit = intval($_GET['limit'] ?? 20);
        $offset = intval($_GET['offset'] ?? 0);
        
        $sql = "SELECT * FROM realtime_events";
        $params = [];
        
        if ($eventType) {
            $sql .= " WHERE event_type = ?";
            $params[] = validateInput($eventType);
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $events = $stmt->fetchAll();
            
            // Decode JSON data for each event
            foreach ($events as &$event) {
                $event['event_data'] = json_decode($event['event_data'], true);
            }
            
            // Get total count for pagination
            $countSql = "SELECT COUNT(*) as total FROM realtime_events";
            if ($eventType) {
                $countSql .= " WHERE event_type = ?";
            }
            
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($eventType ? [$eventType] : []);
            $total = $stmt->fetch()['total'];
            
            sendResponse([
                'events' => $events,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (PDOException $e) {
            sendError('Failed to fetch events: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'POST':
        // Create a test event (for demonstration)
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['event_type']) || !isset($input['event_data'])) {
            sendError('Event type and event data are required');
        }
        
        $eventType = validateInput($input['event_type']);
        $eventData = $input['event_data'];
        
        // Validate event type
        $validTypes = ['stock_update', 'order_status', 'new_offer', 'new_review'];
        if (!in_array($eventType, $validTypes)) {
            sendError('Invalid event type. Must be one of: ' . implode(', ', $validTypes));
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO realtime_events (event_type, event_data) VALUES (?, ?)");
            $stmt->execute([$eventType, json_encode($eventData)]);
            
            $eventId = $pdo->lastInsertId();
            
            sendResponse([
                'id' => $eventId,
                'event_type' => $eventType,
                'event_data' => $eventData,
                'message' => 'Event created successfully'
            ], 201);
        } catch (PDOException $e) {
            sendError('Failed to create event: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'DELETE':
        // Clean up old events
        $hours = intval($_GET['hours'] ?? 24);
        
        if ($hours < 1) {
            sendError('Hours must be at least 1');
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM realtime_events WHERE created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)");
            $stmt->execute([$hours]);
            
            $deletedCount = $stmt->rowCount();
            
            sendResponse([
                'deleted_count' => $deletedCount,
                'message' => "Deleted $deletedCount events older than $hours hours"
            ]);
        } catch (PDOException $e) {
            sendError('Failed to clean up events: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?> 