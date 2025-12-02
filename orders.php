<?php
session_start();
require_once 'functions.php';
require_once 'db.php';

// Define a constant to prevent function redeclaration
define('ORDERS_PHP_LOADED', true);

// Check authentication
// requireAuth();

// Check rate limiting
if (!checkRateLimit('orders_page', $_SESSION['user_id'] ?? 0, 100, 60)) {
    die('Rate limit exceeded. Please try again later.');
}

// ==========================================
// AUTO-FIX DATABASE SCHEMA
// ==========================================
try {
    $conn = getDbConnection();
    
    // Ensure 'orders' table has correct columns
    $orderCols = [
        'po_number' => "VARCHAR(50) DEFAULT '' AFTER customer_id",
        'total_amount' => "DECIMAL(15,2) DEFAULT 0.00 AFTER status",
        'priority' => "VARCHAR(20) DEFAULT 'normal' AFTER total_amount",
        'notes' => "TEXT AFTER priority",
        'created_by' => "INT AFTER notes",
        'payment_terms' => "VARCHAR(50) DEFAULT 'Net 30' AFTER due_date",
        'shipping_method' => "VARCHAR(50) DEFAULT 'Standard' AFTER payment_terms",
        'shipping_cost' => "DECIMAL(10,2) DEFAULT 0.00 AFTER shipping_method",
        'tax_rate' => "DECIMAL(5,2) DEFAULT 0.00 AFTER shipping_cost"
    ];
    
    foreach ($orderCols as $col => $def) {
        try { 
            $conn->query("SELECT $col FROM orders LIMIT 1"); 
        } catch (Exception $e) { 
            $conn->exec("ALTER TABLE orders ADD COLUMN $col $def"); 
        }
    }

    // Ensure 'order_items' table has correct columns
    $itemCols = [
        'unit_price' => "DECIMAL(15,2) DEFAULT 0.00 AFTER quantity",
        'total_price' => "DECIMAL(15,2) DEFAULT 0.00 AFTER unit_price"
    ];
    
    foreach ($itemCols as $col => $def) {
        try { 
            $conn->query("SELECT $col FROM order_items LIMIT 1"); 
        } catch (Exception $e) { 
            $conn->exec("ALTER TABLE order_items ADD COLUMN $col $def"); 
        }
    }
    
    // Ensure 'products' table has dimension_type column
    try {
        $conn->query("SELECT dimension_type FROM products LIMIT 1");
    } catch (Exception $e) {
        $conn->exec("ALTER TABLE products ADD COLUMN dimension_type VARCHAR(20) DEFAULT 'plate' AFTER dimensions");
    }
    
    // And for order_items table
    try {
        $conn->query("SELECT dimension_type FROM order_items LIMIT 1");
    } catch (Exception $e) {
        $conn->exec("ALTER TABLE order_items ADD COLUMN dimension_type VARCHAR(20) DEFAULT 'plate' AFTER dimensions");
    }

} catch (Exception $e) {
    error_log("Schema check error: " . $e->getMessage());
}

// ==========================================
// ENHANCED ORDER FUNCTIONS
// ==========================================

function rearrangeFileArray($file_post) {
    $file_ary = array();
    if (!isset($file_post['name']) || !is_array($file_post['name'])) {
        return $file_ary;
    }
    
    $file_count = count($file_post['name']);
    $file_keys = array_keys($file_post);
    
    for ($i = 0; $i < $file_count; $i++) {
        foreach ($file_keys as $key) {
            $file_ary[$i][$key] = $file_post[$key][$i];
        }
    }
    return $file_ary;
}

function handleUpload($file, $orderId, $sNo) {
    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['filename' => '', 'original' => ''];
    }
    
    $uploadDir = 'uploads/drawings/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Validate file type
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'];
    
    $fileType = mime_content_type($file['tmp_name']);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileType, $allowedTypes) || !in_array($ext, $allowedExtensions)) {
        return ['filename' => '', 'original' => '', 'error' => 'Invalid file type'];
    }
    
    // Check file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['filename' => '', 'original' => '', 'error' => 'File too large'];
    }
    
    $cleanName = preg_replace('/[^a-zA-Z0-9]/', '_', $sNo);
    $filename = $orderId . '_' . $cleanName . '_' . uniqid() . '.' . $ext;
    
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return ['filename' => $filename, 'original' => $file['name']];
    }
    
    return ['filename' => '', 'original' => '', 'error' => 'Upload failed'];
}


function getOrdersEnhanced($search = '', $status = '', $customer_id = '', $page = 1, $perPage = 20) {
    $conn = getDbConnection();
    
    $conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $conditions[] = "(o.order_id LIKE ? OR o.po_number LIKE ? OR c.name LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }
    
    if (!empty($status)) {
        $conditions[] = "o.status = ?";
        $params[] = $status;
    }
    
    if (!empty($customer_id)) {
        $conditions[] = "o.customer_id = ?";
        $params[] = $customer_id;
    }
    
    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
    
    // Get total count
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM orders o 
        LEFT JOIN customers c ON o.customer_id = c.id 
        $whereClause
    ");
    $countStmt->execute($params);
    $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $total = $totalResult['total'];
    
    // Get paginated results
    $offset = ($page - 1) * $perPage;
    
    $sql = "
        SELECT o.*, c.name as customer_name, 
               COUNT(oi.id) as item_count,
               SUM(oi.total_price) as items_total
        FROM orders o 
        LEFT JOIN customers c ON o.customer_id = c.id 
        LEFT JOIN order_items oi ON o.id = oi.order_id 
        $whereClause
        GROUP BY o.id 
        ORDER BY o.created_at DESC 
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    
    // Bind parameters
    $boundParams = $params;
    $boundParams[] = $perPage;
    $boundParams[] = $offset;
    
    $stmt->execute($boundParams);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'orders' => $orders,
        'total' => $total
    ];
}

function getOrderStatistics() {
    $conn = getDbConnection();
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_orders,
                COUNT(CASE WHEN status = 'In Production' THEN 1 END) as production_orders,
                COUNT(CASE WHEN status = 'Shipped' THEN 1 END) as shipped_orders,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_orders,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as weekly_orders
            FROM orders
        ");
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_orders' => $result['total_orders'] ?? 0,
            'total_revenue' => $result['total_revenue'] ?? 0,
            'pending_orders' => $result['pending_orders'] ?? 0,
            'production_orders' => $result['production_orders'] ?? 0,
            'shipped_orders' => $result['shipped_orders'] ?? 0,
            'today_orders' => $result['today_orders'] ?? 0,
            'weekly_orders' => $result['weekly_orders'] ?? 0
        ];
    } catch (Exception $e) {
        error_log("Order statistics error: " . $e->getMessage());
        return [
            'total_orders' => 0,
            'total_revenue' => 0,
            'pending_orders' => 0,
            'production_orders' => 0,
            'shipped_orders' => 0,
            'today_orders' => 0,
            'weekly_orders' => 0
        ];  
    }
}

function calculateOrderTotals($items, $shippingMethod, $taxRate = 10) {
    $subtotal = 0;
    
    foreach ($items as $item) {
        $quantity = intval($item['quantity'] ?? 0);
        $unitPrice = floatval($item['unit_price'] ?? 0);
        $subtotal += $quantity * $unitPrice;
    }
    
    // Calculate shipping
    $shippingCost = 0;
    switch ($shippingMethod) {
        case 'Express':
            $shippingCost = 25;
            break;
        case 'Overnight':
            $shippingCost = 50;
            break;
        case 'Pickup':
            $shippingCost = 0;
            break;
        default: // Standard
            $shippingCost = 10;
    }
    
    // Calculate tax
    $taxAmount = $subtotal * ($taxRate / 100);
    
    // Calculate total
    $total = $subtotal + $taxAmount + $shippingCost;
    
    return [
        'subtotal' => $subtotal,
        'tax_rate' => $taxRate,
        'tax_amount' => $taxAmount,
        'shipping_cost' => $shippingCost,
        'total' => $total
    ];
}

// ==========================================
// REQUEST HANDLING
// ==========================================

// Handle ORDER CREATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token.'];
        header('Location: orders.php');
        exit;
    }

    $customerId = sanitize_input($_POST['customer_id']);
    $poDate = sanitize_input($_POST['po_date']);
    $poNumber = sanitize_input($_POST['po_number'] ?? '');
    
    // Validation
    if (empty($customerId) || empty($poDate)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Customer and PO Date are required.'];
        header('Location: orders.php');
        exit;
    }

    $conn = getDbConnection();
    
    try {
        $conn->beginTransaction();

        // 1. Prepare Order Data
        $orderIdStr = generateNextOrderIdStr();
        $deliveryDate = !empty($_POST['delivery_date']) ? $_POST['delivery_date'] : null;
        
        // Calculate Due Date logic
        $days = 30;
        if (isset($_POST['payment_terms']) && preg_match('/Net (\d+)/', $_POST['payment_terms'], $matches)) {
            $days = intval($matches[1]);
        }
        $dueDate = date('Y-m-d', strtotime($poDate . " + $days days"));

        // 2. Insert Order Header
        $stmt = $conn->prepare("
            INSERT INTO orders 
            (order_id, customer_id, po_number, po_date, delivery_date, due_date, status, priority, 
             payment_terms, shipping_method, notes, created_by, total_amount) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ");
        
        $stmt->execute([
            $orderIdStr,
            $customerId,
            $poNumber,
            $poDate,
            $deliveryDate,
            $dueDate,
            'Pending',
            $_POST['priority'] ?? 'normal',
            $_POST['payment_terms'] ?? 'Net 30',
            $_POST['shipping_method'] ?? 'Standard',
            $_POST['order_notes'] ?? '',
            $_SESSION['user_id'] ?? 0
        ]);
        
        $internalOrderId = $conn->lastInsertId();
        $allItems = [];

        // 3. Process Existing Products
        if (!empty($_POST['product_sno'])) {
            $files = !empty($_FILES['drawing_file_existing']) ? rearrangeFileArray($_FILES['drawing_file_existing']) : [];
            
            foreach ($_POST['product_sno'] as $idx => $sNo) {
                if (empty($sNo)) continue;
                
                $qty = intval($_POST['quantity'][$idx] ?? 0);
                if ($qty <= 0) continue;
                
                // Fetch product details for name/dims
                $pStmt = $conn->prepare("SELECT * FROM products WHERE serial_no = ?");
                $pStmt->execute([$sNo]);
                $product = $pStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$product) {
                    throw new Exception("Product with serial number {$sNo} not found");
                }
                
                $price = floatval($_POST['custom_price'][$idx] ?? $product['price'] ?? 0);
                $rowTotal = $qty * $price;
                
                // Upload File
                $fileData = isset($files[$idx]) ? handleUpload($files[$idx], $orderIdStr, $sNo) : ['filename'=>'', 'original'=>''];

                // Get dimension type and formatted dimensions
                $dimensionType = sanitize_input($_POST['dimension_type'][$idx] ?? 'plate');
                $formattedDimensions = sanitize_input($_POST['product_dimensions'][$idx] ?? $product['dimensions'] ?? '');

                // Insert Item
                $iStmt = $conn->prepare("
                    INSERT INTO order_items 
                    (order_id, product_id, serial_no, name, dimensions, dimension_type, description, quantity, 
                     unit_price, total_price, item_status, drawing_filename, original_filename)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?)
                ");
                
                $iStmt->execute([
                    $internalOrderId,
                    $product['id'] ?? null,
                    $sNo,
                    $product['name'] ?? 'Unknown',
                    $formattedDimensions,
                    $dimensionType,
                    $_POST['product_description'][$idx] ?? '',
                    $qty,
                    $price,
                    $rowTotal,
                    $fileData['filename'],
                    $fileData['original']
                ]);
                
                $allItems[] = [
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'total_price' => $rowTotal
                ];
                
                // Update Stock if product exists in catalog
                if ($product) {
                    $updateStmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE serial_no = ?");
                    $updateStmt->execute([$qty, $sNo]);
                }
            }
        }

        // 4. Process Manual Products
        if (!empty($_POST['manual_product_name'])) {
            $files = !empty($_FILES['drawing_file_manual']) ? rearrangeFileArray($_FILES['drawing_file_manual']) : [];
            
            foreach ($_POST['manual_product_name'] as $idx => $name) {
                if (empty($name)) continue;
                
                $qty = intval($_POST['manual_quantity'][$idx] ?? 0);
                if ($qty <= 0) continue;
                
                $price = floatval($_POST['manual_price'][$idx] ?? 0);
                $rowTotal = $qty * $price;
                
                $manualSNo = 'MAN-' . uniqid();
                
                // Upload File
                $fileData = isset($files[$idx]) ? handleUpload($files[$idx], $orderIdStr, $manualSNo) : ['filename'=>'', 'original'=>''];

                // Get dimension type and formatted dimensions
                $dimensionType = sanitize_input($_POST['manual_dimension_type'][$idx] ?? 'plate');
                $formattedDimensions = sanitize_input($_POST['manual_product_dimensions'][$idx] ?? '');

                // Insert Item
                $iStmt = $conn->prepare("
                    INSERT INTO order_items 
                    (order_id, product_id, serial_no, name, dimensions, dimension_type, description, quantity, 
                     unit_price, total_price, item_status, drawing_filename, original_filename)
                    VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?)
                ");
                
                $iStmt->execute([
                    $internalOrderId,
                    $manualSNo,
                    $name,
                    $formattedDimensions,
                    $dimensionType,
                    $_POST['manual_product_description'][$idx] ?? '',
                    $qty,
                    $price,
                    $rowTotal,
                    $fileData['filename'],
                    $fileData['original']
                ]);
                
                $allItems[] = [
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'total_price' => $rowTotal
                ];
            }
        }

        // 5. Calculate and Update Order Total
        if (!empty($allItems)) {
            $totals = calculateOrderTotals($allItems, $_POST['shipping_method'] ?? 'Standard');
            
            $updateStmt = $conn->prepare("
                UPDATE orders SET 
                total_amount = ?,
                shipping_cost = ?,
                tax_rate = ?
                WHERE id = ?
            ");
            
            $updateStmt->execute([
                $totals['total'],
                $totals['shipping_cost'],
                $totals['tax_rate'],
                $internalOrderId
            ]);
        }

        $conn->commit();
        
        logActivity("Order Created", "Order $orderIdStr created by " . ($_SESSION['username'] ?? 'User'));
        $_SESSION['message'] = ['type' => 'success', 'text' => "Order $orderIdStr created successfully!"];
        
        // Redirect to success state
        header("Location: orders.php?success=" . urlencode($orderIdStr));
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Order creation error: " . $e->getMessage());
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error creating order: ' . $e->getMessage()];
    }
    
    header('Location: orders.php');
    exit;
}

// Handle ORDER UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token.'];
        header('Location: orders.php');
        exit;
    }

    $orderId = sanitize_input($_POST['order_id']);
    $status = sanitize_input($_POST['status']);

    try {
        $conn = getDbConnection();
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        
        if ($stmt->execute([$status, $orderId])) {
            $_SESSION['message'] = ['type' => 'success', 'text' => "Order $orderId updated successfully!"];
            logActivity("Order Updated", "Order $orderId status changed to $status");
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to update order.'];
        }
    } catch (Exception $e) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
    }

    header('Location: orders.php');
    exit;
}

// Handle ORDER DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token.'];
        header('Location: orders.php');
        exit;
    }

    $orderId = sanitize_input($_POST['order_id']);

    try {
        $conn = getDbConnection();
        
        // Get order items to restore stock
        $itemsStmt = $conn->prepare("
            SELECT oi.serial_no, oi.quantity, p.id 
            FROM order_items oi 
            LEFT JOIN products p ON oi.serial_no = p.serial_no 
            WHERE oi.order_id = (SELECT id FROM orders WHERE order_id = ?)
        ");
        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Restore stock for catalog products
        foreach ($items as $item) {
            if ($item['id']) {
                $restoreStmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
                $restoreStmt->execute([$item['quantity'], $item['id']]);
            }
        }
        
        // Delete the order (cascade will delete order_items)
        $stmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
        
        if ($stmt->execute([$orderId])) {
            $_SESSION['message'] = ['type' => 'success', 'text' => "Order $orderId deleted successfully!"];
            logActivity("Order Deleted", "Order $orderId deleted");
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to delete order.'];
        }
    } catch (Exception $e) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
    }

    header('Location: orders.php');
    exit;
}

// ==========================================
// DATA FETCHING FOR DISPLAY
// ==========================================

$conn = getDbConnection();

// Fetch Customers
$customers = $conn->query("SELECT * FROM customers WHERE status = 'active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Products
$products = $conn->query("SELECT * FROM products WHERE stock_quantity > 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get parameters for filtering
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$customerFilter = $_GET['customer'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

// Fetch orders with filters
$ordersData = getOrdersEnhanced($search, $statusFilter, $customerFilter, $page, $perPage);
$orders = $ordersData['orders'];
$totalOrders = $ordersData['total'];
$totalPages = ceil($totalOrders / $perPage);

// Fetch Recent Orders (For Dashboard)
$recentOrders = $conn->query("
    SELECT o.*, c.name as customer_name 
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Stats
$orderStats = getOrderStatistics();

$totalOrdersCount = $orderStats['total_orders'] ?? 0;
$totalRevenue = $orderStats['total_revenue'] ?? 0;
$pendingOrders = $orderStats['pending_orders'] ?? 0;
$todayOrders = $orderStats['today_orders'] ?? 0;

$csrfToken = generateCsrfToken();
$today = date('Y-m-d');

// Helper to map Customer IDs to Names for the Recent List
$customerMap = [];
foreach($customers as $c) { 
    $customerMap[$c['id']] = $c['name']; 
}

// Success order ID for highlighting
$successOrderId = isset($_GET['success']) ? sanitize_input($_GET['success']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - Alphasonix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root { 
            --primary: #4361ee; 
            --light: #f8f9fa; 
            --border-color: #dee2e6; 
        }
        body { 
            background-color: #f5f7fb; 
            font-family: 'Segoe UI', sans-serif; 
        }
        .dashboard-header { 
            background: white; 
            border-radius: 0.75rem; 
            padding: 1.5rem; 
            margin-bottom: 1.5rem; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); 
        }
        .stat-card { 
            background: white; 
            border-radius: 0.75rem; 
            padding: 1.5rem; 
            border: none; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); 
            height: 100%; 
        }
        .stat-value { 
            font-size: 2rem; 
            font-weight: 700; 
            color: #212529; 
        }
        .content-card { 
            background: white; 
            border-radius: 0.75rem; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); 
            margin-bottom: 1.5rem; 
        }
        .card-header-custom { 
            padding: 1.25rem; 
            border-bottom: 1px solid var(--border-color); 
            font-weight: 600; 
            background: var(--light);
        }
        .card-body-custom { 
            padding: 1.5rem; 
        }
        .product-entry { 
            background: #f9f9f9; 
            border: 1px solid #eee; 
            border-radius: 8px; 
            padding: 15px; 
            margin-bottom: 15px; 
            position: relative; 
        }
        .remove-btn { 
            position: absolute; 
            top: 10px; 
            right: 10px; 
        }
        .order-row.new-entry { 
            animation: highlight 2s ease; 
        }
        @keyframes highlight {
            0% { background-color: rgba(67, 97, 238, 0.2); }
            100% { background-color: transparent; }
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-sourcing { background-color: #ffeaa7; color: #856404; }
        .status-production { background-color: #d4edda; color: #155724; }
        .status-qc { background-color: #cce7ff; color: #004085; }
        .status-packaging { background-color: #e2e3e5; color: #383d41; }
        .status-shipped { background-color: #d1ecf1; color: #0c5460; }
        
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }
        
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }
        }
        
        .dimension-inputs .col-3,
        .dimension-inputs .col-4 {
            padding-left: 3px;
            padding-right: 3px;
        }
        
        .dimension-inputs input {
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        
        <!-- Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= $_SESSION['message']['type'] == 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                <?= $_SESSION['message']['text'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <!-- Success Overlay -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <h4><i class="bi bi-check-circle"></i> Success!</h4>
                <p>Order <strong><?= htmlspecialchars($_GET['success']) ?></strong> has been created.</p>
                <a href="orders.php" class="btn btn-sm btn-success">Create Another</a>
            </div>
        <?php endif; ?>

        <div class="dashboard-header">
            <h3><i class="bi bi-cart"></i> Order Management</h3>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="text-muted">Total Orders</div>
                    <div class="stat-value"><?= number_format($totalOrdersCount) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="text-muted">Revenue</div>
                    <div class="stat-value">₹<?= number_format($totalRevenue, 2) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="text-muted">Pending</div>
                    <div class="stat-value"><?= number_format($pendingOrders) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="text-muted">Today</div>
                    <div class="stat-value"><?= number_format($todayOrders) ?></div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Create Order Form -->
            <div class="col-lg-8">
                <div class="content-card">
                    <div class="card-header-custom">Create New Order</div>
                    <div class="card-body-custom">
                        <form action="orders.php" method="POST" enctype="multipart/form-data" id="orderForm">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="create_order" value="1">

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Client <span class="text-danger">*</span></label>
                                    <select name="customer_id" class="form-select" required>
                                        <option value="">Select Client</option>
                                        <?php foreach ($customers as $c): ?>
                                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">PO Date <span class="text-danger">*</span></label>
                                    <input type="date" name="po_date" class="form-control" value="<?= $today ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">PO Number</label>
                                    <input type="text" name="po_number" class="form-control" placeholder="PO-12345">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Delivery Date</label>
                                    <input type="date" name="delivery_date" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Payment Terms</label>
                                    <select name="payment_terms" class="form-select">
                                        <option value="Net 30">Net 30</option>
                                        <option value="Net 15">Net 15</option>
                                        <option value="Due on Receipt">Due on Receipt</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Shipping</label>
                                    <select name="shipping_method" id="shipping_method" class="form-select" onchange="calcTotal()">
                                        <option value="Standard">Standard (₹10)</option>
                                        <option value="Express">Express (₹25)</option>
                                        <option value="Overnight">Overnight (₹50)</option>
                                        <option value="Pickup">Pickup (₹0)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Priority</label>
                                    <select name="priority" class="form-select">
                                        <option value="normal">Normal</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea name="order_notes" class="form-control" rows="2" placeholder="Any special instructions..."></textarea>
                                </div>
                            </div>

                            <h5 class="mb-3">Products</h5>
                            
                            <!-- Product Containers -->
                            <div id="productContainer">
                                <!-- Default Empty Manual Item -->
                                <div class="product-entry manual-entry">
                                    <button type="button" class="btn-close remove-btn" onclick="removeItem(this)"></button>
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label class="small">Product Name <span class="text-danger">*</span></label>
                                            <input type="text" name="manual_product_name[]" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="small">Qty <span class="text-danger">*</span></label>
                                            <input type="number" name="manual_quantity[]" class="form-control form-control-sm qty" value="1" min="1" onchange="calcTotal()" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small">Price (₹) <span class="text-danger">*</span></label>
                                            <input type="number" name="manual_price[]" class="form-control form-control-sm price" value="0" step="0.01" onchange="calcTotal()" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small">Drawing</label>
                                            <input type="file" name="drawing_file_manual[]" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx">
                                        </div>
                                        
                                        <!-- Dimension Type and Inputs -->
                                        <div class="col-md-4">
                                            <label class="small">Dimension Type</label>
                                            <select name="manual_dimension_type[]" class="form-select form-select-sm" onchange="updateDimensionInputs(this)">
                                                <option value="plate">Plate (L x W x T)</option>
                                                <option value="pipe">Pipe (L x OD x ID x T)</option>
                                                <option value="t-joint">T-Joint (L x W x T x H)</option>
                                                <option value="block">Block (L x W x H)</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-8">
                                            <label class="small">Dimensions</label>
                                            <div class="dimension-inputs">
                                                <div class="row g-1">
                                                    <div class="col-4">
                                                        <input type="number" name="manual_dimension_L[]" class="form-control form-control-sm" placeholder="L (mm)" step="0.1">
                                                    </div>
                                                    <div class="col-4">
                                                        <input type="number" name="manual_dimension_W[]" class="form-control form-control-sm" placeholder="W (mm)" step="0.1">
                                                    </div>
                                                    <div class="col-4">
                                                        <input type="number" name="manual_dimension_T[]" class="form-control form-control-sm" placeholder="T (mm)" step="0.1">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <input type="hidden" name="manual_product_dimensions[]" class="dimensions-hidden">
                                            <textarea name="manual_product_description[]" class="form-control form-control-sm mt-1" placeholder="Description" rows="1"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mb-4">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addManualItem()">
                                    <i class="bi bi-plus"></i> Add Custom Item
                                </button>
                                <button type="button" class="btn btn-outline-dark btn-sm" onclick="addExistingItem()">
                                    <i class="bi bi-plus"></i> Add Catalog Item
                                </button>
                            </div>

                            <!-- Totals -->
                            <div class="alert alert-light border">
                                <div class="d-flex justify-content-between">
                                    <span>Subtotal:</span>
                                    <strong id="displaySubtotal">₹0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Shipping:</span>
                                    <strong id="displayShipping">₹10.00</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Tax (10%):</span>
                                    <strong id="displayTax">₹0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between fs-5 mt-2 pt-2 border-top">
                                    <strong>Total:</strong>
                                    <strong class="text-primary" id="displayTotal">₹0.00</strong>
                                </div>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-circle"></i> Create Order
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Recent Orders & Order List -->
            <div class="col-lg-4">
                <!-- Recent Orders -->
                <div class="content-card mb-4">
                    <div class="card-header-custom">Recent Orders</div>
                    <div class="list-group list-group-flush">
                        <?php if (count($recentOrders) > 0): ?>
                            <?php foreach ($recentOrders as $o): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <strong><?= htmlspecialchars($o['order_id']) ?></strong>
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $o['status'])) ?>">
                                            <?= htmlspecialchars($o['status']) ?>
                                        </span>
                                    </div>
                                    <small class="text-muted"><?= htmlspecialchars($customerMap[$o['customer_id']] ?? 'Unknown') ?></small>
                                    <div class="mt-1">₹<?= number_format($o['total_amount'], 2) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-3 text-center text-muted">No orders yet.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Order Search & Filter -->
                <div class="content-card">
                    <div class="card-header-custom">Order Search</div>
                    <div class="card-body-custom">
                        <form method="get" action="orders.php">
                            <div class="mb-3">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Order ID, PO Number, Customer...">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="Sourcing Material" <?= $statusFilter === 'Sourcing Material' ? 'selected' : '' ?>>Sourcing Material</option>
                                    <option value="In Production" <?= $statusFilter === 'In Production' ? 'selected' : '' ?>>In Production</option>
                                    <option value="Ready for QC" <?= $statusFilter === 'Ready for QC' ? 'selected' : '' ?>>Ready for QC</option>
                                    <option value="QC Completed" <?= $statusFilter === 'QC Completed' ? 'selected' : '' ?>>QC Completed</option>
                                    <option value="Packaging" <?= $statusFilter === 'Packaging' ? 'selected' : '' ?>>Packaging</option>
                                    <option value="Ready for Dispatch" <?= $statusFilter === 'Ready for Dispatch' ? 'selected' : '' ?>>Ready for Dispatch</option>
                                    <option value="Shipped" <?= $statusFilter === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Customer</label>
                                <select name="customer" class="form-select">
                                    <option value="">All Customers</option>
                                    <?php foreach ($customers as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= $customerFilter == $c['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Search Orders
                            </button>
                            <?php if (!empty($search) || !empty($statusFilter) || !empty($customerFilter)): ?>
                                <a href="orders.php" class="btn btn-outline-secondary w-100 mt-2">
                                    <i class="bi bi-x"></i> Clear Filters
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders Table -->
        <?php if (!empty($search) || !empty($statusFilter) || !empty($customerFilter) || $totalOrders > 0): ?>
        <div class="content-card">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <span>Order List</span>
                <span class="badge bg-primary"><?= number_format($totalOrders) ?> orders</span>
            </div>
            <div class="card-body-custom">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>PO Date</th>
                                <th>Due Date</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                        <p class="mt-2">No orders found matching your criteria.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr class="order-row <?= ($successOrderId === $order['order_id']) ? 'new-entry' : '' ?>">
                                        <td>
                                            <strong><?= htmlspecialchars($order['order_id']) ?></strong>
                                            <?php if (!empty($order['po_number'])): ?>
                                                <br><small class="text-muted">PO: <?= htmlspecialchars($order['po_number']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                        <td><?= date('M j, Y', strtotime($order['po_date'])) ?></td>
                                        <td>
                                            <?php if ($order['due_date']): ?>
                                                <?= date('M j, Y', strtotime($order['due_date'])) ?>
                                                <?php if (strtotime($order['due_date']) < time()): ?>
                                                    <span class="badge bg-danger ms-1">Overdue</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= $order['item_count'] ?> items</span>
                                        </td>
                                        <td>
                                            <strong>₹<?= number_format($order['total_amount'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $order['status'])) ?>">
                                                <?= htmlspecialchars($order['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="./order_details.php?order_id=<?= urlencode($order['order_id']) ?>" 
                                                   class="btn btn-outline-primary" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-warning" 
                                                        onclick="updateOrderStatus('<?= htmlspecialchars($order['order_id']) ?>')"
                                                        title="Update Status">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="confirmDelete('<?= htmlspecialchars($order['order_id']) ?>')"
                                                        title="Delete Order">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Order pagination">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&customer=<?= urlencode($customerFilter) ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&customer=<?= urlencode($customerFilter) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&customer=<?= urlencode($customerFilter) ?>">
                                        Next
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Order Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="updateStatusForm" method="post">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="update_order" value="1">
                    <input type="hidden" name="order_id" id="updateOrderId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">New Status</label>
                            <select name="status" class="form-select" required>
                                <option value="Pending">Pending</option>
                                <option value="Sourcing Material">Sourcing Material</option>
                                <option value="In Production">In Production</option>
                                <option value="Ready for QC">Ready for QC</option>
                                <option value="QC Completed">QC Completed</option>
                                <option value="Packaging">Packaging</option>
                                <option value="Ready for Dispatch">Ready for Dispatch</option>
                                <option value="Shipped">Shipped</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="deleteForm" method="post">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="delete_order" value="1">
                    <input type="hidden" name="order_id" id="deleteOrderId">
                    <div class="modal-body">
                        <p>Are you sure you want to delete order <strong id="deleteOrderNumber"></strong>?</p>
                        <p class="text-danger">This action cannot be undone. Stock quantities will be restored for catalog items.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden Template for Existing Items -->
    <template id="existingTemplate">
        <div class="product-entry existing-entry">
            <button type="button" class="btn-close remove-btn" onclick="removeItem(this)"></button>
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="small">Product <span class="text-danger">*</span></label>
                    <select name="product_sno[]" class="form-select form-select-sm" onchange="updateProductDetails(this)" required>
                        <option value="">Select Product</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['serial_no'] ?>" 
                                    data-price="<?= $p['price'] ?>" 
                                    data-dimensions="<?= htmlspecialchars($p['dimensions']) ?>"
                                    data-dimension-type="<?= $p['dimension_type'] ?? 'plate' ?>">
                                <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['serial_no']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small">Qty <span class="text-danger">*</span></label>
                    <input type="number" name="quantity[]" class="form-control form-control-sm qty" value="1" min="1" onchange="calcTotal()" required>
                </div>
                <div class="col-md-4">
                    <label class="small">Price (₹) <span class="text-danger">*</span></label>
                    <input type="number" name="custom_price[]" class="form-control form-control-sm price" value="0" step="0.01" onchange="calcTotal()" required>
                </div>
                
                <!-- Dimension Type and Inputs -->
                <div class="col-md-4">
                    <label class="small">Dimension Type</label>
                    <select name="dimension_type[]" class="form-select form-select-sm" onchange="updateDimensionInputs(this)" data-original-type="">
                        <option value="plate">Plate (L x W x T)</option>
                        <option value="pipe">Pipe (L x OD x ID x T)</option>
                        <option value="t-joint">T-Joint (L x W x T x H)</option>
                        <option value="block">Block (L x W x H)</option>
                    </select>
                </div>
                
                <div class="col-md-8">
                    <label class="small">Dimensions</label>
                    <div class="dimension-inputs">
                        <!-- Plate dimensions by default -->
                        <div class="row g-1">
                            <div class="col-4">
                                <input type="number" name="dimension_L[]" class="form-control form-control-sm" placeholder="L (mm)" step="0.1">
                            </div>
                            <div class="col-4">
                                <input type="number" name="dimension_W[]" class="form-control form-control-sm" placeholder="W (mm)" step="0.1">
                            </div>
                            <div class="col-4">
                                <input type="number" name="dimension_T[]" class="form-control form-control-sm" placeholder="T (mm)" step="0.1">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-12">
                    <input type="file" name="drawing_file_existing[]" class="form-control form-control-sm mt-1" accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx">
                    <input type="hidden" name="product_dimensions[]" class="dimensions-hidden">
                    <textarea name="product_description[]" class="form-control form-control-sm mt-1" placeholder="Notes" rows="1"></textarea>
                </div>
            </div>
        </div>
    </template>

    <!-- Hidden Template for Manual Items -->
    <template id="manualTemplate">
        <div class="product-entry manual-entry">
            <button type="button" class="btn-close remove-btn" onclick="removeItem(this)"></button>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="small">Product Name <span class="text-danger">*</span></label>
                    <input type="text" name="manual_product_name[]" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-2">
                    <label class="small">Qty <span class="text-danger">*</span></label>
                    <input type="number" name="manual_quantity[]" class="form-control form-control-sm qty" value="1" min="1" onchange="calcTotal()" required>
                </div>
                <div class="col-md-3">
                    <label class="small">Price (₹) <span class="text-danger">*</span></label>
                    <input type="number" name="manual_price[]" class="form-control form-control-sm price" value="0" step="0.01" onchange="calcTotal()" required>
                </div>
                <div class="col-md-3">
                    <label class="small">Drawing</label>
                    <input type="file" name="drawing_file_manual[]" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx">
                </div>
                
                <!-- Dimension Type and Inputs for manual products -->
                <div class="col-md-4">
                    <label class="small">Dimension Type</label>
                    <select name="manual_dimension_type[]" class="form-select form-select-sm" onchange="updateDimensionInputs(this)">
                        <option value="plate">Plate (L x W x T)</option>
                        <option value="pipe">Pipe (L x OD x ID x T)</option>
                        <option value="t-joint">T-Joint (L x W x T x H)</option>
                        <option value="block">Block (L x W x H)</option>
                    </select>
                </div>
                
                <div class="col-md-8">
                    <label class="small">Dimensions</label>
                    <div class="dimension-inputs">
                        <div class="row g-1">
                            <div class="col-4">
                                <input type="number" name="manual_dimension_L[]" class="form-control form-control-sm" placeholder="L (mm)" step="0.1">
                            </div>
                            <div class="col-4">
                                <input type="number" name="manual_dimension_W[]" class="form-control form-control-sm" placeholder="W (mm)" step="0.1">
                            </div>
                            <div class="col-4">
                                <input type="number" name="manual_dimension_T[]" class="form-control form-control-sm" placeholder="T (mm)" step="0.1">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-12">
                    <input type="hidden" name="manual_product_dimensions[]" class="dimensions-hidden">
                    <textarea name="manual_product_description[]" class="form-control form-control-sm mt-1" placeholder="Description" rows="1"></textarea>
                </div>
            </div>
        </div>
    </template>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Product Management
        function addManualItem() {
            const tpl = document.getElementById('manualTemplate').content.cloneNode(true);
            document.getElementById('productContainer').appendChild(tpl);
        }

        function addExistingItem() {
            const tpl = document.getElementById('existingTemplate').content.cloneNode(true);
            document.getElementById('productContainer').appendChild(tpl);
        }

        function removeItem(btn) {
            btn.closest('.product-entry').remove();
            calcTotal();
        }

        function updateProductDetails(select) {
            const option = select.options[select.selectedIndex];
            const price = option.dataset.price || 0;
            const dimensions = option.dataset.dimensions || '';
            const dimensionType = option.dataset.dimensionType || 'plate';
            
            const row = select.closest('.row');
            row.querySelector('.price').value = price;
            
            // Update dimension type
            const dimensionTypeSelect = row.querySelector('select[name="dimension_type[]"]');
            dimensionTypeSelect.value = dimensionType;
            dimensionTypeSelect.setAttribute('data-original-type', dimensionType);
            
            // Update dimension inputs based on type
            updateDimensionInputs(dimensionTypeSelect);
            
            // If dimensions exist, parse and fill them
            if (dimensions) {
                const dimParts = dimensions.split('x').map(part => part.trim());
                
                // Fill dimension inputs based on type
                switch(dimensionType) {
                    case 'plate':
                        if (dimParts.length >= 3) {
                            row.querySelector('[name="dimension_L[]"]').value = dimParts[0] || '';
                            row.querySelector('[name="dimension_W[]"]').value = dimParts[1] || '';
                            row.querySelector('[name="dimension_T[]"]').value = dimParts[2] || '';
                        }
                        break;
                        
                    case 'pipe':
                        if (dimParts.length >= 4) {
                            row.querySelector('[name="dimension_L[]"]').value = dimParts[0] || '';
                            row.querySelector('[name="dimension_OD[]"]').value = dimParts[1] || '';
                            row.querySelector('[name="dimension_ID[]"]').value = dimParts[2] || '';
                            row.querySelector('[name="dimension_T[]"]').value = dimParts[3] || '';
                        }
                        break;
                        
                    case 't-joint':
                        if (dimParts.length >= 4) {
                            row.querySelector('[name="dimension_L[]"]').value = dimParts[0] || '';
                            row.querySelector('[name="dimension_W[]"]').value = dimParts[1] || '';
                            row.querySelector('[name="dimension_T[]"]').value = dimParts[2] || '';
                            row.querySelector('[name="dimension_H[]"]').value = dimParts[3] || '';
                        }
                        break;
                        
                    case 'block':
                        if (dimParts.length >= 3) {
                            row.querySelector('[name="dimension_L[]"]').value = dimParts[0] || '';
                            row.querySelector('[name="dimension_W[]"]').value = dimParts[1] || '';
                            row.querySelector('[name="dimension_H[]"]').value = dimParts[2] || '';
                        }
                        break;
                }
            }
            
            calcTotal();
        }

        // Function to update dimension inputs based on type
        function updateDimensionInputs(select) {
            const type = select.value;
            const row = select.closest('.row');
            const dimensionDiv = row.querySelector('.dimension-inputs');
            const isManual = select.name.includes('manual');
            
            let html = '';
            
            switch(type) {
                case 'plate':
                    html = `
                        <div class="row g-1">
                            <div class="col-4">
                                <input type="number" name="${isManual ? 'manual_dimension_L[]' : 'dimension_L[]'}" 
                                       class="form-control form-control-sm" placeholder="L (mm)" step="0.1">
                            </div>
                            <div class="col-4">
                                <input type="number" name="${isManual ? 'manual_dimension_W[]' : 'dimension_W[]'}" 
                                       class="form-control form-control-sm" placeholder="W (mm)" step="0.1">
                            </div>
                            <div class="col-4">
                                <input type="number" name="${isManual ? 'manual_dimension_T[]' : 'dimension_T[]'}" 
                                       class="form-control form-control-sm" placeholder="T (mm)" step="0.1">
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'pipe':
                    html = `
                        <div class="row g-1">
                            <div class="col-3">
                                <input type="number" name="${isManual ? 'manual_dimension_L[]' : 'dimension_L[]'}" 
                                       class="form-control form-control-sm" placeholder="L (mm)" step="0.1">
                            </div>
                            <div class="col-3">
                                <input type="number" name="${isManual ? 'manual_dimension_OD[]' : 'dimension_OD[]'}" 
                                       class="form-control form-control-sm" placeholder="OD (mm)" step="0.1">
                            </div>
                            <div class="col-3">
                                <input type="number" name="${isManual ? 'manual_dimension_ID[]' : 'dimension_ID[]'}" 
                                       class="form-control form-control-sm" placeholder="ID (mm)" step="0.1">
                            </div>
                            <div class="col-3">
                                <input type="number" name="${isManual ? 'manual_dimension_T[]' : 'dimension_T[]'}" 
                                       class="form-control form-control-sm" placeholder="T (mm)" step="0.1">
                            </div>
                        </div>
                    `;
                    break;
                    
                case 't-joint':
                    html = `
                        <div class="row g-1">
                            <div class="col-3">
                                <input type="number" name="${isManual ? 'manual_dimension_L[]' : 'dimension_L[]'}" 
                                       class="form-control form-control-sm" placeholder="L (mm)" step="0.1">
                            </div>
                            <div class="col-3">
                                <input type="number" name="${isManual ? 'manual_dimension_W[]' : 'dimension_W[]'}" 
                                       class="form-control form-control-sm" placeholder="W (mm)" step="0.1">
                            </div>
                            <div class="col-3">
                                <input type="number" name="${isManual ? 'manual_dimension_T[]' : 'dimension_T[]'}" 
                                       class="form-control form-control-sm" placeholder="T (mm)" step="0.1">
                            </div>
                            <div class="col-3">
                                <input type="number" name="${isManual ? 'manual_dimension_H[]' : 'dimension_H[]'}" 
                                       class="form-control form-control-sm" placeholder="H (mm)" step="0.1">
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'block':
                    html = `
                        <div class="row g-1">
                            <div class="col-4">
                                <input type="number" name="${isManual ? 'manual_dimension_L[]' : 'dimension_L[]'}" 
                                       class="form-control form-control-sm" placeholder="L (mm)" step="0.1">
                            </div>
                            <div class="col-4">
                                <input type="number" name="${isManual ? 'manual_dimension_W[]' : 'dimension_W[]'}" 
                                       class="form-control form-control-sm" placeholder="W (mm)" step="0.1">
                            </div>
                            <div class="col-4">
                                <input type="number" name="${isManual ? 'manual_dimension_H[]' : 'dimension_H[]'}" 
                                       class="form-control form-control-sm" placeholder="H (mm)" step="0.1">
                            </div>
                        </div>
                    `;
                    break;
            }
            
            dimensionDiv.innerHTML = html;
        }

        // Function to format dimensions before form submission
        function formatDimensionsForSubmission() {
            // Process existing products
            document.querySelectorAll('.existing-entry').forEach((entry, index) => {
                const typeSelect = entry.querySelector('select[name="dimension_type[]"]');
                const type = typeSelect ? typeSelect.value : 'plate';
                const hiddenInput = entry.querySelector('.dimensions-hidden');
                
                let dimensions = '';
                
                switch(type) {
                    case 'plate':
                        const L = entry.querySelector('[name="dimension_L[]"]')?.value || '';
                        const W = entry.querySelector('[name="dimension_W[]"]')?.value || '';
                        const T = entry.querySelector('[name="dimension_T[]"]')?.value || '';
                        dimensions = `${L} x ${W} x ${T}`;
                        break;
                        
                    case 'pipe':
                        const L_p = entry.querySelector('[name="dimension_L[]"]')?.value || '';
                        const OD = entry.querySelector('[name="dimension_OD[]"]')?.value || '';
                        const ID = entry.querySelector('[name="dimension_ID[]"]')?.value || '';
                        const T_p = entry.querySelector('[name="dimension_T[]"]')?.value || '';
                        dimensions = `${L_p} x ${OD} x ${ID} x ${T_p}`;
                        break;
                        
                    case 't-joint':
                        const L_t = entry.querySelector('[name="dimension_L[]"]')?.value || '';
                        const W_t = entry.querySelector('[name="dimension_W[]"]')?.value || '';
                        const T_t = entry.querySelector('[name="dimension_T[]"]')?.value || '';
                        const H_t = entry.querySelector('[name="dimension_H[]"]')?.value || '';
                        dimensions = `${L_t} x ${W_t} x ${T_t} x ${H_t}`;
                        break;
                        
                    case 'block':
                        const L_b = entry.querySelector('[name="dimension_L[]"]')?.value || '';
                        const W_b = entry.querySelector('[name="dimension_W[]"]')?.value || '';
                        const H_b = entry.querySelector('[name="dimension_H[]"]')?.value || '';
                        dimensions = `${L_b} x ${W_b} x ${H_b}`;
                        break;
                }
                
                if (hiddenInput) {
                    hiddenInput.value = dimensions.trim();
                }
            });
            
            // Process manual products
            document.querySelectorAll('.manual-entry').forEach((entry, index) => {
                const typeSelect = entry.querySelector('select[name="manual_dimension_type[]"]');
                const type = typeSelect ? typeSelect.value : 'plate';
                const hiddenInput = entry.querySelector('.dimensions-hidden');
                
                let dimensions = '';
                
                switch(type) {
                    case 'plate':
                        const L = entry.querySelector('[name="manual_dimension_L[]"]')?.value || '';
                        const W = entry.querySelector('[name="manual_dimension_W[]"]')?.value || '';
                        const T = entry.querySelector('[name="manual_dimension_T[]"]')?.value || '';
                        dimensions = `${L} x ${W} x ${T}`;
                        break;
                        
                    case 'pipe':
                        const L_p = entry.querySelector('[name="manual_dimension_L[]"]')?.value || '';
                        const OD = entry.querySelector('[name="manual_dimension_OD[]"]')?.value || '';
                        const ID = entry.querySelector('[name="manual_dimension_ID[]"]')?.value || '';
                        const T_p = entry.querySelector('[name="manual_dimension_T[]"]')?.value || '';
                        dimensions = `${L_p} x ${OD} x ${ID} x ${T_p}`;
                        break;
                        
                    case 't-joint':
                        const L_t = entry.querySelector('[name="manual_dimension_L[]"]')?.value || '';
                        const W_t = entry.querySelector('[name="manual_dimension_W[]"]')?.value || '';
                        const T_t = entry.querySelector('[name="manual_dimension_T[]"]')?.value || '';
                        const H_t = entry.querySelector('[name="manual_dimension_H[]"]')?.value || '';
                        dimensions = `${L_t} x ${W_t} x ${T_t} x ${H_t}`;
                        break;
                        
                    case 'block':
                        const L_b = entry.querySelector('[name="manual_dimension_L[]"]')?.value || '';
                        const W_b = entry.querySelector('[name="manual_dimension_W[]"]')?.value || '';
                        const H_b = entry.querySelector('[name="manual_dimension_H[]"]')?.value || '';
                        dimensions = `${L_b} x ${W_b} x ${H_b}`;
                        break;
                }
                
                if (hiddenInput) {
                    hiddenInput.value = dimensions.trim();
                }
            });
        }

        function calcTotal() {
            let subtotal = 0;
            const container = document.getElementById('productContainer');
            
            // Calc all rows
            const entries = container.querySelectorAll('.product-entry');
            entries.forEach(entry => {
                const qty = parseFloat(entry.querySelector('.qty').value) || 0;
                const price = parseFloat(entry.querySelector('.price').value) || 0;
                subtotal += (qty * price);
            });

            // Calc Shipping
            const method = document.getElementById('shipping_method').value;
            let shipping = 10;
            if(method === 'Express') shipping = 25;
            if(method === 'Overnight') shipping = 50;
            if(method === 'Pickup') shipping = 0;

            const tax = subtotal * 0.10;
            const total = subtotal + tax + shipping;

            document.getElementById('displaySubtotal').innerText = '₹' + subtotal.toFixed(2);
            document.getElementById('displayTax').innerText = '₹' + tax.toFixed(2);
            document.getElementById('displayShipping').innerText = '₹' + shipping.toFixed(2);
            document.getElementById('displayTotal').innerText = '₹' + total.toFixed(2);
        }

        // Order Management
        function updateOrderStatus(orderId) {
            document.getElementById('updateOrderId').value = orderId;
            const modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
            modal.show();
        }

        function confirmDelete(orderId) {
            document.getElementById('deleteOrderId').value = orderId;
            document.getElementById('deleteOrderNumber').textContent = orderId;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }

        // Form Validation
        document.getElementById('orderForm')?.addEventListener('submit', function(e) {
            // Format dimensions before submission
            formatDimensionsForSubmission();
            
            const customerSelect = this.querySelector('select[name="customer_id"]');
            const poDateInput = this.querySelector('input[name="po_date"]');
            const productEntries = this.querySelectorAll('.product-entry');
            
            let isValid = true;

            // Validate main form
            if (!customerSelect.value) {
                customerSelect.classList.add('is-invalid');
                isValid = false;
            } else {
                customerSelect.classList.remove('is-invalid');
            }

            if (!poDateInput.value) {
                poDateInput.classList.add('is-invalid');
                isValid = false;
            } else {
                poDateInput.classList.remove('is-invalid');
            }

            // Validate product entries
            let hasValidProducts = false;
            productEntries.forEach(entry => {
                const nameInput = entry.querySelector('input[name="manual_product_name[]"], select[name="product_sno[]"]');
                const qtyInput = entry.querySelector('.qty');
                const priceInput = entry.querySelector('.price');

                if (nameInput && nameInput.value && qtyInput.value > 0 && priceInput.value > 0) {
                    hasValidProducts = true;
                    nameInput.classList.remove('is-invalid');
                    qtyInput.classList.remove('is-invalid');
                    priceInput.classList.remove('is-invalid');
                } else if (nameInput && (nameInput.value || qtyInput.value > 0 || priceInput.value > 0)) {
                    // Partial entry - show errors
                    if (!nameInput.value) nameInput.classList.add('is-invalid');
                    if (qtyInput.value <= 0) qtyInput.classList.add('is-invalid');
                    if (priceInput.value <= 0) priceInput.classList.add('is-invalid');
                    isValid = false;
                }
            });

            if (!hasValidProducts) {
                alert('Please add at least one valid product to the order.');
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                alert('Please check the form for errors. All required fields must be filled correctly.');
            }
        });

        // Initialize calculations on page load
        document.addEventListener('DOMContentLoaded', function() {
            calcTotal();
            
            // Initialize dimension inputs for any existing items
            document.querySelectorAll('select[name="dimension_type[]"], select[name="manual_dimension_type[]"]').forEach(select => {
                updateDimensionInputs(select);
            });
            
            // Auto-hide success message after 5 seconds
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.opacity = '0';
                    setTimeout(() => successAlert.remove(), 500);
                }, 5000);
            }
        });

        // Mobile sidebar toggle (if needed)
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.toggle('mobile-open');
            }
        }
    </script>
</body>
</html>