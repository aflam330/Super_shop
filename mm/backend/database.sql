-- Super Shop Management System (SSMS) Database Schema
-- Enhanced for complete SSMS requirements

-- Users table for authentication and role management
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('customer', 'receptionist', 'admin') DEFAULT 'customer',
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Products table with enhanced fields
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    category VARCHAR(50) NOT NULL,
    image_url VARCHAR(255),
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Orders table with enhanced fields
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    final_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('bkash', 'nagad', 'card', 'cash') NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    transaction_id VARCHAR(100),
    customer_phone VARCHAR(20),
    customer_name VARCHAR(100),
    customer_email VARCHAR(100),
    customer_address TEXT,
    order_status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Order items table
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Returns table with enhanced fields
CREATE TABLE returns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    user_id INT,
    product_id INT NOT NULL,
    return_reason TEXT NOT NULL,
    return_status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    refund_amount DECIMAL(10,2),
    return_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_by INT,
    notes TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Reviews/Feedback table
CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Weekly reports table for receptionists
CREATE TABLE weekly_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    receptionist_id INT NOT NULL,
    week_start_date DATE NOT NULL,
    week_end_date DATE NOT NULL,
    total_sales DECIMAL(10,2) NOT NULL,
    total_orders INT NOT NULL,
    top_selling_products TEXT,
    customer_feedback_summary TEXT,
    notes TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT,
    review_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    FOREIGN KEY (receptionist_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Sales analytics table
CREATE TABLE sales_analytics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL,
    total_sales DECIMAL(10,2) NOT NULL,
    total_orders INT NOT NULL,
    average_order_value DECIMAL(10,2),
    top_product_id INT,
    top_product_sales INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (top_product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Real-time events table
CREATE TABLE realtime_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_type VARCHAR(50) NOT NULL,
    event_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample users
INSERT INTO users (username, email, password_hash, role, first_name, last_name, phone) VALUES
('admin', 'admin@supershop.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Admin', 'User', '+8801712345678'),
('receptionist1', 'receptionist@supershop.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'receptionist', 'John', 'Doe', '+8801812345678'),
('customer1', 'customer@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', 'Jane', 'Smith', '+8801912345678');

-- Insert enhanced products with expiry dates
INSERT INTO products (name, description, price, stock_quantity, category, image_url, expiry_date) VALUES
-- Electronics
('Smart Watch', 'Advanced fitness tracking smartwatch with heart rate monitor', 99.99, 15, 'Electronics', 'https://via.placeholder.com/200/3B82F6/FFFFFF?text=Smart+Watch', NULL),
('Wireless Earbuds', 'High-quality wireless earbuds with noise cancellation', 49.99, 25, 'Electronics', 'https://via.placeholder.com/200/10B981/FFFFFF?text=Wireless+Earbuds', NULL),
('Laptop', '15-inch laptop with latest processor and 8GB RAM', 899.99, 8, 'Electronics', 'https://via.placeholder.com/200/EF4444/FFFFFF?text=Laptop', NULL),
('Bluetooth Speaker', 'Portable bluetooth speaker with 20W output', 89.99, 20, 'Electronics', 'https://via.placeholder.com/200/8B5CF6/FFFFFF?text=Bluetooth+Speaker', NULL),
('Smartphone', 'Latest smartphone with 128GB storage and 48MP camera', 599.99, 12, 'Electronics', 'https://via.placeholder.com/200/F59E0B/FFFFFF?text=Smartphone', NULL),
('Tablet', '10-inch tablet perfect for work and entertainment', 299.99, 18, 'Electronics', 'https://via.placeholder.com/200/EC4899/FFFFFF?text=Tablet', NULL),

-- Fashion
('Running Shoes', 'Comfortable running shoes with cushioned sole', 79.99, 30, 'Fashion', 'https://via.placeholder.com/200/06B6D4/FFFFFF?text=Running+Shoes', NULL),
('Denim Jacket', 'Classic denim jacket for casual wear', 69.99, 22, 'Fashion', 'https://via.placeholder.com/200/84CC16/FFFFFF?text=Denim+Jacket', NULL),
('Leather Bag', 'Premium leather bag with multiple compartments', 129.99, 15, 'Fashion', 'https://via.placeholder.com/200/F97316/FFFFFF?text=Leather+Bag', NULL),
('Sunglasses', 'Stylish sunglasses with UV protection', 39.99, 35, 'Fashion', 'https://via.placeholder.com/200/6366F1/FFFFFF?text=Sunglasses', NULL),
('Wristwatch', 'Elegant wristwatch with leather strap', 159.99, 10, 'Fashion', 'https://via.placeholder.com/200/A855F7/FFFFFF?text=Wristwatch', NULL),

-- Home & Living
('Coffee Maker', 'Automatic coffee maker with timer', 129.99, 12, 'Home & Living', 'https://via.placeholder.com/200/DC2626/FFFFFF?text=Coffee+Maker', NULL),
('Bedside Lamp', 'Modern bedside lamp with touch control', 45.99, 28, 'Home & Living', 'https://via.placeholder.com/200/EA580C/FFFFFF?text=Bedside+Lamp', NULL),
('Kitchen Mixer', 'Professional kitchen mixer with multiple attachments', 199.99, 8, 'Home & Living', 'https://via.placeholder.com/200/CA8A04/FFFFFF?text=Kitchen+Mixer', NULL),
('Wall Clock', 'Elegant wall clock with silent movement', 29.99, 40, 'Home & Living', 'https://via.placeholder.com/200/65A30D/FFFFFF?text=Wall+Clock', NULL),
('Throw Pillow', 'Soft throw pillow with decorative design', 24.99, 50, 'Home & Living', 'https://via.placeholder.com/200/0891B2/FFFFFF?text=Throw+Pillow', NULL),

-- Sports & Fitness
('Yoga Mat', 'Non-slip yoga mat for home workouts', 34.99, 25, 'Sports & Fitness', 'https://via.placeholder.com/200/7C3AED/FFFFFF?text=Yoga+Mat', NULL),
('Dumbbells Set', 'Adjustable dumbbells set 5-25kg', 149.99, 6, 'Sports & Fitness', 'https://via.placeholder.com/200/DB2777/FFFFFF?text=Dumbbells+Set', NULL),
('Basketball', 'Official size basketball for indoor/outdoor use', 49.99, 20, 'Sports & Fitness', 'https://via.placeholder.com/200/059669/FFFFFF?text=Basketball', NULL),

-- Books & Stationery
('Programming Book', 'Complete guide to modern programming', 39.99, 15, 'Books & Stationery', 'https://via.placeholder.com/200/1E40AF/FFFFFF?text=Programming+Book', NULL),
('Notebook Set', 'Premium notebook set with 5 notebooks', 19.99, 30, 'Books & Stationery', 'https://via.placeholder.com/200/9D174D/FFFFFF?text=Notebook+Set', NULL),

-- Food Items with expiry dates
('Organic Milk', 'Fresh organic milk 1L', 4.99, 50, 'Food & Beverages', 'https://via.placeholder.com/200/059669/FFFFFF?text=Organic+Milk', DATE_ADD(CURDATE(), INTERVAL 7 DAY)),
('Whole Grain Bread', 'Fresh whole grain bread 500g', 3.99, 30, 'Food & Beverages', 'https://via.placeholder.com/200/CA8A04/FFFFFF?text=Bread', DATE_ADD(CURDATE(), INTERVAL 5 DAY)),
('Fresh Eggs', 'Farm fresh eggs 12 pieces', 5.99, 40, 'Food & Beverages', 'https://via.placeholder.com/200/F59E0B/FFFFFF?text=Eggs', DATE_ADD(CURDATE(), INTERVAL 14 DAY));

-- Insert sample orders with user relationships
INSERT INTO orders (user_id, order_number, total_amount, tax_amount, discount_amount, final_amount, payment_method, payment_status, transaction_id, customer_phone, customer_name, customer_email, order_status) VALUES
(3, 'ORD-2024-001', 149.98, 15.00, 10.00, 154.98, 'bkash', 'completed', 'BKASH123456', '+8801912345678', 'Jane Smith', 'customer@example.com', 'delivered'),
(3, 'ORD-2024-002', 299.99, 30.00, 0.00, 329.99, 'nagad', 'completed', 'NAGAD789012', '+8801912345678', 'Jane Smith', 'customer@example.com', 'processing'),
(NULL, 'ORD-2024-003', 89.99, 9.00, 5.00, 93.99, 'card', 'completed', 'CARD345678', '+8801712345678', 'John Doe', 'john@example.com', 'delivered');

-- Insert order items
INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price) VALUES
(1, 1, 1, 99.99, 99.99),
(1, 2, 1, 49.99, 49.99),
(2, 6, 1, 299.99, 299.99),
(3, 4, 1, 89.99, 89.99);

-- Insert sample reviews
INSERT INTO reviews (product_id, user_id, rating, comment) VALUES
(1, 3, 5, 'Excellent smartwatch with great features!'),
(2, 3, 4, 'Good quality earbuds, great sound.'),
(3, NULL, 5, 'Amazing laptop, very fast performance.'),
(4, NULL, 4, 'Good speaker quality, portable design.');

-- Insert sample returns
INSERT INTO returns (order_id, user_id, product_id, return_reason, return_status, refund_amount, processed_by) VALUES
(1, 3, 2, 'Not satisfied with sound quality', 'approved', 49.99, 2);

-- Insert sample weekly report
INSERT INTO weekly_reports (receptionist_id, week_start_date, week_end_date, total_sales, total_orders, top_selling_products, customer_feedback_summary, notes) VALUES
(2, '2024-01-01', '2024-01-07', 1549.95, 15, 'Smart Watch, Wireless Earbuds, Laptop', 'Overall positive feedback, customers satisfied with product quality', 'Good week with steady sales');

-- Insert sample sales analytics
INSERT INTO sales_analytics (date, total_sales, total_orders, average_order_value, top_product_id, top_product_sales) VALUES
(CURDATE(), 1549.95, 15, 103.33, 1, 5),
(DATE_SUB(CURDATE(), INTERVAL 1 DAY), 899.99, 8, 112.50, 3, 3),
(DATE_SUB(CURDATE(), INTERVAL 2 DAY), 1299.97, 12, 108.33, 2, 4);

-- Create indexes for better performance
CREATE INDEX idx_products_category ON products(category);
CREATE INDEX idx_orders_user_id ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(order_status);
CREATE INDEX idx_returns_order_id ON returns(order_id);
CREATE INDEX idx_reviews_product_id ON reviews(product_id);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_products_expiry ON products(expiry_date); 