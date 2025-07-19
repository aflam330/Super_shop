<?php
require_once '../config.php';

setCORSHeaders();

// API Documentation
$endpoints = [
    'products' => [
        'GET /api/products' => 'Get all products (with optional category and search filters)',
        'GET /api/products?id={id}' => 'Get specific product',
        'POST /api/products' => 'Create new product',
        'PUT /api/products?id={id}' => 'Update product',
        'DELETE /api/products?id={id}' => 'Delete product'
    ],
    'orders' => [
        'GET /api/orders' => 'Get all orders',
        'GET /api/orders?order_id={order_id}' => 'Get specific order with items',
        'GET /api/orders?status={status}' => 'Get orders by status',
        'POST /api/orders' => 'Create new order',
        'PUT /api/orders?order_id={order_id}' => 'Update order status'
    ],
    'returns' => [
        'GET /api/returns' => 'Get all returns',
        'GET /api/returns?return_id={return_id}' => 'Get specific return',
        'GET /api/returns?order_id={order_id}' => 'Get returns by order',
        'POST /api/returns' => 'Create return request',
        'PUT /api/returns?return_id={return_id}' => 'Update return status'
    ],
    'feedback' => [
        'GET /api/feedback' => 'Get all feedback',
        'GET /api/feedback?product_id={product_id}' => 'Get feedback for specific product',
        'POST /api/feedback' => 'Submit new feedback'
    ],
    'events' => [
        'GET /api/events' => 'Get recent real-time events',
        'GET /api/events?type={event_type}' => 'Get events by type',
        'POST /api/events' => 'Create test event',
        'DELETE /api/events?hours={hours}' => 'Clean up old events'
    ]
];

$response = [
    'api_name' => 'Super Shop Real-Time API',
    'version' => API_VERSION,
    'description' => 'Real-time e-commerce API with WebSocket support',
    'base_url' => 'http://localhost:8000/backend/api',
    'websocket_url' => 'ws://localhost:8080',
    'endpoints' => $endpoints,
    'event_types' => [
        'stock_update' => 'Product stock level changes',
        'order_status' => 'Order status updates',
        'new_offer' => 'New product offers',
        'new_review' => 'New customer reviews'
    ],
    'status' => 'running',
    'timestamp' => date('Y-m-d H:i:s')
];

sendResponse($response);
?> 