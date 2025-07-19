# Super Shop Management System (SSMS)

A comprehensive real-time interactive e-commerce website with role-based access control for customers, receptionists, and administrators.

## 🚀 Features

### Customer Features (FR1-FR6)
- **User Registration & Login** - Secure authentication system
- **Product Browsing** - Browse 21+ products with categories and search
- **Shopping Cart** - Add products with real-time stock updates
- **Checkout System** - Multiple payment methods (bKash, Nagad, Card)
- **Order Tracking** - Real-time order status updates
- **Returns Management** - 5-day return policy with receipt validation
- **Product Reviews** - Rate and review products (1-5 stars)

### Receptionist Features (FR7-FR12)
- **Customer Assistance** - Help customers with purchases
- **Bill Generation** - Create itemized bills with tax and discount calculations
- **Product Recommendations** - Highlight top-rated and trending products
- **Return Processing** - Approve/reject returns within policy guidelines
- **Weekly Reports** - Submit detailed sales and feedback reports
- **Return Policy Information** - Provide policy details to customers

### Admin Features (FR13-FR18)
- **Dashboard Analytics** - Real-time statistics and overview
- **Inventory Management** - Add, edit, delete products with stock tracking
- **Sales Analytics** - Detailed sales reports and trends
- **User Management** - Manage customer, receptionist, and admin accounts
- **Expiry Management** - Monitor and remove expired products
- **System Settings** - Configure return policies, tax rates, etc.

## 🛠️ Technology Stack

### Frontend
- **HTML5** - Semantic markup
- **CSS3 with Tailwind CSS** - Modern, responsive design
- **JavaScript (ES6+)** - Interactive functionality
- **Chart.js** - Data visualization for analytics

### Backend
- **PHP 7.4+** - Server-side logic
- **MySQL 8.0+** - Database management
- **WebSocket** - Real-time communication
- **RESTful APIs** - Clean API architecture

### Real-time Features
- **WebSocket Server** - Live updates and notifications
- **AJAX** - Asynchronous data loading
- **LocalStorage** - Client-side data persistence

## 📋 Database Schema

### Core Tables
- `users` - User accounts with role-based access
- `products` - Product catalog with stock and expiry tracking
- `orders` - Order management with payment details
- `order_items` - Individual items in orders
- `returns` - Return requests and processing
- `reviews` - Product ratings and feedback
- `weekly_reports` - Receptionist weekly submissions
- `sales_analytics` - Sales data for reporting
- `realtime_events` - WebSocket event logging

### Key Features
- **Foreign Key Relationships** - Data integrity
- **Indexes** - Performance optimization
- **JSON Storage** - Flexible event data
- **Timestamp Tracking** - Audit trails

## 🚀 Installation

### Prerequisites
- XAMPP/WAMP/LAMP server
- PHP 7.4 or higher
- MySQL 8.0 or higher
- Modern web browser

### Setup Instructions

1. **Clone/Download the Project**
   ```bash
   # Place files in your web server directory
   # e.g., C:\xampp\htdocs\mm\
   ```

2. **Database Setup**
   ```sql
   -- Import the database schema
   mysql -u root -p < backend/database.sql
   ```

3. **Configuration**
   ```php
   // Edit backend/config.php
   $db_host = 'localhost';
   $db_name = 'supershop';
   $db_user = 'your_username';
   $db_pass = 'your_password';
   ```

4. **WebSocket Server**
   ```bash
   # Start the WebSocket server
   php backend/ws-server.php
   ```

5. **Access the Application**
   ```
   http://localhost/mm/
   ```

## 👥 User Roles & Access

### Demo Credentials

#### Customer
- **Username:** customer1
- **Password:** password
- **Access:** Product browsing, shopping, returns, reviews

#### Receptionist
- **Username:** receptionist1
- **Password:** password
- **Access:** Customer assistance, billing, returns processing, reports

#### Admin
- **Username:** admin
- **Password:** password
- **Access:** Full system administration

## 📱 Pages & Functionality

### Public Pages
- `index.html` - Homepage with product showcase
- `catalog.html` - Product catalog with search/filter
- `product.html` - Individual product details
- `cart.html` - Shopping cart management
- `checkout.html` - Payment and order completion
- `orders.html` - Order history and tracking
- `returns.html` - Return request submission
- `feedback.html` - Product reviews and ratings

### Authentication Pages
- `login.html` - User login with role selection
- `register.html` - New customer registration

### Role-Specific Dashboards
- `admin-dashboard.html` - Complete admin panel
- `receptionist-dashboard.html` - Receptionist tools

## 🔧 API Endpoints

### Authentication (`backend/api/auth.php`)
- `POST ?action=register` - User registration
- `POST ?action=login` - User login
- `POST ?action=logout` - User logout
- `GET ?action=profile` - Get user profile
- `PUT ?action=update_profile` - Update profile
- `POST ?action=change_password` - Change password
- `GET ?action=users` - Get all users (admin)
- `PUT ?action=update_user` - Update user (admin)

### Products (`backend/api/products.php`)
- `GET` - Get all products
- `GET ?id=X` - Get specific product
- `POST` - Add new product (admin)
- `PUT` - Update product (admin)
- `DELETE` - Delete product (admin)

### Orders (`backend/api/orders.php`)
- `GET` - Get user orders
- `POST` - Create new order
- `PUT ?action=update_status` - Update order status

### Returns (`backend/api/returns.php`)
- `GET` - Get user returns
- `POST` - Submit return request
- `PUT ?action=process` - Process return (receptionist)

### Feedback (`backend/api/feedback.php`)
- `GET` - Get product reviews
- `POST` - Submit review

### Admin (`backend/api/admin.php`)
- `GET ?action=dashboard_stats` - Dashboard statistics
- `GET ?action=sales_analytics` - Sales analytics
- `GET ?action=low_stock` - Low stock products
- `GET ?action=expired_products` - Expired products
- `PUT ?action=update_stock` - Update product stock
- `POST ?action=remove_expired` - Remove expired products
- `GET ?action=weekly_reports` - Get weekly reports
- `PUT ?action=review_report` - Review weekly report

### Receptionist (`backend/api/receptionist.php`)
- `GET ?action=top_rated` - Top rated products
- `GET ?action=trending` - Trending products
- `POST ?action=generate_bill` - Generate bill
- `GET ?action=return_policy` - Get return policy
- `POST ?action=submit_report` - Submit weekly report
- `GET ?action=assistance_data` - Customer assistance data
- `PUT ?action=process_return` - Process return

## 🎨 Design Features

### Dynamic Theme Switching
- **Blue Theme** - Default professional look
- **Green Theme** - Fresh and natural
- **Pink Theme** - Modern and vibrant
- **Yellow Theme** - Warm and energetic

### Responsive Design
- **Mobile-First** - Optimized for all devices
- **Grid Layouts** - Flexible product displays
- **Touch-Friendly** - Mobile interaction support

### Real-time Updates
- **Live Stock** - Real-time inventory updates
- **Order Status** - Instant order tracking
- **Notifications** - User-friendly alerts
- **WebSocket Events** - Seamless communication

## 🔒 Security Features

### Authentication & Authorization
- **Password Hashing** - Secure password storage
- **Session Management** - Secure user sessions
- **Role-Based Access** - Granular permissions
- **Input Validation** - SQL injection prevention

### Data Protection
- **Prepared Statements** - SQL injection protection
- **CORS Headers** - Cross-origin security
- **Input Sanitization** - XSS prevention
- **Error Handling** - Secure error messages

## 📊 Analytics & Reporting

### Sales Analytics
- **Daily/Weekly/Monthly** - Flexible time periods
- **Product Performance** - Top-selling items
- **Category Analysis** - Category-wise sales
- **Revenue Tracking** - Financial insights

### Inventory Analytics
- **Stock Levels** - Low stock alerts
- **Expiry Tracking** - Product expiration management
- **Demand Forecasting** - Sales trends analysis

### User Analytics
- **Customer Behavior** - Shopping patterns
- **Return Analysis** - Return rate insights
- **Feedback Analysis** - Customer satisfaction

## 🚀 Performance Features

### Optimization
- **Database Indexing** - Fast query performance
- **Image Optimization** - Placeholder images for demo
- **Caching** - LocalStorage for cart data
- **Lazy Loading** - Efficient resource loading

### Scalability
- **Modular Architecture** - Easy to extend
- **API-First Design** - Frontend/backend separation
- **Stateless APIs** - Horizontal scaling ready
- **WebSocket Scaling** - Real-time communication

## 🔧 Configuration Options

### System Settings
- **Return Policy Days** - Configurable return period
- **Low Stock Threshold** - Customizable alerts
- **Tax Rate** - Adjustable tax percentage
- **Free Shipping Threshold** - Minimum order for free shipping

### Payment Methods
- **bKash** - Mobile banking
- **Nagad** - Digital payment
- **Card Payment** - Credit/debit cards
- **Cash** - In-store payments

## 📝 Development Guidelines

### Code Structure
- **MVC Pattern** - Organized code structure
- **RESTful APIs** - Standard API design
- **Error Handling** - Comprehensive error management
- **Documentation** - Inline code comments

### Best Practices
- **Security First** - Input validation and sanitization
- **Performance** - Optimized queries and caching
- **User Experience** - Intuitive interface design
- **Accessibility** - WCAG compliance features

## 🐛 Troubleshooting

### Common Issues
1. **Database Connection** - Check config.php settings
2. **WebSocket Server** - Ensure PHP WebSocket extension
3. **File Permissions** - Set proper read/write permissions
4. **Browser Compatibility** - Use modern browsers

### Debug Mode
```php
// Enable error reporting in development
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 📞 Support

### Documentation
- **API Documentation** - Available at `/backend/api/index.php`
- **Database Schema** - See `backend/database.sql`
- **Code Comments** - Comprehensive inline documentation

### Contact
For technical support or feature requests, please refer to the project documentation or create an issue in the repository.

## 📄 License

This project is developed for educational and demonstration purposes. Please ensure compliance with local regulations when deploying in production environments.

---

**Super Shop Management System** - Complete e-commerce solution with real-time features and role-based access control. 