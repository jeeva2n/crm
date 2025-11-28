<?php
session_start();
require_once 'functions.php';
require_once 'db.php';

// Define a constant to prevent function redeclaration
define('CUSTOMERS_PHP_LOADED', true);

// Check authentication
// requireAuth();

// Check rate limiting
if (!checkRateLimit('customers_page', $_SESSION['user_id'], 100, 60)) {
    die('Rate limit exceeded. Please try again later.');
}

// ==========================================
// ENHANCED CUSTOMER FUNCTIONS
// ==========================================

function addCustomerEnhanced($customer) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("
        INSERT INTO customers 
        (id, name, email, phone, company, address, city, state, zip, country, 
         tax_id, notes, credit_limit, status, signup_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([
        $customer['id'],
        $customer['name'],
        $customer['email'],
        $customer['phone'],
        $customer['company'],
        $customer['address'],
        $customer['city'],
        $customer['state'],
        $customer['zip'],
        $customer['country'],
        $customer['tax_id'],
        $customer['notes'],
        $customer['credit_limit'],
        $customer['status'],
        $customer['signup_date']
    ]);
}

function updateCustomerEnhanced($customerId, $updateData) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("
        UPDATE customers 
        SET name = ?, email = ?, phone = ?, company = ?, address = ?, 
            city = ?, state = ?, zip = ?, country = ?, tax_id = ?, 
            notes = ?, credit_limit = ?, status = ?
        WHERE id = ?
    ");
    
    return $stmt->execute([
        $updateData['name'],
        $updateData['email'],
        $updateData['phone'],
        $updateData['company'],
        $updateData['address'],
        $updateData['city'],
        $updateData['state'],
        $updateData['zip'],
        $updateData['country'],
        $updateData['tax_id'],
        $updateData['notes'],
        $updateData['credit_limit'],
        $updateData['status'],
        $customerId
    ]);
}

function getCustomersEnhanced($search = '', $status = '', $sort = 'newest', $page = 1, $perPage = 20) {
    $conn = getDbConnection();
    
    $conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $conditions[] = "(c.name LIKE ? OR c.email LIKE ? OR c.company LIKE ? OR c.phone LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    if (!empty($status)) {
        $conditions[] = "c.status = ?";
        $params[] = $status;
    }
    
    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
    
    // Determine sort order
    $orderBy = 'ORDER BY ';
    switch ($sort) {
        case 'name_asc':
            $orderBy .= 'c.name ASC';
            break;
        case 'name_desc':
            $orderBy .= 'c.name DESC';
            break;
        case 'oldest':
            $orderBy .= 'c.created_at ASC';
            break;
        case 'spent_desc':
            $orderBy .= 'total_spent DESC';
            break;
        case 'orders_desc':
            $orderBy .= 'total_orders DESC';
            break;
        default:
            $orderBy .= 'c.created_at DESC';
    }
    
    // Get total count
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM customers c $whereClause");
    $countStmt->execute($params);
    $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $total = $totalResult['total'];
    
    // Get paginated results
    $offset = ($page - 1) * $perPage;
    
    $sql = "
        SELECT c.*, 
               COUNT(DISTINCT o.id) as total_orders,
               COALESCE(SUM(o.total_amount), 0) as total_spent,
               MAX(o.po_date) as last_order_date
        FROM customers c
        LEFT JOIN orders o ON c.id = o.customer_id
        $whereClause
        GROUP BY c.id
        $orderBy
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    
    // Bind parameters dynamically
    $paramTypes = '';
    $boundParams = [];
    
    foreach ($params as $param) {
        $boundParams[] = $param;
        $paramTypes .= 's';
    }
    
    $boundParams[] = $perPage;
    $boundParams[] = $offset;
    $paramTypes .= 'ii';
    
    $stmt->execute($boundParams);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'customers' => $customers,
        'total' => $total
    ];
}

function getCustomerDetails($customerId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("
        SELECT c.*, 
               COUNT(DISTINCT o.id) as total_orders,
               COALESCE(SUM(o.total_amount), 0) as total_spent,
               MAX(o.po_date) as last_order_date
        FROM customers c
        LEFT JOIN orders o ON c.id = o.customer_id
        WHERE c.id = ?
        GROUP BY c.id
    ");
    
    $stmt->execute([$customerId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getCustomerOrders($customerId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("
        SELECT o.*, COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.customer_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    
    $stmt->execute([$customerId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCustomerCommunications($customerId) {
    $conn = getDbConnection();
    
    // First, ensure the table exists
    try {
        $stmt = $conn->prepare("
            SELECT * FROM customer_communications
            WHERE customer_id = ?
            ORDER BY sent_at DESC
            LIMIT 10
        ");
        
        $stmt->execute([$customerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Table doesn't exist, return empty array
        return [];
    }
}

function getCustomerStatistics() {
    $conn = getDbConnection();
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_customers,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_customers,
                COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_customers,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_customers,
                COALESCE(AVG(total_spent), 0) as avg_customer_value
            FROM (
                SELECT 
                    c.id, 
                    c.status, 
                    c.created_at, 
                    COALESCE(SUM(o.total_amount), 0) as total_spent
                FROM customers c
                LEFT JOIN orders o ON c.id = o.customer_id
                GROUP BY c.id
            ) as customer_stats
        ");
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ensure all keys exist
        return [
            'total_customers' => $result['total_customers'] ?? 0,
            'active_customers' => $result['active_customers'] ?? 0,
            'inactive_customers' => $result['inactive_customers'] ?? 0,
            'new_customers' => $result['new_customers'] ?? 0,
            'avg_customer_value' => $result['avg_customer_value'] ?? 0
        ];
    } catch (Exception $e) {
        // Fallback if query fails
        return [
            'total_customers' => 0,
            'active_customers' => 0,
            'inactive_customers' => 0,
            'new_customers' => 0,
            'avg_customer_value' => 0
        ];
    }
}

function sendWelcomeEmail($email, $name) {
    $subject = "Welcome to Alphasonix!";
    $body = "Dear $name,\n\n";
    $body .= "Thank you for choosing Alphasonix. We're excited to have you as our customer.\n\n";
    $body .= "If you have any questions, please don't hesitate to contact us.\n\n";
    $body .= "Best regards,\nThe Alphasonix Team";
    
    return sendEmailNotification($email, $subject, $body);
}

function sendCustomerCommunication($customerId, $type, $subject, $message) {
    $conn = getDbConnection();
    
    // Get customer email
    $customer = getCustomer($customerId);
    if (!$customer) return false;
    
    try {
        // Save communication record
        $stmt = $conn->prepare("
            INSERT INTO customer_communications (customer_id, type, subject, message, sent_by, sent_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $customerId,
            $type,
            $subject,
            $message,
            $_SESSION['user_id']
        ]);
        
        // Send actual email
        if ($type === 'email') {
            return sendEmailNotification($customer['email'], $subject, $message);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Communication error: " . $e->getMessage());
        return false;
    }
}

function importCustomersFromCSV($filePath) {
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        return ['success' => false, 'error' => 'Failed to open file'];
    }
    
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        return ['success' => false, 'error' => 'Empty CSV file'];
    }
    
    $imported = 0;
    $errors = [];
    
    // Map CSV headers to database fields
    $headerMap = [
        'name' => array_search('name', $header) !== false ? array_search('name', $header) : null,
        'email' => array_search('email', $header) !== false ? array_search('email', $header) : null,
        'phone' => array_search('phone', $header) !== false ? array_search('phone', $header) : null,
        'company' => array_search('company', $header) !== false ? array_search('company', $header) : null,
        'address' => array_search('address', $header) !== false ? array_search('address', $header) : null,
        'city' => array_search('city', $header) !== false ? array_search('city', $header) : null,
        'state' => array_search('state', $header) !== false ? array_search('state', $header) : null,
        'zip' => array_search('zip', $header) !== false ? array_search('zip', $header) : null,
        'country' => array_search('country', $header) !== false ? array_search('country', $header) : null,
        'tax_id' => array_search('tax_id', $header) !== false ? array_search('tax_id', $header) : null,
        'notes' => array_search('notes', $header) !== false ? array_search('notes', $header) : null,
        'credit_limit' => array_search('credit_limit', $header) !== false ? array_search('credit_limit', $header) : null
    ];
    
    // Check required fields
    if ($headerMap['name'] === null || $headerMap['email'] === null) {
        fclose($handle);
        return ['success' => false, 'error' => 'CSV must contain name and email columns'];
    }
    
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) < 2) continue; // Minimum: name and email
        
        try {
            $customerData = [
                'id' => getNextCustomerId(),
                'name' => $row[$headerMap['name']] ?? '',
                'email' => $row[$headerMap['email']] ?? '',
                'phone' => $row[$headerMap['phone']] ?? '',
                'company' => $row[$headerMap['company']] ?? '',
                'address' => $row[$headerMap['address']] ?? '',
                'city' => $row[$headerMap['city']] ?? '',
                'state' => $row[$headerMap['state']] ?? '',
                'zip' => $row[$headerMap['zip']] ?? '',
                'country' => $row[$headerMap['country']] ?? '',
                'tax_id' => $row[$headerMap['tax_id']] ?? '',
                'notes' => $row[$headerMap['notes']] ?? '',
                'credit_limit' => $row[$headerMap['credit_limit']] ?? '0',
                'status' => 'active',
                'signup_date' => date('Y-m-d')
            ];
            
            if (addCustomerEnhanced($customerData)) {
                $imported++;
            }
        } catch (Exception $e) {
            $errors[] = "Row " . ($imported + count($errors) + 1) . ": " . $e->getMessage();
        }
    }
    
    fclose($handle);
    
    if ($imported > 0) {
        return ['success' => true, 'imported' => $imported];
    } else {
        return ['success' => false, 'error' => 'No customers imported. Errors: ' . implode(', ', $errors)];
    }
}

function exportCustomersToCSV() {
    requireRole('manager');
    
    $customers = getCustomers();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=customers_export_' . date('Y-m-d_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");
    
    // Headers
    fputcsv($output, [
        'ID', 'Name', 'Email', 'Phone', 'Company', 'Address', 
        'City', 'State', 'ZIP', 'Country', 'Tax ID', 'Status',
        'Credit Limit', 'Total Orders', 'Total Spent', 'Signup Date'
    ]);
    
    // Data
    foreach ($customers as $customer) {
        fputcsv($output, [
            $customer['id'],
            $customer['name'],
            $customer['email'],
            $customer['phone'] ?? '',
            $customer['company'] ?? '',
            $customer['address'] ?? '',
            $customer['city'] ?? '',
            $customer['state'] ?? '',
            $customer['zip'] ?? '',
            $customer['country'] ?? '',
            $customer['tax_id'] ?? '',
            $customer['status'] ?? 'active',
            $customer['credit_limit'] ?? '0',
            $customer['total_orders'] ?? '0',
            $customer['total_spent'] ?? '0',
            $customer['signup_date'] ?? ''
        ]);
    }
    
    fclose($output);
    logActivity("Customers exported", "Customer database exported to CSV");
    exit;
}

// ==========================================
// REQUEST HANDLING
// ==========================================

// Handle form submission for adding a new customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token. Please try again.'];
        header('Location: customers.php');
        exit;
    }

    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone'] ?? '');
    $company = sanitize_input($_POST['company'] ?? '');
    $address = sanitize_input($_POST['address'] ?? '');
    $city = sanitize_input($_POST['city'] ?? '');
    $state = sanitize_input($_POST['state'] ?? '');
    $zip = sanitize_input($_POST['zip'] ?? '');
    $country = sanitize_input($_POST['country'] ?? '');
    $tax_id = sanitize_input($_POST['tax_id'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');
    $credit_limit = sanitize_input($_POST['credit_limit'] ?? '0');

    // Validate inputs
    if (!validateInput($email, 'email')) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Please provide a valid email address.'];
    } elseif (!empty($phone) && !validateInput($phone, 'phone')) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Please provide a valid phone number.'];
    } elseif (!empty($name) && !empty($email)) {
        try {
            $newCustomerId = getNextCustomerId();

            $newCustomer = [
                'id' => $newCustomerId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'company' => $company,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zip' => $zip,
                'country' => $country,
                'tax_id' => $tax_id,
                'notes' => $notes,
                'credit_limit' => $credit_limit,
                'status' => 'active',
                'signup_date' => date('Y-m-d')
            ];
            
            if (addCustomerEnhanced($newCustomer)) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Customer added successfully! (ID: ' . $newCustomerId . ')'];
                logActivity("Customer added", "Customer {$newCustomerId} - {$name} added to database");
                
                // Send welcome email
                sendWelcomeEmail($email, $name);
                
                header('Location: ./customers.php?new=' . urlencode($newCustomerId));
                exit;
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Error adding customer to database.'];
            }
        } catch (Exception $e) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Please provide valid name and email.'];
    }
}

// Handle customer update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token. Please try again.'];
        header('Location: customers.php');
        exit;
    }

    $customerId = sanitize_input($_POST['customer_id']);
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone'] ?? '');
    $company = sanitize_input($_POST['company'] ?? '');
    $address = sanitize_input($_POST['address'] ?? '');
    $city = sanitize_input($_POST['city'] ?? '');
    $state = sanitize_input($_POST['state'] ?? '');
    $zip = sanitize_input($_POST['zip'] ?? '');
    $country = sanitize_input($_POST['country'] ?? '');
    $tax_id = sanitize_input($_POST['tax_id'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');
    $credit_limit = sanitize_input($_POST['credit_limit'] ?? '0');
    $status = sanitize_input($_POST['status'] ?? 'active');

    if (!empty($name) && !empty($email) && validateInput($email, 'email')) {
        try {
            $updateData = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'company' => $company,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zip' => $zip,
                'country' => $country,
                'tax_id' => $tax_id,
                'notes' => $notes,
                'credit_limit' => $credit_limit,
                'status' => $status
            ];
            
            if (updateCustomerEnhanced($customerId, $updateData)) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Customer updated successfully!'];
                logActivity("Customer updated", "Customer {$customerId} - {$name} updated");
                header('Location: customers.php');
                exit;
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Error updating customer.'];
            }
        } catch (Exception $e) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Please provide valid name and email.'];
    }
}

// Handle customer deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token. Please try again.'];
        header('Location: customers.php');
        exit;
    }

    $customerId = sanitize_input($_POST['customer_id']);

    try {
        if (deleteCustomer($customerId)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Customer deleted successfully!'];
            logActivity("Customer deleted", "Customer {$customerId} deleted");
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error deleting customer.'];
        }
    } catch (Exception $e) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
    }

    header('Location: customers.php');
    exit;
}

// Handle customer import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_customers'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token. Please try again.'];
        header('Location: customers.php');
        exit;
    }

    if (isset($_FILES['customer_csv']) && $_FILES['customer_csv']['error'] == 0) {
        $result = importCustomersFromCSV($_FILES['customer_csv']['tmp_name']);
        
        if ($result['success']) {
            $_SESSION['message'] = ['type' => 'success', 'text' => "Imported {$result['imported']} customers successfully!"];
            logActivity("Customers imported", "{$result['imported']} customers imported from CSV");
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => $result['error']];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Please select a CSV file to import.'];
    }

    header('Location: customers.php');
    exit;
}

// Handle customer export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    exportCustomersToCSV();
    exit;
}

// Handle sending communication
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_communication'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token. Please try again.'];
        header('Location: customers.php');
        exit;
    }

    $customerId = sanitize_input($_POST['customer_id']);
    $type = sanitize_input($_POST['communication_type']);
    $subject = sanitize_input($_POST['subject']);
    $message = sanitize_input($_POST['message']);

    if (sendCustomerCommunication($customerId, $type, $subject, $message)) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Communication sent successfully!'];
        logActivity("Customer communication", "Sent {$type} to customer {$customerId}");
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to send communication.'];
    }

    header('Location: customers.php?view=' . $customerId);
    exit;
}

// ==========================================
// DATA FETCHING FOR DISPLAY
// ==========================================

// Get parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$sort = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'newest';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

// Get customers with filters
$customersData = getCustomersEnhanced($search, $status, $sort, $page, $perPage);
$customers = $customersData['customers'];
$totalCustomers = $customersData['total'];
$totalPages = ceil($totalCustomers / $perPage);

// Get customer statistics
$customerStats = getCustomerStatistics();

$editCustomer = null;
$viewCustomer = null;

// Check if we're editing a customer
if (isset($_GET['edit'])) {
    $editId = sanitize_input($_GET['edit']);
    $editCustomer = getCustomerDetails($editId);
}

// Check if we're viewing customer details
if (isset($_GET['view'])) {
    $viewId = sanitize_input($_GET['view']);
    $viewCustomer = getCustomerDetails($viewId);
    if ($viewCustomer) {
        $viewCustomer['orders'] = getCustomerOrders($viewId);
        $viewCustomer['communications'] = getCustomerCommunications($viewId);
    }
}

// Generate CSRF token
$csrfToken = generateCsrfToken();

// Get current page for active state highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - Alphasonix</title>
    <style>
        /* Your existing CSS styles enhanced */
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

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }

        .customer-management-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .customer-management-header h1 {
            font-size: 2rem;
            color: #212529;
            font-weight: 700;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .stat-info h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #212529;
            margin: 0;
        }

        .stat-info p {
            font-size: 0.875rem;
            color: #6c757d;
            margin: 0;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .form-section {
            padding: 1.5rem;
        }

        .form-section h2 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: #212529;
            font-weight: 600;
        }

        .form-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #dee2e6;
        }

        .form-tab {
            padding: 0.5rem 1rem;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            color: #6c757d;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .form-tab.active {
            color: #4361ee;
            border-bottom-color: #4361ee;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
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

        textarea.form-input {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

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
            background-color: #2a9d8f;
            color: white;
        }

        .btn-primary:hover {
            background-color: #000000ff;
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

        .btn-info {
            background-color: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background-color: #138496;
        }

        .btn-export {
            background-color: #28a745;
            color: white;
        }

        .btn-export:hover {
            background-color: #218838;
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }

        .table-section {
            padding: 1.5rem;
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

        /* Enhanced Search and Filter */
        .search-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 200px;
            padding: 0.75rem 1rem;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #8c8f9eff;
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }

        .filter-select {
            padding: 0.75rem 1rem;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }

        .customers-table {
            width: 100%;
            border-collapse: collapse;
        }

        .customers-table th {
            background-color: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 1px solid #dee2e6;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .customers-table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }

        .customer-row:hover {
            background-color: #f8f9fa;
        }

        .customer-row.new-entry {
            animation: highlight 2s ease;
        }

        @keyframes highlight {
            0% { background-color: rgba(67, 97, 238, 0.2); }
            100% { background-color: transparent; }
        }

        .customer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 1rem;
        }

        .customer-info {
            display: flex;
            align-items: center;
        }

        .customer-details {
            line-height: 1.2;
        }

        .customer-name {
            font-weight: 600;
            color: #212529;
        }

        .customer-email {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .customer-company {
            font-size: 0.75rem;
            color: #6c757d;
            font-style: italic;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
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
        }

        .delete-modal-content {
            width: 90%;
            max-width: 500px;
            padding: 2rem;
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

        /* Customer Detail Modal */
        .customer-detail-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            overflow: auto;
        }

        .customer-detail-content {
            width: 90%;
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            max-height: 90vh;
            overflow-y: auto;
        }

        .detail-header {
            padding: 2rem;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .detail-body {
            padding: 2rem;
        }

        .detail-section {
            margin-bottom: 2rem;
        }

        .detail-section h4 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #212529;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .info-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 0.375rem;
        }

        .info-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-size: 1rem;
            color: #212529;
            font-weight: 500;
        }

        .orders-list, .communications-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .order-item, .comm-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 0.375rem;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-item:hover, .comm-item:hover {
            background: #e9ecef;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a, .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            color: #495057;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .pagination a:hover {
            background-color: #4361ee;
            color: white;
            border-color: #4361ee;
        }

        .pagination .current {
            background-color: #4361ee;
            color: white;
            border-color: #4361ee;
        }

        /* Communication Form */
        .communication-form {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 0.375rem;
            margin-top: 1rem;
        }

        .communication-form h5 {
            margin-bottom: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
        }

        /* Import/Export Section */
        .import-export-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
            margin-bottom: 2rem;
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .import-box, .export-box {
            flex: 1;
            min-width: 250px;
        }

        .import-box h3, .export-box h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #212529;
        }

        @media (max-width: 1024px) {
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

            .form-grid {
                grid-template-columns: 1fr;
            }

            .stats-row {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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

            .customers-table {
                display: block;
                overflow-x: auto;
            }

            .search-container {
                flex-direction: column;
            }

            .header-actions {
                flex-direction: column;
                width: 100%;
                gap: 0.5rem;
            }

            .header-actions .btn {
                width: 100%;
            }

            .customer-detail-content {
                margin: 0;
                width: 100%;
                height: 100%;
                border-radius: 0;
            }
        }

        /* Loading spinner */
        .loading-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #4361ee;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="app-container">
        <button class="mobile-menu-toggle" style="display: none;" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>

        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <?php if (isset($_SESSION['message'])): ?>
                <?php 
                $messageType = $_SESSION['message']['type'];
                echo "<div class='message-alert message-{$messageType}'>" .
                    htmlspecialchars($_SESSION['message']['text']) . "</div>";
                unset($_SESSION['message']);
                ?>
            <?php endif; ?>

            <div class="customer-management-header">
                <h1>👥 Customer Management</h1>
                <div class="header-actions">
                    <a href="customers.php?export=csv" class="btn btn-export">
                        <i class="bi bi-download"></i> Export CSV
                    </a>
                    <button type="button" class="btn btn-info" onclick="showImportForm()">
                        <i class="bi bi-upload"></i> Import
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($customerStats['total_customers']) ?></h3>
                        <p>Total Customers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #20c997);">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($customerStats['active_customers']) ?></h3>
                        <p>Active Customers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107, #ff9800);">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($customerStats['new_customers']) ?></h3>
                        <p>New This Month</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="stat-info">
                        <h3>$<?= number_format($customerStats['avg_customer_value'], 2) ?></h3>
                        <p>Avg. Customer Value</p>
                    </div>
                </div>
            </div>

            <!-- Import/Export Section (Hidden by default) -->
            <div class="import-export-section" id="importSection" style="display: none;">
                <div class="import-box">
                    <h3><i class="bi bi-cloud-upload"></i> Import Customers</h3>
                    <form action="customers.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <div class="form-group">
                            <label class="form-label">CSV File</label>
                            <input type="file" name="customer_csv" class="form-input" accept=".csv" required>
                            <small style="color: #6c757d;">Required columns: name, email. Optional: phone, company, address, etc.</small>
                        </div>
                        <button type="submit" name="import_customers" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Import
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="hideImportForm()">Cancel</button>
                    </form>
                </div>
                <div class="export-box">
                    <h3><i class="bi bi-cloud-download"></i> Export Template</h3>
                    <p>Download a CSV template with all available fields.</p>
                    <a href="download_customer_template.php" class="btn btn-secondary">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Download Template
                    </a>
                </div>
            </div>

            <!-- Main Form Section -->
            <div class="form-section card <?= $editCustomer ? 'edit-form' : '' ?>" <?= $viewCustomer ? 'style="display:none;"' : '' ?>>
                <h2><?= $editCustomer ? '✏️ Edit Customer' : '➕ Add New Customer' ?></h2>
                
                <form action="customers.php" method="post" id="customerForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <?php if ($editCustomer): ?>
                        <input type="hidden" name="customer_id" value="<?= htmlspecialchars($editCustomer['id']) ?>">
                    <?php endif; ?>

                    <!-- Form Tabs -->
                    <div class="form-tabs">
                        <button type="button" class="form-tab active" onclick="switchTab('basic')">Basic Info</button>
                        <button type="button" class="form-tab" onclick="switchTab('contact')">Contact Details</button>
                        <button type="button" class="form-tab" onclick="switchTab('business')">Business Info</button>
                    </div>

                    <!-- Basic Info Tab -->
                    <div class="tab-content active" id="basic-tab">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="name">Customer Name: <span style="color: red;">*</span></label>
                                <input type="text" id="name" name="name" class="form-input"
                                    value="<?= $editCustomer ? htmlspecialchars($editCustomer['name']) : '' ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="email">Email Address: <span style="color: red;">*</span></label>
                                <input type="email" id="email" name="email" class="form-input"
                                    value="<?= $editCustomer ? htmlspecialchars($editCustomer['email']) : '' ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="phone">Phone Number:</label>
                                <input type="tel" id="phone" name="phone" class="form-input"
                                    value="<?= $editCustomer ? htmlspecialchars($editCustomer['phone'] ?? '') : '' ?>"
                                    placeholder="+1 (555) 123-4567">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="company">Company:</label>
                                <input type="text" id="company" name="company" class="form-input"
                                    value="<?= $editCustomer ? htmlspecialchars($editCustomer['company'] ?? '') : '' ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Contact Details Tab -->
                    <div class="tab-content" id="contact-tab">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label class="form-label" for="address">Street Address:</label>
                                <input type="text" id="address" name="address" class="form-input"
                                    value="<?= $editCustomer ? htmlspecialchars($editCustomer['address'] ?? '') : '' ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="city">City:</label>
                                <input type="text" id="city" name="city" class="form-input"
                                    value="<?= $editCustomer ? htmlspecialchars($editCustomer['city'] ?? '') : '' ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="state">State/Province:</label>
                                <input type="text" id="state" name="state" class="form-input"
                                    value="<?= $editCustomer ? htmlspecialchars($editCustomer['state'] ?? '') : '' ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="zip">ZIP/Postal Code:</label>
                                <input type="text" id="zip" name="zip" class="form-input"
                                    value="<?= $editCustomer ? htmlspecialchars($editCustomer['zip'] ?? '') : '' ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="country">Country:</label>
                                <input type="text" id="country" name="country" class="form-input"
                                    value="<?= $editCustomer ? htmlspecialchars($editCustomer['country'] ?? '') : '' ?>"
                                    list="country-list">
                                <datalist id="country-list">
                                    <option value="United States">
                                    <option value="Canada">
                                    <option value="United Kingdom">
                                    <option value="Australia">
                                    <option value="Germany">
                                    <option value="France">
                                    <option value="India">
                                    <option value="China">
                                    <option value="Japan">
                                </datalist>
                            </div>
                        </div>
                    </div>

                    <!-- Business Info Tab -->
                    <div class="tab-content" id="business-tab">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="tax_id">Tax ID/VAT Number:</label>
                                <input type="text" id="tax_id" name="tax_id" class="form-input"
                                    value="<?= $editCustomer ? htmlspecialchars($editCustomer['tax_id'] ?? '') : '' ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="credit_limit">Credit Limit ($):</label>
                                <input type="number" id="credit_limit" name="credit_limit" class="form-input"
                                    value="<?= $editCustomer ? htmlspecialchars($editCustomer['credit_limit'] ?? '0') : '0' ?>"
                                    min="0" step="0.01">
                            </div>

                            <?php if ($editCustomer): ?>
                            <div class="form-group">
                                <label class="form-label" for="status">Status:</label>
                                <select id="status" name="status" class="form-input">
                                    <option value="active" <?= ($editCustomer['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= ($editCustomer['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="pending" <?= ($editCustomer['status'] ?? 'active') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="form-group full-width">
                                <label class="form-label" for="notes">Notes:</label>
                                <textarea id="notes" name="notes" class="form-input" rows="4"><?= $editCustomer ? htmlspecialchars($editCustomer['notes'] ?? '') : '' ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="<?= $editCustomer ? 'update_customer' : 'add_customer' ?>"
                            class="btn <?= $editCustomer ? 'btn-primary' : 'btn-success' ?>">
                            <?= $editCustomer ? '✏️ Update Customer' : '➕ Add Customer' ?>
                        </button>

                        <?php if ($editCustomer): ?>
                            <a href="customers.php" class="btn btn-secondary">❌ Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Customer Detail View -->
            <?php if ($viewCustomer): ?>
            <div class="customer-detail-modal" style="display: block;">
                <div class="customer-detail-content">
                    <div class="detail-header">
                        <div>
                            <h2><?= htmlspecialchars($viewCustomer['name']) ?></h2>
                            <span class="status-badge status-<?= $viewCustomer['status'] ?? 'active' ?>">
                                <?= ucfirst($viewCustomer['status'] ?? 'active') ?>
                            </span>
                        </div>
                        <a href="customers.php" class="btn btn-secondary">
                            <i class="bi bi-x"></i> Close
                        </a>
                    </div>

                    <div class="detail-body">
                        <!-- Customer Info -->
                        <div class="detail-section">
                            <h4><i class="bi bi-person-vcard"></i> Contact Information</h4>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?= htmlspecialchars($viewCustomer['email']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Phone</div>
                                    <div class="info-value"><?= htmlspecialchars($viewCustomer['phone'] ?: 'Not provided') ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Company</div>
                                    <div class="info-value"><?= htmlspecialchars($viewCustomer['company'] ?: 'Not provided') ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Member Since</div>
                                    <div class="info-value"><?= date('M d, Y', strtotime($viewCustomer['signup_date'])) ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Address Info -->
                        <?php if (!empty($viewCustomer['address']) || !empty($viewCustomer['city'])): ?>
                        <div class="detail-section">
                            <h4><i class="bi bi-geo-alt"></i> Address</h4>
                            <div class="info-item">
                                <div class="info-value">
                                    <?= htmlspecialchars($viewCustomer['address'] ?: '') ?><br>
                                    <?= htmlspecialchars($viewCustomer['city'] ?: '') ?>
                                    <?= htmlspecialchars($viewCustomer['state'] ? ', ' . $viewCustomer['state'] : '') ?>
                                    <?= htmlspecialchars($viewCustomer['zip'] ?: '') ?><br>
                                    <?= htmlspecialchars($viewCustomer['country'] ?: '') ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Business Stats -->
                        <div class="detail-section">
                            <h4><i class="bi bi-graph-up"></i> Business Statistics</h4>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Total Orders</div>
                                    <div class="info-value"><?= number_format($viewCustomer['total_orders']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Total Spent</div>
                                    <div class="info-value">$<?= number_format($viewCustomer['total_spent'], 2) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Credit Limit</div>
                                    <div class="info-value">$<?= number_format($viewCustomer['credit_limit'] ?? 0, 2) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Last Order</div>
                                    <div class="info-value">
                                        <?= $viewCustomer['last_order_date'] ? date('M d, Y', strtotime($viewCustomer['last_order_date'])) : 'No orders yet' ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Orders -->
                        <div class="detail-section">
                            <h4><i class="bi bi-cart3"></i> Recent Orders</h4>
                            <div class="orders-list">
                                <?php if (empty($viewCustomer['orders'])): ?>
                                    <p class="text-secondary">No orders yet</p>
                                <?php else: ?>
                                    <?php foreach ($viewCustomer['orders'] as $order): ?>
                                        <div class="order-item">
                                            <div>
                                                <strong>#<?= htmlspecialchars($order['order_id']) ?></strong><br>
                                                <small><?= date('M d, Y', strtotime($order['po_date'])) ?> • 
                                                    <?= $order['item_count'] ?> items</small>
                                            </div>
                                            <div>
                                                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $order['status'])) ?>">
                                                    <?= htmlspecialchars($order['status']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Communication -->
                        <div class="detail-section">
                            <h4><i class="bi bi-envelope"></i> Send Communication</h4>
                            <form action="customers.php" method="post" class="communication-form">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="customer_id" value="<?= $viewCustomer['id'] ?>">
                                
                                <div class="form-group">
                                    <label class="form-label">Type</label>
                                    <select name="communication_type" class="form-input" required>
                                        <option value="email">Email</option>
                                        <option value="notification">System Notification</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Subject</label>
                                    <input type="text" name="subject" class="form-input" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Message</label>
                                    <textarea name="message" class="form-input" rows="4" required></textarea>
                                </div>
                                
                                <button type="submit" name="send_communication" class="btn btn-primary">
                                    <i class="bi bi-send"></i> Send
                                </button>
                            </form>
                        </div>

                        <!-- Communication History -->
                        <div class="detail-section">
                            <h4><i class="bi bi-chat-dots"></i> Communication History</h4>
                            <div class="communications-list">
                                <?php if (empty($viewCustomer['communications'])): ?>
                                    <p class="text-secondary">No communications yet</p>
                                <?php else: ?>
                                    <?php foreach ($viewCustomer['communications'] as $comm): ?>
                                        <div class="comm-item">
                                            <div>
                                                <strong><?= htmlspecialchars($comm['subject']) ?></strong><br>
                                                <small><?= date('M d, Y g:i A', strtotime($comm['sent_at'])) ?> • 
                                                    <?= ucfirst($comm['type']) ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Customers Table -->
            <div class="table-section card">
                <div class="table-header">
                    <h2>📋 Customer Directory</h2>
                    <div class="total-count">
                        Total: <strong><?= $totalCustomers ?></strong> customers
                    </div>
                </div>

                <div class="search-container">
                    <form method="get" action="customers.php" style="display: contents;">
                        <input type="text" name="search" class="search-input"
                            value="<?= htmlspecialchars($search) ?>"
                            placeholder="🔍 Search by name, email, company, or phone...">
                        
                        <select name="status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        </select>
                        
                        <select name="sort" class="filter-select">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                            <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                            <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                            <option value="spent_desc" <?= $sort === 'spent_desc' ? 'selected' : '' ?>>Highest Value</option>
                            <option value="orders_desc" <?= $sort === 'orders_desc' ? 'selected' : '' ?>>Most Orders</option>
                        </select>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Search
                        </button>
                        
                        <?php if (!empty($search) || !empty($status) || $sort !== 'newest'): ?>
                            <a href="customers.php" class="btn btn-secondary">
                                <i class="bi bi-x"></i> Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <table class="customers-table" id="customersTable">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Company</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="7" class="empty-state">
                                    <h3>📭 No customers found</h3>
                                    <p>Add your first customer using the form above.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php
                            $newCustomerId = isset($_GET['new']) ? sanitize_input($_GET['new']) : null;
                            foreach ($customers as $customer):
                            ?>
                                <tr class="customer-row <?= ($newCustomerId === $customer['id']) ? 'new-entry' : '' ?>">
                                    <td>
                                        <div class="customer-info">
                                            <div class="customer-avatar">
                                                <?= strtoupper(substr($customer['name'], 0, 2)) ?>
                                            </div>
                                            <div class="customer-details">
                                                <div class="customer-name"><?= htmlspecialchars($customer['name']) ?></div>
                                                <div class="customer-email"><?= htmlspecialchars($customer['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($customer['phone'] ?: '-') ?>
                                    </td>
                                    <td>
                                        <div class="customer-company">
                                            <?= htmlspecialchars($customer['company'] ?: '-') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?= number_format($customer['total_orders']) ?></strong>
                                    </td>
                                    <td>
                                        <strong>$<?= number_format($customer['total_spent'], 2) ?></strong>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $customer['status'] ?? 'active' ?>">
                                            <?= ucfirst($customer['status'] ?? 'active') ?>
                                        </span>
                                    </td>
                                    <td class="actions-cell">
                                        <a href="customers.php?view=<?= urlencode($customer['id']) ?>" 
                                           class="btn btn-info btn-small"
                                           title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="customers.php?edit=<?= urlencode($customer['id']) ?>" 
                                           class="btn btn-primary btn-small"
                                           title="Edit Customer">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button"
                                            onclick="confirmDelete('<?= htmlspecialchars($customer['id']) ?>', '<?= htmlspecialchars($customer['name']) ?>')"
                                            class="btn btn-danger btn-small" 
                                            title="Delete Customer">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= urlencode($sort) ?>">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        if ($startPage > 1) {
                            echo '<a href="?page=1&search=' . urlencode($search) . '&status=' . urlencode($status) . '&sort=' . urlencode($sort) . '">1</a>';
                            if ($startPage > 2) echo '<span>...</span>';
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= urlencode($sort) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) echo '<span>...</span>';
                            echo '<a href="?page=' . $totalPages . '&search=' . urlencode($search) . '&status=' . urlencode($status) . '&sort=' . urlencode($sort) . '">' . $totalPages . '</a>';
                        }
                        ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= urlencode($sort) ?>">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="deleteModal" class="delete-modal">
        <div class="delete-modal-content card">
            <h3>🗑️ Confirm Deletion</h3>
            <p>Are you sure you want to delete customer <strong id="customerNameToDelete"></strong>?</p>
            <p class="text-secondary">This action cannot be undone.</p>

            <div class="delete-modal-buttons">
                <form id="deleteForm" method="post" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="customer_id" id="customerIdToDelete">
                    <button type="submit" name="delete_customer" class="btn btn-danger">
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
        // Tab switching functionality
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.form-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Mark button as active
            event.target.classList.add('active');
        }

        // Import form toggle
        function showImportForm() {
            document.getElementById('importSection').style.display = 'flex';
        }

        function hideImportForm() {
            document.getElementById('importSection').style.display = 'none';
        }

        // Delete confirmation modal
        function confirmDelete(customerId, customerName) {
            document.getElementById('customerIdToDelete').value = customerId;
            document.getElementById('customerNameToDelete').textContent = customerName;
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

        document.getElementById('deleteModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeDeleteModal();
            }
        });

        // Form validation
        document.getElementById('customerForm').addEventListener('submit', function (e) {
            const nameInput = document.getElementById('name');
            const emailInput = document.getElementById('email');

            nameInput.classList.remove('is-invalid');
            emailInput.classList.remove('is-invalid');

            let isValid = true;

            if (!nameInput.value.trim()) {
                nameInput.classList.add('is-invalid');
                isValid = false;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailInput.value.trim() || !emailRegex.test(emailInput.value.trim())) {
                emailInput.classList.add('is-invalid');
                isValid = false;
            }

            // Validate phone if provided
            const phoneInput = document.getElementById('phone');
            if (phoneInput.value.trim()) {
                const phoneRegex = /^[\d\s\-\+\(\)]+$/;
                if (!phoneRegex.test(phoneInput.value.trim()) || phoneInput.value.trim().replace(/\D/g, '').length < 10) {
                    phoneInput.classList.add('is-invalid');
                    isValid = false;
                }
            }

            if (!isValid) {
                e.preventDefault();
                alert('Please check the form for errors.');
            }
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

        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.mobile-menu-toggle');
            
            if (window.innerWidth <= 1024 && 
                !sidebar.contains(event.target) && 
                !toggleBtn.contains(event.target)) {
                sidebar.classList.remove('mobile-open');
            }
        });

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

        document.addEventListener('DOMContentLoaded', function() {
            const mobileToggle = document.querySelector('.mobile-menu-toggle');
            if (window.innerWidth <= 1024) {
                mobileToggle.style.display = 'block';
            }
        });

        // Format phone number as user types
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            let formattedValue = '';
            
            if (value.length > 0) {
                if (value.length <= 3) {
                    formattedValue = value;
                } else if (value.length <= 6) {
                    formattedValue = `(${value.slice(0, 3)}) ${value.slice(3)}`;
                } else {
                    formattedValue = `(${value.slice(0, 3)}) ${value.slice(3, 6)}-${value.slice(6, 10)}`;
                }
            }
            
            e.target.value = formattedValue;
        });

        // Auto-save form data to localStorage
        const form = document.getElementById('customerForm');
        const formInputs = form.querySelectorAll('input, textarea, select');

        // Load saved data
        formInputs.forEach(input => {
            const savedValue = localStorage.getItem('customerForm_' + input.name);
            if (savedValue && !input.value) {
                input.value = savedValue;
            }
        });

        // Save data on input
        formInputs.forEach(input => {
            input.addEventListener('input', function() {
                localStorage.setItem('customerForm_' + this.name, this.value);
            });
        });

        // Clear saved data on successful submit
        form.addEventListener('submit', function() {
            formInputs.forEach(input => {
                localStorage.removeItem('customerForm_' + input.name);
            });
        });
    </script>
</body>
</html>