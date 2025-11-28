<?php
// functions.php

// ===== CONSTANTS =====
if (!defined('UPLOAD_BASE_PATH')) {
    define('UPLOAD_BASE_PATH', __DIR__ . '/uploads/');
}

if (!defined('ALLOWED_EXTENSIONS')) {
    define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'gif']);
}

if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
}

// ===== DATABASE CONNECTION =====
require_once 'db.php';

// ===== AUTHENTICATION FUNCTIONS =====
function requireAdmin($redirect = 'login.php') {
    if (!isset($_SESSION['user_id'])) {
        header("Location: $redirect");
        exit;
    }

    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        die('Access denied. Administrator privileges required.');
    }
}

// function requireAuth($redirect = 'login.php') {
//     if (!isset($_SESSION['user_id'])) {
//         header("Location: $redirect");
//         exit;
//     }
// }

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function hasRole($requiredRole) {
    $userRole = $_SESSION['role'] ?? 'employee';
    $rolesHierarchy = ['employee' => 1, 'manager' => 2, 'admin' => 3];

    $userLevel = $rolesHierarchy[$userRole] ?? 0;
    $requiredLevel = $rolesHierarchy[$requiredRole] ?? 0;

    return $userLevel >= $requiredLevel;
}

function requireRole($requiredRole) {
    // requireAuth();

    if (!hasRole($requiredRole)) {
        http_response_code(403);
        die('Access denied. Insufficient permissions.');
    }
}

// ===== SECURITY FUNCTIONS =====
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function sanitizeFilePath($path) {
    $path = str_replace(['../', './', '..\\', '.\\'], '', $path);
    return preg_replace('/[^a-zA-Z0-9\/._-]/', '', $path);
}

function sanitize_input($data) {
    if ($data === null) return '';
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validateInput($data, $type) {
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_VALIDATE_EMAIL);
        case 'phone':
            return preg_match('/^[\d\s\-\+\(\)]+$/', $data) && strlen(preg_replace('/\D/', '', $data)) >= 10;
        default:
            return !empty($data);
    }
}

function checkRateLimit($action, $userId, $limit, $seconds) {
    $key = 'rate_limit_' . $action . '_' . $userId;
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 1, 'start_time' => $now];
        return true;
    }
    
    // Reset if time window passed
    if ($now - $_SESSION[$key]['start_time'] > $seconds) {
        $_SESSION[$key] = ['count' => 1, 'start_time' => $now];
        return true;
    }
    
    $_SESSION[$key]['count']++;
    
    return $_SESSION[$key]['count'] <= $limit;
}

// ===== CUSTOMERS FUNCTIONS =====
function getNextCustomerId() {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT MAX(id) as max_id FROM customers");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($result && $result['max_id']) ? $result['max_id'] + 1 : 1;
}

function getCustomers($search = '', $status = '', $limit = null) {
    $conn = getDbConnection();
    
    $sql = "SELECT * FROM customers WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR email LIKE ? OR company LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($status)) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    if ($limit) {
        $sql .= " LIMIT ?";
        $params[] = $limit;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCustomer($id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function addCustomer($customerData) {
    $conn = getDbConnection();
    
    $fields = ['name', 'email', 'phone', 'company', 'address', 'city', 'state', 'zip', 'country', 'tax_id', 'notes', 'credit_limit', 'status', 'signup_date'];
    $placeholders = array_fill(0, count($fields), '?');
    
    $sql = "INSERT INTO customers (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $conn->prepare($sql);
    
    $values = [];
    foreach ($fields as $field) {
        $values[] = $customerData[$field] ?? '';
    }
    
    return $stmt->execute($values);
}

function updateCustomer($customerId, $updateData) {
    $conn = getDbConnection();
    
    $fields = [];
    $values = [];
    
    $allowedFields = ['name', 'email', 'phone', 'company', 'address', 'city', 'state', 'zip', 'country', 'tax_id', 'notes', 'credit_limit', 'status'];
    
    foreach ($allowedFields as $field) {
        if (isset($updateData[$field])) {
            $fields[] = "$field = ?";
            $values[] = $updateData[$field];
        }
    }
    
    if (empty($fields)) {
        return false;
    }
    
    $values[] = $customerId;
    $sql = "UPDATE customers SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    return $stmt->execute($values);
}

function deleteCustomer($customerId) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
    return $stmt->execute([$customerId]);
}

function customerHasOrders($customerId) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as order_count FROM orders WHERE customer_id = ?");
    $stmt->execute([$customerId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['order_count'] > 0;
}

function customerExists($customerId) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM customers WHERE id = ?");
    $stmt->execute([$customerId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

// ===== PRODUCTS FUNCTIONS =====
function getProducts($search = '', $category = '', $stockFilter = '', $limit = null) {
    $conn = getDbConnection();
    
    $sql = "SELECT * FROM products WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (serial_no LIKE ? OR name LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($category)) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    
    if (!empty($stockFilter)) {
        switch ($stockFilter) {
            case 'in_stock':
                $sql .= " AND stock_quantity > 10";
                break;
            case 'low_stock':
                $sql .= " AND stock_quantity BETWEEN 1 AND 10";
                break;
            case 'out_of_stock':
                $sql .= " AND stock_quantity = 0";
                break;
        }
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    if ($limit) {
        $sql .= " LIMIT ?";
        $params[] = $limit;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getProductBySerialNo($serialNo) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT * FROM products WHERE serial_no = ?");
    $stmt->execute([$serialNo]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function addProduct($productData) {
    $conn = getDbConnection();
    
    $fields = ['serial_no', 'name', 'dimensions', 'category', 'price', 'stock_quantity', 'image'];
    $placeholders = array_fill(0, count($fields), '?');
    
    $sql = "INSERT INTO products (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $conn->prepare($sql);
    
    $values = [];
    foreach ($fields as $field) {
        $values[] = $productData[$field] ?? '';
    }
    
    return $stmt->execute($values);
}

function updateProduct($originalSNo, $updateData) {
    $conn = getDbConnection();
    
    $fields = [];
    $values = [];
    
    $allowedFields = ['serial_no', 'name', 'dimensions', 'category', 'price', 'stock_quantity', 'image'];
    
    foreach ($allowedFields as $field) {
        if (isset($updateData[$field])) {
            $fields[] = "$field = ?";
            $values[] = $updateData[$field];
        }
    }
    
    if (empty($fields)) {
        return false;
    }
    
    $values[] = $originalSNo;
    $sql = "UPDATE products SET " . implode(', ', $fields) . " WHERE serial_no = ?";
    $stmt = $conn->prepare($sql);
    
    return $stmt->execute($values);
}

function deleteProduct($sNo) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("DELETE FROM products WHERE serial_no = ?");
    return $stmt->execute([$sNo]);
}

function productExists($sNo) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE serial_no = ?");
    $stmt->execute([$sNo]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

function productUsedInOrders($sNo) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM order_items WHERE serial_no = ?");
    $stmt->execute([$sNo]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

// ===== ORDERS FUNCTIONS =====
function generateNextOrderIdStr() {
    $conn = getDbConnection();
    $prefix = 'ORD-' . date('Y') . '-';
    $stmt = $conn->prepare("SELECT order_id FROM orders WHERE order_id LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastId = $stmt->fetchColumn();
    
    if ($lastId) {
        $num = intval(str_replace($prefix, '', $lastId)) + 1;
        return $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
    }
    return $prefix . '001';
}

function getOrders($search = '', $status = '', $customer_id = '', $limit = null) {
    $conn = getDbConnection();
    
    $sql = "SELECT o.*, c.name as customer_name FROM orders o 
            LEFT JOIN customers c ON o.customer_id = c.id 
            WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (o.order_id LIKE ? OR o.po_number LIKE ? OR c.name LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($status)) {
        $sql .= " AND o.status = ?";
        $params[] = $status;
    }
    
    if (!empty($customer_id)) {
        $sql .= " AND o.customer_id = ?";
        $params[] = $customer_id;
    }
    
    $sql .= " ORDER BY o.created_at DESC";
    
    if ($limit) {
        $sql .= " LIMIT ?";
        $params[] = $limit;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getOrderById($orderId) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT o.*, c.name as customer_name FROM orders o 
                           LEFT JOIN customers c ON o.customer_id = c.id 
                           WHERE o.order_id = ?");
    $stmt->execute([$orderId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function addOrder($orderData) {
    $conn = getDbConnection();
    
    $fields = ['order_id', 'customer_id', 'po_number', 'po_date', 'delivery_date', 'due_date', 'status', 'total_amount', 'priority', 'notes', 'created_by', 'payment_terms', 'shipping_method', 'shipping_cost', 'tax_rate', 'drawing_filename', 'inspection_reports'];
    
    $placeholders = array_fill(0, count($fields), '?');
    $sql = "INSERT INTO orders (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $conn->prepare($sql);
    
    $values = [];
    foreach ($fields as $field) {
        $values[] = $orderData[$field] ?? '';
    }
    
    return $stmt->execute($values);
}

function updateOrder($orderId, $updateData) {
    $conn = getDbConnection();

    $fields = [];
    $values = [];

    $allowedFields = ['status', 'total_amount', 'priority', 'notes', 'payment_terms', 'shipping_method', 'shipping_cost', 'tax_rate', 'drawing_filename', 'inspection_reports'];

    foreach ($allowedFields as $field) {
        if (isset($updateData[$field])) {
            $fields[] = "$field = ?";
            $values[] = $updateData[$field];
        }
    }

    if (empty($fields)) {
        return false;
    }

    $values[] = $orderId;
    $sql = "UPDATE orders SET " . implode(', ', $fields) . " WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    
    return $stmt->execute($values);
}

function deleteOrder($orderId) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
    return $stmt->execute([$orderId]);
}

function orderExists($orderId) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

// ===== ORDER ITEMS FUNCTIONS =====
function getOrderItems($orderId) {
    $conn = getDbConnection();
    
    // Get internal order ID first
    $orderStmt = $conn->prepare("SELECT id FROM orders WHERE order_id = ?");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) return [];
    
    $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id");
    $stmt->execute([$order['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse JSON fields
    $formattedItems = [];
    foreach ($items as $item) {
        $formattedItem = [
            'S.No' => $item['serial_no'],
            'Name' => $item['name'],
            'Dimensions' => $item['dimensions'],
            'Description' => $item['description'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'total_price' => $item['total_price'],
            'item_status' => $item['item_status'],
            'drawing_filename' => $item['drawing_filename'],
            'original_filename' => $item['original_filename'],
            'raw_materials' => !empty($item['raw_materials']) ? json_decode($item['raw_materials'], true) : [],
            'machining_processes' => !empty($item['machining_processes']) ? json_decode($item['machining_processes'], true) : [],
            'inspection_data' => !empty($item['inspection_data']) ? json_decode($item['inspection_data'], true) : [],
            'packaging_lots' => !empty($item['packaging_lots']) ? json_decode($item['packaging_lots'], true) : []
        ];
        $formattedItems[] = $formattedItem;
    }
    
    return $formattedItems;
}

function addOrderItem($orderId, $itemData) {
    $conn = getDbConnection();
    
    // Get internal order ID
    $orderStmt = $conn->prepare("SELECT id FROM orders WHERE order_id = ?");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) return false;
    
    $internalOrderId = $order['id'];
    
    // Get product ID if it's a catalog product
    $productId = null;
    if (isset($itemData['serial_no'])) {
        $product = getProductBySerialNo($itemData['serial_no']);
        $productId = $product ? $product['id'] : null;
    }
    
    $fields = ['order_id', 'product_id', 'serial_no', 'name', 'dimensions', 'description', 'quantity', 'unit_price', 'total_price', 'item_status', 'drawing_filename', 'original_filename'];
    
    $placeholders = array_fill(0, count($fields), '?');
    $sql = "INSERT INTO order_items (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $conn->prepare($sql);
    
    $values = [
        $internalOrderId,
        $productId,
        $itemData['serial_no'] ?? '',
        $itemData['name'] ?? '',
        $itemData['dimensions'] ?? '',
        $itemData['description'] ?? '',
        $itemData['quantity'] ?? 1,
        $itemData['unit_price'] ?? 0,
        $itemData['total_price'] ?? 0,
        $itemData['item_status'] ?? 'Pending',
        $itemData['drawing_filename'] ?? '',
        $itemData['original_filename'] ?? ''
    ];
    
    return $stmt->execute($values);
}
function updateOrderItems($orderId, $items, $status = null) {
    $conn = getDbConnection();
    
    try {
        // Get internal order ID
        $orderStmt = $conn->prepare("SELECT id FROM orders WHERE order_id = ?");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception("Order not found: " . $orderId);
        }
        
        $internalOrderId = $order['id'];
        
        // Update order status if provided
        if ($status !== null) {
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
            $stmt->execute([$status, $orderId]);
        }
        
        // Delete existing order items
        $deleteStmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
        $deleteStmt->execute([$internalOrderId]);
        
        // Insert updated order items
        $insertStmt = $conn->prepare("
            INSERT INTO order_items (order_id, product_id, serial_no, name, dimensions, description, quantity, unit_price, total_price, item_status, drawing_filename, original_filename, raw_materials, machining_processes, inspection_data, packaging_lots) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($items as $item) {
            $rawMaterialsJson = isset($item['raw_materials']) ? json_encode($item['raw_materials']) : '[]';
            $machiningProcessesJson = isset($item['machining_processes']) ? json_encode($item['machining_processes']) : '[]';
            $inspectionDataJson = isset($item['inspection_data']) ? json_encode($item['inspection_data']) : '[]';
            $packagingLotsJson = isset($item['packaging_lots']) ? json_encode($item['packaging_lots']) : '[]';
            
            // Get product ID
            $productId = null;
            if (isset($item['S.No'])) {
                $product = getProductBySerialNo($item['S.No']);
                $productId = $product ? $product['id'] : null;
            }
            
            $insertStmt->execute([
                $internalOrderId,
                $productId,
                $item['S.No'] ?? $item['serial_no'] ?? '',
                $item['Name'] ?? $item['name'] ?? '',
                $item['Dimensions'] ?? $item['dimensions'] ?? '',
                $item['Description'] ?? $item['description'] ?? '',
                $item['quantity'] ?? 1,
                $item['unit_price'] ?? 0,
                $item['total_price'] ?? 0,
                $item['item_status'] ?? 'Pending',
                $item['drawing_filename'] ?? '',
                $item['original_filename'] ?? '',
                $rawMaterialsJson,
                $machiningProcessesJson,
                $inspectionDataJson,
                $packagingLotsJson
            ]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error updating order items: " . $e->getMessage());
        throw $e;
    }
}

// ===== ACTIVITY LOGGING =====
function logActivity($action, $details = '') {
    $conn = getDbConnection();

    $currentUser = $_SESSION['username'] ?? 'System';
    $userId = $_SESSION['user_id'] ?? $currentUser;
    $userRole = $_SESSION['role'] ?? 'employee';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $stmt = $conn->prepare("
        INSERT INTO activity_logs (action, details, user_id, username, user_role, ip_address) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    return $stmt->execute([$action, $details, $userId, $currentUser, $userRole, $ipAddress]);
}

function logChange($orderId, $stage, $description, $itemIndex = null) {
    $conn = getDbConnection();

    $orderStmt = $conn->prepare("SELECT id FROM orders WHERE order_id = ?");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) return false;

    $currentUser = $_SESSION['username'] ?? 'System';
    $userId = $_SESSION['user_id'] ?? $currentUser;
    $userRole = $_SESSION['role'] ?? 'employee';

    $stmt = $conn->prepare("
        INSERT INTO order_history (order_id, changed_by, user_id, user_role, stage, change_description, item_index) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    return $stmt->execute([
        $order['id'],
        $currentUser,
        $userId,
        $userRole,
        $stage,
        $description,
        $itemIndex
    ]);
}

function getOrderHistory($orderId) {
    $conn = getDbConnection();

    $orderStmt = $conn->prepare("SELECT id FROM orders WHERE order_id = ?");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) return [];

    $stmt = $conn->prepare("SELECT * FROM order_history WHERE order_id = ? ORDER BY change_date DESC");
    $stmt->execute([$order['id']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===== FILE UPLOAD FUNCTIONS =====
function handleItemFileUpload($file, $index, $orderId, $productSNo) {
    if (!isset($file['name']) || empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['filename' => '', 'filepath' => '', 'original' => '', 'error' => 'No file uploaded'];
    }

    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];

    if ($fileError !== UPLOAD_ERR_OK) {
        return ['filename' => '', 'filepath' => '', 'original' => '', 'error' => 'File upload error: ' . $fileError];
    }

    if ($fileSize > MAX_FILE_SIZE) {
        return ['filename' => '', 'filepath' => '', 'original' => '', 'error' => 'File size exceeds 10MB limit'];
    }

    $uploadDir = 'uploads/drawings/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($fileExtension, ALLOWED_EXTENSIONS)) {
        return ['filename' => '', 'filepath' => '', 'original' => '', 'error' => 'Invalid file type'];
    }

    $safeFileName = $orderId . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $productSNo) . '_' . uniqid() . '.' . $fileExtension;
    $uploadPath = $uploadDir . $safeFileName;

    if (move_uploaded_file($fileTmpName, $uploadPath)) {
        return [
            'filename' => $safeFileName,
            'filepath' => $uploadPath,
            'original' => $fileName,
            'error' => ''
        ];
    } else {
        return ['filename' => '', 'filepath' => '', 'original' => '', 'error' => 'Failed to move uploaded file'];
    }
}

function createThumbnail($source, $destination, $width, $height) {
    if (!extension_loaded('gd') || !function_exists('imagecreatefromjpeg')) {
        return copy($source, $destination);
    }

    $info = getimagesize($source);
    if (!$info) return false;

    $mime = $info['mime'];
    
    try {
        switch ($mime) {
            case 'image/jpeg': 
                $image = imagecreatefromjpeg($source);
                break;
            case 'image/png':  
                $image = imagecreatefrompng($source);
                break;
            case 'image/gif':  
                $image = imagecreatefromgif($source);
                break;
            case 'image/webp': 
                $image = imagecreatefromwebp($source);
                break;
            default: 
                return false;
        }

        if (!$image) return false;

        $srcW = imagesx($image);
        $srcH = imagesy($image);
        $ratio = $srcW / $srcH;

        if ($width / $height > $ratio) {
            $width = $height * $ratio;
        } else {
            $height = $width / $ratio;
        }

        $thumb = imagecreatetruecolor($width, $height);

        // Handle transparency for PNG and GIF
        if ($mime == 'image/png' || $mime == 'image/gif' || $mime == 'image/webp') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
            imagefilledrectangle($thumb, 0, 0, $width, $height, $transparent);
        }

        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $width, $height, $srcW, $srcH);

        switch ($mime) {
            case 'image/jpeg': 
                imagejpeg($thumb, $destination, 85);
                break;
            case 'image/png':  
                imagepng($thumb, $destination, 8);
                break;
            case 'image/gif':  
                imagegif($thumb, $destination);
                break;
            case 'image/webp': 
                imagewebp($thumb, $destination, 85);
                break;
        }

        imagedestroy($image);
        imagedestroy($thumb);
        return true;
    } catch (Exception $e) {
        error_log("Thumbnail creation error: " . $e->getMessage());
        return copy($source, $destination);
    }
}

// ===== UTILITY FUNCTIONS =====
function sendEmailNotification($to, $subject, $message) {
    // Basic email logging - implement proper mailer in production
    $logEntry = "[" . date('Y-m-d H:i:s') . "] TO: $to | SUBJECT: $subject | MESSAGE: " . substr($message, 0, 100) . "\n";
    file_put_contents(__DIR__ . '/email_log.txt', $logEntry, FILE_APPEND);
    return true;
}

function formatDate($date, $format = 'M j, Y') {
    if (empty($date)) return 'N/A';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : 'Invalid Date';
}

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';

    $units = ['B', 'KB', 'MB', 'GB'];
    $base = log($bytes, 1024);
    $unit = $units[floor($base)];

    return round(pow(1024, $base - floor($base)), 2) . ' ' . $unit;
}

// ===== INITIALIZATION =====
// Create necessary directories if they don't exist
$uploadDirs = [
    'uploads/drawings',
    'uploads/products',
    'uploads/products/thumbs',
    'uploads/documents',
    'uploads/materials_docs',
    'uploads/machining_docs', 
    'uploads/inspection_docs',
    'uploads/packaging_photos',
    'uploads/packaging_photos/thumbs',
    'uploads/shipping_docs'
];

foreach ($uploadDirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}
// ===== PIPELINE SPECIFIC FUNCTIONS =====

function hasAccessToOrder($orderId, $userId, $userRole) {
    if ($userRole === 'admin') {
        return true;
    }
    
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT created_by FROM orders WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $order && ($order['created_by'] == $userId);
}

function handleStageFileUpload($file, $index, $orderId, $type) {
    $uploadDir = 'uploads/' . $type . '_docs/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    
    if ($fileError !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error'];
    }
    
    if ($fileSize > (10 * 1024 * 1024)) {
        return ['success' => false, 'error' => 'File too large'];
    }
    
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
    if (!in_array($fileExtension, $allowedExtensions)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    $safeFileName = $orderId . '_' . $type . '_' . uniqid() . '.' . $fileExtension;
    $uploadPath = $uploadDir . $safeFileName;
    
    if (move_uploaded_file($fileTmpName, $uploadPath)) {
        return [
            'success' => true,
            'filename' => $safeFileName,
            'filepath' => $uploadPath,
            'original' => $fileName
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to move file'];
}

function handleMultipleFileUpload($fileArray, $index, $orderId, $type) {
    if (!isset($fileArray['tmp_name'][$index]) || empty($fileArray['tmp_name'][$index])) {
        return ['success' => false, 'error' => 'No file uploaded'];
    }
    
    $uploadDir = 'uploads/' . $type . '_photos/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileName = $fileArray['name'][$index];
    $fileTmpName = $fileArray['tmp_name'][$index];
    $fileSize = $fileArray['size'][$index];
    $fileError = $fileArray['error'][$index];
    
    if ($fileError !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error'];
    }
    
    if ($fileSize > (10 * 1024 * 1024)) {
        return ['success' => false, 'error' => 'File too large'];
    }
    
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'gif'];
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    $safeFileName = $orderId . '_' . $type . '_' . uniqid() . '.' . $fileExtension;
    $uploadPath = $uploadDir . $safeFileName;
    
    if (move_uploaded_file($fileTmpName, $uploadPath)) {
        // Create thumbnail for images
        if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
            $thumbDir = $uploadDir . 'thumbs/';
            if (!file_exists($thumbDir)) {
                mkdir($thumbDir, 0755, true);
            }
            createThumbnail($uploadPath, $thumbDir . $safeFileName, 200, 200);
        }
        
        return [
            'success' => true,
            'filename' => $safeFileName,
            'filepath' => $uploadPath,
            'original' => $fileName
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to move file'];
}

function sendOrderStatusNotification($orderId, $status) {
    logActivity("Status Notification", "Order $orderId status changed to $status");
    return true;
}

function sendShippingNotification($customerId, $orderId, $trackingNumber) {
    logActivity("Shipping Notification", "Order $orderId shipped with tracking: $trackingNumber");
    return true;
}

// ===== DATABASE SCHEMA SETUP =====
function setupPipelineSchema() {
    $conn = getDbConnection();
    
    try {
        // Create activity_logs table if it doesn't exist
        $conn->exec("
            CREATE TABLE IF NOT EXISTS activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(255) NOT NULL,
                details TEXT,
                user_id VARCHAR(100),
                username VARCHAR(100),
                user_role VARCHAR(50),
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create order_history table if it doesn't exist
        $conn->exec("
            CREATE TABLE IF NOT EXISTS order_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                changed_by VARCHAR(100),
                user_id VARCHAR(100),
                user_role VARCHAR(50),
                stage VARCHAR(100),
                change_description TEXT,
                item_index INT,
                change_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            )
        ");
        
        return true;
    } catch (Exception $e) {
        error_log("Schema setup error: " . $e->getMessage());
        return false;
    }
}

// Initialize schema on include
setupPipelineSchema();
?>