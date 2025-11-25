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
    <style>
        :root {
            --primary-blue: #4a90e2;
            --secondary-blue: #5bb0e8;
            --light-blue: #a8edea;
            --accent-blue: #2c7bd6;
            --white: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --border-radius: 8px;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--white) 100%);
            color: var(--gray-800);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--primary-blue);
            color: var(--white);
            text-decoration: none;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            transition: all 0.2s ease;
        }

        .back-link:hover {
            background: var(--accent-blue);
            transform: translateY(-1px);
            box-shadow: var(--shadow-hover);
        }

        h1 {
            color: var(--gray-900);
            margin-bottom: 25px;
            font-size: 2rem;
            font-weight: 700;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-500);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .order-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .order-link {
            text-decoration: none;
            color: inherit;
        }

        .order-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            padding: 0;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.2s ease;
        }

        .order-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
            border-color: var(--primary-blue);
        }

        .order-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--light-blue) 100%);
            color: var(--white);
            padding: 20px;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .order-details {
            padding: 20px;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-200);
        }

        .detail-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .detail-label {
            color: var(--gray-600);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-chip {
            background: var(--primary-blue);
            color: var(--white);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
    </style>
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
    <style>
        :root {
            --primary-blue: #4a90e2;
            --secondary-blue: #5bb0e8;
            --light-blue: #a8edea;
            --accent-blue: #2c7bd6;
            --white: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --border-radius: 8px;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--white) 100%);
            color: var(--gray-800);
            line-height: 1.6;
            min-height: 100vh;
            font-size: 14px;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header Section */
        .header-section {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--light-blue) 100%);
            color: var(--white);
            padding: 30px;
            border-radius: var(--border-radius);
            text-align: center;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }

        .header-section h1 {
            font-size: 1.8rem;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .header-section p {
            font-size: 1rem;
            opacity: 0.9;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            justify-content: center;
            margin-bottom: 25px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--white);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
            transform: translateY(-1px);
            box-shadow: var(--shadow-hover);
        }

        .btn-back {
            background: var(--primary-blue);
            color: var(--white);
            border: 1px solid var(--primary-blue);
        }

        .btn-back:hover {
            background: var(--secondary-blue);
            border-color: var(--secondary-blue);
        }

        /* Order Info Grid */
        .order-info-section {
            background: var(--white);
            padding: 25px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow);
        }

        .order-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .info-card {
            background: var(--gray-50);
            padding: 16px;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-200);
            transition: all 0.2s ease;
        }

        .info-card:hover {
            border-color: var(--primary-blue);
            box-shadow: var(--shadow-hover);
        }

        .info-label {
            font-size: 0.75rem;
            color: var(--gray-600);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 1rem;
            color: var(--gray-900);
            font-weight: 600;
        }

        /* Status Badge Styles */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .status-pending { background: var(--gray-200); color: var(--gray-700); }
        .status-sourcing-material { background: var(--light-blue); color: var(--primary-blue); }
        .status-in-production { background: #fef3c7; color: #92400e; }
        .status-ready-for-qc { background: #fed7aa; color: #9a3412; }
        .status-qc-completed { background: #d1fae5; color: #065f46; }
        .status-packaging { background: #e9d5ff; color: #6b21a8; }
        .status-ready-for-dispatch { background: var(--light-blue); color: var(--primary-blue); }
        .status-shipped { background: #d1fae5; color: #065f46; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-in-progress { background: #fef3c7; color: #92400e; }
        .status-not-started { background: var(--gray-200); color: var(--gray-700); }
        .status-qc-passed { background: #d1fae5; color: #065f46; }
        .status-rework-required { background: #fee2e2; color: #991b1b; }

        /* Stage Container Styles */
        .stage-container {
            background: var(--white);
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .stage-header {
            background: var(--primary-blue);
            color: var(--white);
            padding: 16px 20px;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stage-icon {
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .stage-content {
            padding: 20px;
        }

        /* Item Section Styles */
        .item-section {
            background: var(--white);
            margin-bottom: 25px;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .item-header {
            background: var(--gray-50);
            padding: 20px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-header div:first-child {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .item-quantity-badge {
            background: var(--primary-blue);
            color: var(--white);
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Data Table Styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background: var(--white);
            border-radius: var(--border-radius);
            overflow: hidden;
            border: 1px solid var(--gray-200);
        }

        .data-table thead {
            background: var(--gray-50);
        }

        .data-table th {
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            border-bottom: 1px solid var(--gray-200);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
            font-size: 0.9rem;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .data-table tbody tr:hover {
            background-color: var(--gray-50);
        }

        /* Process Box Styles */
        .process-box {
            background: var(--white);
            border: 1px solid var(--gray-200);
            padding: 20px;
            margin: 15px 0;
            border-radius: var(--border-radius);
            transition: all 0.2s ease;
        }

        .process-box:hover {
            border-color: var(--primary-blue);
            box-shadow: var(--shadow-hover);
        }

        .process-box h4 {
            color: var(--gray-900);
            margin-bottom: 15px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .process-sequence {
            background: var(--primary-blue);
            color: var(--white);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            min-width: 20px;
            text-align: center;
            font-weight: 600;
        }

        .process-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .process-grid-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .process-grid-label {
            font-size: 0.75rem;
            color: var(--gray-600);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .process-grid-value {
            font-size: 0.9rem;
            color: var(--gray-800);
            font-weight: 500;
        }

        /* Image Gallery Styles */
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }

        .image-item {
            position: relative;
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            overflow: hidden;
            transition: all 0.2s ease;
        }

        .image-item:hover {
            border-color: var(--primary-blue);
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .image-wrapper {
            position: relative;
            width: 100%;
            padding-top: 100%;
            overflow: hidden;
        }

        .image-wrapper img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.2s ease;
            cursor: pointer;
        }

        .image-item:hover .image-wrapper img {
            transform: scale(1.05);
        }

        .image-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.7) 0%, transparent 100%);
            padding: 10px;
            transform: translateY(100%);
            transition: transform 0.2s ease;
        }

        .image-item:hover .image-overlay {
            transform: translateY(0);
        }

        .image-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.9);
            color: var(--gray-700);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .btn-icon:hover {
            background: var(--white);
            color: var(--primary-blue);
            transform: scale(1.1);
        }

        /* Document List Styles */
        .document-list {
            list-style: none;
            padding: 0;
            margin: 10px 0;
        }

        .document-item {
            background: var(--white);
            border: 1px solid var(--gray-200);
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: var(--border-radius);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
        }

        .document-item:hover {
            border-color: var(--primary-blue);
            background: var(--gray-50);
        }

        .document-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .document-icon {
            width: 36px;
            height: 36px;
            background: var(--primary-blue);
            color: var(--white);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .document-name {
            color: var(--gray-800);
            font-weight: 500;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .document-name:hover {
            color: var(--primary-blue);
        }

        .document-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        /* Specification Box */
        .spec-box {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            padding: 20px;
            margin: 15px 0;
        }

        .spec-box h3 {
            color: var(--gray-900);
            margin-bottom: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .spec-item {
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-200);
        }

        .spec-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .spec-label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 4px;
            font-size: 0.9rem;
        }

        .spec-value {
            color: var(--gray-600);
            line-height: 1.5;
            font-size: 0.9rem;
        }

        /* Remarks Box */
        .remarks-box {
            background: var(--gray-50);
            padding: 15px;
            border-radius: var(--border-radius);
            margin: 15px 0;
            border-left: 4px solid var(--primary-blue);
        }

        .remarks-box strong {
            color: var(--gray-700);
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .remarks-box p {
            color: var(--gray-600);
            margin: 0;
            line-height: 1.5;
            font-size: 0.9rem;
        }

        /* Stats Box */
        .stats-box {
            background: var(--light-blue);
            border: 1px solid var(--primary-blue);
            border-radius: var(--border-radius);
            padding: 20px;
            margin: 15px 0;
        }

        .stats-box h4 {
            color: var(--primary-blue);
            margin-bottom: 15px;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Change History Section */
        .change-history-section {
            background: var(--white);
            margin: 20px 0;
            border-radius: var(--border-radius);
            padding: 20px;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow);
        }

        .change-history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-200);
        }

        .change-history-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .change-entry {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: var(--border-radius);
            transition: all 0.2s ease;
        }

        .change-entry:hover {
            border-color: var(--primary-blue);
            background: var(--white);
        }

        .change-meta {
            display: flex;
            gap: 15px;
            font-size: 0.75rem;
            color: var(--gray-600);
            margin-bottom: 6px;
            flex-wrap: wrap;
        }

        .change-details {
            font-size: 0.9rem;
            color: var(--gray-800);
            line-height: 1.4;
        }

        /* Footer Section */
        .footer-section {
            background: var(--gray-50);
            margin-top: 30px;
            padding: 25px;
            border-radius: var(--border-radius);
            text-align: center;
            border: 1px solid var(--gray-200);
            color: var(--gray-600);
        }

        .footer-section p {
            margin: 4px 0;
            font-size: 0.9rem;
        }

        /* Tooltip Styles */
        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 160px;
            background-color: var(--gray-800);
            color: var(--white);
            text-align: center;
            padding: 6px 10px;
            border-radius: 4px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -80px;
            opacity: 0;
            transition: opacity 0.2s;
            font-size: 0.75rem;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        /* Empty State */
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-500);
        }

        .no-data-icon {
            font-size: 2.5rem;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        .no-data p {
            font-size: 1rem;
            margin: 0;
        }

        /* Back to Top Button */
        #backToTop {
            display: none;
            position: fixed;
            bottom: 25px;
            right: 25px;
            z-index: 99;
            font-size: 16px;
            border: none;
            outline: none;
            background-color: var(--primary-blue);
            color: var(--white);
            cursor: pointer;
            padding: 10px 14px;
            border-radius: 50%;
            box-shadow: var(--shadow-hover);
            transition: all 0.2s ease;
        }

        #backToTop:hover {
            background-color: var(--secondary-blue);
            transform: translateY(-2px);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.9);
        }

        .modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90vh;
            margin-top: 5vh;
            border-radius: var(--border-radius);
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 25px;
            color: #f1f1f1;
            font-size: 35px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .modal-close:hover {
            color: var(--white);
            transform: scale(1.1);
        }

        #caption {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 600px;
            text-align: center;
            color: #ccc;
            padding: 8px 0;
            font-size: 0.9rem;
        }

        /* Print Styles */
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                margin: 0;
                padding: 10px;
                background: var(--white);
                font-size: 12px;
            }

            .main-container {
                max-width: 100%;
                padding: 0;
            }

            .stage-container,
            .item-section {
                page-break-inside: avoid;
                margin-bottom: 15px;
            }

            .header-section {
                background: var(--white) !important;
                color: var(--gray-900) !important;
                border: 1px solid var(--gray-300);
            }

            .stage-header {
                background: var(--gray-100) !important;
                color: var(--gray-900) !important;
                border: 1px solid var(--gray-300);
            }

            .item-header {
                background: var(--gray-100) !important;
                color: var(--gray-900) !important;
                border: 1px solid var(--gray-300);
            }

            .status-badge {
                border: 1px solid var(--gray-400);
            }

            .btn {
                display: none !important;
            }

            .image-overlay {
                display: none !important;
            }

            .process-box:hover,
            .document-item:hover,
            .change-entry:hover {
                transform: none !important;
                box-shadow: none !important;
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-container {
                padding: 15px;
            }

            .header-section {
                padding: 20px;
            }

            .header-section h1 {
                font-size: 1.5rem;
            }

            .order-info-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .action-buttons {
                margin-bottom: 20px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .process-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .image-gallery {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 12px;
            }

            .item-header {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .stage-header {
                flex-direction: column;
                gap: 8px;
                align-items: flex-start;
            }

            .change-meta {
                flex-direction: column;
                gap: 8px;
            }

            .data-table {
                font-size: 0.8rem;
            }

            .data-table th,
            .data-table td {
                padding: 8px 10px;
            }
        }

        @media (max-width: 480px) {
            .main-container {
                padding: 10px;
            }

            .header-section {
                padding: 15px;
            }

            .header-section h1 {
                font-size: 1.3rem;
            }

            .stage-content {
                padding: 15px;
            }

            .process-box {
                padding: 15px;
            }

            .image-gallery {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Utility Classes */
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .mb-0 { margin-bottom: 0; }
        .mt-0 { margin-top: 0; }
        .mb-1 { margin-bottom: 8px; }
        .mt-1 { margin-top: 8px; }
        .mb-2 { margin-bottom: 16px; }
        .mt-2 { margin-top: 16px; }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, .3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
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