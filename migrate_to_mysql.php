<?php
session_start();
require_once 'functions.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    die('Access denied. Only administrators can run migration.');
}

function migrateToMySQL() {
    $results = [];
    
    // Create database tables
    $results[] = createDatabaseTables();
    
    // Migrate customers
    $results[] = migrateCustomers();
    
    // Migrate products
    $results[] = migrateProducts();
    
    // Migrate orders (this is more complex due to JSON data)
    $results[] = migrateOrders();
    
    return $results;
}

function createDatabaseTables() {
    try {
        $conn = getDbConnection();
        
        // Customers table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS customers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                signup_date DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Products table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                serial_no VARCHAR(50) UNIQUE NOT NULL,
                name VARCHAR(255) NOT NULL,
                dimensions VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Orders table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS orders (
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
            )
        ");
        
        // Order items table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS order_items (
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
            )
        ");
        
        // Order history table
        $conn->exec("
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
            )
        ");
        
        // Activity logs table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(255) NOT NULL,
                details TEXT,
                user_id VARCHAR(100),
                username VARCHAR(100),
                user_role VARCHAR(50),
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        return "✅ Database tables created successfully";
        
    } catch (PDOException $e) {
        return "❌ Error creating tables: " . $e->getMessage();
    }
}

function migrateCustomers() {
    try {
        $csvCustomers = getCsvData('customers.csv');
        $migrated = 0;
        $errors = 0;
        
        foreach ($csvCustomers as $customer) {
            try {
                // Check if customer already exists
                $conn = getDbConnection();
                $stmt = $conn->prepare("SELECT id FROM customers WHERE id = ?");
                $stmt->execute([$customer['id']]);
                $exists = $stmt->fetch();
                
                if (!$exists) {
                    $stmt = $conn->prepare("INSERT INTO customers (id, name, email, signup_date) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$customer['id'], $customer['name'], $customer['email'], $customer['signup_date']]);
                    $migrated++;
                }
            } catch (PDOException $e) {
                $errors++;
                error_log("Error migrating customer {$customer['id']}: " . $e->getMessage());
            }
        }
        
        return "✅ Customers migrated: $migrated successful, $errors errors";
        
    } catch (Exception $e) {
        return "❌ Error migrating customers: " . $e->getMessage();
    }
}

function migrateProducts() {
    try {
        $csvProducts = getCsvData('products.csv');
        $migrated = 0;
        $errors = 0;
        
        foreach ($csvProducts as $product) {
            try {
                // Check if product already exists
                $conn = getDbConnection();
                $stmt = $conn->prepare("SELECT id FROM products WHERE serial_no = ?");
                $stmt->execute([$product['S.No']]);
                $exists = $stmt->fetch();
                
                if (!$exists) {
                    $stmt = $conn->prepare("INSERT INTO products (serial_no, name, dimensions) VALUES (?, ?, ?)");
                    $stmt->execute([$product['S.No'], $product['Name'], $product['Dimensions']]);
                    $migrated++;
                }
            } catch (PDOException $e) {
                $errors++;
                error_log("Error migrating product {$product['S.No']}: " . $e->getMessage());
            }
        }
        
        return "✅ Products migrated: $migrated successful, $errors errors";
        
    } catch (Exception $e) {
        return "❌ Error migrating products: " . $e->getMessage();
    }
}

function migrateOrders() {
    try {
        $csvOrders = getCsvData('orders.csv');
        $migrated = 0;
        $errors = 0;
        
        foreach ($csvOrders as $order) {
            try {
                // Check if order already exists
                $conn = getDbConnection();
                $stmt = $conn->prepare("SELECT id FROM orders WHERE order_id = ?");
                $stmt->execute([$order['order_id']]);
                $exists = $stmt->fetch();
                
                if (!$exists) {
                    // Get customer internal ID
                    $stmt = $conn->prepare("SELECT id FROM customers WHERE id = ?");
                    $stmt->execute([$order['customer_id']]);
                    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($customer) {
                        $stmt = $conn->prepare("
                            INSERT INTO orders (order_id, customer_id, po_date, delivery_date, due_date, items_json, status, drawing_filename, inspection_reports_json) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $order['order_id'],
                            $customer['id'],
                            $order['po_date'],
                            $order['delivery_date'] ?? null,
                            $order['due_date'] ?? null,
                            $order['items_json'],
                            $order['status'] ?? 'Pending',
                            $order['drawing_filename'] ?? '',
                            $order['inspection_reports_json'] ?? '[]'
                        ]);
                        
                        $migrated++;
                    } else {
                        $errors++;
                        error_log("Customer not found for order {$order['order_id']}: {$order['customer_id']}");
                    }
                }
            } catch (PDOException $e) {
                $errors++;
                error_log("Error migrating order {$order['order_id']}: " . $e->getMessage());
            }
        }
        
        return "✅ Orders migrated: $migrated successful, $errors errors";
        
    } catch (Exception $e) {
        return "❌ Error migrating orders: " . $e->getMessage();
    }
}

// Handle migration request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrate'])) {
    $results = migrateToMySQL();
    $_SESSION['migration_results'] = $results;
    header('Location: migrate_to_mysql.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrate to MySQL - Alphasonix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { margin-bottom: 20px; }
        .success { color: #198754; }
        .error { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h2 class="mb-0">🚀 Migrate to MySQL Database</h2>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <strong>⚠️ Important:</strong> 
                            <ul class="mb-0">
                                <li>Backup your CSV files before migration</li>
                                <li>This process cannot be undone</li>
                                <li>Only run this once</li>
                                <li>Make sure you have MySQL running</li>
                            </ul>
                        </div>
                        
                        <?php if (isset($_SESSION['migration_results'])): ?>
                            <div class="alert alert-info">
                                <h5>Migration Results:</h5>
                                <?php foreach ($_SESSION['migration_results'] as $result): ?>
                                    <div class="<?= strpos($result, '❌') !== false ? 'error' : 'success' ?>">
                                        <?= htmlspecialchars($result) ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php unset($_SESSION['migration_results']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post">
                            <div class="d-grid gap-2">
                                <button type="submit" name="migrate" class="btn btn-primary btn-lg" 
                                        onclick="return confirm('Are you sure you want to migrate all data to MySQL? This cannot be undone.')">
                                    🚀 Start Migration
                                </button>
                                <a href="home.php" class="btn btn-secondary">← Back to Dashboard</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>