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
  <link rel="stylesheet" href="css/get_history.css">
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