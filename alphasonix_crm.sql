CREATE DATABASE IF NOT EXISTS alpha;
USE alpha;

-- Customers table with enhanced fields
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(50),
    company VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    zip VARCHAR(20),
    country VARCHAR(100),
    tax_id VARCHAR(100),
    notes TEXT,
    credit_limit DECIMAL(15,2) DEFAULT 0.00,
    status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    signup_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Products table with enhanced fields
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    serial_no VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    dimensions VARCHAR(100) NOT NULL,
    category VARCHAR(100),
    price DECIMAL(15,2) DEFAULT 0.00,
    stock_quantity INT DEFAULT 0,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Orders table with enhanced fields
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    po_number VARCHAR(50),
    po_date DATE NOT NULL,
    delivery_date DATE,
    due_date DATE,
    status ENUM('Pending', 'Sourcing Material', 'In Production', 'Ready for QC', 'QC Completed', 'Packaging', 'Ready for Dispatch', 'Shipped') DEFAULT 'Pending',
    total_amount DECIMAL(15,2) DEFAULT 0.00,
    priority ENUM('normal', 'high', 'urgent') DEFAULT 'normal',
    notes TEXT,
    created_by INT,
    payment_terms VARCHAR(50) DEFAULT 'Net 30',
    shipping_method VARCHAR(50) DEFAULT 'Standard',
    shipping_cost DECIMAL(10,2) DEFAULT 0.00,
    tax_rate DECIMAL(5,2) DEFAULT 0.00,
    drawing_filename VARCHAR(255),
    inspection_reports JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Order items table with enhanced fields
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT,
    serial_no VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    dimensions VARCHAR(100),
    description TEXT,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(15,2) DEFAULT 0.00,
    total_price DECIMAL(15,2) DEFAULT 0.00,
    item_status ENUM('Pending', 'Sourcing Material', 'In Production', 'Ready for QC', 'QC Completed', 'Packaging', 'Ready for Dispatch', 'Shipped') DEFAULT 'Pending',
    drawing_filename VARCHAR(255),
    original_filename VARCHAR(255),
    raw_materials JSON,
    machining_processes JSON,
    inspection_data JSON,
    packaging_lots JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Order history table
CREATE TABLE IF NOT EXISTS order_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    change_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    changed_by VARCHAR(100) NOT NULL,
    user_id VARCHAR(100),
    user_role VARCHAR(50),
    stage VARCHAR(100) NOT NULL,
    change_description TEXT NOT NULL,
    item_index VARCHAR(50),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Activity logs table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    user_id VARCHAR(100),
    username VARCHAR(100),
    user_role VARCHAR(50),
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Customer communications table
CREATE TABLE IF NOT EXISTS customer_communications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    type ENUM('email', 'notification') NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    sent_by INT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Insert sample data
INSERT IGNORE INTO customers (id, name, email, phone, company, status, signup_date) VALUES
(1, 'John Smith', 'john.smith@example.com', '+1-555-0101', 'Tech Solutions Inc', 'active', '2024-01-15'),
(2, 'Sarah Johnson', 'sarah.j@example.com', '+1-555-0102', 'Manufacturing Corp', 'active', '2024-01-20'),
(3, 'Mike Wilson', 'mike.wilson@example.com', '+1-555-0103', 'Industrial Works', 'active', '2024-02-01');

INSERT IGNORE INTO products (id, serial_no, name, dimensions, category, price, stock_quantity) VALUES
(1, 'PROD-001', 'Steel Plate 10mm', '1000x2000x10mm', 'Plates', 450.00, 25),
(2, 'PROD-002', 'Aluminum Bar 50mm', '6000x50mm', 'Bars', 120.00, 40),
(3, 'PROD-003', 'Stainless Pipe 2"', '6000x2"', 'Pipes', 85.00, 30);

INSERT IGNORE INTO orders (id, order_id, customer_id, po_number, po_date, status, total_amount) VALUES
(1, 'ORD-2024-001', 1, 'PO-1001', '2024-03-01', 'In Production', 1250.00),
(2, 'ORD-2024-002', 2, 'PO-1002', '2024-03-05', 'Pending', 850.00),
(3, 'ORD-2024-003', 3, 'PO-1003', '2024-03-10', 'Sourcing Material', 2100.00);

INSERT IGNORE INTO order_items (order_id, product_id, serial_no, name, dimensions, quantity, unit_price, total_price, item_status) VALUES
(1, 1, 'PROD-001', 'Steel Plate 10mm', '1000x2000x10mm', 2, 450.00, 900.00, 'In Production'),
(1, 2, 'PROD-002', 'Aluminum Bar 50mm', '6000x50mm', 1, 120.00, 120.00, 'Pending'),
(2, 3, 'PROD-003', 'Stainless Pipe 2"', '6000x2"', 10, 85.00, 850.00, 'Pending');