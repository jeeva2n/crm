<?php
session_start();
require_once 'functions.php';

// Get all orders and customers from database
$orders = getOrders();
$customers = getCustomers();
$customerMap = array_column($customers, 'name', 'id');

// Get order ID from query string for detailed view
$selectedOrderId = isset($_GET['order_id']) ? sanitize_input($_GET['order_id']) : '';

// Function to calculate order progress
function calculateOrderProgress($order) {
    $items = getOrderItems($order['order_id']);
    if (empty($items)) return 0;
    
    $totalStages = 7; // Total possible stages
    $completedStages = 0;
    
    foreach ($items as $item) {
        $itemStages = 0;
        
        // Stage 1: Order Created (always completed)
        $itemStages++;
        
        // Stage 2: Raw Materials
        if (!empty($item['raw_materials'])) $itemStages++;
        
        // Stage 3: Machining Processes
        if (!empty($item['machining_processes'])) {
            $allProcessesCompleted = true;
            foreach ($item['machining_processes'] as $process) {
                if ($process['status'] !== 'Completed') {
                    $allProcessesCompleted = false;
                    break;
                }
            }
            if ($allProcessesCompleted) $itemStages++;
        }
        
        // Stage 4: Quality Inspection
        if (!empty($item['inspection_data'])) {
            $hasPassedInspection = false;
            foreach ($item['inspection_data'] as $inspection) {
                if ($inspection['status'] === 'QC Passed') {
                    $hasPassedInspection = true;
                    break;
                }
            }
            if ($hasPassedInspection) $itemStages++;
        }
        
        // Stage 5: Packaging
        if (!empty($item['packaging_lots'])) $itemStages++;
        
        // Stage 6: Shipping Docs
        if (!empty($item['packaging_lots'])) {
            $hasShippingDocs = false;
            foreach ($item['packaging_lots'] as $lot) {
                if (!empty($lot['shipping_documents'])) {
                    $hasShippingDocs = true;
                    break;
                }
            }
            if ($hasShippingDocs) $itemStages++;
        }
        
        // Stage 7: Dispatch
        if (!empty($item['packaging_lots'])) {
            $allDispatched = true;
            foreach ($item['packaging_lots'] as $lot) {
                if (($lot['dispatch_status'] ?? '') !== 'Shipped') {
                    $allDispatched = false;
                    break;
                }
            }
            if ($allDispatched) $itemStages++;
        }
        
        $completedStages += $itemStages;
    }
    
    $totalPossibleStages = count($items) * $totalStages;
    return $totalPossibleStages > 0 ? round(($completedStages / $totalPossibleStages) * 100) : 0;
}

// Function to get order timeline
function getOrderTimeline($order) {
    $timeline = [];
    $items = getOrderItems($order['order_id']);
    
    // Order creation
    $timeline[] = [
        'date' => $order['po_date'],
        'event' => 'Order Created',
        'description' => 'Purchase order received and processed',
        'icon' => 'bi-clipboard-check',
        'status' => 'completed'
    ];
    
    foreach ($items as $itemIndex => $item) {
        $itemName = $item['Name'];
        
        // Raw Materials
        if (!empty($item['raw_materials'])) {
            foreach ($item['raw_materials'] as $material) {
                $timeline[] = [
                    'date' => $material['purchase_date'],
                    'event' => "Raw Materials Sourced - {$itemName}",
                    'description' => "{$material['type']} ({$material['grade']}) from {$material['vendor']}",
                    'icon' => 'bi-tools',
                    'status' => 'completed'
                ];
            }
        }
        
        // Machining Processes
        if (!empty($item['machining_processes'])) {
            foreach ($item['machining_processes'] as $process) {
                $status = $process['status'] === 'Completed' ? 'completed' : 'in-progress';
                $timeline[] = [
                    'date' => $process['start_date'],
                    'event' => "Machining Process - {$itemName}",
                    'description' => "{$process['name']} by {$process['vendor']} - {$process['status']}",
                    'icon' => 'bi-gear',
                    'status' => $status
                ];
                
                if ($process['actual_completion']) {
                    $timeline[] = [
                        'date' => $process['actual_completion'],
                        'event' => "Process Completed - {$itemName}",
                        'description' => "{$process['name']} completed successfully",
                        'icon' => 'bi-check-circle',
                        'status' => 'completed'
                    ];
                }
            }
        }
        
        // Quality Inspections
        if (!empty($item['inspection_data'])) {
            foreach ($item['inspection_data'] as $inspection) {
                $timeline[] = [
                    'date' => $inspection['inspection_date'],
                    'event' => "Quality Inspection - {$itemName}",
                    'description' => "{$inspection['type']} - {$inspection['status']} by {$inspection['technician_name']}",
                    'icon' => 'bi-search',
                    'status' => $inspection['status'] === 'QC Passed' ? 'completed' : 'warning'
                ];
            }
        }
        
        // Packaging
        if (!empty($item['packaging_lots'])) {
            foreach ($item['packaging_lots'] as $lotIndex => $lot) {
                $timeline[] = [
                    'date' => $lot['packaging_date'],
                    'event' => "Packaging Completed - {$itemName}",
                    'description' => "Lot #" . ($lotIndex + 1) . " - {$lot['products_in_lot']} products packaged",
                    'icon' => 'bi-box',
                    'status' => 'completed'
                ];
            }
        }
        
        // Shipping Documents
        if (!empty($item['packaging_lots'])) {
            foreach ($item['packaging_lots'] as $lotIndex => $lot) {
                if (!empty($lot['shipping_documents'])) {
                    $timeline[] = [
                        'date' => $lot['packaging_date'], // Use packaging date as approximate
                        'event' => "Shipping Documents Prepared - {$itemName}",
                        'description' => "Lot #" . ($lotIndex + 1) . " - Documents ready for dispatch",
                        'icon' => 'bi-file-text',
                        'status' => 'completed'
                    ];
                }
            }
        }
        
        // Dispatch
        if (!empty($item['packaging_lots'])) {
            foreach ($item['packaging_lots'] as $lotIndex => $lot) {
                if (!empty($lot['dispatch_status']) && $lot['dispatch_status'] === 'Shipped') {
                    $timeline[] = [
                        'date' => $lot['dispatch_date'],
                        'event' => "Dispatched - {$itemName}",
                        'description' => "Lot #" . ($lotIndex + 1) . " shipped via {$lot['transport_mode']} - Tracking: {$lot['tracking_number']}",
                        'icon' => 'bi-truck',
                        'status' => 'completed'
                    ];
                }
            }
        }
    }
    
    // Sort timeline by date
    usort($timeline, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });
    
    return $timeline;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking - Alphasonix CRM</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="css/order_tracking.css">

</head>
<body>
    <?php include 'sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="container-fluid py-4">
            <!-- Header Section -->
            <div class="dashboard-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="h3 mb-2">
                            <i class="bi bi-geo-alt me-2"></i>Order Tracking
                        </h1>
                        <p class="text-muted mb-0">Real-time tracking and status monitoring for all orders</p>
                    </div>
                    <div class="text-end">
                        <div class="badge bg-light text-dark p-2">
                            <i class="bi bi-calendar3 me-1"></i> <?= date('l, F j, Y') ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <div class="bg-primary bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-clipboard-data fs-4 text-primary"></i>
                                </div>
                            </div>
                            <div>
                                <h5 class="mb-1">Total Orders</h5>
                                <h2 class="mb-0 text-primary"><?= count($orders) ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <div class="bg-success bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-gear fs-4 text-success"></i>
                                </div>
                            </div>
                            <div>
                                <h5 class="mb-1">In Production</h5>
                                <h2 class="mb-0 text-success">
                                    <?= count(array_filter($orders, fn($order) => $order['status'] === 'In Production')) ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <div class="bg-info bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-truck fs-4 text-info"></i>
                                </div>
                            </div>
                            <div>
                                <h5 class="mb-1">Ready to Ship</h5>
                                <h2 class="mb-0 text-info">
                                    <?= count(array_filter($orders, fn($order) => $order['status'] === 'Ready for Dispatch')) ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="filter-section">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="search-box">
                            <input type="text" id="orderSearch" class="form-control" 
                                   placeholder="Search by Order ID, Client Name, or Status...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="statusFilter" class="form-select">
                            <option value="">All Statuses</option>
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
                    <div class="col-md-2">
                        <select id="clientFilter" class="form-select">
                            <option value="">All Clients</option>
                            <?php foreach ($customerMap as $id => $name): ?>
                                <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button id="clearFilters" class="btn btn-secondary w-100">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            </div>

            <?php if ($selectedOrderId && $selectedOrder = getOrderById($selectedOrderId)): ?>
                <!-- Detailed Order View -->
                <div class="content-card">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-geo-alt me-2"></i>Tracking Order #<?= htmlspecialchars($selectedOrderId) ?>
                        </h5>
                        <a href="order_tracking.php" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-left me-2"></i>Back to All Orders
                        </a>
                    </div>
                    <div class="card-body-custom">
                        <!-- Order Summary -->
                        <div class="summary-grid">
                            <div class="summary-card">
                                <div class="summary-icon client">
                                    <i class="bi bi-building"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Client</div>
                                    <div class="fw-bold"><?= htmlspecialchars($customerMap[$selectedOrder['customer_id']] ?? 'N/A') ?></div>
                                </div>
                            </div>
                            <div class="summary-card">
                                <div class="summary-icon date">
                                    <i class="bi bi-calendar"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">PO Date</div>
                                    <div class="fw-bold"><?= htmlspecialchars($selectedOrder['po_date']) ?></div>
                                </div>
                            </div>
                            <div class="summary-card">
                                <div class="summary-icon delivery">
                                    <i class="bi bi-truck"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Delivery Date</div>
                                    <div class="fw-bold"><?= htmlspecialchars($selectedOrder['delivery_date'] ?: 'Not set') ?></div>
                                </div>
                            </div>
                            <div class="summary-card">
                                <div class="summary-icon progress">
                                    <i class="bi bi-graph-up"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Overall Progress</div>
                                    <div class="fw-bold"><?= calculateOrderProgress($selectedOrder) ?>%</div>
                                </div>
                            </div>
                        </div>

                        <!-- Progress Visualization -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">Production Progress</h6>
                                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $selectedOrder['status'])) ?>">
                                    <?= htmlspecialchars($selectedOrder['status']) ?>
                                </span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width: <?= calculateOrderProgress($selectedOrder) ?>%"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted">0%</small>
                                <small class="text-muted">50%</small>
                                <small class="text-muted">100%</small>
                            </div>
                        </div>

                        <!-- Timeline -->
                        <div class="mb-4">
                            <h6 class="mb-3">Order Timeline</h6>
                            <?php $timeline = getOrderTimeline($selectedOrder); ?>
                            <?php if (!empty($timeline)): ?>
                                <div class="timeline">
                                    <?php foreach ($timeline as $event): ?>
                                        <div class="timeline-item <?= $event['status'] ?>">
                                            <div class="timeline-marker">
                                                <i class="bi <?= $event['icon'] ?>"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <h6 class="mb-0"><?= htmlspecialchars($event['event']) ?></h6>
                                                    <small class="text-muted"><?= date('M j, Y', strtotime($event['date'])) ?></small>
                                                </div>
                                                <p class="mb-0 text-muted small"><?= htmlspecialchars($event['description']) ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state py-4">
                                    <i class="bi bi-clock-history"></i>
                                    <p class="mb-0">No timeline events recorded yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Quick Actions -->
                        <div>
                            <h6 class="mb-3">Quick Actions</h6>
                            <div class="d-flex gap-2">
                                <a href="pipeline.php?order_id=<?= htmlspecialchars($selectedOrderId) ?>" class="btn btn-primary">
                                    <i class="bi bi-diagram-3 me-2"></i>View in Pipeline
                                </a>
                                <a href="order-stages-view.php?order_id=<?= htmlspecialchars($selectedOrderId) ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-layers me-2"></i>View All Stages
                                </a>
                                <a href="print_order.php?order_id=<?= htmlspecialchars($selectedOrderId) ?>" target="_blank" class="btn btn-outline-primary">
                                    <i class="bi bi-printer me-2"></i>Print Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Orders Grid View -->
                <div class="row">
                    <?php if (empty($orders)): ?>
                        <div class="col-12">
                            <div class="content-card">
                                <div class="card-body-custom text-center">
                                    <div class="empty-state">
                                        <i class="bi bi-clipboard-x"></i>
                                        <h5>No Orders Found</h5>
                                        <p class="mb-3">There are no orders in the system yet.</p>
                                        <a href="orders.php" class="btn btn-primary">
                                            <i class="bi bi-plus-circle me-2"></i>Create First Order
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_reverse($orders) as $order): ?>
                            <?php
                            $progress = calculateOrderProgress($order);
                            $customerName = $customerMap[$order['customer_id']] ?? 'N/A';
                            $daysRemaining = $order['delivery_date'] ? 
                                floor((strtotime($order['delivery_date']) - time()) / 86400) : null;
                            ?>
                            <div class="col-lg-6 col-xl-4 mb-4 order-card" 
                                 data-status="<?= htmlspecialchars($order['status']) ?>" 
                                 data-client="<?= htmlspecialchars($order['customer_id']) ?>">
                                <div class="order-card">
                                    <div class="order-card-header">
                                        <div>
                                            <h6 class="mb-1">#<?= htmlspecialchars($order['order_id']) ?></h6>
                                            <small class="text-muted"><?= htmlspecialchars($customerName) ?></small>
                                        </div>
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $order['status'])) ?>">
                                            <?= htmlspecialchars($order['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="order-card-body">
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between small text-muted mb-1">
                                                <span><i class="bi bi-calendar me-1"></i>PO: <?= htmlspecialchars($order['po_date']) ?></span>
                                                <?php if ($order['delivery_date'] && $daysRemaining !== null): ?>
                                                    <span class="days-remaining <?= $daysRemaining < 7 ? 'urgent' : ($daysRemaining < 14 ? 'warning' : 'normal') ?>">
                                                        <?= $daysRemaining > 0 ? "$daysRemaining days left" : abs($daysRemaining) . " days overdue" ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between small text-muted mb-1">
                                                <span>Overall Progress</span>
                                                <span><?= $progress ?>%</span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar" style="width: <?= $progress ?>%"></div>
                                            </div>
                                        </div>

                                        <?php
                                        $items = getOrderItems($order['order_id']);
                                        if (!empty($items)):
                                        ?>
                                            <div class="small">
                                                <strong>Items:</strong>
                                                <div class="mt-1">
                                                    <?php foreach (array_slice($items, 0, 2) as $item): ?>
                                                        <span class="item-tag"><?= htmlspecialchars($item['Name']) ?> (Qty: <?= $item['quantity'] ?>)</span>
                                                    <?php endforeach; ?>
                                                    <?php if (count($items) > 2): ?>
                                                        <span class="text-muted">+<?= count($items) - 2 ?> more</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="order-card-footer">
                                        <div class="d-flex gap-1">
                                            <a href="order_tracking.php?order_id=<?= htmlspecialchars($order['order_id']) ?>" 
                                               class="btn btn-primary btn-sm">
                                                <i class="bi bi-geo-alt"></i>
                                            </a>
                                            <a href="pipeline.php?order_id=<?= htmlspecialchars($order['order_id']) ?>" 
                                               class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-diagram-3"></i>
                                            </a>
                                        </div>
                                        <small class="text-muted">
                                            Updated: <?= date('M j, Y', strtotime($order['po_date'])) ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('orderSearch');
            const statusFilter = document.getElementById('statusFilter');
            const clientFilter = document.getElementById('clientFilter');
            const clearFilters = document.getElementById('clearFilters');
            const orderCards = document.querySelectorAll('.order-card');

            function filterOrders() {
                const searchTerm = searchInput.value.toLowerCase();
                const statusValue = statusFilter.value;
                const clientValue = clientFilter.value;

                orderCards.forEach(card => {
                    const orderId = card.querySelector('.order-card-header h6').textContent.toLowerCase();
                    const clientName = card.querySelector('.order-card-header small').textContent.toLowerCase();
                    const status = card.getAttribute('data-status');
                    const clientId = card.getAttribute('data-client');

                    const matchesSearch = orderId.includes(searchTerm) || clientName.includes(searchTerm);
                    const matchesStatus = !statusValue || status === statusValue;
                    const matchesClient = !clientValue || clientId === clientValue;

                    card.style.display = matchesSearch && matchesStatus && matchesClient ? 'block' : 'none';
                });
            }

            searchInput.addEventListener('input', filterOrders);
            statusFilter.addEventListener('change', filterOrders);
            clientFilter.addEventListener('change', filterOrders);

            clearFilters.addEventListener('click', function() {
                searchInput.value = '';
                statusFilter.value = '';
                clientFilter.value = '';
                filterOrders();
            });
        });
    </script>
</body>
</html>