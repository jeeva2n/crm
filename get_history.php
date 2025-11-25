<?php
// Enable error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'functions.php';

// Require admin privileges
requireAdmin('login.php');

// Get pagination parameters
$currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$searchTerm = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Get activity history from database
$historyData = getActivityHistory($currentPage, 50, $searchTerm);

// Get current page for sidebar
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Activity History - Alphasonix</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #5e72e4;
            --secondary: #2dce89;
            --success: #2dce89;
            --warning: #fb6340;
            --danger: #f5365c;
            --info: #11cdef;
            --light: #f7f8fb;
            --dark: #172b4d;
            --white: #ffffff;
            --gray-100: #f8f9fe;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #8898aa;
            --gray-700: #525f7f;
            --gray-800: #32325d;
            --gray-900: #212529;
            
            --shadow-sm: 0 0 0.5rem rgba(0, 0, 0, .075);
            --shadow: 0 0 2rem 0 rgba(136, 152, 170, .15);
            --shadow-lg: 0 0 3rem rgba(0, 0, 0, .175);
            --border-radius: 0.375rem;
            --border-radius-lg: 0.75rem;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--light);
            color: var(--gray-800);
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        /* Navigation Bar */
        .navbar-custom {
            background: var(--white);
            box-shadow: var(--shadow-sm);
            padding: 1rem 0;
            margin-bottom: 2rem;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary) !important;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .navbar-brand i {
            font-size: 1.75rem;
        }

        .navbar-nav {
            gap: 0.5rem;
        }

        .nav-link-custom {
            color: var(--gray-700) !important;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        .nav-link-custom:hover {
            background: var(--gray-100);
            color: var(--primary) !important;
        }

        .nav-link-custom.active {
            background: rgba(94, 114, 228, 0.1);
            color: var(--primary) !important;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--gray-600);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .logout-btn {
            background: var(--danger);
            color: var(--white);
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .logout-btn:hover {
            background: #e53755;
            color: var(--white);
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* Page Header */
        .page-header {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-subtitle {
            color: var(--gray-600);
            margin: 0;
        }

        .admin-badge {
            background: linear-gradient(135deg, var(--primary), #667eea);
            color: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Action Section */
        .action-section {
            margin-bottom: 2rem;
        }

        .action-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            transition: all 0.3s ease;
            text-align: center;
            border: 2px solid transparent;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow);
            border-color: var(--primary);
        }

        .action-card i {
            font-size: 2rem;
            margin-bottom: 0.75rem;
            display: block;
        }

        .action-card.primary { color: var(--primary); }
        .action-card.info { color: var(--info); }
        .action-card.success { color: var(--success); }

        .action-card-title {
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }

        /* Search Section */
        .search-section {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
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
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--gray-100);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(94, 114, 228, 0.1);
        }

        .btn-search {
            background: var(--primary);
            color: var(--white);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-search:hover {
            background: #4c63d2;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-clear {
            background: var(--gray-400);
            color: var(--white);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-clear:hover {
            background: var(--gray-500);
            color: var(--white);
            transform: translateY(-2px);
        }

        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary);
        }

        .stat-card.success::before { background: var(--success); }
        .stat-card.info::before { background: var(--info); }
        .stat-card.warning::before { background: var(--warning); }

        .stat-icon {
            position: absolute;
            right: 1.5rem;
            top: 1.5rem;
            font-size: 2rem;
            opacity: 0.3;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0;
        }

        .stat-label {
            color: var(--gray-600);
            margin: 0;
            font-weight: 500;
        }

        /* History Cards - Kanban Style */
        .history-grid {
            display: grid;
            gap: 1.5rem;
        }

        .history-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary);
        }

        .history-card.success { border-left-color: var(--success); }
        .history-card.warning { border-left-color: var(--warning); }
        .history-card.danger { border-left-color: var(--danger); }
        .history-card.info { border-left-color: var(--info); }

        .history-card:hover {
            box-shadow: var(--shadow);
            transform: translateX(5px);
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .history-user {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .history-timestamp {
            color: var(--gray-500);
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .history-action {
            background: var(--gray-100);
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            display: inline-block;
            margin-bottom: 1rem;
            color: var(--primary);
            font-weight: 600;
        }

        .history-action.success { background: rgba(45, 206, 137, 0.1); color: var(--success); }
        .history-action.warning { background: rgba(251, 99, 64, 0.1); color: var(--warning); }
        .history-action.danger { background: rgba(245, 54, 92, 0.1); color: var(--danger); }
        .history-action.info { background: rgba(17, 205, 239, 0.1); color: var(--info); }

        .history-details {
            color: var(--gray-700);
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .history-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .history-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        .stage-badge {
            background: var(--info);
            color: var(--white);
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .stage-badge.success { background: var(--success); }
        .stage-badge.warning { background: var(--warning); }
        .stage-badge.danger { background: var(--danger); }

        .ip-badge {
            background: var(--gray-200);
            color: var(--gray-700);
            padding: 0.25rem 0.75rem;
            border-radius: var(--border-radius);
            font-size: 0.75rem;
            font-family: monospace;
        }

        /* No Data State */
        .no-data {
            background: var(--white);
            padding: 4rem 2rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            text-align: center;
        }

        .no-data i {
            font-size: 4rem;
            color: var(--gray-400);
            margin-bottom: 1rem;
        }

        .no-data h3 {
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        .no-data p {
            color: var(--gray-600);
            margin: 0;
        }

        /* Pagination */
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .page-link-custom {
            padding: 0.5rem 1rem;
            background: var(--white);
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius);
            color: var(--gray-700);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            min-width: 40px;
            text-align: center;
        }

        .page-link-custom:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(94, 114, 228, 0.05);
        }

        .page-link-custom.active {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }

        .page-link-custom.disabled {
            background: var(--gray-100);
            color: var(--gray-400);
            border-color: var(--gray-200);
            cursor: not-allowed;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar-custom {
                padding: 0.5rem 0;
            }

            .page-header {
                padding: 1.5rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .action-cards {
                grid-template-columns: 1fr;
            }

            .search-form {
                flex-direction: column;
            }

            .search-input, .btn-search, .btn-clear {
                width: 100%;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .history-header {
                flex-direction: column;
                gap: 0.5rem;
            }

            .history-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar-custom">
        <div class="main-container">
            <div class="d-flex justify-content-between align-items-center">
                <a href="home.php" class="navbar-brand">
                    <i class="fas fa-industry"></i>
                    Alphasonix CRM
                </a>
                
                <div class="d-flex align-items-center gap-4">
                    <div class="navbar-nav d-flex flex-row">
                        <a href="home.php" class="nav-link-custom">
                            <i class="fas fa-home"></i>
                            <span class="d-none d-md-inline">Dashboard</span>
                        </a>
                        <a href="orders.php" class="nav-link-custom">
                            <i class="fas fa-box"></i>
                            <span class="d-none d-md-inline">Orders</span>
                        </a>
                        <a href="pipeline.php" class="nav-link-custom">
                            <i class="fas fa-tasks"></i>
                            <span class="d-none d-md-inline">Pipeline</span>
                        </a>
                        <a href="get_history.php" class="nav-link-custom active">
                            <i class="fas fa-history"></i>
                            <span class="d-none d-md-inline">History</span>
                        </a>
                    </div>

                    <?php if (isset($_SESSION['username'])): ?>
                        <div class="user-info">
                            <div class="user-avatar">
                                <?= strtoupper(substr($_SESSION['username'], 0, 2)) ?>
                            </div>
                            <div class="d-none d-md-block">
                                <small class="d-block"><?= htmlspecialchars($_SESSION['username']) ?></small>
                                <small class="text-muted"><?= ucfirst($_SESSION['role'] ?? 'user') ?></small>
                            </div>
                            <a href="logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to log out?');">
                                <i class="fas fa-sign-out-alt"></i>
                                <span class="d-none d-md-inline">Logout</span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-history"></i>
                        System Activity History
                    </h1>
                    <p class="page-subtitle">Monitor all system activities and user actions</p>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-shield-alt"></i>
                    Administrator Access
                </div>
            </div>
        </div>

        <!-- Action Cards -->
        <div class="action-section">
            <div class="action-cards">
                <a href="home.php" class="action-card primary">
                    <i class="fas fa-arrow-left"></i>
                    <p class="action-card-title">Back to Dashboard</p>
                </a>
                <a href="get_history.php" class="action-card info">
                    <i class="fas fa-clock-rotate-left"></i>
                    <p class="action-card-title">Activity History</p>
                </a>
                <a href="data_management.php" class="action-card success">
                    <i class="fas fa-database"></i>
                    <p class="action-card-title">Data Management</p>
                </a>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <form method="GET" action="get_history.php">
                <div class="search-form">
                    <input type="text" 
                           name="search" 
                           class="search-input"
                           placeholder="Search actions, users, IP addresses, or details..." 
                           value="<?= htmlspecialchars($searchTerm) ?>">
                    <button type="submit" class="btn-search">
                        <i class="fas fa-search"></i>
                        Search
                    </button>
                    <?php if (!empty($searchTerm)): ?>
                        <a href="get_history.php" class="btn-clear">
                            <i class="fas fa-times"></i>
                            Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <i class="fas fa-chart-line stat-icon"></i>
                <h3 class="stat-value"><?= number_format($historyData['total_logs']) ?></h3>
                <p class="stat-label">Total Activities</p>
            </div>
            <div class="stat-card success">
                <i class="fas fa-file-alt stat-icon"></i>
                <h3 class="stat-value"><?= $historyData['current_page'] ?> / <?= $historyData['total_pages'] ?></h3>
                <p class="stat-label">Page</p>
            </div>
            <?php if (!empty($searchTerm)): ?>
                <div class="stat-card info">
                    <i class="fas fa-filter stat-icon"></i>
                    <h3 class="stat-value"><?= count($historyData['logs']) ?></h3>
                    <p class="stat-label">Search Results for "<?= htmlspecialchars($searchTerm) ?>"</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Activity History Cards -->
        <?php if (!empty($historyData['logs'])): ?>
            <div class="history-grid">
                <?php foreach ($historyData['logs'] as $log): ?>
                    <?php
                    // Determine card style based on action type
                    $cardClass = 'history-card';
                    $actionClass = 'history-action';
                    
                    if (stripos($log['action'], 'error') !== false || stripos($log['action'], 'failed') !== false) {
                        $cardClass .= ' danger';
                        $actionClass .= ' danger';
                    } elseif (stripos($log['action'], 'success') !== false || stripos($log['action'], 'created') !== false) {
                        $cardClass .= ' success';
                        $actionClass .= ' success';
                    } elseif (stripos($log['action'], 'warning') !== false || stripos($log['action'], 'updated') !== false) {
                        $cardClass .= ' warning';
                        $actionClass .= ' warning';
                    } else {
                        $cardClass .= ' info';
                        $actionClass .= ' info';
                    }
                    ?>
                    <div class="<?= $cardClass ?>">
                        <div class="history-header">
                            <div class="history-user">
                                <i class="fas fa-user-circle"></i>
                                <?= htmlspecialchars($log['user'] ?? 'System') ?>
                            </div>
                            <div class="history-timestamp">
                                <i class="far fa-clock"></i>
                                <?= htmlspecialchars($log['timestamp'] ?? 'N/A') ?>
                            </div>
                        </div>
                        
                        <div class="<?= $actionClass ?>">
                            <i class="fas fa-bolt"></i>
                            <?= htmlspecialchars($log['action'] ?? 'N/A') ?>
                        </div>
                        
                        <?php if (!empty($log['details'])): ?>
                            <div class="history-details">
                                <?= htmlspecialchars($log['details']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="history-meta">
                            <?php if (!empty($log['stage'])): ?>
                                <div class="history-meta-item">
                                    <span class="stage-badge">
                                        <i class="fas fa-layer-group"></i>
                                        <?= htmlspecialchars($log['stage']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <div class="history-meta-item">
                                <span class="ip-badge">
                                    <i class="fas fa-network-wired"></i>
                                    <?= htmlspecialchars($log['ip_address'] ?? 'Unknown') ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-data">
                <i class="fas fa-inbox"></i>
                <h3>No Activity Logs Found</h3>
                <p>
                    <?php if (!empty($searchTerm)): ?>
                        No activity logs found for your search criteria.
                    <?php else: ?>
                        No activity logs recorded yet. System activities will appear here as users interact with the system.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($historyData['total_pages'] > 1): ?>
            <div class="pagination-wrapper">
                <?php if ($historyData['has_previous']): ?>
                    <a href="?page=<?= $historyData['current_page'] - 1 ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>"
                        class="page-link-custom">
                        <i class="fas fa-chevron-left"></i>
                        Previous
                    </a>
                <?php else: ?>
                    <span class="page-link-custom disabled">
                        <i class="fas fa-chevron-left"></i>
                        Previous
                    </span>
                <?php endif; ?>

                <?php 
                // Show max 5 page numbers
                $start = max(1, $historyData['current_page'] - 2);
                $end = min($historyData['total_pages'], $historyData['current_page'] + 2);
                
                if ($start > 1): ?>
                    <a href="?page=1<?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>" 
                       class="page-link-custom">1</a>
                    <?php if ($start > 2): ?>
                        <span class="page-link-custom disabled">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i == $historyData['current_page']): ?>
                        <span class="page-link-custom active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>"
                            class="page-link-custom"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $historyData['total_pages']): ?>
                    <?php if ($end < $historyData['total_pages'] - 1): ?>
                        <span class="page-link-custom disabled">...</span>
                    <?php endif; ?>
                    <a href="?page=<?= $historyData['total_pages'] ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>" 
                       class="page-link-custom"><?= $historyData['total_pages'] ?></a>
                <?php endif; ?>

                <?php if ($historyData['has_next']): ?>
                    <a href="?page=<?= $historyData['current_page'] + 1 ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>"
                        class="page-link-custom">
                        Next
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="page-link-custom disabled">
                        Next
                        <i class="fas fa-chevron-right"></i>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 30 seconds to get latest activities
        setTimeout(function() {
            window.location.reload();
        }, 30000);

        // Add loading state to search form
        document.querySelector('form')?.addEventListener('submit', function() {
            this.classList.add('loading');
        });
    </script>
</body>
</html>