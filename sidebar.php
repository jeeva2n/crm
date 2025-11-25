<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current page for active state highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <style>
    /* Sidebar Styles - Light Theme */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: 280px;
        background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
        border-right: 1px solid #dee2e6;
        padding: 0;
        z-index: 1000;
        overflow-y: auto;
        transition: all 0.3s ease;
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

    .nav-badge {
        margin-left: auto;
        background: #e63946;
        color: white;
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 1rem;
        font-weight: 600;
        min-width: 20px;
        text-align: center;
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

    /* Main content margin for sidebar */
    .main-content {
        margin-left: 280px;
        transition: margin-left 0.3s ease;
    }

    /* Mobile responsive */
    @media (max-width: 1024px) {
        .sidebar {
            transform: translateX(-100%);
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

    /* Font weight adjustments */
    * {
        font-weight: 500;
    }

    .nav-link {
        font-weight: 500;
    }

    .nav-link.active {
        font-weight: 600;
    }

    .profile-name {
        font-weight: 600;
    }

    /* Bootstrap icon adjustments */
    .bi {
        font-size: 1.125rem;
    }
    </style>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
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

        <div class="sidebar-nav">
            <!-- Dashboard Section -->
            <div class="nav-section">
                <h3 class="nav-title">Main</h3>
                <ul class="nav-links">
                    <li class="nav-item">
                        <a href="home.php" class="nav-link <?= $current_page == 'home.php' ? 'active' : '' ?>">
                            <span class="nav-icon"><i class="bi bi-speedometer2"></i></span>
                            <span>Dashboard</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Order Management Section -->
            <div class="nav-section">
                <h3 class="nav-title">Order Management</h3>
                <ul class="nav-links">
                    <li class="nav-item">
                        <a href="orders.php" class="nav-link <?= $current_page == 'orders.php' ? 'active' : '' ?>">
                            <span class="nav-icon"><i class="bi bi-cart-plus"></i></span>
                            <span>Create Order</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="pipeline.php" class="nav-link <?= $current_page == 'pipeline.php' ? 'active' : '' ?>">
                            <span class="nav-icon"><i class="bi bi-diagram-3"></i></span>
                            <span>Production Pipeline</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="order_tracking.php" class="nav-link <?= $current_page == 'order_tracking.php' ? 'active' : '' ?>">
                            <span class="nav-icon"><i class="bi bi-geo-alt"></i></span>
                            <span>Order Tracking</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Document Management Section -->
            <div class="nav-section">
                <h3 class="nav-title">Documents</h3>
                <ul class="nav-links">
                    <li class="nav-item">
                        <a href="./deep/login.php" class="nav-link <?= $current_page == 'document-manager.php' ? 'active' : '' ?>">
                            <span class="nav-icon"><i class="bi bi-folder"></i></span>
                            <span>Document Manager</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Client & Product Management -->
            <div class="nav-section">
                <h3 class="nav-title">Management</h3>
                <ul class="nav-links">
                    <li class="nav-item">
                        <a href="customers.php" class="nav-link <?= $current_page == 'customers.php' ? 'active' : '' ?>">
                            <span class="nav-icon"><i class="bi bi-people"></i></span>
                            <span>Client Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="products.php" class="nav-link <?= $current_page == 'products.php' ? 'active' : '' ?>">
                            <span class="nav-icon"><i class="bi bi-box-seam"></i></span>
                            <span>Product Catalog</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

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
                <a href="./deep/logout.php" class="logout-btn" title="Logout">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </nav>
      
    <script>
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