<?php
session_start();
require_once 'functions.php';

// Handle adding a new product manually
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $sNo = sanitize_input($_POST['s_no']);
    $name = sanitize_input($_POST['name']);
    $dimensions = sanitize_input($_POST['dimensions']);

    if (!empty($sNo) && !empty($name) && !empty($dimensions)) {
        // Check if S.No already exists in DATABASE
        if (productExists($sNo)) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Product S.No already exists. Please use a different S.No.'];
        } else {
            $newProductData = [
                'S.No' => $sNo,
                'Name' => $name,
                'Dimensions' => $dimensions
            ];

            // Add to DATABASE
            if (addProduct($newProductData)) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Product added successfully to database!'];
                logActivity("Product added", "Product {$sNo} - {$name} added to database");
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Could not add product to database.'];
            }
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Please fill in all required fields.'];
    }

    header('Location: products.php');
    exit;
}

// Handle product update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $originalSNo = sanitize_input($_POST['original_s_no']);
    $newSNo = sanitize_input($_POST['s_no']);
    $name = sanitize_input($_POST['name']);
    $dimensions = sanitize_input($_POST['dimensions']);

    if (!empty($newSNo) && !empty($name) && !empty($dimensions)) {
        // Check if new S.No already exists in DATABASE (if it's different from original)
        if ($newSNo !== $originalSNo && productExists($newSNo)) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Product S.No already exists. Please use a different S.No.'];
        } else {
            $updateData = [
                'S.No' => $newSNo,
                'Name' => $name,
                'Dimensions' => $dimensions
            ];

            if (updateProduct($originalSNo, $updateData)) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Product updated successfully in database!'];
                logActivity("Product updated", "Product {$originalSNo} updated to {$newSNo}");
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Error updating product.'];
            }
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Please fill in all required fields.'];
    }

    header('Location: products.php');
    exit;
}

// Handle product deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $sNo = sanitize_input($_POST['s_no']);

    // Check if product is used in any orders in DATABASE
    if (productUsedInOrders($sNo)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Cannot delete product that is used in existing orders.'];
    } else {
        if (deleteProduct($sNo)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Product deleted successfully from database!'];
            logActivity("Product deleted", "Product {$sNo} deleted from database");
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error deleting product.'];
        }
    }

    header('Location: products.php');
    exit;
}

// Handle File Upload Logic - Store in DATABASE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_products'])) {
    if (isset($_FILES['product_csv']) && $_FILES['product_csv']['error'] == 0) {
        $allowed_mime_types = ['text/csv', 'application/csv', 'application/vnd.ms-excel', 'text/plain'];
        $uploaded_file_info = finfo_open(FILEINFO_MIME_TYPE);
        $uploaded_mime_type = finfo_file($uploaded_file_info, $_FILES['product_csv']['tmp_name']);
        finfo_close($uploaded_file_info);

        if (in_array($uploaded_mime_type, $allowed_mime_types)) {
            $temp_file = $_FILES['product_csv']['tmp_name'];
            $handle = fopen($temp_file, 'r');
            $header = fgetcsv($handle);
            fclose($handle);

            // Clean header - remove BOM and trim spaces
            $header = array_map(function($item) {
                $item = trim($item);
                // Remove UTF-8 BOM if present
                if (substr($item, 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf)) {
                    $item = substr($item, 3);
                }
                return $item;
            }, $header);

            $expected_header = ['S.No', 'Name', 'Dimensions'];

            if (count(array_intersect($header, $expected_header)) == count($expected_header)) {
                // Read CSV and insert into DATABASE
                $handle = fopen($temp_file, 'r');
                $header = fgetcsv($handle); // Skip header
                $imported = 0;
                $errors = 0;
                $error_details = [];
                
                while (($row = fgetcsv($handle)) !== FALSE) {
                    if (count($row) >= 3) {
                        $productData = array_combine($header, $row);
                        
                        // Clean the data
                        $productData = array_map('trim', $productData);
                        
                        // Check if product already exists in DATABASE
                        if (!productExists($productData['S.No'])) {
                            if (addProduct($productData)) {
                                $imported++;
                            } else {
                                $errors++;
                                $error_details[] = "Failed to add: {$productData['S.No']}";
                            }
                        } else {
                            $errors++;
                            $error_details[] = "Duplicate: {$productData['S.No']}";
                        }
                    }
                }
                fclose($handle);
                
                if ($imported > 0) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => "Product CSV imported successfully! $imported products added to database."];
                    logActivity("Products imported", "{$imported} products imported from CSV");
                } else {
                    $error_text = "No new products imported. $errors errors encountered.";
                    if (!empty($error_details)) {
                        $error_text .= " Issues: " . implode(', ', array_slice($error_details, 0, 5));
                        if (count($error_details) > 5) $error_text .= "...";
                    }
                    $_SESSION['message'] = ['type' => 'error', 'text' => $error_text];
                }
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Invalid CSV format. The header must contain: S.No, Name, Dimensions'];
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Invalid file type. Not a recognized CSV format.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: No file uploaded or an upload error occurred.'];
    }
    header('Location: products.php');
    exit;
}

// Get products from DATABASE
$products = getProducts();
$editProduct = null;

// Check if we're editing a product
if (isset($_GET['edit'])) {
    $editSNo = sanitize_input($_GET['edit']);
    foreach ($products as $product) {
        if ($product['serial_no'] === $editSNo) {
            $editProduct = [
                'S.No' => $product['serial_no'],
                'Name' => $product['name'],
                'Dimensions' => $product['dimensions']
            ];
            break;
        }
    }
}

// Filter products based on search query if provided
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = strtolower(trim($_GET['search']));
    $products = array_filter($products, function ($product) use ($search) {
        return strpos(strtolower($product['name']), $search) !== false ||
               strpos(strtolower($product['dimensions']), $search) !== false ||
               strpos(strtolower($product['serial_no']), $search) !== false;
    });
}

// Get current page for active state highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - Alphasonix</title>
    <style>
        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        /* Layout */
        .app-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
            border-right: 1px solid #dee2e6;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .sidebar-header {
            padding: 2rem 1.5rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
            background: rgba(67, 97, 238, 0.05);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 1.25rem;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
        }

        .logo-primary {
            font-size: 1.25rem;
            font-weight: 700;
            color: #212529;
            line-height: 1.2;
        }

        .logo-secondary {
            font-size: 0.75rem;
            color: #6c757d;
            font-weight: 500;
        }

        .sidebar-nav {
            padding: 1.5rem 0;
        }

        .nav-section {
            margin-bottom: 1.5rem;
        }

        .nav-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0 1.5rem 0.75rem;
        }

        .nav-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-item {
            margin: 0.25rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            color: #495057;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(67, 97, 238, 0.1), transparent);
            transition: left 0.5s;
        }

        .nav-link:hover::before {
            left: 100%;
        }

        .nav-link:hover {
            color: #4361ee;
            background: rgba(67, 97, 238, 0.05);
            border-left-color: #4361ee;
        }

        .nav-link.active {
            color: #4361ee;
            background: linear-gradient(90deg, rgba(67, 97, 238, 0.1), transparent);
            border-left-color: #4361ee;
            font-weight: 600;
        }

        .nav-link.active .nav-icon {
            color: #4361ee;
        }

        .nav-icon {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.125rem;
            transition: all 0.3s ease;
            color: #6c757d;
        }

        .nav-link:hover .nav-icon,
        .nav-link.active .nav-icon {
            color: #4361ee;
        }

        .user-profile {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1.5rem;
            background: rgba(67, 97, 238, 0.05);
            border-top: 1px solid #dee2e6;
        }

        .profile-content {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            color: #212529;
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 0.125rem;
        }

        .profile-role {
            color: #6c757d;
            font-size: 0.75rem;
        }

        .logout-btn {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.375rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logout-btn:hover {
            color: #e63946;
            background: rgba(230, 57, 70, 0.1);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }

        /* Product Management Header */
        .product-management-header {
            margin-bottom: 2rem;
        }

        .product-management-header h1 {
            font-size: 2rem;
            color: #212529;
            font-weight: 700;
        }

        /* Card Styles */
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
            margin-bottom: 2rem;
            overflow: hidden;
        }

        /* Management Grid */
        .management-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        /* Form Section */
        .form-section {
            padding: 1.5rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
        }

        .form-section h2 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: #212529;
            font-weight: 600;
        }

        .upload-info {
            background: #e7f3ff;
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .upload-info code {
            background: #fff;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-family: monospace;
            font-weight: 600;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #495057;
        }

        .form-input {
            padding: 0.75rem 1rem;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }

        .form-input.is-invalid {
            border-color: #e63946;
            box-shadow: 0 0 0 0.2rem rgba(230, 57, 70, 0.25);
        }

        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-success {
            background-color: #2a9d8f;
            color: white;
        }

        .btn-success:hover {
            background-color: #21867a;
        }

        .btn-primary {
            background-color: #4361ee;
            color: white;
        }

        .btn-primary:hover {
            background-color: #3a56d4;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .btn-danger {
            background-color: #e63946;
            color: white;
        }

        .btn-danger:hover {
            background-color: #d32f3c;
        }

        .btn-warning {
            background-color: #f39c12;
            color: white;
        }

        .btn-warning:hover {
            background-color: #e67e22;
        }

        .btn-info {
            background-color: #4cc9f0;
            color: white;
        }

        .btn-info:hover {
            background-color: #4361ee;
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }

        /* Search Section */
        .search-section {
            margin-bottom: 2rem;
        }

        .search-form {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .search-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }

        /* Table Section */
        .table-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .table-header h2 {
            font-size: 1.5rem;
            color: #212529;
            font-weight: 600;
        }

        .total-count {
            font-size: 1rem;
            color: #6c757d;
        }

        .total-count strong {
            color: #4361ee;
        }

        /* Table Styles */
        .products-table {
            width: 100%;
            border-collapse: collapse;
        }

        .products-table th {
            background-color: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 1px solid #dee2e6;
        }

        .products-table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }

        .product-row:hover {
            background-color: #f8f9fa;
        }

        .actions-cell {
            display: flex;
            gap: 0.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .empty-state h3 {
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        /* Message Alerts */
        .message-alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 0.375rem;
            font-weight: 600;
            transition: opacity 0.5s ease;
        }

        .message-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Delete Modal */
        .delete-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .delete-modal.show {
            opacity: 1;
            display: flex;
        }

        .delete-modal-content {
            width: 90%;
            max-width: 500px;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }

        .delete-modal.show .delete-modal-content {
            transform: translateY(0);
        }

        .delete-modal h3 {
            margin-bottom: 1rem;
            color: #212529;
            font-weight: 600;
        }

        .delete-modal p {
            margin-bottom: 1rem;
        }

        .text-secondary {
            color: #6c757d;
        }

        .delete-modal-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.mobile-open {
                transform: translateX(0);
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            }

            .main-content {
                margin-left: 0;
            }

            .mobile-menu-toggle {
                display: block;
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 1001;
                background: #4361ee;
                color: white;
                border: none;
                border-radius: 0.375rem;
                padding: 0.75rem;
                cursor: pointer;
                box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.1);
            }

            .management-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .actions-cell {
                flex-direction: column;
            }

            .products-table {
                display: block;
                overflow-x: auto;
            }

            .search-form {
                flex-direction: column;
                align-items: stretch;
            }

            .delete-modal-buttons {
                flex-direction: column;
            }
        }

        /* Custom scrollbar for sidebar */
        .sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #adb5bd;
            border-radius: 2px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #6c757d;
        }
    </style>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>

<body>
    <div class="app-container">
        <!-- Mobile Menu Toggle (hidden on desktop) -->
        <button class="mobile-menu-toggle" style="display: none;" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>

        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="home.php" class="logo">
                    <div class="logo-icon">
                        <i class="bi bi-building"></i>
                    </div>
                    <div class="logo-text">
                        <span class="logo-primary">Alphasonix</span>
                        <span class="logo-secondary">Manufacturing CRM</span>
                    </div>
                </a>
            </div>
            <?php include 'sidebar.php' ?>
            <!-- User Profile Section -->
            <div class="user-profile">
                <div class="profile-content">
                    <div class="profile-avatar">
                        <?php
                        $username = $_SESSION['username'] ?? 'User';
                        echo strtoupper(substr($username, 0, 2));
                        ?>
                    </div>
                    <div class="profile-info">
                        <div class="profile-name"><?= htmlspecialchars($username) ?></div>
                        <div class="profile-role"><?= ucfirst($_SESSION['role'] ?? 'User') ?></div>
                    </div>
                    <a href="dlogout.php" class="logout-btn" title="Logout">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Display messages -->
            <?php if (isset($_SESSION['message'])): ?>
                <?php
                $messageType = $_SESSION['message']['type'] === 'success' ? 'success' : 'error';
                echo "<div class='message-alert message-{$messageType}'>" .
                    htmlspecialchars($_SESSION['message']['text']) . "</div>";
                unset($_SESSION['message']);
                ?>
            <?php endif; ?>

            <!-- Product Management Header -->
            <div class="product-management-header">
                <h1>📦 Product Management</h1>
            </div>

            <!-- Management Grid -->
            <div class="management-grid">
                <!-- CSV Upload Section -->
                <div class="form-section upload-section">
                    <h2>📤 Upload Product List</h2>
                    <form action="products.php" method="post" enctype="multipart/form-data">
                        <div class="upload-info">
                            <strong>📋 CSV Format Requirements:</strong><br>
                            The file must have exactly this header: <code>S.No,Name,Dimensions</code><br>
                            This will add new products to your DATABASE.
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="product_csv">Select CSV File:</label>
                            <input type="file" id="product_csv" name="product_csv" accept=".csv" class="form-input" required>
                        </div>

                        <button type="submit" name="upload_products" class="btn btn-success">
                            📤 Upload Products to Database
                        </button>
                    </form>
                </div>

                <!-- Manual Add/Edit Section -->
                <div class="form-section manual-add-section <?= $editProduct ? 'edit-form' : '' ?>">
                    <h2><?= $editProduct ? '✏️ Edit Product' : '➕ Add New Product' ?></h2>
                    <form action="products.php" method="post">
                        <?php if ($editProduct): ?>
                            <input type="hidden" name="original_s_no" value="<?= htmlspecialchars($editProduct['S.No']) ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label class="form-label" for="s_no">Serial Number:</label>
                            <input type="text" id="s_no" name="s_no" class="form-input"
                                value="<?= $editProduct ? htmlspecialchars($editProduct['S.No']) : '' ?>"
                                placeholder="e.g., PROD001" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="name">Product Name:</label>
                            <input type="text" id="name" name="name" class="form-input"
                                value="<?= $editProduct ? htmlspecialchars($editProduct['Name']) : '' ?>"
                                placeholder="Enter product name" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="dimensions">Dimensions:</label>
                            <input type="text" id="dimensions" name="dimensions" class="form-input"
                                value="<?= $editProduct ? htmlspecialchars($editProduct['Dimensions']) : '' ?>"
                                placeholder="e.g., 100x50x25mm" required>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="<?= $editProduct ? 'update_product' : 'add_product' ?>"
                                class="btn <?= $editProduct ? 'btn-warning' : 'btn-info' ?>">
                                <?= $editProduct ? '✏️ Update Product' : '➕ Add Product' ?>
                            </button>

                            <?php if ($editProduct): ?>
                                <a href="products.php" class="btn btn-secondary">❌ Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Search Section -->
            <div class="search-section">
                <form method="get" action="products.php" class="search-form">
                    <input type="text" id="search" name="search" class="search-input"
                        value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                        placeholder="🔍 Search by S.No, Name, or Dimensions...">
                    <button type="submit" class="btn btn-primary">🔍 Search</button>
                    <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                        <a href="products.php" class="btn btn-secondary">❌ Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Products Table -->
            <div class="table-section">
                <div class="table-header">
                    <h2>📋 Product Catalog</h2>
                    <div class="total-count">
                        <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                            Found: <strong><?= count($products) ?></strong> products
                        <?php else: ?>
                            Total: <strong><?= count($products) ?></strong> products
                        <?php endif; ?>
                    </div>
                </div>

                <table class="products-table">
                    <thead>
                        <tr>
                            <th>🔢 S.No</th>
                            <th>📦 Product Name</th>
                            <th>📏 Dimensions</th>
                            <th>🛠️ Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="4" class="empty-state">
                                    <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                                        <h3>🔍 No products found</h3>
                                        <p>No products match your search criteria.</p>
                                    <?php else: ?>
                                        <h3>📭 No products available</h3>
                                        <p>Upload a CSV file or add products manually to get started.</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr class="product-row">
                                    <td><strong><?= htmlspecialchars($product['serial_no']) ?></strong></td>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><?= htmlspecialchars($product['dimensions']) ?></td>
                                    <td class="actions-cell">
                                        <a href="products.php?edit=<?= urlencode($product['serial_no']) ?>" class="btn btn-warning btn-small"
                                            title="Edit Product">
                                            ✏️ Edit
                                        </a>
                                        <button type="button"
                                            onclick="confirmDelete('<?= htmlspecialchars($product['serial_no']) ?>', '<?= htmlspecialchars($product['name']) ?>')"
                                            class="btn btn-danger btn-small" title="Delete Product">
                                            🗑️ Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="delete-modal">
        <div class="delete-modal-content">
            <h3>🗑️ Confirm Deletion</h3>
            <p>Are you sure you want to delete product <strong id="productNameToDelete"></strong>?</p>
            <p class="text-secondary">This action cannot be undone.</p>

            <div class="delete-modal-buttons">
                <form id="deleteForm" method="post" style="display: inline;">
                    <input type="hidden" name="s_no" id="productSNoToDelete">
                    <button type="submit" name="delete_product" class="btn btn-danger">
                        🗑️ Yes, Delete
                    </button>
                </form>
                <button type="button" onclick="closeDeleteModal()" class="btn btn-secondary">
                    ❌ Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        // Delete confirmation modal
        function confirmDelete(sNo, productName) {
            document.getElementById('productSNoToDelete').value = sNo;
            document.getElementById('productNameToDelete').textContent = productName;
            const modal = document.getElementById('deleteModal');
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
        }

        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeleteModal();
            }
        });

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const inputs = this.querySelectorAll('input[required]');
                let hasEmpty = false;

                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        hasEmpty = true;
                        input.classList.add('is-invalid');
                    } else {
                        input.classList.remove('is-invalid');
                    }
                });

                if (hasEmpty) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });

        // Success/Error message auto-hide
        document.addEventListener('DOMContentLoaded', function() {
            const messageAlerts = document.querySelectorAll('.message-alert');
            messageAlerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });

        // Sidebar toggle for mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('mobile-open');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.mobile-menu-toggle');

            if (window.innerWidth <= 1024 &&
                !sidebar.contains(event.target) &&
                !toggleBtn.contains(event.target)) {
                sidebar.classList.remove('mobile-open');
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const mobileToggle = document.querySelector('.mobile-menu-toggle');

            if (window.innerWidth > 1024) {
                sidebar.classList.remove('mobile-open');
                mobileToggle.style.display = 'none';
            } else {
                mobileToggle.style.display = 'block';
            }
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const mobileToggle = document.querySelector('.mobile-menu-toggle');
            if (window.innerWidth <= 1024) {
                mobileToggle.style.display = 'block';
            }
        });
    </script>
</body>

</html>