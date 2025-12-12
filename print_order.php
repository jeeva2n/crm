<?php
session_start();
require_once 'functions.php';

// Define standard statuses
$STANDARD_STATUSES = [
    'Pending',
    'Sourcing Material',
    'In Production',
    'Ready for QC',
    'QC Completed',
    'Packaging',
    'Ready for Dispatch',
    'Shipped'
];

// Get order ID from query parameter
$orderId = isset($_GET['order_id']) ? sanitize_input($_GET['order_id']) : '';

if (empty($orderId)) {
    die("Order ID is required");
}

// Get order data from database
$currentOrder = getOrderById($orderId);
$customers = getCustomers();
$customerMap = array_column($customers, 'name', 'id');

if (!$currentOrder) {
    die("Order not found");
}

// Get order items from database
$items = getOrderItems($orderId);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= htmlspecialchars($currentOrder['order_id']) ?> - Print</title>
  <link rel="stylesheet" href="css/print_order.css">
</head>

<body>
    <!-- Print Actions (Visible only on screen) -->
    <div class="print-actions no-print">
        <button onclick="window.print()" class="btn">🖨️ Print Document</button>
        <button onclick="window.close()" class="btn btn-secondary">❌ Close</button>
        <a href="pipeline.php" class="btn btn-secondary">📊 Back to Pipeline</a>
    </div>

    <!-- Print Content -->
    <div class="print-container">
        <div class="page-header no-print">localhost/alpha/alpha/pipeline.php</div>


        <h1 class="order-header"># Order Information</h1>
        <div style="margin-bottom: 5px;">
            <strong>Order #:</strong> <?= htmlspecialchars($currentOrder['order_id']) ?> &nbsp;&nbsp;&nbsp;
            <strong>Client:</strong> <?= htmlspecialchars($customerMap[$currentOrder['customer_id']] ?? 'N/A') ?>
        </div>
        <div style="margin-bottom: 20px;">
            <strong>PO Date:</strong> <?= htmlspecialchars($currentOrder['po_date']) ?> &nbsp;&nbsp;&nbsp;
            <strong>Delivery:</strong> <?= htmlspecialchars($currentOrder['delivery_date'] ?? 'N/A') ?>
        </div>

        <div class="order-info-box">
            <div class="order-info-header"># Order Information</div>
            <div class="order-info-grid">
                <div>
                    <div class="info-item">
                        <span class="info-label">Order #:</span>
                        <span><?= htmlspecialchars($currentOrder['order_id']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">PO Date:</span>
                        <span><?= htmlspecialchars($currentOrder['po_date']) ?></span>
                    </div>
                </div>
                <div>
                    <div class="info-item">
                        <span class="info-label">Client:</span>
                        <span><?= htmlspecialchars($customerMap[$currentOrder['customer_id']] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Delivery:</span>
                        <span><?= htmlspecialchars($currentOrder['delivery_date'] ?? 'N/A') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php foreach ($items as $itemIndex => $item): ?>
            <div class="item-section">
                <div class="item-header">
                    ▶ <?= htmlspecialchars($item['Name'] ?? 'Unnamed Item') ?>
                    <span class="item-qty">Qty: <?= htmlspecialchars($item['quantity'] ?? '1') ?></span>
                </div>

                <?php if (!empty($item['Dimensions'])): ?>
                    <div class="item-details">
                        <strong>Dimensions:</strong> <?= htmlspecialchars($item['Dimensions']) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($item['Description'])): ?>
                    <div class="item-details">
                        <strong>Description:</strong> <?= htmlspecialchars($item['Description']) ?>
                    </div>
                <?php endif; ?>

                <!-- Stage 2: Raw Materials -->
                <?php if (!empty($item['raw_materials'])): ?>
                    <div class="stage-section">
                        <div class="stage-header">
                            <span><span class="stage-header-icon">🔧</span> Stage 2: Raw Materials Sourcing</span>
                            <span class="person-in-charge">Person in charge: Procurement Head</span>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>TYPE</th>
                                    <th>GRADE</th>
                                    <th>DIMENSIONS</th>
                                    <th>VENDOR</th>
                                    <th>DATE</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($item['raw_materials'] as $material): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($material['type']) ?></td>
                                        <td><?= htmlspecialchars($material['grade']) ?></td>
                                        <td><?= htmlspecialchars($material['dimensions']) ?></td>
                                        <td><?= htmlspecialchars($material['vendor']) ?></td>
                                        <td><?= htmlspecialchars($material['purchase_date']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Stage 3: Machining Processes -->
                <?php if (!empty($item['machining_processes'])): ?>
                    <div class="stage-section">
                        <div class="stage-header">
                            <span><span class="stage-header-icon">⚙️</span> Stage 3: Machining Processes</span>
                            <span class="person-in-charge">Person in charge: Production Manager</span>
                        </div>
                        <?php foreach ($item['machining_processes'] as $process): ?>
                            <div class="process-entry">
                                <div class="process-header">
                                    <span>
                                        <strong>Seq #<?= htmlspecialchars($process['sequence']) ?>: <?= htmlspecialchars($process['name']) ?></strong>
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $process['status'])) ?>">
                                            <?= htmlspecialchars($process['status']) ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="process-detail"><strong>Vendor:</strong> <?= htmlspecialchars($process['vendor']) ?></div>
                                <div class="process-detail"><strong>Start Date:</strong> <?= htmlspecialchars($process['start_date']) ?></div>
                                <div class="process-detail"><strong>Expected Completion:</strong> <?= htmlspecialchars($process['expected_completion']) ?></div>
                                <?php if (!empty($process['actual_completion'])): ?>
                                    <div class="process-detail"><strong>Actual Completion:</strong> <?= htmlspecialchars($process['actual_completion']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($process['remarks'])): ?>
                                    <div class="process-detail"><strong>Remarks:</strong> <?= htmlspecialchars($process['remarks']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($process['documents'])): ?>
                                    <div class="documents-list">
                                        <strong>Documents:</strong>
                                        <?php foreach ($process['documents'] as $docIndex => $doc): ?>
                                            📄 <?= htmlspecialchars($process['original_filenames'][$docIndex] ?? 'Document') ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Stage 4: Quality Inspection -->
                <?php if (!empty($item['inspection_data'])): ?>
                    <div class="stage-section">
                        <div class="stage-header">
                            <span><span class="stage-header-icon">🔍</span> Stage 4: Quality Inspection</span>
                            <span class="person-in-charge">Person in charge: QC Manager</span>
                        </div>
                        <?php foreach ($item['inspection_data'] as $inspIndex => $inspection): ?>
                            <div class="inspection-entry">
                                <div class="inspection-header">
                                    <span><strong>Inspection #<?= ($inspIndex + 1) ?>: <?= htmlspecialchars($inspection['type']) ?></strong>
                                        <span class="inspection-passed"><?= htmlspecialchars($inspection['status']) ?></span></span>
                                    <span style="font-size: 9px; color: #666;"><?= date('M j, Y', strtotime($inspection['inspection_date'])) ?></span>
                                </div>
                                <div class="process-detail"><strong>Technician:</strong> <?= htmlspecialchars($inspection['technician_name']) ?></div>
                                <div class="process-detail"><strong>Inspection Date:</strong> <?= htmlspecialchars($inspection['inspection_date']) ?></div>
                                <div class="process-detail"><strong>Inspection ID:</strong> <?= htmlspecialchars($inspection['inspection_id'] ?? 'N/A') ?></div>
                                <?php if (!empty($inspection['remarks'])): ?>
                                    <div class="process-detail"><strong>Remarks:</strong> <?= htmlspecialchars($inspection['remarks']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($inspection['documents'])): ?>
                                    <div class="documents-list">
                                        <strong>QC Reports/Documents:</strong>
                                        <?php foreach ($inspection['documents'] as $docIndex => $doc): ?>
                                            📄 <?= htmlspecialchars($inspection['original_filenames'][$docIndex] ?? 'Document') ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <!-- Inspection Summary -->
                        <div class="inspection-summary">
                            <div class="summary-title">📊 Inspection Summary</div>
                            <?php
                            $passCount = 0;
                            $reworkCount = 0;
                            $minorCount = 0;
                            foreach ($item['inspection_data'] as $insp) {
                                if ($insp['status'] === 'QC Passed') $passCount++;
                                elseif ($insp['status'] === 'Rework Required') $reworkCount++;
                                else $minorCount++;
                            }
                            ?>
                            <div>✓ Passed: <?= $passCount ?>   ○ Rework: <?= $reworkCount ?> &nbsp; △ Minor Issues: <?= $minorCount ?></div>
                            <div><strong>Total Inspections:</strong> <?= count($item['inspection_data']) ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Stage 5: Packaging -->
                <?php if (!empty($item['packaging_lots'])): ?>
                    <div class="stage-section">
                        <div class="stage-header">
                            <span><span class="stage-header-icon">📦</span> Stage 5: Packaging</span>
                            <span class="person-in-charge">Person in charge: Packaging Team</span>
                        </div>
                        <?php foreach ($item['packaging_lots'] as $lotIndex => $lot): ?>
                            <div class="packaging-lot">
                                <div class="lot-header">Lot #<?= ($lotIndex + 1) ?> - <?= htmlspecialchars($lot['products_in_lot']) ?> products</div>
                                <div class="packaging-grid">
                                    <div><strong>Packaging Type:</strong> <?= htmlspecialchars($lot['packaging_type']) ?></div>
                                    <div><strong>Date:</strong> <?= htmlspecialchars($lot['packaging_date']) ?></div>
                                    <div><strong>Packages:</strong> <?= htmlspecialchars($lot['num_packages']) ?></div>
                                    <div><strong>Net Weight:</strong> <?= htmlspecialchars($lot['net_weight']) ?> kg</div>
                                    <div><strong>Gross Weight:</strong> <?= htmlspecialchars($lot['gross_weight']) ?> kg</div>
                                    <div><strong>Docs Included:</strong> <?= htmlspecialchars($lot['docs_included']) ?></div>
                                </div>

                                <?php if (!empty($lot['fumigation_completed']) && $lot['fumigation_completed'] === 'Yes'): ?>
                                    <div class="fumigation-box">
                                        <strong>✅ Fumigation Details:</strong><br>
                                        Certificate #<?= htmlspecialchars($lot['fumigation_certificate_number']) ?> |
                                        Date: <?= htmlspecialchars($lot['fumigation_date']) ?> |
                                        Agency: <?= htmlspecialchars($lot['fumigation_agency']) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($lot['photos'])): ?>
                                    <div style="margin-top: 5px;">
                                        <strong>Product Photos:</strong><br>
                                        <?php foreach ($lot['photos'] as $photoIndex => $photo): ?>
                                            🖼 <?= htmlspecialchars($lot['original_photo_names'][$photoIndex] ?? 'Photo') ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Stage 6: Shipping Documentation -->
                                <?php if (!empty($lot['shipping_documents'])): ?>
                                    <div class="shipping-section">
                                        <strong>📋 Stage 6: Shipping Documentation - Lot #<?= ($lotIndex + 1) ?></strong>
                                        <span style="float: right; font-size: 9px;">Person in charge: Documentation Team</span>
                                        <div style="clear: both; margin-top: 5px;">
                                            <strong>Shipping Documents:</strong><br>
                                            <?php foreach ($lot['shipping_documents'] as $docIndex => $doc): ?>
                                                📄 <?= htmlspecialchars($lot['shipping_original_filenames'][$docIndex] ?? 'Document') ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Stage 7: Dispatch -->
                                <?php if (!empty($lot['dispatch_status']) && $lot['dispatch_status'] === 'Shipped'): ?>
                                    <div class="dispatch-section">
                                        <strong>🚚 Stage 7: Dispatch - Lot #<?= ($lotIndex + 1) ?></strong>
                                        <span style="float: right; font-size: 9px;">Person in charge: Logistics Team</span>
                                        <div style="clear: both; margin-top: 5px;">
                                            <strong>✅ Dispatched Successfully</strong><br>
                                            <strong>Dispatch Date:</strong> <?= htmlspecialchars($lot['dispatch_date']) ?><br>
                                            <strong>Transport:</strong> <?= htmlspecialchars($lot['transport_mode']) ?><br>
                                            <strong>Tracking #:</strong> <?= htmlspecialchars($lot['tracking_number']) ?><br>
                                            <?php if (!empty($lot['dispatch_remarks'])): ?>
                                                <strong>Remarks:</strong> <?= htmlspecialchars($lot['dispatch_remarks']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="page-footer">
            Page 1 of 1 • <?= date('m/d/y, g:i A') ?> • Alphasonix CRM
        </div>
    </div>

    <script>
        window.onload = function() {
            // Auto-print when page loads
            // setTimeout(function() {
            //     window.print();
            // }, 500);
        };
    </script>
</body>

</html>