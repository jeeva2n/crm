CREATE DATABASE IF NOT EXISTS alphasonix_crm 
-- Customers table
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    signup_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    serial_no VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    dimensions VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Orders table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    po_date DATE NOT NULL,
    delivery_date DATE,
    due_date DATE,
    status ENUM('Pending', 'Sourcing Material', 'In Production', 'Ready for QC', 'QC Completed', 'Packaging', 'Ready for Dispatch', 'Shipped') DEFAULT 'Pending',
    drawing_filename VARCHAR(255),
    inspection_reports JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Order items table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT,
    serial_no VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    dimensions VARCHAR(100),
    description TEXT,
    quantity INT DEFAULT 1,
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
CREATE TABLE order_history (
    id INT AUTO_INCREMENT PRIMARY KEY,z
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
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    user_id VARCHAR(100),
    username VARCHAR(100),
    user_role VARCHAR(50),
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);