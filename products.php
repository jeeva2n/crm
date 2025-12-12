<?php
session_start();
require_once 'functions.php';
require_once 'db.php';

// Define a constant to prevent function redeclaration
define('PRODUCTS_PHP_LOADED', true);

// Check authentication
// requireAuth();

// Check rate limiting
// if (!checkRateLimit('products_page', $_SESSION['user_id'], 100, 60)) {
//     die('Rate limit exceeded. Please try again later.');
// }

// Define upload directories
$uploadDir = 'uploads/products/';
$thumbDir = 'uploads/products/thumbs/';

// Ensure directories exist
if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);
if (!file_exists($thumbDir)) mkdir($thumbDir, 0755, true);

// ==========================================
// ENHANCED PRODUCT FUNCTIONS
// ==========================================

function handleProductImageUpload($file, $productSNo) {
    global $uploadDir, $thumbDir;
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        return ['success' => false, 'error' => 'Invalid file extension.'];
    }
    
    // Check file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File size too large. Maximum 5MB allowed.'];
    }
    
    // Generate secure filename
    $cleanSNo = preg_replace('/[^a-zA-Z0-9]/', '_', $productSNo);
    $filename = 'prod_' . $cleanSNo . '_' . uniqid() . '.' . $ext;
    
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        // Create thumbnail
        createThumbnail($uploadDir . $filename, $thumbDir . $filename, 200, 200);
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'error' => 'Failed to upload file.'];
}

function getProductsEnhanced($search = '', $category = '', $stockFilter = '', $page = 1, $perPage = 20) {
    $conn = getDbConnection();
    
    $conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $conditions[] = "(serial_no LIKE ? OR name LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam]);
    }
    
    if (!empty($category)) {
        $conditions[] = "category = ?";
        $params[] = $category;
    }
    
    if (!empty($stockFilter)) {
        switch ($stockFilter) {
            case 'in_stock':
                $conditions[] = "stock_quantity > 10";
                break;
            case 'low_stock':
                $conditions[] = "stock_quantity BETWEEN 1 AND 10";
                break;
            case 'out_of_stock':
                $conditions[] = "stock_quantity = 0";
                break;
        }
    }
    
    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
    
    // Get total count
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM products $whereClause");
    $countStmt->execute($params);
    $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $total = $totalResult['total'];
    
    // Get paginated results
    $offset = ($page - 1) * $perPage;
    
    $sql = "SELECT * FROM products $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    
    // Bind parameters
    $boundParams = $params;
    $boundParams[] = $perPage;
    $boundParams[] = $offset;
    
    $stmt->execute($boundParams);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'products' => $products,
        'total' => $total
    ];
}

function getProductStatistics() {
    $conn = getDbConnection();
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_products,
                COUNT(DISTINCT category) as total_categories,
                SUM(CASE WHEN stock_quantity > 10 THEN 1 ELSE 0 END) as in_stock_count,
                SUM(CASE WHEN stock_quantity BETWEEN 1 AND 10 THEN 1 ELSE 0 END) as low_stock_count,
                SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock_count,
                COALESCE(SUM(stock_quantity), 0) as total_inventory_value
            FROM products
        ");
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_products' => $result['total_products'] ?? 0,
            'total_categories' => $result['total_categories'] ?? 0,
            'in_stock_count' => $result['in_stock_count'] ?? 0,
            'low_stock_count' => $result['low_stock_count'] ?? 0,
            'out_of_stock_count' => $result['out_of_stock_count'] ?? 0,
            'total_inventory_value' => $result['total_inventory_value'] ?? 0
        ];
    } catch (Exception $e) {
        error_log("Product statistics error: " . $e->getMessage());
        return [
            'total_products' => 0,
            'total_categories' => 0,
            'in_stock_count' => 0,
            'low_stock_count' => 0,
            'out_of_stock_count' => 0,
            'total_inventory_value' => 0
        ];
    }
}

function importProductsFromCSV($filePath) {
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        return ['success' => false, 'error' => 'Failed to open file'];
    }
    
    // Read and clean header
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        return ['success' => false, 'error' => 'Empty CSV file'];
    }
    
    $header = array_map(function($h) {
        return trim(str_replace("\xEF\xBB\xBF", '', $h));
    }, $header);

    // Map headers to database columns
    $map = array_flip($header);
    
    // Check required columns
    if (!isset($map['S.No']) || !isset($map['Name']) || !isset($map['Dimensions'])) {
        fclose($handle);
        return ['success' => false, 'error' => 'CSV must contain S.No, Name, and Dimensions columns'];
    }

    $conn = getDbConnection();
    $imported = 0;
    $updated = 0;
    $errors = [];
    
    // Prepare statement for insert/update
    $stmt = $conn->prepare("
        INSERT INTO products (serial_no, name, dimensions, category, price, stock_quantity, image) 
        VALUES (?, ?, ?, ?, ?, ?, '')
        ON DUPLICATE KEY UPDATE 
        name = VALUES(name), 
        dimensions = VALUES(dimensions), 
        category = VALUES(category),
        price = VALUES(price), 
        stock_quantity = VALUES(stock_quantity)
    ");

    $rowNum = 1;
    while (($row = fgetcsv($handle)) !== FALSE) {
        $rowNum++;
        
        if (count($row) < 3) {
            $errors[] = "Row $rowNum: Insufficient columns";
            continue;
        }

        try {
            $sNo = trim($row[$map['S.No']] ?? '');
            $name = trim($row[$map['Name']] ?? '');
            $dimensions = trim($row[$map['Dimensions']] ?? '');
            
            // Skip empty rows
            if (empty($sNo) || empty($name)) {
                $errors[] = "Row $rowNum: Missing serial number or name";
                continue;
            }
            
            $category = isset($map['Category']) ? trim($row[$map['Category']] ?? '') : '';
            
            // Clean and validate price
            $priceStr = isset($map['Price']) ? $row[$map['Price']] : '0';
            $price = floatval(preg_replace('/[^0-9.]/', '', $priceStr));
            
            // Clean and validate stock quantity
            $stockStr = isset($map['StockQuantity']) ? $row[$map['StockQuantity']] : '0';
            $stockQuantity = intval(preg_replace('/[^0-9]/', '', $stockStr));
            
            if ($stmt->execute([$sNo, $name, $dimensions, $category, $price, $stockQuantity])) {
                if ($stmt->rowCount() == 1) {
                    $imported++; // New insert
                } else {
                    $updated++; // Update on duplicate
                }
            }
        } catch (Exception $e) {
            $errors[] = "Row $rowNum: " . $e->getMessage();
        }
    }
    
    fclose($handle);
    
    if ($imported > 0 || $updated > 0) {
        $message = "";
        if ($imported > 0) $message .= "$imported imported";
        if ($updated > 0) $message .= ($imported > 0 ? ", " : "") . "$updated updated";
        
        return [
            'success' => true, 
            'message' => $message,
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors
        ];
    } else {
        $errorMsg = 'No products imported. ';
        if (!empty($errors)) {
            $errorMsg .= 'Errors: ' . implode('; ', array_slice($errors, 0, 5));
            if (count($errors) > 5) $errorMsg .= '... and ' . (count($errors) - 5) . ' more';
        }
        return ['success' => false, 'error' => $errorMsg];
    }
}

function exportProductsToCSV() {
    requireRole('manager'); 
    
    $conn = getDbConnection();
    $stmt = $conn->query("
        SELECT 
            serial_no as 'S.No', 
            name as 'Name', 
            dimensions as 'Dimensions', 
            category as 'Category', 
            price as 'Price', 
            stock_quantity as 'StockQuantity',
            created_at as 'Date Added'
        FROM products 
        ORDER BY created_at DESC
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=products_export_' . date('Y-m-d_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");
    
    if (!empty($products)) {
        // Write headers
        fputcsv($output, array_keys($products[0]));
        
        // Write data
        foreach ($products as $row) {
            fputcsv($output, $row);
        }
    } else {
        // Write empty headers if no products
        fputcsv($output, ['S.No', 'Name', 'Dimensions', 'Category', 'Price', 'StockQuantity', 'Date Added']);
    }
    
    fclose($output);
    logActivity("Products exported", "Product catalog exported to CSV");
    exit;
}

// ==========================================
// REQUEST HANDLING
// ==========================================

// Handle ADD PRODUCT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token.'];
        header('Location: products.php');
        exit;
    }
    
    $sNo = sanitize_input($_POST['s_no']);
    $name = sanitize_input($_POST['name']);
    $dimensions = sanitize_input($_POST['dimensions']);
    $category = sanitize_input($_POST['category'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock_quantity = intval($_POST['stock_quantity'] ?? 0);

    // Validation
    if (empty($sNo) || empty($name) || empty($dimensions)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Serial Number, Name, and Dimensions are required.'];
        header('Location: products.php');
        exit;
    }

    if ($price < 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Price cannot be negative.'];
        header('Location: products.php');
        exit;
    }

    if ($stock_quantity < 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Stock quantity cannot be negative.'];
        header('Location: products.php');
        exit;
    }

    try {
        $imageFilename = '';
        // Handle product image upload
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
            $uploadResult = handleProductImageUpload($_FILES['product_image'], $sNo);
            if ($uploadResult['success']) {
                $imageFilename = $uploadResult['filename'];
            } else {
                $_SESSION['message'] = ['type' => 'warning', 'text' => 'Product added but image failed: ' . $uploadResult['error']];
            }
        }

        $conn = getDbConnection();
        $stmt = $conn->prepare("
            INSERT INTO products (serial_no, name, dimensions, category, price, stock_quantity, image) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$sNo, $name, $dimensions, $category, $price, $stock_quantity, $imageFilename])) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Product added successfully!'];
            logActivity("Product added", "Product {$sNo} - {$name} added to catalog");
            
            // Send notification to inventory manager
            sendEmailNotification('inventory@alphasonix.com', 'New Product Added', 
                "New product added:\nSerial: $sNo\nName: $name\nPrice: $$price\nStock: $stock_quantity");
                
            header('Location: products.php?new=' . urlencode($sNo));
            exit;
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Database error: Failed to add product.'];
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Serial Number already exists.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
        }
    } catch (Exception $e) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
    }

    header('Location: products.php');
    exit;
}

// Handle UPDATE PRODUCT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token.'];
        header('Location: products.php');
        exit;
    }
    
    $originalSNo = sanitize_input($_POST['original_s_no']);
    $newSNo = sanitize_input($_POST['s_no']);
    $name = sanitize_input($_POST['name']);
    $dimensions = sanitize_input($_POST['dimensions']);
    $category = sanitize_input($_POST['category'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock_quantity = intval($_POST['stock_quantity'] ?? 0);

    // Validation
    if (empty($newSNo) || empty($name) || empty($dimensions)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Serial Number, Name, and Dimensions are required.'];
        header('Location: products.php');
        exit;
    }

    try {
        $conn = getDbConnection();
        
        $imageClause = "";
        $params = [$newSNo, $name, $dimensions, $category, $price, $stock_quantity];
        
        // Handle Image Update
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
            $uploadResult = handleProductImageUpload($_FILES['product_image'], $newSNo);
            if ($uploadResult['success']) {
                $imageClause = ", image = ?";
                $params[] = $uploadResult['filename'];
                
                // Remove old image if it exists
                $stmt = $conn->prepare("SELECT image FROM products WHERE serial_no = ?");
                $stmt->execute([$originalSNo]);
                $oldImg = $stmt->fetchColumn();
                if ($oldImg && !empty($oldImg)) {
                    @unlink($uploadDir . $oldImg);
                    @unlink($thumbDir . $oldImg);
                }
            }
        }
        
        $params[] = $originalSNo; // WHERE clause param

        $sql = "UPDATE products SET serial_no=?, name=?, dimensions=?, category=?, price=?, stock_quantity=? $imageClause WHERE serial_no=?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute($params)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Product updated successfully!'];
            logActivity("Product updated", "Product {$originalSNo} updated");
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Update failed.'];
        }
    } catch (Exception $e) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
    }

    header('Location: products.php');
    exit;
}

// Handle DELETE PRODUCT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Security token invalid.'];
        header('Location: products.php');
        exit;
    }
    
    $sNo = sanitize_input($_POST['s_no']);

    try {
        $conn = getDbConnection();
        
        // Check if product is used in any orders
        $checkStmt = $conn->prepare("
            SELECT COUNT(*) as usage_count 
            FROM order_items oi 
            JOIN orders o ON oi.order_id = o.id 
            WHERE oi.serial_no = ?
        ");
        $checkStmt->execute([$sNo]);
        $usage = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usage['usage_count'] > 0) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Cannot delete product. It is used in existing orders.'];
            header('Location: products.php');
            exit;
        }
        
        // Get image info before delete
        $imgStmt = $conn->prepare("SELECT image FROM products WHERE serial_no = ?");
        $imgStmt->execute([$sNo]);
        $img = $imgStmt->fetchColumn();

        // Delete the product
        $stmt = $conn->prepare("DELETE FROM products WHERE serial_no = ?");
        if ($stmt->execute([$sNo])) {
            // Delete associated files
            if ($img && !empty($img)) {
                @unlink($uploadDir . $img);
                @unlink($thumbDir . $img);
            }
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Product deleted successfully.'];
            logActivity("Product deleted", "Product {$sNo} deleted from catalog");
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error deleting product.'];
        }
    } catch (Exception $e) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
    }

    header('Location: products.php');
    exit;
}

// Handle CSV UPLOAD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_products'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token.'];
    } elseif (isset($_FILES['product_csv']) && $_FILES['product_csv']['error'] == 0) {
        
        $result = importProductsFromCSV($_FILES['product_csv']['tmp_name']);
        
        if ($result['success']) {
            $message = "Import successful: " . $result['message'];
            if (!empty($result['errors'])) {
                $message .= ". Some errors occurred: " . implode('; ', array_slice($result['errors'], 0, 3));
                if (count($result['errors']) > 3) {
                    $message .= " and " . (count($result['errors']) - 3) . " more";
                }
            }
            $_SESSION['message'] = ['type' => 'success', 'text' => $message];
            logActivity("Product Import", "Imported products from CSV: " . $result['message']);
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => $result['error']];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Please select a valid CSV file to upload.'];
    }
    header('Location: products.php');
    exit;
}

// Handle EXPORT
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    exportProductsToCSV();
    exit;
}

// ==========================================
// DATA FETCHING FOR DISPLAY
// ==========================================

// Get parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$stockFilter = $_GET['stock'] ?? '';

// Get products with filters
$productsData = getProductsEnhanced($search, $categoryFilter, $stockFilter, $page, $perPage);
$products = $productsData['products'];
$totalProducts = $productsData['total'];
$totalPages = ceil($totalProducts / $perPage);

// Get categories for filter dropdown
try {
    $conn = getDbConnection();
    $categories = $conn->query("SELECT DISTINCT category FROM products WHERE category != '' AND category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $categories = [];
}

// Get product statistics
$productStats = getProductStatistics();

$csrfToken = generateCsrfToken();

// Edit Logic
$editProduct = null;
if (isset($_GET['edit'])) {
    $editSNo = sanitize_input($_GET['edit']);
    try {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT * FROM products WHERE serial_no = ?");
        $stmt->execute([$editSNo]);
        $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error loading product: ' . $e->getMessage()];
    }
}

// Success highlighting
$newProductSNo = isset($_GET['new']) ? sanitize_input($_GET['new']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - Alphasonix</title>
  <link rel="stylesheet" href="css/products.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>

<body>
    <div class="app-container">
        <button class="mobile-menu-toggle" style="display: none;" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>

        <!-- Sidebar Include -->
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <?php if (isset($_SESSION['message'])): ?>
                <?php
                $messageType = $_SESSION['message']['type'];
                $messageClass = 'message-' . $messageType;
                echo "<div class='message-alert {$messageClass}'>" .
                    htmlspecialchars($_SESSION['message']['text']) . "</div>";
                unset($_SESSION['message']);
                ?>
            <?php endif; ?>

            <div class="product-management-header">
                <h1>📦 Product Management</h1>
                <div class="header-actions">
                    <a href="products.php?export=csv" class="btn btn-export">
                        <i class="bi bi-download"></i> Export CSV
                    </a>
                    <?php if (hasRole('admin')): ?>
                    <button type="button" class="btn btn-primary" onclick="generateBarcodes()">
                        <i class="bi bi-upc"></i> Generate Barcodes
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
                    <div class="stat-info">
                        <h3><?= number_format($productStats['total_products']) ?></h3>
                        <p>Total Products</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #20c997);">
                        <i class="bi bi-tags"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($productStats['total_categories']) ?></h3>
                        <p>Categories</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #2a9d8f, #21867a);">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($productStats['in_stock_count']) ?></h3>
                        <p>In Stock</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107, #ff9800);">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($productStats['low_stock_count']) ?></h3>
                        <p>Low Stock</p>
                    </div>
                </div>
            </div>

            <div class="management-grid">
                <!-- CSV Upload -->
                <div class="form-section upload-section">
                    <h2>📤 Upload Product List</h2>
                    <form action="products.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <div style="margin-bottom: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 0.375rem;">
                            <strong>📋 CSV Format:</strong> 
                            <div style="font-size: 0.875rem; margin-top: 0.5rem;">
                                <strong>Required:</strong> <code>S.No, Name, Dimensions</code><br>
                                <strong>Optional:</strong> <code>Category, Price, StockQuantity</code>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Select CSV File:</label>
                            <input type="file" name="product_csv" accept=".csv" class="form-input" required>
                        </div>
                        <button type="submit" name="upload_products" class="btn btn-success">
                            <i class="bi bi-upload"></i> Upload CSV
                        </button>
                    </form>
                </div>

                <!-- Add/Edit Form -->
                <div class="form-section manual-add-section">
                    <h2><?= $editProduct ? '✏️ Edit Product' : '➕ Add New Product' ?></h2>
                    <form action="products.php" method="post" enctype="multipart/form-data" id="productForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <?php if ($editProduct): ?>
                            <input type="hidden" name="original_s_no" value="<?= htmlspecialchars($editProduct['serial_no']) ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label class="form-label">Serial Number: <span style="color: red;">*</span></label>
                            <input type="text" name="s_no" class="form-input" 
                                value="<?= $editProduct ? htmlspecialchars($editProduct['serial_no']) : '' ?>" 
                                required <?= $editProduct ? 'readonly' : '' ?>>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Product Name: <span style="color: red;">*</span></label>
                            <input type="text" name="name" class="form-input" 
                                value="<?= $editProduct ? htmlspecialchars($editProduct['name']) : '' ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Dimensions: <span style="color: red;">*</span></label>
                            <input type="text" name="dimensions" class="form-input" 
                                value="<?= $editProduct ? htmlspecialchars($editProduct['dimensions']) : '' ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Category:</label>
                            <input type="text" name="category" class="form-input" 
                                value="<?= $editProduct ? htmlspecialchars($editProduct['category']) : '' ?>" 
                                list="category-list">
                            <datalist id="category-list">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Price (₹):</label>
                            <input type="number" name="price" class="form-input" 
                                value="<?= $editProduct ? htmlspecialchars($editProduct['price']) : '0' ?>" 
                                min="0" step="0.01">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stock Quantity:</label>
                            <input type="number" name="stock_quantity" class="form-input" 
                                value="<?= $editProduct ? htmlspecialchars($editProduct['stock_quantity']) : '0' ?>" 
                                min="0" step="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Product Image:</label>
                            <div class="image-upload-container" onclick="document.getElementById('product_image').click()">
                                <input type="file" id="product_image" name="product_image" accept="image/*" style="display: none;" onchange="previewImage(this)">
                                <i class="bi bi-cloud-upload" style="font-size: 2rem; color: #6c757d;"></i>
                                <p style="margin: 0.5rem 0;">Click to upload image</p>
                                <small style="color: #6c757d;">JPG, PNG, GIF, WebP (Max 5MB)</small>
                                <?php if ($editProduct && !empty($editProduct['image'])): ?>
                                    <div class="image-preview" id="image-preview">
                                        <img src="uploads/products/thumbs/<?= htmlspecialchars($editProduct['image']) ?>" 
                                             alt="Product Image" 
                                             onerror="this.style.display='none'">
                                    </div>
                                <?php else: ?>
                                    <div class="image-preview" id="image-preview" style="display: none;">
                                        <img src="" alt="Image Preview">
                                    </div>
                                <?php endif; ?>
                            </div>
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

            <!-- Search -->
            <div class="search-section">
                <form method="get" action="products.php" class="search-form">
                    <input type="text" name="search" class="search-input" 
                        value="<?= htmlspecialchars($search) ?>" 
                        placeholder="🔍 Search by Serial No or Name...">
                    <select name="category" class="filter-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" 
                                <?= $categoryFilter === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="stock" class="filter-select">
                        <option value="">All Stock</option>
                        <option value="in_stock" <?= $stockFilter === 'in_stock' ? 'selected' : '' ?>>In Stock (>10)</option>
                        <option value="low_stock" <?= $stockFilter === 'low_stock' ? 'selected' : '' ?>>Low Stock (1-10)</option>
                        <option value="out_of_stock" <?= $stockFilter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Search
                    </button>
                    <?php if (!empty($search) || !empty($categoryFilter) || !empty($stockFilter)): ?>
                        <a href="products.php" class="btn btn-secondary">
                            <i class="bi bi-x"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Table -->
            <div class="table-section">
                <div class="table-header">
                    <h2>📋 Product Catalog</h2>
                    <div class="total-count">Total: <strong><?= number_format($totalProducts) ?></strong> products</div>
                </div>
                <table class="products-table">
                    <thead>
                        <tr>
                            <th class="product-image-cell">Image</th>
                            <th>S.No</th>
                            <th>Name</th>
                            <th>Dimensions</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $inStockCount = 0; 
                        $lowStockCount = 0;
                        if (empty($products)): ?>
                            <tr>
                                <td colspan="8" class="empty-state" style="text-align: center; padding: 3rem; color: #6c757d;">
                                    <h3>📦 No products found</h3>
                                    <p>Add your first product using the form above.</p>
                                </td>
                            </tr>
                        <?php else: 
                            foreach ($products as $product):
                                $stock = intval($product['stock_quantity'] ?? 0);
                                if ($stock > 10) $inStockCount++; 
                                elseif ($stock > 0) $lowStockCount++;
                        ?>
                            <tr class="product-row <?= ($newProductSNo === $product['serial_no']) ? 'new-entry' : '' ?>">
                                <td class="product-image-cell">
                                    <?php if (!empty($product['image'])): ?>
                                        <img src="uploads/products/thumbs/<?= htmlspecialchars($product['image']) ?>" 
                                             class="product-image"
                                             alt="<?= htmlspecialchars($product['name']) ?>"
                                             onerror="this.style.display='none'">
                                    <?php else: ?>
                                        <div class="no-image" style="width: 60px; height: 60px; background: #f8f9fa; border-radius: 0.375rem; display: flex; align-items: center; justify-content: center; color: #6c757d;">
                                            <i class="bi bi-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($product['serial_no']) ?></strong></td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td><?= htmlspecialchars($product['dimensions']) ?></td>
                                <td><?= htmlspecialchars($product['category'] ?? '-') ?></td>
                                <td>₹<?= number_format($product['price'] ?? 0, 2) ?></td>
                                <td>
                                    <?php 
                                    $stock = intval($product['stock_quantity'] ?? 0);
                                    if ($stock > 10): 
                                        echo '<span class="stock-badge stock-in">'.$stock.'</span>'; 
                                    elseif ($stock > 0): 
                                        echo '<span class="stock-badge stock-low">'.$stock.'</span>'; 
                                    else: 
                                        echo '<span class="stock-badge stock-out">Out</span>'; 
                                    endif; 
                                    ?>
                                </td>
                                <td class="actions-cell">
                                    <a href="products.php?edit=<?= urlencode($product['serial_no']) ?>" 
                                       class="btn btn-warning btn-small"
                                       title="Edit Product">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button onclick="confirmDelete('<?= htmlspecialchars($product['serial_no']) ?>', '<?= htmlspecialchars($product['name']) ?>')" 
                                            class="btn btn-danger btn-small" 
                                            title="Delete Product">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($categoryFilter) ?>&stock=<?= urlencode($stockFilter) ?>">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        if ($startPage > 1) {
                            echo '<a href="?page=1&search=' . urlencode($search) . '&category=' . urlencode($categoryFilter) . '&stock=' . urlencode($stockFilter) . '">1</a>';
                            if ($startPage > 2) echo '<span>...</span>';
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($categoryFilter) ?>&stock=<?= urlencode($stockFilter) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) echo '<span>...</span>';
                            echo '<a href="?page=' . $totalPages . '&search=' . urlencode($search) . '&category=' . urlencode($categoryFilter) . '&stock=' . urlencode($stockFilter) . '">' . $totalPages . '</a>';
                        }
                        ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($categoryFilter) ?>&stock=<?= urlencode($stockFilter) ?>">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="delete-modal">
        <div class="delete-modal-content">
            <h3>🗑️ Confirm Deletion</h3>
            <p>Are you sure you want to delete product <strong id="productNameToDelete"></strong>?</p>
            <p class="text-secondary" style="color: #6c757d; font-size: 0.875rem;">
                This action cannot be undone. The product will be permanently removed from the catalog.
            </p>
            <div class="delete-modal-buttons">
                <form id="deleteForm" method="post">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="s_no" id="productSNoToDelete">
                    <button type="submit" name="delete_product" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Yes, Delete
                    </button>
                </form>
                <button onclick="closeDeleteModal()" class="btn btn-secondary">
                    <i class="bi bi-x"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Update stock counts in stats
            document.querySelectorAll('.stat-card h3')[2].textContent = '<?= $inStockCount ?>';
            document.querySelectorAll('.stat-card h3')[3].textContent = '<?= $lowStockCount ?>';
            
            // Mobile sidebar toggle
            const mobileToggle = document.querySelector('.mobile-menu-toggle');
            if (window.innerWidth <= 1024 && mobileToggle) {
                mobileToggle.style.display = 'block';
            }

            // Auto-hide messages
            const messageAlerts = document.querySelectorAll('.message-alert');
            messageAlerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 500);
                }, 5000);
            });
        });

        function previewImage(input) {
            const preview = document.getElementById('image-preview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.querySelector('img').src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function confirmDelete(sNo, name) {
            document.getElementById('productSNoToDelete').value = sNo;
            document.getElementById('productNameToDelete').textContent = name;
            document.getElementById('deleteModal').classList.add('show');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.toggle('mobile-open');
            }
        }

        function generateBarcodes() {
            alert('Barcode generation feature will be implemented in the next version.');
            // Future implementation for barcode generation
        }

        // Form validation
        document.getElementById('productForm')?.addEventListener('submit', function(e) {
            const sNoInput = this.querySelector('input[name="s_no"]');
            const nameInput = this.querySelector('input[name="name"]');
            const dimensionsInput = this.querySelector('input[name="dimensions"]');
            const priceInput = this.querySelector('input[name="price"]');
            const stockInput = this.querySelector('input[name="stock_quantity"]');

            let isValid = true;

            // Remove previous error states
            [sNoInput, nameInput, dimensionsInput, priceInput, stockInput].forEach(input => {
                input.classList.remove('is-invalid');
            });

            // Validate required fields
            if (!sNoInput.value.trim()) {
                sNoInput.classList.add('is-invalid');
                isValid = false;
            }

            if (!nameInput.value.trim()) {
                nameInput.classList.add('is-invalid');
                isValid = false;
            }

            if (!dimensionsInput.value.trim()) {
                dimensionsInput.classList.add('is-invalid');
                isValid = false;
            }

            // Validate numeric fields
            if (priceInput.value < 0) {
                priceInput.classList.add('is-invalid');
                isValid = false;
            }

            if (stockInput.value < 0) {
                stockInput.classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                alert('Please check the form for errors. All required fields must be filled and numeric values cannot be negative.');
            }
        });

        // Close modal when clicking outside
        document.getElementById('deleteModal')?.addEventListener('click', function(e) {
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

        // Responsive sidebar handling
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const mobileToggle = document.querySelector('.mobile-menu-toggle');
            
            if (window.innerWidth > 1024) {
                if (sidebar) sidebar.classList.remove('mobile-open');
                if (mobileToggle) mobileToggle.style.display = 'none';
            } else {
                if (mobileToggle) mobileToggle.style.display = 'block';
            }
        });

        // Auto-format price input
        document.querySelector('input[name="price"]')?.addEventListener('blur', function(e) {
            let value = parseFloat(this.value);
            if (!isNaN(value) && value >= 0) {
                this.value = value.toFixed(2);
            }
        });
    </script>
</body>
</html>