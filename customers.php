<?php
session_start();
require_once 'functions.php';

// Handle form submission for adding a new customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);

    if (!empty($name) && !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $newCustomerId = getNextCustomerId();

        $newCustomer = [
            'id' => $newCustomerId,
            'name' => $name,
            'email' => $email,
            'signup_date' => date('Y-m-d')
        ];
        
        if (addCustomer($newCustomer)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Customer added successfully! (ID: ' . $newCustomerId . ')'];
            header('Location: ./customers.php?new=' . urlencode($newCustomerId));
            exit;
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error adding customer to database.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Please provide valid name and email.'];
    }
}

// Handle customer update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
    $customerId = sanitize_input($_POST['customer_id']);
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);

    if (!empty($name) && !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $updateData = [
            'name' => $name,
            'email' => $email
        ];
        
        if (updateCustomer($customerId, $updateData)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Customer updated successfully!'];
            header('Location: customers.php');
            exit;
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error updating customer.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Please provide valid name and email.'];
    }
}

// Handle customer deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    $customerId = sanitize_input($_POST['customer_id']);

    // Check if customer has any orders
    if (customerHasOrders($customerId)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Cannot delete customer with existing orders.'];
    } else {
        if (deleteCustomer($customerId)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Customer deleted successfully!'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error deleting customer.'];
        }
    }

    header('Location: customers.php');
    exit;
}

$customers = getCustomers();
$editCustomer = null;

// Check if we're editing a customer
if (isset($_GET['edit'])) {
    $editId = sanitize_input($_GET['edit']);
    foreach ($customers as $customer) {
        if ($customer['id'] === $editId) {
            $editCustomer = $customer;
            break;
        }
    }
}

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
        /* Your existing CSS styles remain exactly the same */
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
        }

        .customer-management-header h1 {
            font-size: 2rem;
            color: #212529;
            font-weight: 700;
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

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
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

        .search-container {
            margin-bottom: 1.5rem;
        }

        .search-input {
            width: 100%;
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
        }

        .customers-table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
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
            </div>

            <div class="form-section card <?= $editCustomer ? 'edit-form' : '' ?>">
                <h2><?= $editCustomer ? '✏️ Edit Customer' : '➕ Add New Customer' ?></h2>
                <form action="customers.php" method="post">
                    <?php if ($editCustomer): ?>
                        <input type="hidden" name="customer_id" value="<?= htmlspecialchars($editCustomer['id']) ?>">
                    <?php endif; ?>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="name">Customer Name:</label>
                            <input type="text" id="name" name="name" class="form-input"
                                value="<?= $editCustomer ? htmlspecialchars($editCustomer['name']) : '' ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="email">Email Address:</label>
                            <input type="email" id="email" name="email" class="form-input"
                                value="<?= $editCustomer ? htmlspecialchars($editCustomer['email']) : '' ?>" required>
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

            <div class="table-section card">
                <div class="table-header">
                    <h2>📋 Customer Directory</h2>
                    <div class="total-count">
                        Total Customers: <strong><?= count($customers) ?></strong>
                    </div>
                </div>

                <div class="search-container">
                    <input type="text" id="customerSearch" class="search-input"
                        placeholder="🔍 Search customers by name, email, or ID...">
                </div>

                <table class="customers-table" id="customersTable">
                    <thead>
                        <tr>
                            <th>👤 Customer ID</th>
                            <th>📝 Name</th>
                            <th>📧 Email</th>
                            <th>📅 Sign-up Date</th>
                            <th>🛠️ Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="5" class="empty-state">
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
                                    <td><strong><?= htmlspecialchars($customer['id']) ?></strong></td>
                                    <td><?= htmlspecialchars($customer['name']) ?></td>
                                    <td><?= htmlspecialchars($customer['email']) ?></td>
                                    <td><?= htmlspecialchars($customer['signup_date']) ?></td>
                                    <td class="actions-cell">
                                        <a href="customers.php?edit=<?= urlencode($customer['id']) ?>" class="btn btn-primary btn-small"
                                            title="Edit Customer">
                                            ✏️ Edit
                                        </a>
                                        <button type="button"
                                            onclick="confirmDelete('<?= htmlspecialchars($customer['id']) ?>', '<?= htmlspecialchars($customer['name']) ?>')"
                                            class="btn btn-danger btn-small" title="Delete Customer">
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

    <div id="deleteModal" class="delete-modal">
        <div class="delete-modal-content card">
            <h3>🗑️ Confirm Deletion</h3>
            <p>Are you sure you want to delete customer <strong id="customerNameToDelete"></strong>?</p>
            <p class="text-secondary">This action cannot be undone.</p>

            <div class="delete-modal-buttons">
                <form id="deleteForm" method="post" style="display: inline;">
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
        // Search functionality
        document.getElementById('customerSearch').addEventListener('keyup', function () {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('.customer-row');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

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
        document.querySelector('.form-section form').addEventListener('submit', function (e) {
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
    </script>
</body>
</html>