<?php
session_start();
require_once 'functions.php';

// Get data for dashboard from MySQL
$customers = getCustomers();
$products = getProducts();
$orders = getOrders();

// Calculate metrics
$totalCustomers = count($customers);
$totalProducts = count($products);
$totalOrders = count($orders);

// Calculate today's orders
$today = date('Y-m-d');
$todayOrders = 0;
foreach ($orders as $order) {
    if (isset($order['po_date']) && $order['po_date'] === $today) {
        $todayOrders++;
    }
}

// Calculate orders by status
$ordersByStatus = [
    'pending' => 0,
    'sourcingmaterial' => 0,
    'inproduction' => 0,
    'readyforqc' => 0,
    'qccompleted' => 0,
    'packaging' => 0,
    'readyfordispatch' => 0,
    'shipped' => 0
];

foreach ($orders as $order) {
    if (isset($order['status'])) {
        $status = strtolower(str_replace(' ', '', $order['status']));
        if (isset($ordersByStatus[$status])) {
            $ordersByStatus[$status]++;
        }
    }
}

// Get recent customers (last 5)
$recentCustomers = array_slice(array_reverse($customers), 0, 5);
// Get recent orders (last 8)
$recentOrders = array_slice(array_reverse($orders), 0, 8);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Dashboard - Alphasonix</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Custom CSS -->
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
        
        .metric-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            border: none;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-shadow-hover);
        }
        
        .metric-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        
        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0;
            color: var(--dark);
        }
        
        .metric-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }
        
        .pipeline-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.25rem;
            box-shadow: var(--card-shadow);
            border: none;
            transition: all 0.3s ease;
            text-align: center;
            height: 100%;
        }
        
        .pipeline-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--card-shadow-hover);
        }
        
        .pipeline-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        
        .pipeline-count {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--dark);
        }
        
        .pipeline-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray);
            margin-bottom: 0;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .content-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: var(--card-shadow);
            border: none;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card-header-custom {
            background: white;
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
        }
        
        .card-body-custom {
            padding: 1.5rem;
        }
        
        .customer-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .customer-item:last-child {
            border-bottom: none;
        }
        
        .customer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            background: var(--light);
            border-radius: 0.5rem;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.2s ease;
            margin-bottom: 0.5rem;
        }
        
        .action-btn:hover {
            background: var(--primary);
            color: white;
            text-decoration: none;
        }
        
        .action-icon {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }
        
        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .table-custom {
            margin-bottom: 0;
        }
        
        .table-custom thead th {
            background-color: var(--light);
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            color: var(--gray);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.75rem 1rem;
        }
        
        .table-custom tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .table-custom tbody tr:last-child td {
            border-bottom: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--gray);
        }
        
        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--light-gray);
        }
        
        /* Status colors */
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-sourcingmaterial { background-color: #cce7ff; color: #004085; }
        .status-inproduction { background-color: #ffeaa7; color: #856404; }
        .status-readyforqc { background-color: #d1ecf1; color: #0c5460; }
        .status-qccompleted { background-color: #d4edda; color: #155724; }
        .status-packaging { background-color: #e2e3e5; color: #383d41; }
        .status-readyfordispatch { background-color: #f8d7da; color: #721c24; }
        .status-shipped { background-color: #d1e7dd; color: #0f5132; }
        
        /* Metric icon colors */
        .metric-customers { background-color: rgba(67, 97, 238, 0.1); color: var(--primary); }
        .metric-products { background-color: rgba(76, 201, 240, 0.1); color: var(--success); }
        .metric-orders { background-color: rgba(72, 149, 239, 0.1); color: var(--info); }
        .metric-today { background-color: rgba(247, 37, 133, 0.1); color: var(--warning); }
        
        /* Pipeline icon colors */
        .pipeline-pending { background-color: rgba(255, 243, 205, 0.3); color: #856404; }
        .pipeline-sourcingmaterial { background-color: rgba(204, 231, 255, 0.3); color: #004085; }
        .pipeline-inproduction { background-color: rgba(255, 234, 167, 0.3); color: #856404; }
        .pipeline-readyforqc { background-color: rgba(209, 236, 241, 0.3); color: #0c5460; }
        .pipeline-qccompleted { background-color: rgba(212, 237, 218, 0.3); color: #155724; }
        .pipeline-packaging { background-color: rgba(226, 227, 229, 0.3); color: #383d41; }
        .pipeline-readyfordispatch { background-color: rgba(248, 215, 218, 0.3); color: #721c24; }
        .pipeline-shipped { background-color: rgba(209, 231, 221, 0.3); color: #0f5132; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container-fluid py-4">
            <!-- Header Section -->
            <div class="dashboard-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="h3 mb-2">
                            <i class="bi bi-graph-up me-2"></i>CRM Dashboard
                        </h1>
                        <p class="text-muted mb-0">Welcome back! Here's a snapshot of your business activity.</p>
                    </div>
                    <div class="text-end">
                        <div class="badge bg-light text-dark p-2">
                            <i class="bi bi-calendar3 me-1"></i> <?= date('l, F j, Y') ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Key Metrics Grid -->
            <div class="row g-3 mb-4">
                <div class="col-md-6 col-lg-3">
                    <div class="metric-card">
                        <div class="metric-icon metric-customers">
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                        <p class="metric-title">Total Customers</p>
                        <p class="metric-value" data-target="<?= $totalCustomers ?>">0</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="metric-card">
                        <div class="metric-icon metric-products">
                            <i class="bi bi-box-seam fs-4"></i>
                        </div>
                        <p class="metric-title">Total Products</p>
                        <p class="metric-value" data-target="<?= $totalProducts ?>">0</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="metric-card">
                        <div class="metric-icon metric-orders">
                            <i class="bi bi-receipt fs-4"></i>
                        </div>
                        <p class="metric-title">Total Orders</p>
                        <p class="metric-value" data-target="<?= $totalOrders ?>">0</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="metric-card">
                        <div class="metric-icon metric-today">
                            <i class="bi bi-calendar-check fs-4"></i>
                        </div>
                        <p class="metric-title">Today's Orders</p>
                        <p class="metric-value" data-target="<?= $todayOrders ?>">0</p>
                    </div>
                </div>
            </div>

            <!-- Order Status Pipeline -->
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="section-title">
                        <i class="bi bi-columns-gap"></i> Order Status Pipeline
                    </h2>
                    
                    <div class="row g-3">
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="pipeline-card">
                                <div class="pipeline-icon pipeline-pending">
                                    <i class="bi bi-clock fs-5"></i>
                                </div>
                                <p class="pipeline-count" data-target="<?= $ordersByStatus['pending'] ?>">0</p>
                                <p class="pipeline-title">Pending</p>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="pipeline-card">
                                <div class="pipeline-icon pipeline-sourcingmaterial">
                                    <i class="bi bi-search fs-5"></i>
                                </div>
                                <p class="pipeline-count" data-target="<?= $ordersByStatus['sourcingmaterial'] ?>">0</p>
                                <p class="pipeline-title">Sourcing</p>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="pipeline-card">
                                <div class="pipeline-icon pipeline-inproduction">
                                    <i class="bi bi-gear fs-5"></i>
                                </div>
                                <p class="pipeline-count" data-target="<?= $ordersByStatus['inproduction'] ?>">0</p>
                                <p class="pipeline-title">Production</p>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="pipeline-card">
                                <div class="pipeline-icon pipeline-readyforqc">
                                    <i class="bi bi-clipboard-check fs-5"></i>
                                </div>
                                <p class="pipeline-count" data-target="<?= $ordersByStatus['readyforqc'] ?>">0</p>
                                <p class="pipeline-title">QC Ready</p>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="pipeline-card">
                                <div class="pipeline-icon pipeline-qccompleted">
                                    <i class="bi bi-check-circle fs-5"></i>
                                </div>
                                <p class="pipeline-count" data-target="<?= $ordersByStatus['qccompleted'] ?>">0</p>
                                <p class="pipeline-title">QC Done</p>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="pipeline-card">
                                <div class="pipeline-icon pipeline-packaging">
                                    <i class="bi bi-box-seam fs-5"></i>
                                </div>
                                <p class="pipeline-count" data-target="<?= $ordersByStatus['packaging'] ?>">0</p>
                                <p class="pipeline-title">Packaging</p>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="pipeline-card">
                                <div class="pipeline-icon pipeline-readyfordispatch">
                                    <i class="bi bi-truck fs-5"></i>
                                </div>
                                <p class="pipeline-count" data-target="<?= $ordersByStatus['readyfordispatch'] ?>">0</p>
                                <p class="pipeline-title">Dispatch</p>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="pipeline-card">
                                <div class="pipeline-icon pipeline-shipped">
                                    <i class="bi bi-rocket-takeoff fs-5"></i>
                                </div>
                                <p class="pipeline-count" data-target="<?= $ordersByStatus['shipped'] ?>">0</p>
                                <p class="pipeline-title">Shipped</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="row">
                <!-- Recent Orders Section -->
                <div class="col-lg-8 mb-4">
                    <div class="content-card">
                        <div class="card-header-custom d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-clock-history me-2"></i>Recent Orders
                            </h5>
                            <span class="badge bg-light text-dark">Last 8</span>
                        </div>
                        <div class="card-body-custom p-0">
                            <div class="table-responsive">
                                <table class="table table-custom table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>PO Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Create customer map for lookup
                                        $customerMap = [];
                                        foreach ($customers as $customer) {
                                            $customerMap[$customer['id']] = $customer['name'];
                                        }
                                        
                                        if (empty($recentOrders)): ?>
                                            <tr>
                                                <td colspan="4">
                                                    <div class="empty-state">
                                                        <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                                                        <h5>No Orders Yet</h5>
                                                        <p class="mb-0">New orders will appear here automatically.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recentOrders as $order): ?>
                                                <tr>
                                                    <td>
                                                        <strong>#<?= htmlspecialchars($order['order_id'] ?? 'N/A') ?></strong>
                                                    </td>
                                                    <td>
                                                        <?= htmlspecialchars($customerMap[$order['customer_id']] ?? 'Unknown Customer') ?>
                                                    </td>
                                                    <td>
                                                        <?= htmlspecialchars(date("M j, Y", strtotime($order['po_date'] ?? 'N/A'))) ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        // Get status from order data
                                                        $status = $order['status'] ?? 'Pending';
                                                        // Create CSS class from status
                                                        $statusClass = 'status-badge status-' . strtolower(str_replace(' ', '', $status));
                                                        ?>
                                                        <span class="<?= $statusClass ?>"><?= htmlspecialchars($status) ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar Column: Customers & Actions -->
                <div class="col-lg-4">
                    <!-- Newest Customers Card -->
                    <div class="content-card mb-4">
                        <div class="card-header-custom">
                            <h5 class="mb-0">
                                <i class="bi bi-people me-2"></i>Newest Customers
                            </h5>
                        </div>
                        <div class="card-body-custom">
                            <div class="customers-list">
                                <?php if (empty($recentCustomers)): ?>
                                    <div class="empty-state">
                                        <div class="empty-icon"><i class="bi bi-people"></i></div>
                                        <h5>No Customers Found</h5>
                                        <p class="mb-0">Add customers to see them here.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recentCustomers as $customer): ?>
                                        <div class="customer-item">
                                            <div class="customer-avatar">
                                                <?= strtoupper(substr($customer['name'] ?? '?', 0, 1)) ?>
                                            </div>
                                            <div class="customer-info">
                                                <h6 class="mb-1"><?= htmlspecialchars($customer['name'] ?? 'Unknown Customer') ?></h6>
                                                <p class="text-muted small mb-0"><?= htmlspecialchars($customer['email'] ?? 'No email') ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions Card -->
                    <div class="content-card">
                        <div class="card-header-custom">
                            <h5 class="mb-0">
                                <i class="bi bi-lightning me-2"></i>Quick Actions
                            </h5>
                        </div>
                        <div class="card-body-custom">
                            <div class="quick-actions">
                                <a href="orders.php" class="action-btn">
                                    <span class="action-icon"><i class="bi bi-plus-circle"></i></span>
                                    <span>Create New Order</span>
                                </a>
                                <a href="customers.php" class="action-btn">
                                    <span class="action-icon"><i class="bi bi-person-plus"></i></span>
                                    <span>Add New Customer</span>
                                </a>
                                <a href="products.php" class="action-btn">
                                    <span class="action-icon"><i class="bi bi-box"></i></span>
                                    <span>Manage Products</span>
                                </a>
                                <a href="pipeline.php" class="action-btn">
                                    <span class="action-icon"><i class="bi bi-columns-gap"></i></span>
                                    <span>View Order Pipeline</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        /**
         * Animates a number from 0 to its target value using requestAnimationFrame for smoothness.
         * @param {HTMLElement} el The element containing the number.
         */
        function animateCounter(el) {
            const target = parseInt(el.dataset.target, 10);
            if (isNaN(target)) {
                // If data-target is not set, use the element's text content
                const staticTarget = parseInt(el.textContent, 10);
                 if (!isNaN(staticTarget)) {
                    el.dataset.target = staticTarget;
                    animateCounter(el);
                 }
                return;
            };

            const duration = 1500; // Animation duration in milliseconds
            let startTimestamp = null;

            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const currentValue = Math.floor(progress * target);
                el.textContent = currentValue.toLocaleString(); // Add commas for thousands
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            
            // Start the animation
            window.requestAnimationFrame(step);
        }

        // Apply animation to all counters with a data-target attribute or valid number
        const counters = document.querySelectorAll('.metric-value, .pipeline-count');
        counters.forEach(counter => {
            // Set data-target from text content if not already present
            if (!counter.hasAttribute('data-target')) {
                counter.dataset.target = counter.textContent;
            }
            counter.textContent = '0'; // Set initial text to 0 before animation
            animateCounter(counter);
        });
    });
    </script>
</body>
</html>