<?php
require_once 'config.php';

class WebSocketServer {
    private $server;
    private $clients = [];
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
        $this->server = stream_socket_server("tcp://" . WS_HOST . ":" . WS_PORT, $errno, $errstr);
        
        if (!$this->server) {
            die("Failed to create WebSocket server: $errstr ($errno)");
        }
        
        echo "WebSocket server started on " . WS_HOST . ":" . WS_PORT . "\n";
    }
    
    public function run() {
        while (true) {
            $read = array_merge([$this->server], $this->clients);
            $write = $except = null;
            
            if (stream_select($read, $write, $except, 1) < 1) {
                continue;
            }
            
            // Check for new connections
            if (in_array($this->server, $read)) {
                $client = stream_socket_accept($this->server);
                if ($client) {
                    $this->handleNewConnection($client);
                }
                unset($read[array_search($this->server, $read)]);
            }
            
            // Handle client messages
            foreach ($read as $client) {
                $data = fread($client, 1024);
                if ($data === false || $data === '') {
                    $this->removeClient($client);
                } else {
                    $this->handleMessage($client, $data);
                }
            }
            
            // Check for real-time events from database
            $this->checkRealtimeEvents();
        }
    }
    
    private function handleNewConnection($client) {
        // Perform WebSocket handshake
        $headers = fread($client, 1024);
        if (preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $headers, $matches)) {
            $key = base64_encode(sha1($matches[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
            $headers = "HTTP/1.1 101 Switching Protocols\r\n";
            $headers .= "Upgrade: websocket\r\n";
            $headers .= "Connection: Upgrade\r\n";
            $headers .= "Sec-WebSocket-Accept: $key\r\n\r\n";
            fwrite($client, $headers);
            
            $this->clients[] = $client;
            echo "New client connected. Total clients: " . count($this->clients) . "\n";
            
            // Send initial data
            $this->sendInitialData($client);
        }
    }
    
    private function handleMessage($client, $data) {
        // Decode WebSocket frame
        $decoded = $this->decodeWebSocketFrame($data);
        if ($decoded) {
            $message = json_decode($decoded, true);
            if ($message) {
                $this->processMessage($client, $message);
            }
        }
    }
    
    private function processMessage($client, $message) {
        $type = $message['type'] ?? '';
        
        switch ($type) {
            case 'subscribe_stock':
                // Client wants to receive stock updates
                $this->sendToClient($client, [
                    'type' => 'stock_subscribed',
                    'message' => 'Subscribed to stock updates'
                ]);
                break;
                
            case 'subscribe_orders':
                // Client wants to receive order updates
                $this->sendToClient($client, [
                    'type' => 'orders_subscribed',
                    'message' => 'Subscribed to order updates'
                ]);
                break;
                
            case 'subscribe_offers':
                // Client wants to receive offer updates
                $this->sendToClient($client, [
                    'type' => 'offers_subscribed',
                    'message' => 'Subscribed to offer updates'
                ]);
                break;
                
            case 'subscribe_reviews':
                // Client wants to receive review updates
                $this->sendToClient($client, [
                    'type' => 'reviews_subscribed',
                    'message' => 'Subscribed to review updates'
                ]);
                break;
                
            default:
                $this->sendToClient($client, [
                    'type' => 'error',
                    'message' => 'Unknown message type'
                ]);
        }
    }
    
    private function sendInitialData($client) {
        // Send current stock levels
        try {
            $stmt = $this->pdo->prepare("SELECT id, name, stock_quantity FROM products ORDER BY stock_quantity ASC LIMIT 5");
            $stmt->execute();
            $lowStockProducts = $stmt->fetchAll();
            
            $this->sendToClient($client, [
                'type' => 'initial_data',
                'low_stock_products' => $lowStockProducts,
                'message' => 'Connected to real-time updates'
            ]);
        } catch (PDOException $e) {
            echo "Database error: " . $e->getMessage() . "\n";
        }
    }
    
    private function checkRealtimeEvents() {
        try {
            // Get recent events (last 5 seconds)
            $stmt = $this->pdo->prepare("
                SELECT * FROM realtime_events 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
                ORDER BY created_at DESC
            ");
            $stmt->execute();
            $events = $stmt->fetchAll();
            
            foreach ($events as $event) {
                $eventData = json_decode($event['event_data'], true);
                $this->broadcastEvent($event['event_type'], $eventData);
            }
            
            // Clean up old events (older than 1 hour)
            $stmt = $this->pdo->prepare("DELETE FROM realtime_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $stmt->execute();
            
        } catch (PDOException $e) {
            echo "Database error: " . $e->getMessage() . "\n";
        }
    }
    
    private function broadcastEvent($eventType, $eventData) {
        $message = [
            'type' => $eventType,
            'data' => $eventData,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $encoded = json_encode($message);
        $frame = $this->encodeWebSocketFrame($encoded);
        
        foreach ($this->clients as $client) {
            fwrite($client, $frame);
        }
        
        echo "Broadcasted $eventType event to " . count($this->clients) . " clients\n";
    }
    
    private function sendToClient($client, $data) {
        $encoded = json_encode($data);
        $frame = $this->encodeWebSocketFrame($encoded);
        fwrite($client, $frame);
    }
    
    private function removeClient($client) {
        $key = array_search($client, $this->clients);
        if ($key !== false) {
            unset($this->clients[$key]);
            fclose($client);
            echo "Client disconnected. Total clients: " . count($this->clients) . "\n";
        }
    }
    
    private function decodeWebSocketFrame($data) {
        $opcode = ord($data[0]) & 0x0F;
        $length = ord($data[1]) & 127;
        $maskStart = 2;
        
        if ($length === 126) {
            $maskStart = 4;
        } elseif ($length === 127) {
            $maskStart = 10;
        }
        
        $masks = substr($data, $maskStart, 4);
        $dataStart = $maskStart + 4;
        $decoded = '';
        
        for ($i = $dataStart; $i < strlen($data); $i++) {
            $decoded .= $data[$i] ^ $masks[($i - $dataStart) % 4];
        }
        
        return $decoded;
    }
    
    private function encodeWebSocketFrame($data) {
        $length = strlen($data);
        $frame = chr(129); // FIN + text frame
        
        if ($length <= 125) {
            $frame .= chr($length);
        } elseif ($length <= 65535) {
            $frame .= chr(126) . pack('n', $length);
        } else {
            $frame .= chr(127) . pack('J', $length);
        }
        
        return $frame . $data;
    }
}

// Start the WebSocket server
$server = new WebSocketServer();
$server->run();
?> 