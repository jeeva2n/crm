<?php
session_start();
require_once 'functions.php';

// Require admin privileges
requireAdmin('home.php');

// Handle actions
$action = $_GET['action'] ?? '';
$filename = $_GET['file'] ?? '';

// Fetch all data
function viewAllData()
{
    $path = __DIR__ . "/data/";
    return array_diff(scandir($path), ['.', '..']);
}

// View a single data file
function viewDataFile($fileName)
{
    $filePath = __DIR__ . "/data/" . $fileName;
    return file_exists($filePath) ? file_get_contents($filePath) : "File not found";
}

// Download CSV file
function downloadCsvFile($fileName)
{
    $filePath = __DIR__ . "/data/" . $fileName;

    if (!file_exists($filePath)) {
        return false;
    }

    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=$fileName");
    readfile($filePath);
    exit;
}

// Download all files as ZIP
function downloadAllDataAsZip()
{
    $zipName = "backup_" . date("Ymd_His") . ".zip";
    $zipPath = __DIR__ . "/backups/" . $zipName;

    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE);

    foreach (glob(__DIR__ . "/data/*") as $file) {
        $zip->addFile($file, basename($file));
    }

    $zip->close();
    return $zipName;
}

// Create backup
function createBackup()
{
    return downloadAllDataAsZip();
}

// List available data files
function getDataFilesList()
{
    return array_diff(scandir(__DIR__ . "/data/"), ['.', '..']);
}

// System statistics (example)
function getSystemStats()
{
    return [
        "php_version" => phpversion(),
        "memory_limit" => ini_get("memory_limit"),
        "upload_max" => ini_get("upload_max_filesize")
    ];
}



try {
    switch ($action) {
        case 'view':
            if ($filename === 'all') {
                viewAllData();
            } elseif ($filename) {
                viewDataFile($filename);
            }
            break;

        case 'download':
            if ($filename) {
                downloadCsvFile($filename);
            }
            break;

        case 'download_zip':
            downloadAllDataAsZip();
            break;

        case 'backup':
            $backupPath = createBackup();
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Backup created successfully: ' . basename($backupPath)];
            header('Location: data_management.php');
            exit;
    }
} catch (Exception $e) {
    $_SESSION['message'] = ['type' => 'error', 'text' => $e->getMessage()];
    header('Location: data_management.php');
    exit;
}

// Get data files list
$dataFiles = getDataFilesList();
$current_page = basename($_SERVER['PHP_SELF']);

// Get system stats
$stats = getSystemStats();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Management - Alphasonix</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="css/data_management.css">
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
                        <a href="get_history.php" class="nav-link-custom">
                            <i class="fas fa-history"></i>
                            <span class="d-none d-md-inline">History</span>
                        </a>
                        <a href="data_management.php" class="nav-link-custom active">
                            <i class="fas fa-database"></i>
                            <span class="d-none d-md-inline">Data</span>
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
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert-custom alert-<?= $_SESSION['message']['type'] === 'success' ? 'success' : 'error' ?>">
                <i class="fas fa-<?= $_SESSION['message']['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= htmlspecialchars($_SESSION['message']['text']) ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-database"></i>
                        Data Management Center
                    </h1>
                    <p class="page-subtitle">Manage and backup system data files</p>
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
                    <i class="fas fa-history"></i>
                    <p class="action-card-title">System History</p>
                </a>
                <a href="?action=view&file=all" class="action-card success">
                    <i class="fas fa-eye"></i>
                    <p class="action-card-title">View All Data</p>
                </a>
                <a href="?action=download_zip" class="action-card warning">
                    <i class="fas fa-file-archive"></i>
                    <p class="action-card-title">Download ZIP</p>
                </a>
                <a href="?action=backup" class="action-card" style="color: var(--danger);" onclick="return confirm('Create system backup?')">
                    <i class="fas fa-save"></i>
                    <p class="action-card-title">Create Backup</p>
                </a>
            </div>
        </div>

        <!-- System Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-users stat-icon"></i>
                <h3 class="stat-value"><?= number_format($stats['total_customers']) ?></h3>
                <p class="stat-label">Total Customers</p>
            </div>
            <div class="stat-card info">
                <i class="fas fa-shopping-cart stat-icon"></i>
                <h3 class="stat-value"><?= number_format($stats['total_orders']) ?></h3>
                <p class="stat-label">Total Orders</p>
            </div>
            <div class="stat-card success">
                <i class="fas fa-cube stat-icon"></i>
                <h3 class="stat-value"><?= number_format($stats['total_products']) ?></h3>
                <p class="stat-label">Total Products</p>
            </div>
            <div class="stat-card warning">
                <i class="fas fa-chart-line stat-icon"></i>
                <h3 class="stat-value"><?= number_format($stats['total_activities']) ?></h3>
                <p class="stat-label">System Activities</p>
            </div>
        </div>

        <!-- Data Files Cards -->
        <div class="files-grid">
            <?php foreach ($dataFiles as $file): ?>
                <div class="file-card">
                    <div class="file-header">
                        <div class="file-info">
                            <div class="file-name">
                                <i class="fas fa-file-csv"></i>
                                <?= htmlspecialchars($file['filename']) ?>
                            </div>
                            <div class="file-description">
                                <?= htmlspecialchars($file['description']) ?>
                            </div>
                        </div>
                        <div class="file-status <?= $file['exists'] ? 'status-exists' : 'status-missing' ?>">
                            <i class="fas fa-<?= $file['exists'] ? 'check-circle' : 'times-circle' ?>"></i>
                            <?= $file['exists'] ? 'Available' : 'Missing' ?>
                        </div>
                    </div>

                    <div class="file-details">
                        <div class="detail-item">
                            <div class="detail-icon"><i class="fas fa-hdd"></i></div>
                            <div class="detail-label">File Size</div>
                            <div class="detail-value"><?= formatFileSize($file['size']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-icon"><i class="fas fa-list-ol"></i></div>
                            <div class="detail-label">Records</div>
                            <div class="detail-value"><?= number_format($file['records']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-icon"><i class="fas fa-clock"></i></div>
                            <div class="detail-label">Last Modified</div>
                            <div class="detail-value">
                                <?= $file['modified'] ? date('Y-m-d H:i', $file['modified']) : 'Never' ?>
                            </div>
                        </div>
                    </div>

                    <div class="file-actions">
                        <?php if ($file['exists']): ?>
                            <a href="?action=view&file=<?= urlencode($file['filename']) ?>" class="btn-action btn-view">
                                <i class="fas fa-eye"></i>
                                View Data
                            </a>
                            <a href="?action=download&file=<?= urlencode($file['filename']) ?>" class="btn-action btn-download">
                                <i class="fas fa-download"></i>
                                Download
                            </a>
                        <?php else: ?>
                            <span class="btn-action btn-disabled">
                                <i class="fas fa-times"></i>
                                File Not Available
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>