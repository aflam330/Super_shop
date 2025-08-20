// Real-time WebSocket client for Super Shop
class RealtimeClient {
    constructor() {
        this.ws = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 2000;
        this.eventHandlers = {
            stock_update: [],
            order_status: [],
            new_offer: [],
            new_review: []
        };
        this.isConnected = false;
    }
    
    connect() {
        try {
            this.ws = new WebSocket('ws://localhost:8080');
            
            this.ws.onopen = () => {
                console.log('WebSocket connected');
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.subscribeToAll();
            };
            
            this.ws.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleMessage(data);
                } catch (error) {
                    console.error('Failed to parse WebSocket message:', error);
                }
            };
            
            this.ws.onclose = () => {
                console.log('WebSocket disconnected');
                this.isConnected = false;
                this.attemptReconnect();
            };
            
            this.ws.onerror = (error) => {
                console.error('WebSocket error:', error);
            };
            
        } catch (error) {
            console.error('Failed to create WebSocket connection:', error);
            this.attemptReconnect();
        }
    }
    
    attemptReconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            console.log(`Attempting to reconnect (${this.reconnectAttempts}/${this.maxReconnectAttempts})...`);
            
            setTimeout(() => {
                this.connect();
            }, this.reconnectDelay * this.reconnectAttempts);
        } else {
            console.error('Max reconnection attempts reached');
        }
    }
    
    subscribeToAll() {
        const subscriptions = ['subscribe_stock', 'subscribe_orders', 'subscribe_offers', 'subscribe_reviews'];
        subscriptions.forEach(type => {
            this.sendMessage({ type });
        });
    }
    
    sendMessage(message) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(message));
        }
    }
    
    handleMessage(data) {
        const { type, data: eventData, timestamp } = data;
        
        console.log(`Received ${type} event:`, eventData);
        
        // Call registered event handlers
        if (this.eventHandlers[type]) {
            this.eventHandlers[type].forEach(handler => {
                try {
                    handler(eventData, timestamp);
                } catch (error) {
                    console.error(`Error in ${type} handler:`, error);
                }
            });
        }
        
        // Handle specific event types
        switch (type) {
            case 'stock_update':
                this.handleStockUpdate(eventData);
                break;
            case 'order_status':
                this.handleOrderStatus(eventData);
                break;
            case 'new_offer':
                this.handleNewOffer(eventData);
                break;
            case 'new_review':
                this.handleNewReview(eventData);
                break;
            case 'initial_data':
                this.handleInitialData(eventData);
                break;
        }
    }
    
    handleStockUpdate(data) {
        // Update stock displays on catalog and product pages
        if (data.products) {
            data.products.forEach(product => {
                const stockElement = document.querySelector(`[data-product-id="${product.id}"] .stock`);
                if (stockElement) {
                    stockElement.textContent = `Stock: ${product.stock}`;
                    
                    // Add visual feedback for low stock
                    if (product.stock <= 3) {
                        stockElement.className = 'stock text-red-600 font-bold';
                    } else if (product.stock <= 10) {
                        stockElement.className = 'stock text-yellow-600';
                    } else {
                        stockElement.className = 'stock text-green-600';
                    }
                }
            });
        }
        
        // Show notification
        this.showNotification('Stock updated', 'Product stock levels have been updated');
    }
    
    handleOrderStatus(data) {
        // Update order status displays
        if (data.order) {
            const statusElement = document.querySelector(`[data-order-id="${data.order.order_id}"] .order-status`);
            if (statusElement) {
                statusElement.textContent = data.order.status;
                statusElement.className = `order-status status-${data.order.status}`;
            }
        }
        
        // Show notification
        this.showNotification('Order status updated', `Order ${data.order?.order_id} status: ${data.order?.status}`);
    }
    
    handleNewOffer(data) {
        // Update offers section on homepage
        const offersElement = document.getElementById('offers');
        if (offersElement && data.product) {
            offersElement.innerHTML = `
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4">
                    <p class="font-bold">New Product Available!</p>
                    <p>${data.product.name} - ৳${data.product.price}</p>
                </div>
            `;
        }
        
        // Show notification
        this.showNotification('New offer', `New product: ${data.product?.name}`);
    }
    
    handleNewReview(data) {
        // Add new review to product pages
        if (data.feedback) {
            const reviewsList = document.getElementById('reviews');
            if (reviewsList) {
                const newReview = document.createElement('li');
                newReview.className = 'mb-2 bg-green-50 p-2 rounded';
                newReview.innerHTML = `
                    <span class="font-bold">${data.feedback.customer_name}:</span> 
                    ${data.feedback.message}
                    <span class="text-xs text-gray-500">(Live)</span>
                `;
                reviewsList.insertBefore(newReview, reviewsList.firstChild);
            }
        }
        
        // Show notification
        this.showNotification('New review', `New review from ${data.feedback?.customer_name}`);
    }
    
    handleInitialData(data) {
        // Handle initial data sent on connection
        if (data.low_stock_products) {
            console.log('Low stock products:', data.low_stock_products);
        }
    }
    
    showNotification(title, message) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 bg-blue-500 text-white p-4 rounded shadow-lg z-50 transform transition-transform duration-300';
        notification.innerHTML = `
            <div class="font-bold">${title}</div>
            <div class="text-sm">${message}</div>
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // Remove after 5 seconds
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 5000);
    }
    
    // Event handler registration
    on(eventType, handler) {
        if (this.eventHandlers[eventType]) {
            this.eventHandlers[eventType].push(handler);
        }
    }
    
    off(eventType, handler) {
        if (this.eventHandlers[eventType]) {
            const index = this.eventHandlers[eventType].indexOf(handler);
            if (index > -1) {
                this.eventHandlers[eventType].splice(index, 1);
            }
        }
    }
    
    disconnect() {
        if (this.ws) {
            this.ws.close();
        }
    }
}

// Global instance
window.realtimeClient = new RealtimeClient();

// Auto-connect when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.realtimeClient.connect();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.realtimeClient) {
        window.realtimeClient.disconnect();
    }
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = RealtimeClient;
}

// Add this to your loadUsersData function
async function loadUsersData() {
    try {
        console.log('Fetching users data...');
        const response = await fetch('backend/api/admin.php?action=users');
        console.log('Response received:', response);
        const data = await response.json();
        console.log('Users data:', data);
        
        if (data.success) {
            // Update your UI with data.data.users
        } else {
            console.error('Failed to load users:', data.error);
        }
    } catch (error) {
        console.error('Error loading users:', error);
    }
}