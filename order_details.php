<?php
// order_details.php
session_start();
require_once 'functions.php';
require_once 'db.php';

// Check authentication
// requireAuth();

$order_id = $_GET['order_id'] ?? '';

if (empty($order_id)) {
    header('Location: orders.php');
    exit;
}

$conn = getDbConnection();

// Fetch order details
$stmt = $conn->prepare("
    SELECT o.*, c.name as customer_name, c.email, c.phone, c.address 
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.id 
    WHERE o.order_id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Order not found'];
    header('Location: orders.php');
    exit;
}

// Fetch order items
$itemsStmt = $conn->prepare("
    SELECT * FROM order_items 
    WHERE order_id = ? 
    ORDER BY id
");
$itemsStmt->execute([$order['id']]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$csrfToken = generateCsrfToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - <?= htmlspecialchars($order_id) ?> - Alphasonix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .order-header { background: white; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1.5rem; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 600; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-production { background-color: #d4edda; color: #155724; }
        .status-shipped { background-color: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content" style="margin-left: 280px; padding: 2rem;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="bi bi-file-text"></i> Order Details: <?= htmlspecialchars($order_id) ?></h3>
            <a href="orders.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Orders
            </a>
        </div>

        <!-- Order Header -->
        <div class="order-header">
            <div class="row">
                <div class="col-md-6">
                    <h5>Customer Information</h5>
                    <p><strong><?= htmlspecialchars($order['customer_name']) ?></strong><br>
                    <?= htmlspecialchars($order['email']) ?><br>
                    <?= htmlspecialchars($order['phone']) ?><br>
                    <?= htmlspecialchars($order['address']) ?></p>
                </div>
                <div class="col-md-6">
                    <h5>Order Information</h5>
                    <p>
                        <strong>PO Number:</strong> <?= htmlspecialchars($order['po_number']) ?: 'N/A' ?><br>
                        <strong>PO Date:</strong> <?= date('M j, Y', strtotime($order['po_date'])) ?><br>
                        <strong>Due Date:</strong> <?= date('M j, Y', strtotime($order['due_date'])) ?><br>
                        <strong>Status:</strong> 
                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $order['status'])) ?>">
                            <?= htmlspecialchars($order['status']) ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Order Items</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Serial No</th>
                                <th>Product Name</th>
                                <th>Dimensions</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['serial_no']) ?></td>
                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                    <td><?= htmlspecialchars($item['dimensions']) ?></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td>$<?= number_format($item['unit_price'], 2) ?></td>
                                    <td>$<?= number_format($item['total_price'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($item['item_status']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-end"><strong>Total:</strong></td>
                                <td><strong>$<?= number_format($order['total_amount'], 2) ?></strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>