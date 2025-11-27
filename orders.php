<?php
session_start();
require_once 'functions.php';

// --- STAGE 1: Handle Order Creation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    $customerId = sanitize_input($_POST['customer_id']);
    $poDate = sanitize_input($_POST['po_date']);
    $deliveryDate = sanitize_input($_POST['delivery_date']);

    $orderId = getNextOrderId();
    $products = getProducts(); // Get from database
    $orderItems = [];
    $uploadErrors = [];

    // Process existing products
    if (isset($_POST['product_sno']) && is_array($_POST['product_sno'])) {
        foreach ($_POST['product_sno'] as $index => $productSNo) {
            if (!empty($productSNo)) {
                $product = getProductBySerialNo($productSNo);
                if ($product) {
                    $quantity = (int) ($_POST['quantity'][$index] ?? 1);
                    if ($quantity > 0) {
                        $dimensions = sanitize_input($_POST['product_dimensions'][$index] ?? '');
                        $description = sanitize_input($_POST['product_description'][$index] ?? '');
                        
                        // Handle file upload for existing products
                        $drawingData = handleItemFileUpload($_FILES['drawing_file_existing'], $index, $orderId, $productSNo);

                        if ($drawingData['error']) {
                            $uploadErrors[] = "Error for product '{$product['name']}': {$drawingData['error']}";
                            continue;
                        }

                        $orderItems[] = createOrderItem(
                            $productSNo,
                            $product['name'],
                            !empty($dimensions) ? $dimensions : $product['dimensions'],
                            $description,
                            $quantity,
                            $drawingData
                        );
                    }
                } else {
                    $uploadErrors[] = "Product not found: {$productSNo}";
                }
            }
        }
    }

    // Process manual products
    if (isset($_POST['manual_product_name']) && is_array($_POST['manual_product_name'])) {
        foreach ($_POST['manual_product_name'] as $index => $manualName) {
            $manualName = sanitize_input($manualName);
            if (!empty($manualName)) {
                $manualQty = (int) ($_POST['manual_quantity'][$index] ?? 1);
                if ($manualQty > 0) {
                    $manualSNo = generateManualProductId();
                    $manualDimensions = sanitize_input($_POST['manual_product_dimensions'][$index] ?? '');
                    $manualDescription = sanitize_input($_POST['manual_product_description'][$index] ?? '');
                    
                    // Handle file upload for manual products
                    $drawingData = handleItemFileUpload($_FILES['drawing_file_manual'], $index, $orderId, $manualSNo);

                    if ($drawingData['error']) {
                        $uploadErrors[] = "Error for custom product '{$manualName}': {$drawingData['error']}";
                        continue;
                    }
                    
                    $orderItems[] = createOrderItem(
                        $manualSNo,
                        $manualName,
                        $manualDimensions,
                        $manualDescription,
                        $manualQty,
                        $drawingData
                    );
                }
            }
        }
    }
    
    
    // Check for upload errors before proceeding
    if (!empty($uploadErrors)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => implode('<br>', $uploadErrors)];
    } elseif (!empty($customerId) && !empty($orderItems) && !empty($poDate)) {
        $newOrder = [
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'po_date' => $poDate,
            'delivery_date' => $deliveryDate,
            'due_date' => '',
            'status' => 'Pending',
            'drawing_filename' => '',
            'inspection_reports' => '[]'
        ];
        
        if (addOrder($newOrder)) {
            // Add order items to database
            foreach ($orderItems as $item) {
                $success = addOrderItem($orderId, $item);
                if (!$success) {
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error adding order items to database.'];
                    header('Location: orders.php');
                    exit;
                }
            }
            
            logChange($orderId, 'Order Creation', "New order created with " . count($orderItems) . " items");
            logActivity("Order Created", "Order {$orderId} created for customer {$customerId}");
            
            $_SESSION['message'] = ['type' => 'success', 'text' => "Order {$orderId} created successfully!"];
            header('Location: orders.php');
            exit;
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error creating order in database.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Please select a client and add at least one valid product.'];
    }
}

// --- Get data for display ---
$customers = getCustomers(); // From database
$products = getProducts();   // From database  
$orders = getOrders();       // From database
$today = date('Y-m-d');

// Calculate stats for dashboard
$totalOrders = count($orders);
$totalCustomers = count($customers);
$totalProducts = count($products);

// Get recent orders for sidebar
$recentOrders = array_slice(array_reverse($orders), 0, 5);
$customerMap = array_column($customers, 'name', 'id');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - Alphasonix CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --info: #4895ef;
            --warning: #f72585;
            --danger: #e63946;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --border-color: #dee2e6;
            --card-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --card-shadow-hover: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: #f5f7fb;
            color: #495057;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--card-shadow);
            border-left: 4px solid var(--primary);
        }
        
        .content-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: var(--card-shadow);
            border: none;
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .content-card:hover {
            box-shadow: var(--card-shadow-hover);
        }
        
        .card-header-custom {
            background: white;
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
        }
        
        .card-body-custom {
            padding: 1.5rem;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary);
            border-color: var(--secondary);
        }
        
        .stat-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.25rem;
            box-shadow: var(--card-shadow);
            border: none;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow-hover);
        }
        
        .product-entry {
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background: var(--light);
        }
        
        .product-entry-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .product-type-badge {
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .product-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .product-form-grid .full-width {
            grid-column: 1 / -1;
        }
        
        .file-upload-wrapper {
            position: relative;
        }
        
        .file-upload-label {
            display: block;
            border: 2px dashed var(--border-color);
            border-radius: 0.5rem;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-upload-label:hover {
            border-color: var(--primary);
            background: rgba(67, 97, 238, 0.05);
        }
        
        .timeline-display {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            text-align: center;
        }
        
        .timeline-normal {
            background: rgba(76, 201, 240, 0.1);
            color: #0c5460;
            border: 1px solid rgba(76, 201, 240, 0.3);
        }
        
        .timeline-warning {
            background: rgba(247, 37, 133, 0.1);
            color: #721c24;
            border: 1px solid rgba(247, 37, 133, 0.3);
        }
        
        .timeline-urgent {
            background: rgba(230, 57, 70, 0.1);
            color: #721c24;
            border: 1px solid rgba(230, 57, 70, 0.3);
        }
        
        .timeline-overdue {
            background: rgba(230, 57, 70, 0.2);
            color: #721c24;
            border: 1px solid rgba(230, 57, 70, 0.5);
        }
        
        .recent-order-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .recent-order-item:last-child {
            border-bottom: none;
        }
        
        .order-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }
        
        .order-info {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .order-info strong {
            font-size: 0.875rem;
            color: var(--dark);
        }
        
        .order-info span {
            font-size: 0.75rem;
            color: var(--gray);
        }
        
        .order-status {
            margin-left: auto;
        }
        
        .quick-actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }
        
        .quick-action-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s ease;
        }
        
        .quick-action-card:hover {
            background: var(--primary);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
        }
        
        .action-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        /* Dimension Selection Styles */
        .dimension-type-selector {
            margin-bottom: 1rem;
        }
        
        .dimension-inputs {
            display: none;
        }
        
        .dimension-inputs.active {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 0.5rem;
        }
        
        .dimension-input-group {
            display: flex;
            flex-direction: column;
        }
        
        .dimension-input-group label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray);
            margin-bottom: 0.25rem;
        }
        
        .dimension-result {
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: var(--light);
            border-radius: 0.375rem;
            font-family: monospace;
            font-size: 0.875rem;
            color: var(--dark);
            border: 1px solid var(--border-color);
        }
        
        .dimension-result:empty {
            display: none;
        }
        
        .file-preview-container {
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: var(--light);
            border-radius: 0.375rem;
        }
        
        .selected-file-info {
            display: flex;
            align-items: center;
        }
        
        .file-icon {
            margin-right: 0.5rem;
            font-size: 1.5rem;
        }
        
        .file-details {
            display: flex;
            flex-direction: column;
        }
        
        .file-name {
            font-weight: 500;
            color: var(--dark);
        }
        
        .file-size {
            color: var(--gray);
            font-size: 0.875rem;
        }
        
        .file-upload-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }
        
        .file-upload-content i {
            font-size: 2rem;
            color: var(--gray);
        }
        
        .file-upload-text {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .file-upload-title {
            font-weight: 500;
            color: var(--dark);
        }
        
        .file-upload-subtitle {
            font-size: 0.875rem;
            color: var(--gray);
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid py-4">
            <?php if (isset($_SESSION['message'])): ?>
                <?php 
                $messageType = $_SESSION['message']['type'];
                $alertClass = $messageType === 'success' ? 'alert-success' : 'alert-danger';
                echo "<div class='alert $alertClass alert-dismissible fade show' role='alert'>" .
                    $_SESSION['message']['text'] .
                    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' .
                    "</div>";
                unset($_SESSION['message']);
                ?>
            <?php endif; ?>

            <div class="dashboard-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="h3 mb-2">
                            <i class="bi bi-cart-plus me-2"></i>Order Management
                        </h1>
                        <p class="text-muted mb-0">Create and manage customer orders with real-time tracking</p>
                    </div>
                    <div class="text-end">
                        <div class="badge bg-light text-dark p-2">
                            <i class="bi bi-calendar3 me-1"></i> <?= date('l, F j, Y') ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <div class="bg-primary bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-file-text fs-4 text-primary"></i>
                                </div>
                            </div>
                            <div>
                                <h5 class="mb-1">Total Orders</h5>
                                <h2 class="mb-0 text-primary"><?= $totalOrders ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <div class="bg-success bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-people fs-4 text-success"></i>
                                </div>
                            </div>
                            <div>
                                <h5 class="mb-1">Active Clients</h5>
                                <h2 class="mb-0 text-success"><?= $totalCustomers ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <div class="bg-info bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-box-seam fs-4 text-info"></i>
                                </div>
                            </div>
                            <div>
                                <h5 class="mb-1">Products</h5>
                                <h2 class="mb-0 text-info"><?= $totalProducts ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="content-card">
                        <div class="card-header-custom">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-plus-circle me-2"></i>Create New Order
                                </h5>
                                <span class="badge bg-primary">Step 1: Order Details</span>
                            </div>
                        </div>
                        <div class="card-body-custom">
                            <form action="orders.php" method="post" enctype="multipart/form-data">
                                <div class="mb-4">
                                    <h6 class="mb-3">
                                        <i class="bi bi-info-circle me-2"></i>Order Information
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="customer_id" class="form-label">Client Name</label>
                                            <select name="customer_id" id="customer_id" class="form-select" required>
                                                <option value="">-- Choose a Client --</option>
                                                <?php foreach ($customers as $customer): ?>
                                                    <option value="<?= htmlspecialchars($customer['id']) ?>">
                                                        <?= htmlspecialchars($customer['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="po_date" class="form-label">PO Date</label>
                                            <input type="date" id="po_date" name="po_date" class="form-control" value="<?= $today ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="delivery_date" class="form-label">Delivery Date</label>
                                            <input type="date" id="delivery_date" name="delivery_date" class="form-control"
                                                onchange="calculateDaysLeft()">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Timeline</label>
                                            <div id="days_remaining" class="timeline-display timeline-normal">
                                                <span class="timeline-label">Not set</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <h6 class="mb-3">
                                        <i class="bi bi-box-seam me-2"></i>Products & Items
                                    </h6>
                                    
                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="mb-0">
                                                <i class="bi bi-box me-2"></i>Catalog Products
                                            </h6>
                                            <button type="button" id="addExistingProductBtn" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-plus me-1"></i>Add Product
                                            </button>
                                        </div>
                                        <div id="existingProductsContainer">
                                            <?= renderProductEntry('existing', $products); ?>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="mb-0">
                                                <i class="bi bi-pencil me-2"></i>Custom Products
                                            </h6>
                                            <button type="button" id="addManualProductBtn" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-plus me-1"></i>Add Custom
                                            </button>
                                        </div>
                                        <div id="manualProductsContainer">
                                            <?= renderProductEntry('manual'); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" name="create_order" class="btn btn-primary">
                                        <i class="bi bi-rocket-takeoff me-2"></i>
                                        Create Order & Start Pipeline
                                    </button>
                                    <button type="reset" class="btn btn-secondary">
                                        <i class="bi bi-arrow-clockwise me-2"></i>
                                        Reset Form
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="content-card mb-4">
                        <div class="card-header-custom">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-clock-history me-2"></i>Recent Orders
                                </h5>
                                <span class="badge bg-primary">Last 5</span>
                            </div>
                        </div>
                        <div class="card-body-custom p-0">
                            <div class="recent-orders-list">
                                <?php if (empty($recentOrders)): ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-1"></i>
                                        <p class="mt-2 mb-0">No Orders Yet</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recentOrders as $order): ?>
                                        <div class="recent-order-item">
                                            <div class="order-avatar">
                                                <?= strtoupper(substr($customerMap[$order['customer_id']] ?? 'C', 0, 1)) ?>
                                            </div>
                                            <div class="order-info">
                                                <strong>#<?= htmlspecialchars($order['order_id']) ?></strong>
                                                <span><?= htmlspecialchars($customerMap[$order['customer_id']] ?? 'Unknown') ?></span>
                                            </div>
                                            <div class="order-status">
                                                <span class="badge bg-secondary"><?= htmlspecialchars($order['status']) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="content-card">
                        <div class="card-header-custom">
                            <h5 class="mb-0">
                                <i class="bi bi-lightning me-2"></i>Quick Actions
                            </h5>
                        </div>
                        <div class="card-body-custom">
                            <div class="quick-actions-grid">
                                <a href="pipeline.php" class="quick-action-card">
                                    <div class="action-icon"><i class="bi bi-diagram-3"></i></div>
                                    <span>View Pipeline</span>
                                </a>
                                <a href="customers.php" class="quick-action-card">
                                    <div class="action-icon"><i class="bi bi-people"></i></div>
                                    <span>Manage Clients</span>
                                </a>
                                <a href="products.php" class="quick-action-card">
                                    <div class="action-icon"><i class="bi bi-box"></i></div>
                                    <span>Products</span>
                                </a>
                                <a href="home.php" class="quick-action-card">
                                    <div class="action-icon"><i class="bi bi-speedometer"></i></div>
                                    <span>Dashboard</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function calculateDaysLeft() {
        const poDateEl = document.getElementById('po_date');
        const deliveryDateEl = document.getElementById('delivery_date');
        const daysRemainingElement = document.getElementById('days_remaining');

        if (!poDateEl.value || !deliveryDateEl.value) {
            daysRemainingElement.innerHTML = '<span class="timeline-label">Not set</span>';
            daysRemainingElement.className = "timeline-display timeline-normal";
            return;
        }

        const poDate = new Date(poDateEl.value);
        const deliveryDate = new Date(deliveryDateEl.value);
        
        const diffTime = deliveryDate.getTime() - poDate.getTime();
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        let timelineClass = 'timeline-normal';
        let icon = '📅';

        if (diffDays < 0) {
            timelineClass = 'timeline-overdue';
            icon = '⏰';
            daysRemainingElement.innerHTML = `<span class="timeline-icon">${icon}</span><span class="timeline-label">Overdue</span>`;
        } else if (diffDays < 7) {
            timelineClass = 'timeline-urgent';
            icon = '⚡';
            daysRemainingElement.innerHTML = `<span class="timeline-icon">${icon}</span><span class="timeline-label">${diffDays} day${diffDays !== 1 ? 's' : ''}</span>`;
        } else if (diffDays < 14) {
            timelineClass = 'timeline-warning';
            icon = '📋';
            daysRemainingElement.innerHTML = `<span class="timeline-icon">${icon}</span><span class="timeline-label">${diffDays} days</span>`;
        } else {
            timelineClass = 'timeline-normal';
            icon = '✅';
            daysRemainingElement.innerHTML = `<span class="timeline-icon">${icon}</span><span class="timeline-label">${diffDays} days</span>`;
        }

        daysRemainingElement.className = `timeline-display ${timelineClass}`;
    }

    function handleDimensionTypeChange(selectElement) {
        const entry = selectElement.closest('.product-entry');
        const dimensionInputsContainers = entry.querySelectorAll('.dimension-inputs');
        const selectedType = selectElement.value;
        const dimensionResult = entry.querySelector('.dimension-result');
        
        // Hide all dimension input containers
        dimensionInputsContainers.forEach(container => {
            container.classList.remove('active');
        });
        
        // Show the selected type's inputs
        if (selectedType) {
            const activeContainer = entry.querySelector(`.dimension-inputs[data-type="${selectedType}"]`);
            if (activeContainer) {
                activeContainer.classList.add('active');
            }
        }
        
        // Clear dimension result
        dimensionResult.textContent = '';
        
        // Clear the hidden dimension input
        const hiddenDimensionInput = entry.querySelector('.product-dimensions-hidden');
        if (hiddenDimensionInput) {
            hiddenDimensionInput.value = '';
        }
    }

    function updateDimensionString(entry) {
        const selectedType = entry.querySelector('.dimension-type-select').value;
        const dimensionResult = entry.querySelector('.dimension-result');
        const hiddenDimensionInput = entry.querySelector('.product-dimensions-hidden');
        
        if (!selectedType) {
            dimensionResult.textContent = '';
            hiddenDimensionInput.value = '';
            return;
        }
        
        let dimensionString = '';
        const activeContainer = entry.querySelector(`.dimension-inputs[data-type="${selectedType}"].active`);
        
        if (activeContainer) {
            const inputs = activeContainer.querySelectorAll('input[type="number"]');
            const values = Array.from(inputs).map(input => input.value || '0');
            
            switch(selectedType) {
                case 'plate':
                    if (values[0] && values[1] && values[2]) {
                        dimensionString = `${values[0]} × ${values[1]} × ${values[2]}`;
                    }
                    break;
                case 'pipe':
                    if (values[0] && values[1] && values[2] && values[3]) {
                        dimensionString = `L: ${values[0]} × OD: ${values[1]} × ID: ${values[2]} × T: ${values[3]}`;
                    }
                    break;
                case 't-joint':
                    if (values[0] && values[1] && values[2] && values[3]) {
                        dimensionString = `${values[0]} × ${values[1]} × ${values[2]} × ${values[3]}`;
                    }
                    break;
                case 'custom':
                    const customInput = activeContainer.querySelector('input[type="text"]');
                    dimensionString = customInput.value;
                    break;
            }
        }
        
        dimensionResult.textContent = dimensionString || 'Please fill in all dimensions';
        hiddenDimensionInput.value = dimensionString;
    }

    function updateDimensions(selectElement) {
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const entry = selectElement.closest('.product-entry');
        const dimensionTypeSelect = entry.querySelector('.dimension-type-select');
        
        if (selectedOption && selectedOption.value) {
            // You can set a default dimension type based on the product if needed
            // For now, just reset the dimension selector
            if (dimensionTypeSelect) {
                dimensionTypeSelect.value = '';
                handleDimensionTypeChange(dimensionTypeSelect);
            }
        }
    }

    function previewSelectedFile(inputElement) {
        const file = inputElement.files[0];
        const formGroup = inputElement.closest('.form-group');
        const previewContainer = formGroup.querySelector('.file-preview-container');

        if (file) {
            const fileNameEl = previewContainer.querySelector('.file-name');
            const fileSizeEl = previewContainer.querySelector('.file-size');
            const fileIconEl = previewContainer.querySelector('.file-icon');

            fileNameEl.textContent = file.name;
            fileSizeEl.textContent = `(${(file.size / 1024).toFixed(1)} KB)`;

            const iconMap = { 'image': '🖼️', 'pdf': '📄', 'dwg': '📐' };
            let icon = '📎';

            if (file.type.startsWith('image/')) icon = iconMap.image;
            else if (file.type === 'application/pdf') icon = iconMap.pdf;
            else if (file.name.toLowerCase().endsWith('.dwg')) icon = iconMap.dwg;
            
            fileIconEl.textContent = icon;
            previewContainer.style.display = 'flex';
        } else {
            previewContainer.style.display = 'none';
        }
    }

    document.getElementById('addExistingProductBtn').addEventListener('click', function () {
        const container = document.getElementById('existingProductsContainer');
        const firstEntry = container.querySelector('.product-entry');
        if (!firstEntry) return;
        const newEntry = firstEntry.cloneNode(true);

        const newId = `file_existing_${Date.now()}`;
        newEntry.querySelector('input[type="file"]').id = newId;
        newEntry.querySelector('label.file-upload-label').setAttribute('for', newId);

        // Clear the new entry
        newEntry.querySelector('select').selectedIndex = 0;
        newEntry.querySelector('.dimension-type-select').selectedIndex = 0;
        newEntry.querySelectorAll('.dimension-inputs').forEach(container => {
            container.classList.remove('active');
            container.querySelectorAll('input').forEach(input => input.value = '');
        });
        newEntry.querySelector('.dimension-result').textContent = '';
        newEntry.querySelector('.product-dimensions-hidden').value = '';
        newEntry.querySelector('textarea').value = '';
        newEntry.querySelector('input[type="file"]').value = null;
        newEntry.querySelector('.file-preview-container').style.display = 'none';

        container.appendChild(newEntry);
    });

    document.getElementById('addManualProductBtn').addEventListener('click', function () {
        const container = document.getElementById('manualProductsContainer');
        const firstEntry = container.querySelector('.product-entry');
        if (!firstEntry) return;
        
        // Count existing entries to create sequential numbering
        const existingEntries = container.querySelectorAll('.product-entry').length;
        const newEntryNumber = existingEntries + 1;
        
        const newEntry = firstEntry.cloneNode(true);
        
        // Update title with numbering
        const titleBadge = newEntry.querySelector('.product-type-badge');
        titleBadge.innerHTML = `<i class="bi bi-pencil"></i> Custom Item #${newEntryNumber}`;

        // Generate a unique ID for the file input
        const newId = `file_manual_${Date.now()}`;
        newEntry.querySelector('input[type="file"]').id = newId;
        newEntry.querySelector('label.file-upload-label').setAttribute('for', newId);

        // Clear the new entry
        newEntry.querySelector('input[name^="manual_product_name"]').value = '';
        newEntry.querySelector('.dimension-type-select').selectedIndex = 0;
        newEntry.querySelectorAll('.dimension-inputs').forEach(container => {
            container.classList.remove('active');
            container.querySelectorAll('input').forEach(input => input.value = '');
        });
        newEntry.querySelector('.dimension-result').textContent = '';
        newEntry.querySelector('.product-dimensions-hidden').value = '';
        newEntry.querySelector('textarea').value = '';
        newEntry.querySelector('input[type="file"]').value = null;
        newEntry.querySelector('.file-preview-container').style.display = 'none';

        container.appendChild(newEntry);
        
        // Update numbering for all entries to ensure they're sequential
        updateManualProductNumbering();
    });

    // Function to update numbering of all manual product entries
    function updateManualProductNumbering() {
        const container = document.getElementById('manualProductsContainer');
        const entries = container.querySelectorAll('.product-entry');
        
        entries.forEach((entry, index) => {
            const titleBadge = entry.querySelector('.product-type-badge');
            titleBadge.innerHTML = `<i class="bi bi-pencil"></i> Custom Item #${index + 1}`;
        });
    }

    function removeProduct(button) {
        const entry = button.closest('.product-entry');
        const container = entry.parentElement;
        const isManualContainer = container.id === 'manualProductsContainer';

        if (container.querySelectorAll('.product-entry').length > 1) {
            entry.remove();
            // Update numbering if this is a manual product
            if (isManualContainer) {
                updateManualProductNumbering();
            }
        } else {
            // If it's the last one, just clear it
            const inputs = entry.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                if (input.type === 'file') {
                    input.value = null;
                } else if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                } else {
                    input.value = '';
                }
            });
            
            // Hide dimension inputs
            const dimensionInputs = entry.querySelectorAll('.dimension-inputs');
            dimensionInputs.forEach(container => {
                container.classList.remove('active');
            });
            
            // Clear dimension result
            const dimensionResult = entry.querySelector('.dimension-result');
            if (dimensionResult) {
                dimensionResult.textContent = '';
            }
            
            entry.querySelector('.file-preview-container').style.display = 'none';
            
            // Reset numbering if it's a manual product
            if (isManualContainer) {
                const titleBadge = entry.querySelector('.product-type-badge');
                titleBadge.innerHTML = '<i class="bi bi-pencil"></i> Custom Item #1';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('delivery_date').addEventListener('change', calculateDaysLeft);
        document.getElementById('po_date').addEventListener('change', calculateDaysLeft);
        calculateDaysLeft();

        document.querySelectorAll('.product-select').forEach(select => {
            updateDimensions(select);
        });

        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });
    </script>
</body>
</html>

<?php
/**
 * Renders the HTML for a single product entry form.
 */
function renderProductEntry($type, $products = null) {
    $isExisting = ($type === 'existing');
    $prefix = $isExisting ? '' : 'manual_';
    $class = $isExisting ? 'existing-product-entry' : 'manual-product-entry';
    $uniqueFileId = 'file_' . $type . '_' . uniqid();
    $uniqueDimensionId = 'dim_' . $type . '_' . uniqid();

    ob_start();
    ?>
    <div class="product-entry <?= $class ?>">
        <div class="product-entry-header">
            <div class="product-type-badge">
                <i class="bi <?= $isExisting ? 'bi-box' : 'bi-pencil' ?>"></i>
                <?= $isExisting ? 'Catalog Item' : 'Custom Item #1' ?>
            </div>
            <button type="button" class="btn btn-danger btn-sm remove-product-btn" onclick="removeProduct(this)">
                <i class="bi bi-x"></i>
            </button>
        </div>
        <div class="product-form-grid">
            <?php if ($isExisting): ?>
                <div class="form-group">
                    <label class="form-label">Product Selection</label>
                    <select name="product_sno[]" class="form-select product-select" onchange="updateDimensions(this)">
                        <option value="">-- Select Product --</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= htmlspecialchars($product['serial_no']) ?>"
                                data-dimensions="<?= htmlspecialchars($product['dimensions']) ?>">
                                <?= htmlspecialchars($product['name']) ?> (<?= htmlspecialchars($product['serial_no']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <div class="form-group">
                    <label class="form-label">Product Name</label>
                    <input type="text" name="manual_product_name[]" placeholder="Enter product name" class="form-control">
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label class="form-label">Quantity</label>
                <input type="number" name="<?= $prefix ?>quantity[]" min="1" value="1" placeholder="Qty" class="form-control">
            </div>
            
            <div class="form-group full-width">
                <label class="form-label">Dimension Type</label>
                <select class="form-select dimension-type-select" onchange="handleDimensionTypeChange(this)">
                    <option value="">-- Select Dimension Type --</option>
                    <option value="plate">Plate (L × W × T)</option>
                    <option value="pipe">Pipe (L × OD × ID × T)</option>
                    <option value="t-joint">T-Joint (L × W × T × H)</option>
                    <option value="custom">Block/Custom</option>
                </select>
            </div>
            
            <!-- Plate Dimensions -->
            <div class="form-group full-width dimension-inputs" data-type="plate">
                <div class="dimension-input-group">
                    <label>Length (L)</label>
                    <input type="number" step="0.01" placeholder="0.00" class="form-control" 
                           onchange="updateDimensionString(this.closest('.product-entry'))">
                </div>
                <div class="dimension-input-group">
                    <label>Width (W)</label>
                    <input type="number" step="0.01" placeholder="0.00" class="form-control" 
                           onchange="updateDimensionString(this.closest('.product-entry'))">
                </div>
                <div class="dimension-input-group">
                    <label>Thickness (T)</label>
                    <input type="number" step="0.01" placeholder="0.00" class="form-control" 
                           onchange="updateDimensionString(this.closest('.product-entry'))">
                </div>
            </div>
            
            <!-- Pipe Dimensions -->
            <div class="form-group full-width dimension-inputs" data-type="pipe">
                <div class="dimension-input-group">
                    <label>Length (L)</label>
                    <input type="number" step="0.01" placeholder="0.00" class="form-control" 
                           onchange="updateDimensionString(this.closest('.product-entry'))">
                </div>
                <div class="dimension-input-group">
                    <label>Outer Dia (OD)</label>
                    <input type="number" step="0.01" placeholder="0.00" class="form-control" 
                           onchange="updateDimensionString(this.closest('.product-entry'))">
                </div>
                <div class="dimension-input-group">
                    <label>Inner Dia (ID)</label>
                    <input type="number" step="0.01" placeholder="0.00" class="form-control" 
                           onchange="updateDimensionString(this.closest('.product-entry'))">
                </div>
                <div class="dimension-input-group">
                    <label>Thickness (T)</label>
                    <input type="number" step="0.01" placeholder="0.00" class="form-control" 
                           onchange="updateDimensionString(this.closest('.product-entry'))">
                </div>
            </div>
            
            <!-- T-Joint Dimensions -->
            <div class="form-group full-width dimension-inputs" data-type="t-joint">
                <div class="dimension-input-group">
                    <label>Length (L)</label>
                    <input type="number" step="0.01" placeholder="0.00" class="form-control" 
                           onchange="updateDimensionString(this.closest('.product-entry'))">
                </div>
                <div class="dimension-input-group">
                    <label>Width (W)</label>
                    <input type="number" step="0.01" placeholder="0.00" class="form-control" 
                           onchange="updateDimensionString(this.closest('.product-entry'))">
                </div>
                <div class="dimension-input-group">
                    <label>Thickness (T)</label>
                    <input type="number" step="0.01" placeholder="0.00" class="form-control" 
                           onchange="updateDimensionString(this.closest('.product-entry'))">
                </div>
                <div class="dimension-input-group">
                    <label>Height (H)</label>
                    <input type="number" step="0.01" placeholder="0.00" class="form-control" 
                           onchange="updateDimensionString(this.closest('.product-entry'))">
                </div>
            </div>
            
            <!-- Custom Dimensions -->
            <div class="form-group full-width dimension-inputs" data-type="custom">
                <div class="dimension-input-group" style="grid-column: 1 / -1;">
                    <label>Custom Dimensions</label>
                    <input type="text" placeholder="Enter custom dimensions" class="form-control" 
                           onchange="updateDimensionString(this.closest('.product-entry'))">
                </div>
            </div>
            
            <!-- Hidden input to store the final dimension string -->
            <input type="hidden" name="<?= $prefix ?>product_dimensions[]" class="product-dimensions-hidden product-dimensions">
            
            <!-- Display the formatted dimensions -->
            <div class="form-group full-width">
                <div class="dimension-result"></div>
            </div>
            
            <div class="form-group full-width">
                <label class="form-label">Description</label>
                <textarea name="<?= $prefix ?>product_description[]" rows="2"
                    placeholder="<?= $isExisting ? 'Additional notes...' : 'Product description...' ?>"
                    class="form-control"></textarea>
            </div>
            
            <div class="form-group full-width">
                <label class="form-label">Drawing File (Optional)</label>
                <div class="file-upload-wrapper">
                    <input type="file" name="drawing_file_<?= $type ?>[]" class="form-control" 
                           id="<?= $uniqueFileId ?>" onchange="previewSelectedFile(this)" style="display: none;">
                    <label for="<?= $uniqueFileId ?>" class="file-upload-label">
                        <div class="file-upload-content">
                            <i class="bi bi-cloud-upload"></i>
                            <div class="file-upload-text">
                                <span class="file-upload-title">Click to upload drawing</span>
                                <span class="file-upload-subtitle">Any file type - Max 10MB</span>
                            </div>
                        </div>
                    </label>
                </div>
                <div class="file-preview-container" style="display:none;">
                    <div class="selected-file-info">
                        <span class="file-icon">📄</span>
                        <div class="file-details">
                            <span class="file-name"></span>
                            <span class="file-size"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>
