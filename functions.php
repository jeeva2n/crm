<?php
// ===== CONSTANTS =====
if (!defined('UPLOAD_BASE_PATH')) {
    define('UPLOAD_BASE_PATH', __DIR__ . '/uploads/documents/');
}

if (!defined('ALLOWED_EXTENSIONS')) {
    define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg']);
}

if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
}

// ===== DATABASE CONNECTION =====
require_once 'db.php';

function getDbConnection() {
    $database = new Database();
    return $database->getConnection();
}

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

function requireAuth($redirect = 'login.php') {
    if (!isset($_SESSION['user_id'])) {
        header("Location: $redirect");
        exit;
    }
}

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
    requireAuth();
    
    if (!hasRole($requiredRole)) {
        http_response_code(403);
        die('Access denied. Insufficient permissions.');
    }
}

// ===== SECURITY FUNCTIONS =====
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
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

// ===== ACCESS CONTROL FUNCTIONS =====
function hasAccessToFolder($folderPath, $userRole, $userId) {
    // Admin has access to all folders
    if ($userRole === 'admin') {
        return true;
    }
    
    // Employees can only access their own folders and public folders
    $folderName = basename($folderPath);
    
    // Allow access if it's the user's own folder (prefixed with user ID)
    if (strpos($folderName, $userId . '_') === 0) {
        return true;
    }
    
    // Allow access to public folders (no user ID prefix)
    if (!preg_match('/^\d+_/', $folderName)) {
        return true;
    }
    
    return false;
}

function hasModifyPermission($filePath, $userRole, $userId) {
    // Admin can modify everything
    if ($userRole === 'admin') {
        return true;
    }
    
    // Users can only modify files in their own folders
    $folderName = basename(dirname($filePath));
    return strpos($folderName, $userId . '_') === 0;
}

// ===== DOCUMENT MANAGEMENT FUNCTIONS =====
function getFoldersWithAccess($basePath, $type, $userRole, $userId) {
    $folders = [];
    $typePath = $basePath . $type . '/';
    
    if (!is_dir($typePath)) {
        return $folders;
    }
    
    $items = scandir($typePath);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $fullPath = $typePath . $item;
        if (is_dir($fullPath)) {
            // Employees can access their own folders and public folders
            if (strpos($item, $userId . '_') === 0 || !preg_match('/^\d+_/', $item)) {
                $folders[] = $type . '/' . $item;
            }
        }
    }
    
    return $folders;
}

function getFilesInFolder($folderPath, $userRole, $userId) {
    $files = [];
    $fullPath = UPLOAD_BASE_PATH . $folderPath;
    
    if (!is_dir($fullPath) || !hasAccessToFolder($folderPath, $userRole, $userId)) {
        return $files;
    }
    
    $items = scandir($fullPath);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $filePath = $fullPath . '/' . $item;
        if (is_file($filePath)) {
            $files[] = [
                'name' => $item,
                'path' => $folderPath . '/' . $item,
                'size' => filesize($filePath),
                'modified' => filemtime($filePath)
            ];
        }
    }
    
    return $files;
}

function getFileDisplayInfo($filePath, $userRole, $userId) {
    return [
        'can_delete' => hasModifyPermission($filePath, $userRole, $userId),
        'can_edit' => hasModifyPermission($filePath, $userRole, $userId)
    ];
}

function getAllFolders($basePath) {
    $folders = [];
    $types = ['jobsheets', 'invoices'];
    
    foreach ($types as $type) {
        $typePath = $basePath . $type . '/';
        
        if (!is_dir($typePath)) {
            continue;
        }
        
        $items = scandir($typePath);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $fullPath = $typePath . $item;
            if (is_dir($fullPath)) {
                $folders[] = $type . '/' . $item;
            }
        }
    }
    
    return $folders;
}

// ===== ACTIVITY HISTORY FUNCTIONS =====
function getActivityHistory($page = 1, $perPage = 50, $searchTerm = '') {
    $conn = getDbConnection();
    
    // Calculate offset
    $offset = ($page - 1) * $perPage;
    
    // Build search condition
    $searchCondition = '';
    $params = [];
    
    if (!empty($searchTerm)) {
        $searchCondition = "WHERE (action LIKE ? OR details LIKE ? OR username LIKE ? OR ip_address LIKE ?)";
        $searchParam = "%$searchTerm%";
        $params = [$searchParam, $searchParam, $searchParam, $searchParam];
    }
    
    // Get total count
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM activity_logs $searchCondition");
    if (!empty($searchTerm)) {
        $countStmt->execute($params);
    } else {
        $countStmt->execute();
    }
    $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $totalLogs = $totalResult['total'];
    $totalPages = ceil($totalLogs / $perPage);
    
    // Get logs for current page - FIXED: Use integer parameters for LIMIT and OFFSET
    $sql = "SELECT * FROM activity_logs $searchCondition ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    
    // Bind parameters with explicit types
    if (!empty($searchTerm)) {
        // For search queries, we have 4 search params + limit + offset
        $stmt->bindParam(1, $params[0], PDO::PARAM_STR);
        $stmt->bindParam(2, $params[1], PDO::PARAM_STR);
        $stmt->bindParam(3, $params[2], PDO::PARAM_STR);
        $stmt->bindParam(4, $params[3], PDO::PARAM_STR);
        $stmt->bindParam(5, $perPage, PDO::PARAM_INT);
        $stmt->bindParam(6, $offset, PDO::PARAM_INT);
    } else {
        // For non-search queries, just limit and offset
        $stmt->bindParam(1, $perPage, PDO::PARAM_INT);
        $stmt->bindParam(2, $offset, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the logs for display
    $formattedLogs = [];
    foreach ($logs as $log) {
        $formattedLogs[] = [
            'user' => $log['username'],
            'action' => $log['action'],
            'details' => $log['details'],
            'timestamp' => date('M j, Y g:i A', strtotime($log['created_at'])),
            'ip_address' => $log['ip_address'],
            'stage' => $log['user_role']
        ];
    }
    
    return [
        'logs' => $formattedLogs,
        'total_logs' => $totalLogs,
        'current_page' => $page,
        'total_pages' => $totalPages,
        'has_previous' => $page > 1,
        'has_next' => $page < $totalPages
    ];
}

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

function getActivityLogs($limit = 100) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===== CUSTOMERS FUNCTIONS ===
function getNextCustomerId() {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT MAX(id) as max_id FROM customers");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextId = 1;
    if ($result && $result['max_id']) {
        $nextId = $result['max_id'] + 1;
    }
    return $nextId;
}

function getCustomers() {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT * FROM customers ORDER BY created_at DESC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addCustomer($customerData) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("INSERT INTO customers (name, email, signup_date) VALUES (?, ?, ?)");
    return $stmt->execute([
        $customerData['name'], 
        $customerData['email'], 
        $customerData['signup_date']
    ]);
}

function updateCustomer($customerId, $updateData) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("UPDATE customers SET name = ?, email = ? WHERE id = ?");
    return $stmt->execute([$updateData['name'], $updateData['email'], $customerId]);
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

// === PRODUCTS FUNCTIONS ===
function getProducts() {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT * FROM products ORDER BY created_at DESC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addProduct($productData) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("INSERT INTO products (serial_no, name, dimensions) VALUES (?, ?, ?)");
    return $stmt->execute([
        $productData['serial_no'] ?? $productData['S.No'] ?? '',
        $productData['name'] ?? $productData['Name'] ?? '',
        $productData['dimensions'] ?? $productData['Dimensions'] ?? ''
    ]);
}

function updateProduct($originalSNo, $updateData) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("UPDATE products SET serial_no = ?, name = ?, dimensions = ? WHERE serial_no = ?");
    return $stmt->execute([
        $updateData['serial_no'] ?? $updateData['S.No'] ?? '',
        $updateData['name'] ?? $updateData['Name'] ?? '',
        $updateData['dimensions'] ?? $updateData['Dimensions'] ?? '',
        $originalSNo
    ]);
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
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM order_items oi 
        JOIN orders o ON oi.order_id = o.id 
        WHERE oi.serial_no = ?
    ");
    $stmt->execute([$sNo]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

function getProductBySerialNo($serialNo) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT * FROM products WHERE serial_no = ?");
    $stmt->execute([$serialNo]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getProductBySNo($sNo) {
    return getProductBySerialNo($sNo);
}

// === ORDERS FUNCTIONS ===
function getNextOrderId() {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(order_id, 2) AS UNSIGNED)) as max_id FROM orders WHERE order_id LIKE 'O%'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextId = 1;
    if ($result && $result['max_id']) {
        $nextId = $result['max_id'] + 1;
    }
    return 'O' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
}

function getOrders() {
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT o.*, c.name as customer_name 
        FROM orders o 
        LEFT JOIN customers c ON o.customer_id = c.id 
        ORDER BY o.created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addOrder($orderData) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        INSERT INTO orders (order_id, customer_id, po_date, delivery_date, due_date, status, drawing_filename, inspection_reports) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([
        $orderData['order_id'],
        $orderData['customer_id'],
        $orderData['po_date'],
        $orderData['delivery_date'] ?? null,
        $orderData['due_date'] ?? null,
        $orderData['status'] ?? 'Pending',
        $orderData['drawing_filename'] ?? '',
        $orderData['inspection_reports'] ?? '[]'
    ]);
}

function updateOrder($orderId, $updateData) {
    $conn = getDbConnection();
    
    $fields = [];
    $values = [];
    
    foreach ($updateData as $key => $value) {
        $fields[] = "$key = ?";
        $values[] = $value;
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

function getOrderById($orderId) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT o.*, c.name as customer_name 
        FROM orders o 
        LEFT JOIN customers c ON o.customer_id = c.id 
        WHERE o.order_id = ?
    ");
    $stmt->execute([$orderId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function orderExists($orderId) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

// === ORDER ITEMS FUNCTIONS ===
function addOrderItem($orderId, $itemData) {
    $conn = getDbConnection();
    
    // First get the internal order ID
    $orderStmt = $conn->prepare("SELECT id FROM orders WHERE order_id = ?");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) return false;
    
    $internalOrderId = $order['id'];
    
    // Get product ID if it's a catalog product
    $productId = null;
    if (isset($itemData['serial_no']) || isset($itemData['S.No'])) {
        $productSNo = $itemData['serial_no'] ?? $itemData['S.No'];
        $productStmt = $conn->prepare("SELECT id FROM products WHERE serial_no = ?");
        $productStmt->execute([$productSNo]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
        $productId = $product ? $product['id'] : null;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO order_items (order_id, product_id, serial_no, name, dimensions, description, quantity, item_status, drawing_filename, original_filename) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([
        $internalOrderId,
        $productId,
        $itemData['serial_no'] ?? $itemData['S.No'] ?? '',
        $itemData['name'] ?? $itemData['Name'] ?? '',
        $itemData['dimensions'] ?? $itemData['Dimensions'] ?? '',
        $itemData['description'] ?? $itemData['Description'] ?? '',
        $itemData['quantity'] ?? 1,
        $itemData['item_status'] ?? 'Pending',
        $itemData['drawing_filename'] ?? '',
        $itemData['original_filename'] ?? ''
    ]);
}

function getOrderItems($orderId) {
    $conn = getDbConnection();
    
    // Get order internal ID
    $orderStmt = $conn->prepare("SELECT id FROM orders WHERE order_id = ?");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) return [];
    
    $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id");
    $stmt->execute([$order['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse JSON fields and convert to array structure
    $formattedItems = [];
    foreach ($items as $item) {
        $formattedItem = [
            'S.No' => $item['serial_no'],
            'Name' => $item['name'],
            'Dimensions' => $item['dimensions'],
            'Description' => $item['description'],
            'quantity' => $item['quantity'],
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

function getOrderItemsCount($orderId) {
    $conn = getDbConnection();
    
    $orderStmt = $conn->prepare("SELECT id FROM orders WHERE order_id = ?");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) return 0;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM order_items WHERE order_id = ?");
    $stmt->execute([$order['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

function updateOrderItems($orderId, $items, $status = null) {
    $conn = getDbConnection();
    
    try {
        $conn->beginTransaction();
        
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
            INSERT INTO order_items (order_id, serial_no, name, dimensions, description, quantity, item_status, drawing_filename, original_filename, raw_materials, machining_processes, inspection_data, packaging_lots) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($items as $item) {
            // Handle JSON fields for complex data
            $rawMaterialsJson = isset($item['raw_materials']) ? json_encode($item['raw_materials'], JSON_UNESCAPED_UNICODE) : '[]';
            $machiningProcessesJson = isset($item['machining_processes']) ? json_encode($item['machining_processes'], JSON_UNESCAPED_UNICODE) : '[]';
            $inspectionDataJson = isset($item['inspection_data']) ? json_encode($item['inspection_data'], JSON_UNESCAPED_UNICODE) : '[]';
            $packagingLotsJson = isset($item['packaging_lots']) ? json_encode($item['packaging_lots'], JSON_UNESCAPED_UNICODE) : '[]';
            
            $insertStmt->execute([
                $internalOrderId,
                $item['S.No'] ?? $item['serial_no'] ?? '',
                $item['Name'] ?? $item['name'] ?? '',
                $item['Dimensions'] ?? $item['dimensions'] ?? '',
                $item['Description'] ?? $item['description'] ?? '',
                $item['quantity'] ?? 1,
                $item['item_status'] ?? 'Pending',
                $item['drawing_filename'] ?? '',
                $item['original_filename'] ?? '',
                $rawMaterialsJson,
                $machiningProcessesJson,
                $inspectionDataJson,
                $packagingLotsJson
            ]);
        }
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error updating order items: " . $e->getMessage());
        return false;
    }
}

// === ORDER HISTORY FUNCTIONS ===
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

// === FILE UPLOAD FUNCTIONS ===
function handleItemFileUpload($fileArray, $index, $orderId, $productSNo) {
    if (!isset($fileArray['name'][$index]) || empty($fileArray['name'][$index])) {
        return ['filename' => '', 'filepath' => '', 'original' => '', 'error' => ''];
    }

    $fileName = $fileArray['name'][$index];
    $fileTmpName = $fileArray['tmp_name'][$index];
    $fileSize = $fileArray['size'][$index];
    $fileError = $fileArray['error'][$index];
    
    if ($fileError !== UPLOAD_ERR_OK) {
        return ['filename' => '', 'filepath' => '', 'original' => '', 'error' => 'File upload error: ' . $fileError];
    }
    
    if ($fileSize > 10 * 1024 * 1024) {
        return ['filename' => '', 'filepath' => '', 'original' => '', 'error' => 'File size exceeds 10MB limit'];
    }
    
    $uploadDir = 'uploads/drawings/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $safeFileName = $orderId . '_' . $productSNo . '_' . uniqid() . '.' . $fileExtension;
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

function createOrderItem($serialNo, $name, $dimensions, $description, $quantity, $drawingData) {
    return [
        'S.No' => $serialNo,
        'Name' => $name,
        'Dimensions' => $dimensions,
        'Description' => $description,
        'quantity' => $quantity,
        'item_status' => 'Pending',
        'drawing_filename' => $drawingData['filename'],
        'original_filename' => $drawingData['original'] ?? ''
    ];
}

function generateManualProductId() {
    return 'MANUAL_' . uniqid();
}

// === UTILITY FUNCTIONS ===
function safeJsonDecode($json, $default = []) {
    if (empty($json)) return $default;
    
    $decoded = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        return $default;
    }
    
    return $decoded;
}

function formatDate($date, $format = 'M j, Y') {
    if (empty($date)) return 'N/A';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : 'Invalid Date';
}

function handleDbError($e, $context = 'Database operation') {
    error_log("$context failed: " . $e->getMessage());
    return false;
}

// === CSV FUNCTIONS (for backup/compatibility) ===
function getCsvData($filename) {
    $data = [];
    if (!file_exists($filename)) {
        return $data;
    }  
    
    $fp = fopen($filename, 'r');
    $headers = fgetcsv($fp);
    
    if ($headers === false) {
        fclose($fp);
        return $data;
    }
    
    while (($row = fgetcsv($fp)) !== false) {
        if (count($row) === count($headers)) {
            $data[] = array_combine($headers, $row);
        }
    }
    
    fclose($fp);
    return $data;
}

function ensureCsvFile($filename, $headers) {
    if (!file_exists($filename)) {
        $fp = fopen($filename, 'w');
        fputcsv($fp, $headers);
        fclose($fp);
    }
}

function appendCsvData($filename, $data) {
    $fp = fopen($filename, 'a');
    fputcsv($fp, $data);
    fclose($fp);
}

function getAvailableUsers() {
    $users = [];
    
    // Try to get users from database first
    if (function_exists('getUsers')) {
        try {
            $dbUsers = getUsers();
            foreach ($dbUsers as $user) {
                if (isset($user['id']) && isset($user['username'])) {
                    $users[$user['id']] = $user['username'];
                }
            }
        } catch (Exception $e) {
            // Fallback to CSV if database fails
        }
    }
    
    // Fallback to CSV users
    if (empty($users) && file_exists('users.csv')) {
        $csvUsers = getCsvData('users.csv');
        foreach ($csvUsers as $user) {
            if (isset($user['id']) && isset($user['username'])) {
                $users[$user['id']] = $user['username'];
            }
        }
    }
    
    // If still no users, create some default ones
    if (empty($users)) {
        $users = [
            '1' => 'admin',
            '2' => 'employee1', 
            '3' => 'employee2',
            '4' => 'manager1'
        ];
    }
    
    return $users;
}

function getAllUploadHistory($limit = 10) {
    $uploads = [];
    $uploadFile = 'document_uploads.csv';
    
    if (!file_exists($uploadFile)) {
        return $uploads;
    }
    
    $data = getCsvData($uploadFile);
    
    // Sort by upload date descending and limit results
    usort($data, function($a, $b) {
        return strtotime($b['upload_date'] ?? '') - strtotime($a['upload_date'] ?? '');
    });
    
    return array_slice($data, 0, $limit);
}

function getUserUploadHistory($userId, $userRole, $limit = 5) {
    $uploads = [];
    $uploadFile = 'document_uploads.csv';
    
    if (!file_exists($uploadFile)) {
        return $uploads;
    }
    
    $data = getCsvData($uploadFile);
    $userUploads = array_filter($data, function($upload) use ($userId) {
        return isset($upload['uploaded_by_id']) && $upload['uploaded_by_id'] == $userId;
    });
    
    // Sort by upload date descending and limit results
    usort($userUploads, function($a, $b) {
        return strtotime($b['upload_date'] ?? '') - strtotime($a['upload_date'] ?? '');
    });
    
    return array_slice($userUploads, 0, $limit);
}

// === INITIALIZATION ===
// Create necessary directories if they don't exist
if (!file_exists(UPLOAD_BASE_PATH)) {
    mkdir(UPLOAD_BASE_PATH, 0777, true);
}

if (!file_exists('uploads/drawings/')) {
    mkdir('uploads/drawings/', 0777, true);
}
// ===== DATA MANAGEMENT FUNCTIONS =====

// Define data path constant
if (!defined('DATA_PATH')) {
    define('DATA_PATH', __DIR__ . '/');
}

function getDataFilesList() {
    $files = [
        'customers.csv' => 'Customer Database',
        'products.csv' => 'Product Catalog', 
        'orders.csv' => 'Order Records',
        'order_history.csv' => 'Order History',
        'activity_logs.csv' => 'System Activity Log',
        'document_uploads.csv' => 'Document Uploads Log'
    ];
    
    $result = [];
    foreach ($files as $filename => $description) {
        $filePath = DATA_PATH . $filename;
        $exists = file_exists($filePath);
        
        $result[] = [
            'filename' => $filename,
            'description' => $description,
            'exists' => $exists,
            'size' => $exists ? filesize($filePath) : 0,
            'records' => $exists ? count(getCsvData($filename)) : 0,
            'modified' => $exists ? filemtime($filePath) : 0
        ];
    }
    
    return $result;
}

function getSystemStats() {
    $conn = getDbConnection();
    
    $stats = [
        'total_customers' => 0,
        'total_orders' => 0,
        'total_products' => 0,
        'total_activities' => 0
    ];
    
    try {
        // Get customer count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM customers");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_customers'] = $result['count'] ?? 0;
        
        // Get order count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_orders'] = $result['count'] ?? 0;
        
        // Get product count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_products'] = $result['count'] ?? 0;
        
        // Get activity count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM activity_logs");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_activities'] = $result['count'] ?? 0;
        
    } catch (Exception $e) {
        // If database tables don't exist, use file-based counts
        $stats['total_customers'] = count(getCsvData('customers.csv'));
        $stats['total_orders'] = count(getCsvData('orders.csv'));
        $stats['total_products'] = count(getCsvData('products.csv'));
        $stats['total_activities'] = count(getCsvData('activity_logs.csv'));
    }
    
    return $stats;
}

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB'];
    $base = log($bytes, 1024);
    $unit = $units[floor($base)];
    
    return round(pow(1024, $base - floor($base)), 2) . ' ' . $unit;
}

function downloadCsvFile($filename) {
    $dataFiles = [
        'customers.csv' => 'Customer Database',
        'products.csv' => 'Product Catalog',
        'orders.csv' => 'Order Records',
        'order_history.csv' => 'Order History',
        'activity_logs.csv' => 'System Activity Log',
        'document_uploads.csv' => 'Document Uploads Log'
    ];
    
    // Validate filename
    if (!array_key_exists($filename, $dataFiles)) {
        throw new Exception('Invalid file specified.');
    }
    
    $filePath = DATA_PATH . $filename;
    
    if (!file_exists($filePath)) {
        throw new Exception('File not found: ' . $filename);
    }
    
    $displayName = $dataFiles[$filename];
    $safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $displayName) . '.csv';
    
    // Set headers for download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Pragma: no-cache');
    header('Expires: 0');
    
    readfile($filePath);
    exit;
}

function viewCsvFile($filename) {
    $dataFiles = [
        'customers.csv' => 'Customer Database',
        'products.csv' => 'Product Catalog',
        'orders.csv' => 'Order Records',
        'order_history.csv' => 'Order History',
        'activity_logs.csv' => 'System Activity Log',
        'document_uploads.csv' => 'Document Uploads Log'
    ];
    
    // Validate filename
    if (!array_key_exists($filename, $dataFiles)) {
        throw new Exception('Invalid file specified.');
    }
    
    $filePath = DATA_PATH . $filename;
    
    if (!file_exists($filePath)) {
        throw new Exception('File not found: ' . $filename);
    }
    
    $displayName = $dataFiles[$filename];
    $data = getCsvData($filename);
    displayCsvAsTable($data, $displayName, $filename);
    exit;
}

function downloadAllDataAsZip() {
    $dataFiles = [
        'customers.csv' => 'Customer Database',
        'products.csv' => 'Product Catalog',
        'orders.csv' => 'Order Records',
        'order_history.csv' => 'Order History',
        'activity_logs.csv' => 'System Activity Log',
        'document_uploads.csv' => 'Document Uploads Log'
    ];
    
    $zipFilename = 'alphasonix_data_backup_' . date('Y-m-d_His') . '.zip';
    $zipPath = sys_get_temp_dir() . '/' . $zipFilename;
    
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
        throw new Exception('Cannot create ZIP file');
    }
    
    foreach ($dataFiles as $filename => $displayName) {
        $filePath = DATA_PATH . $filename;
        
        if (file_exists($filePath) && filesize($filePath) > 0) {
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $displayName) . '.csv';
            $zip->addFile($filePath, $safeName);
        }
    }
    
    if ($zip->numFiles == 0) {
        throw new Exception('No data files available for download');
    }
    
    $zip->close();
    
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
    header('Content-Length: ' . filesize($zipPath));
    header('Pragma: no-cache');
    header('Expires: 0');
    
    readfile($zipPath);
    unlink($zipPath);
    exit;
}

function viewAllData() {
    $dataFiles = [
        'customers.csv' => 'Customer Database',
        'products.csv' => 'Product Catalog',
        'orders.csv' => 'Order Records',
        'order_history.csv' => 'Order History',
        'activity_logs.csv' => 'System Activity Log',
        'document_uploads.csv' => 'Document Uploads Log'
    ];
    
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>All Data - Alphasonix CRM</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { padding: 20px; background: #f8f9fa; }
            .table-container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
            h1 { color: #333; margin-bottom: 30px; }
            .back-btn { margin-bottom: 20px; }
            .table th { background-color: #f8f9fa; }
            .file-info { background: #e9ecef; padding: 10px 15px; border-radius: 5px; margin-bottom: 15px; }
        </style>
    </head>
    <body>
        <div class="container-fluid">
            <a href="data_management.php" class="btn btn-primary back-btn">
                <i class="fas fa-arrow-left"></i> Back to Data Management
            </a>
            <h1><i class="fas fa-database"></i> All System Data</h1>';
    
    foreach ($dataFiles as $filename => $displayName) {
        $filePath = DATA_PATH . $filename;
        
        if (file_exists($filePath) && filesize($filePath) > 0) {
            $data = getCsvData($filename);
            displayCsvAsTable($data, $displayName, $filename);
        } else {
            echo '<div class="table-container">
                    <div class="file-info">
                        <h4><i class="fas fa-file"></i> ' . htmlspecialchars($displayName) . '</h4>
                        <small class="text-muted">File: ' . htmlspecialchars($filename) . '</small>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> No data available or file is empty
                    </div>
                  </div>';
        }
    }
    
    echo '</div></body></html>';
    exit;
}

function displayCsvAsTable($data, $title, $filename) {
    if (empty($data)) {
        echo '<div class="table-container">
                <div class="file-info">
                    <h4><i class="fas fa-file"></i> ' . htmlspecialchars($title) . '</h4>
                    <small class="text-muted">File: ' . htmlspecialchars($filename) . '</small>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> File exists but contains no records
                </div>
              </div>';
        return;
    }
    
    echo '<div class="table-container">
            <div class="file-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4><i class="fas fa-file-csv"></i> ' . htmlspecialchars($title) . '</h4>
                        <small class="text-muted">File: ' . htmlspecialchars($filename) . ' | ' . count($data) . ' records</small>
                    </div>
                    <a href="data_management.php?action=download&file=' . urlencode($filename) . '" 
                       class="btn btn-success btn-sm">
                        <i class="fas fa-download"></i> Download CSV
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>';
    
    // Table headers
    $headers = array_keys($data[0]);
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
    }
    
    echo '</tr></thead><tbody>';
    
    // Table rows
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            // Truncate very long content for better display
            $displayCell = $cell;
            if (strlen($cell) > 100) {
                $displayCell = substr($cell, 0, 100) . '...';
            }
            echo '<td title="' . htmlspecialchars($cell) . '">' . htmlspecialchars($displayCell) . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</tbody></table></div></div>';
}

function createBackup() {
    // This is a placeholder - implement actual backup logic
    // For now, just create a ZIP backup of data files
    return downloadAllDataAsZip();
}
?>