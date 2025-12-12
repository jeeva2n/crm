<?php
session_start();
require_once 'functions.php';

// Get order ID from URL parameter
$orderId = isset($_GET['order_id']) ? sanitize_input($_GET['order_id']) : '';

// Function to track changes - using database
function getChangeHistory($orderId)
{
    return getOrderHistory($orderId);
}

// If no order ID provided, show order selection page
if (empty($orderId)) {
    $orders = getOrders();
    $customers = getCustomers();
    $customerMap = array_column($customers, 'name', 'id');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Order - Stage View</title>
</head>
<body>
    <div class="container">
        <a href="pipeline.php" class="back-link">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Back to Pipeline
        </a>
        <h1>Select Order to View Stages</h1>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📋</div>
                <p>No orders found in the system.</p>
            </div>
        <?php else: ?>
            <div class="order-grid">
                <?php foreach (array_reverse($orders) as $order): ?>
                    <a href="order-stages-view.php?order_id=<?= htmlspecialchars($order['order_id']) ?>" class="order-link">
                        <div class="order-card">
                            <div class="order-header">
                                Order #<?= htmlspecialchars($order['order_id']) ?>
                            </div>
                            <div class="order-details">
                                <div class="detail-item">
                                    <span class="detail-label">Client:</span>
                                    <span><?= htmlspecialchars($customerMap[$order['customer_id']] ?? 'N/A') ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">PO Date:</span>
                                    <span><?= htmlspecialchars($order['po_date']) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Status:</span>
                                    <span class="status-chip"><?= htmlspecialchars($order['status']) ?></span>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
    exit;
}

// Get order data from database
$order = getOrderById($orderId);
if (!$order) {
    die("Order not found. <a href='order-stages-view.php'>Back to orders</a>");
}

// Get customer data from database
$customers = getCustomers();
$customerMap = array_column($customers, 'name', 'id');
$customerName = $customerMap[$order['customer_id']] ?? 'N/A';

// Get items from database
$items = getOrderItems($orderId);

// Get change history from database
$changeHistory = getChangeHistory($orderId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= htmlspecialchars($orderId) ?> - Complete Stage View</title>
<link rel="stylesheet" href="css/order-stages-view.css">
</head>
<body>
    <div class="main-container">
        <!-- Header Section -->
        <div class="header-section">
            <h1>Order #<?= htmlspecialchars($orderId) ?> - Complete Stage Details</h1>
            <p>Comprehensive view of all processing stages with change history</p>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons no-print">
            <a href="pipeline.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Pipeline
            </a>
        </div>

        <!-- Order Information -->
        <div class="order-info-section">
            <div class="order-info-grid">
                <div class="info-card">
                    <div class="info-label">Order Number</div>
                    <div class="info-value"><?= htmlspecialchars($orderId) ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Client Name</div>
                    <div class="info-value"><?= htmlspecialchars($customerName) ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">PO Date</div>
                    <div class="info-value"><?= htmlspecialchars($order['po_date']) ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Delivery Date</div>
                    <div class="info-value">
                        <?= htmlspecialchars($order['delivery_date'] ?: 'Not set') ?>
                        <?php if ($order['delivery_date']):
                            $daysLeft = floor((strtotime($order['delivery_date']) - time()) / 86400);
                            $daysColor = $daysLeft < 7 ? 'var(--danger)' : ($daysLeft < 14 ? 'var(--warning)' : 'var(--success)');
                            ?>
                            <div style="font-size: 0.875rem; color: <?= $daysColor ?>; margin-top: 5px;">
                                <?= $daysLeft > 0 ? "$daysLeft days remaining" : abs($daysLeft) . " days overdue" ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-label">Order Status</div>
                    <div class="info-value">
                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $order['status'])) ?>">
                            <?= htmlspecialchars($order['status']) ?>
                        </span>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-label">Total Items</div>
                    <div class="info-value"><?= count($items) ?></div>
                </div>
            </div>
        </div>

        <!-- Change History Section -->
        <?php if (!empty($changeHistory)): ?>
            <div class="change-history-section no-print">
                <div class="change-history-header">
                    <div class="change-history-title">
                        <i class="fas fa-history"></i> Change History
                    </div>
                    <div style="font-size: 0.875rem; color: var(--gray-600);">
                        Total Changes: <?= count($changeHistory) ?>
                    </div>
                </div>
                <?php foreach (array_slice(array_reverse($changeHistory), 0, 5) as $change): ?>
                    <div class="change-entry">
                        <div class="change-meta">
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($change['changed_by'] ?? 'System') ?></span>
                            <span><i class="fas fa-clock"></i> <?= htmlspecialchars($change['change_date'] ?? '') ?></span>
                            <span><i class="fas fa-layer-group"></i> <?= htmlspecialchars($change['stage'] ?? '') ?></span>
                        </div>
                        <div class="change-details">
                            <?= htmlspecialchars($change['change_description'] ?? '') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Order Drawing/Reference -->
        <?php if (!empty($order['drawing_filename'])): ?>
            <div class="stage-container">
                <div class="stage-header">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div class="stage-icon">📐</div>
                        <span>Order Drawing/Reference</span>
                    </div>
                    <div class="no-print">
                        <span style="font-size: 0.875rem;">Uploaded on order creation</span>
                    </div>
                </div>
                <div class="stage-content">
                    <div class="image-gallery">
                        <div class="image-item">
                            <div class="image-wrapper">
                                <img src="uploads/drawings/<?= htmlspecialchars($order['drawing_filename']) ?>"
                                    alt="Order Drawing">
                            </div>
                            <div class="image-overlay">
                                <div class="image-actions">
                                    <a href="uploads/drawings/<?= htmlspecialchars($order['drawing_filename']) ?>"
                                        download="Order_<?= $orderId ?>_Drawing_<?= htmlspecialchars($order['drawing_filename']) ?>"
                                        class="btn-icon tooltip">
                                        <i class="fas fa-download"></i>
                                        <span class="tooltiptext">Download Image</span>
                                    </a>
                                    <a href="uploads/drawings/<?= htmlspecialchars($order['drawing_filename']) ?>"
                                        target="_blank" class="btn-icon tooltip">
                                        <i class="fas fa-external-link-alt"></i>
                                        <span class="tooltiptext">Open in New Tab</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Items Processing Details -->
        <?php foreach ($items as $itemIndex => $item): ?>
            <div class="item-section">
                <div class="item-header">
                    <div>
                        <i class="fas fa-box"></i>
                        <?= htmlspecialchars($item['Name']) ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <span class="item-quantity-badge">
                            Qty: <?= htmlspecialchars($item['quantity']) ?>
                        </span>
                        <span
                            class="status-badge status-<?= strtolower(str_replace(' ', '-', $item['item_status'] ?? 'pending')) ?>">
                            <?= htmlspecialchars($item['item_status'] ?? 'Pending') ?>
                        </span>
                    </div>
                </div>

                <!-- Item Specifications -->
                <?php if (!empty($item['Dimensions']) || !empty($item['Description'])): ?>
                    <div class="stage-content">
                        <div class="spec-box">
                            <h3 style="margin-bottom: 20px; color: var(--secondary-blue);">
                                <i class="fas fa-info-circle"></i> Item Specifications
                            </h3>
                            <?php if (!empty($item['Dimensions'])): ?>
                                <div class="spec-item">
                                    <div class="spec-label">Dimensions</div>
                                    <div class="spec-value"><?= htmlspecialchars($item['Dimensions']) ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($item['Description'])): ?>
                                <div class="spec-item">
                                    <div class="spec-label">Description</div>
                                    <div class="spec-value"><?= nl2br(htmlspecialchars($item['Description'])) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Stage 2: Raw Materials -->
                <?php if (!empty($item['raw_materials'])): ?>
                    <div class="stage-container">
                        <div class="stage-header">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div class="stage-icon">🔧</div>
                                <span>Stage 2: Raw Materials Sourcing</span>
                            </div>
                        </div>
                        <div class="stage-content">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Grade</th>
                                        <th>Dimensions</th>
                                        <th>Vendor</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($item['raw_materials'] as $material): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($material['type'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($material['grade'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($material['dimensions'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($material['vendor'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($material['purchase_date'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Stage 3: Machining Processes -->
                <?php if (!empty($item['machining_processes'])): ?>
                    <div class="stage-container">
                        <div class="stage-header">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div class="stage-icon">⚙️</div>
                                <span>Stage 3: Machining Processes</span>
                            </div>
                        </div>
                        <div class="stage-content">
                            <?php foreach ($item['machining_processes'] as $processIndex => $process): ?>
                                <div class="process-box">
                                    <h4>
                                        <span class="process-sequence">#<?= htmlspecialchars($process['sequence'] ?? '') ?></span>
                                        <?= htmlspecialchars($process['name'] ?? '') ?>
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $process['status'] ?? 'not-started')) ?>">
                                            <?= htmlspecialchars($process['status'] ?? 'Not Started') ?>
                                        </span>
                                    </h4>
                                    
                                    <div class="process-grid">
                                        <div class="process-grid-item">
                                            <div class="process-grid-label">Vendor</div>
                                            <div class="process-grid-value"><?= htmlspecialchars($process['vendor'] ?? '') ?></div>
                                        </div>
                                        <div class="process-grid-item">
                                            <div class="process-grid-label">Start Date</div>
                                            <div class="process-grid-value"><?= htmlspecialchars($process['start_date'] ?? '') ?></div>
                                        </div>
                                        <div class="process-grid-item">
                                            <div class="process-grid-label">Expected Completion</div>
                                            <div class="process-grid-value"><?= htmlspecialchars($process['expected_completion'] ?? '') ?></div>
                                        </div>
                                        <?php if (!empty($process['actual_completion'])): ?>
                                            <div class="process-grid-item">
                                                <div class="process-grid-label">Actual Completion</div>
                                                <div class="process-grid-value"><?= htmlspecialchars($process['actual_completion']) ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($process['remarks'])): ?>
                                        <div class="remarks-box">
                                            <strong>Remarks:</strong>
                                            <p><?= htmlspecialchars($process['remarks']) ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($process['documents'])): ?>
                                        <div class="document-list">
                                            <?php foreach ($process['documents'] as $docIndex => $doc): ?>
                                                <div class="document-item">
                                                    <div class="document-info">
                                                        <div class="document-icon">
                                                            <i class="fas fa-file"></i>
                                                        </div>
                                                        <a href="uploads/machining_docs/<?= htmlspecialchars($doc) ?>" 
                                                           class="document-name" target="_blank">
                                                            <?= htmlspecialchars($process['original_filenames'][$docIndex] ?? $doc) ?>
                                                        </a>
                                                    </div>
                                                    <div class="document-actions">
                                                        <a href="uploads/machining_docs/<?= htmlspecialchars($doc) ?>" 
                                                           download class="btn-icon tooltip">
                                                            <i class="fas fa-download"></i>
                                                            <span class="tooltiptext">Download</span>
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Stage 4: Quality Inspection -->
                <?php if (!empty($item['inspection_data'])): ?>
                    <div class="stage-container">
                        <div class="stage-header">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div class="stage-icon">🔍</div>
                                <span>Stage 4: Quality Inspection</span>
                            </div>
                        </div>
                        <div class="stage-content">
                            <?php 
                            $inspections = $item['inspection_data'];
                            usort($inspections, function ($a, $b) {
                                return strtotime($b['inspection_date'] ?? '') - strtotime($a['inspection_date'] ?? '');
                            });
                            ?>

                            <?php foreach ($inspections as $inspIndex => $inspection): ?>
                                <div class="process-box" style="border-left: 4px solid <?= 
                                    ($inspection['status'] ?? '') == 'QC Passed' ? 'var(--success)' : 
                                    (($inspection['status'] ?? '') == 'Rework Required' ? 'var(--danger)' : 'var(--warning)') 
                                ?>;">
                                    <h4>
                                        Inspection #<?= (count($inspections) - $inspIndex) ?>: <?= htmlspecialchars($inspection['type'] ?? '') ?>
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $inspection['status'] ?? '')) ?>">
                                            <?= htmlspecialchars($inspection['status'] ?? '') ?>
                                        </span>
                                    </h4>
                                    
                                    <div class="process-grid">
                                        <div class="process-grid-item">
                                            <div class="process-grid-label">Technician</div>
                                            <div class="process-grid-value"><?= htmlspecialchars($inspection['technician_name'] ?? '') ?></div>
                                        </div>
                                        <div class="process-grid-item">
                                            <div class="process-grid-label">Inspection Date</div>
                                            <div class="process-grid-value"><?= htmlspecialchars($inspection['inspection_date'] ?? '') ?></div>
                                        </div>
                                        <div class="process-grid-item">
                                            <div class="process-grid-label">Inspection ID</div>
                                            <div class="process-grid-value"><?= htmlspecialchars($inspection['inspection_id'] ?? 'N/A') ?></div>
                                        </div>
                                    </div>

                                    <?php if (!empty($inspection['remarks'])): ?>
                                        <div class="remarks-box">
                                            <strong>Remarks:</strong>
                                            <p><?= htmlspecialchars($inspection['remarks']) ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($inspection['documents'])): ?>
                                        <div class="document-list">
                                            <?php foreach ($inspection['documents'] as $docIndex => $doc): ?>
                                                <div class="document-item">
                                                    <div class="document-info">
                                                        <div class="document-icon">
                                                            <i class="fas fa-file-alt"></i>
                                                        </div>
                                                        <a href="uploads/inspection_reports/<?= htmlspecialchars($doc) ?>" 
                                                           class="document-name" target="_blank">
                                                            <?= htmlspecialchars($inspection['original_filenames'][$docIndex] ?? $doc) ?>
                                                        </a>
                                                    </div>
                                                    <div class="document-actions">
                                                        <a href="uploads/inspection_reports/<?= htmlspecialchars($doc) ?>" 
                                                           download class="btn-icon tooltip">
                                                            <i class="fas fa-download"></i>
                                                            <span class="tooltiptext">Download</span>
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                            <!-- Inspection Summary -->
                            <div class="stats-box">
                                <h4><i class="fas fa-chart-bar"></i> Inspection Summary</h4>
                                <?php
                                $passCount = 0;
                                $reworkCount = 0;
                                $minorCount = 0;
                                foreach ($item['inspection_data'] as $insp) {
                                    if (($insp['status'] ?? '') === 'QC Passed') $passCount++;
                                    elseif (($insp['status'] ?? '') === 'Rework Required') $reworkCount++;
                                    else $minorCount++;
                                }
                                ?>
                                <div class="process-grid">
                                    <div class="process-grid-item">
                                        <div class="process-grid-label">Passed</div>
                                        <div class="process-grid-value" style="color: var(--success);"><?= $passCount ?></div>
                                    </div>
                                    <div class="process-grid-item">
                                        <div class="process-grid-label">Rework Required</div>
                                        <div class="process-grid-value" style="color: var(--danger);"><?= $reworkCount ?></div>
                                    </div>
                                    <div class="process-grid-item">
                                        <div class="process-grid-label">Minor Issues</div>
                                        <div class="process-grid-value" style="color: var(--warning);"><?= $minorCount ?></div>
                                    </div>
                                    <div class="process-grid-item">
                                        <div class="process-grid-label">Total Inspections</div>
                                        <div class="process-grid-value"><?= count($item['inspection_data']) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Stage 5: Packaging -->
                <?php if (!empty($item['packaging_lots'])): ?>
                    <div class="stage-container">
                        <div class="stage-header">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div class="stage-icon">📦</div>
                                <span>Stage 5: Packaging</span>
                            </div>
                        </div>
                        <div class="stage-content">
                            <?php foreach ($item['packaging_lots'] as $lotIndex => $lot): ?>
                                <div class="process-box">
                                    <h4>
                                        Lot #<?= ($lotIndex + 1) ?> - <?= htmlspecialchars($lot['products_in_lot'] ?? '') ?> products
                                    </h4>
                                    
                                    <div class="process-grid">
                                        <div class="process-grid-item">
                                            <div class="process-grid-label">Packaging Type</div>
                                            <div class="process-grid-value"><?= htmlspecialchars($lot['packaging_type'] ?? '') ?></div>
                                        </div>
                                        <div class="process-grid-item">
                                            <div class="process-grid-label">Packaging Date</div>
                                            <div class="process-grid-value"><?= htmlspecialchars($lot['packaging_date'] ?? '') ?></div>
                                        </div>
                                        <div class="process-grid-item">
                                            <div class="process-grid-label">Number of Packages</div>
                                            <div class="process-grid-value"><?= htmlspecialchars($lot['num_packages'] ?? '') ?></div>
                                        </div>
                                        <div class="process-grid-item">
                                            <div class="process-grid-label">Net Weight</div>
                                            <div class="process-grid-value"><?= htmlspecialchars($lot['net_weight'] ?? '') ?> kg</div>
                                        </div>
                                        <div class="process-grid-item">
                                            <div class="process-grid-label">Gross Weight</div>
                                            <div class="process-grid-value"><?= htmlspecialchars($lot['gross_weight'] ?? '') ?> kg</div>
                                        </div>
                                        <div class="process-grid-item">
                                            <div class="process-grid-label">Docs Included</div>
                                            <div class="process-grid-value"><?= htmlspecialchars($lot['docs_included'] ?? 'No') ?></div>
                                        </div>
                                    </div>

                                    <!-- Fumigation Information -->
                                    <?php if (!empty($lot['fumigation_completed']) && $lot['fumigation_completed'] === 'Yes'): ?>
                                        <div class="remarks-box" style="background: #d1fae5; border-left-color: var(--success);">
                                            <strong><i class="fas fa-check-circle" style="color: var(--success);"></i> Fumigation Completed</strong>
                                            <p>
                                                Certificate #<?= htmlspecialchars($lot['fumigation_certificate_number'] ?? 'N/A') ?> | 
                                                Date: <?= htmlspecialchars($lot['fumigation_date'] ?? 'N/A') ?> | 
                                                Agency: <?= htmlspecialchars($lot['fumigation_agency'] ?? 'N/A') ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Product Photos -->
                                    <?php if (!empty($lot['photos'])): ?>
                                        <div style="margin: 20px 0;">
                                            <strong style="display: block; margin-bottom: 10px;">Product Photos:</strong>
                                            <div class="image-gallery">
                                                <?php foreach ($lot['photos'] as $photoIndex => $photo): ?>
                                                    <div class="image-item">
                                                        <div class="image-wrapper">
                                                            <img src="uploads/packaging_photos/<?= htmlspecialchars($photo) ?>"
                                                                alt="Product Photo">
                                                        </div>
                                                        <div class="image-overlay">
                                                            <div class="image-actions">
                                                                <a href="uploads/packaging_photos/<?= htmlspecialchars($photo) ?>"
                                                                    download class="btn-icon tooltip">
                                                                    <i class="fas fa-download"></i>
                                                                    <span class="tooltiptext">Download</span>
                                                                </a>
                                                                <a href="uploads/packaging_photos/<?= htmlspecialchars($photo) ?>"
                                                                    target="_blank" class="btn-icon tooltip">
                                                                    <i class="fas fa-external-link-alt"></i>
                                                                    <span class="tooltiptext">Open</span>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Shipping Documents -->
                                    <?php if (!empty($lot['shipping_documents'])): ?>
                                        <div style="margin: 20px 0;">
                                            <strong style="display: block; margin-bottom: 10px;">Shipping Documents:</strong>
                                            <div class="document-list">
                                                <?php foreach ($lot['shipping_documents'] as $docIndex => $doc): ?>
                                                    <div class="document-item">
                                                        <div class="document-info">
                                                            <div class="document-icon">
                                                                <i class="fas fa-shipping-fast"></i>
                                                            </div>
                                                            <a href="uploads/shipping_docs/<?= htmlspecialchars($doc) ?>" 
                                                               class="document-name" target="_blank">
                                                                <?= htmlspecialchars($lot['shipping_original_filenames'][$docIndex] ?? $doc) ?>
                                                            </a>
                                                        </div>
                                                        <div class="document-actions">
                                                            <a href="uploads/shipping_docs/<?= htmlspecialchars($doc) ?>" 
                                                               download class="btn-icon tooltip">
                                                                <i class="fas fa-download"></i>
                                                                <span class="tooltiptext">Download</span>
                                                            </a>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Dispatch Information -->
                                    <?php if (!empty($lot['dispatch_status'])): ?>
                                        <div class="remarks-box" style="background: #dbeafe; border-left-color: var(--primary-blue);">
                                            <strong><i class="fas fa-truck" style="color: var(--primary-blue);"></i> Dispatch Details</strong>
                                            <p>
                                                <strong>Status:</strong> <?= htmlspecialchars($lot['dispatch_status']) ?><br>
                                                <strong>Dispatch Date:</strong> <?= htmlspecialchars($lot['dispatch_date'] ?? '') ?><br>
                                                <strong>Transport Mode:</strong> <?= htmlspecialchars($lot['transport_mode'] ?? '') ?><br>
                                                <strong>Tracking Number:</strong> <?= htmlspecialchars($lot['tracking_number'] ?? '') ?>
                                                <?php if (!empty($lot['dispatch_remarks'])): ?>
                                                    <br><strong>Remarks:</strong> <?= htmlspecialchars($lot['dispatch_remarks']) ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- No Data State -->
                <?php if (empty($item['raw_materials']) && empty($item['machining_processes']) && empty($item['inspection_data']) && empty($item['packaging_lots'])): ?>
                    <div class="stage-content">
                        <div class="no-data">
                            <div class="no-data-icon">📭</div>
                            <p>No processing data available for this item yet.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <!-- Footer Section -->
        <div class="footer-section">
            <p><strong>Report generated on:</strong> <?= date('F d, Y \a\t g:i A') ?></p>
            <p>© <?= date('Y') ?> Alpha Sonix NDT Solutions - All Rights Reserved</p>
            <p style="font-size: 0.875rem; margin-top: 10px;">
                This document contains confidential information. Please handle with care.
            </p>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="modal" style="display: none;">
        <span class="modal-close" onclick="closeImageModal()">&times;</span>
        <img class="modal-content" id="modalImage">
        <div id="caption"></div>
    </div>

    <!-- Back to Top Button -->
    <button onclick="scrollToTop()" id="backToTop" title="Go to top">↑</button>

    <script>
        // JavaScript functionality
        function openImageModal(src) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            modal.style.display = 'block';
            modalImg.src = src;
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function downloadPDF() {
            alert('PDF download functionality would be implemented with html2pdf library');
        }

        // Show/hide back to top button
        window.onscroll = function() {
            const backToTop = document.getElementById('backToTop');
            if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
                backToTop.style.display = 'block';
            } else {
                backToTop.style.display = 'none';
            }
        };

        // Close modal when clicking outside
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });

        // Add Font Awesome icons (fallback)
        if (!document.querySelector('link[href*="font-awesome"]')) {
            const faLink = document.createElement('link');
            faLink.rel = 'stylesheet';
            faLink.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css';
            document.head.appendChild(faLink);
        }

        // Make all images clickable to open modal
        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('.image-wrapper img');
            images.forEach(img => {
                img.addEventListener('click', function() {
                    openImageModal(this.src);
                });
            });
        });
    </script>
</body>
</html>