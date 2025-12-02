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

        .action-card.primary {
            color: var(--primary);
        }

        .action-card.info {
            color: var(--info);
        }

        .action-card.success {
            color: var(--success);
        }

        .action-card.warning {
            color: var(--warning);
        }

        .action-card-title {
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 2rem;
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

        .stat-card.success::before {
            background: var(--success);
        }

        .stat-card.info::before {
            background: var(--info);
        }

        .stat-card.warning::before {
            background: var(--warning);
        }

        .stat-card.danger::before {
            background: var(--danger);
        }

        .stat-icon {
            position: absolute;
            right: 1.5rem;
            top: 1.5rem;
            font-size: 3rem;
            opacity: 0.1;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0;
            line-height: 1;
        }

        .stat-label {
            color: var(--gray-600);
            margin: 0.5rem 0 0 0;
            font-weight: 500;
        }

        /* File Cards - Kanban Style */
        .files-grid {
            display: grid;
            gap: 1.5rem;
        }

        .file-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            padding: 2rem;
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary);
        }

        .file-card:hover {
            box-shadow: var(--shadow);
            transform: translateX(5px);
        }

        .file-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .file-name i {
            color: var(--primary);
        }

        .file-description {
            color: var(--gray-600);
            line-height: 1.5;
        }

        .file-status {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .status-exists {
            background: rgba(45, 206, 137, 0.1);
            color: var(--success);
        }

        .status-missing {
            background: rgba(245, 54, 92, 0.1);
            color: var(--danger);
        }

        .file-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background: var(--gray-100);
            border-radius: var(--border-radius);
        }

        .detail-item {
            text-align: center;
        }

        .detail-icon {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .detail-label {
            font-size: 0.75rem;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .detail-value {
            font-weight: 700;
            color: var(--gray-800);
            font-size: 1.125rem;
        }

        .file-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-weight: 600;
            border: none;
            cursor: pointer;
        }

        .btn-view {
            background: var(--primary);
            color: var(--white);
        }

        .btn-view:hover {
            background: #4c63d2;
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-download {
            background: var(--success);
            color: var(--white);
        }

        .btn-download:hover {
            background: #26a969;
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-disabled {
            background: var(--gray-300);
            color: var(--gray-600);
            cursor: not-allowed;
        }

        /* Message Alert */
        .alert-custom {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(45, 206, 137, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: rgba(245, 54, 92, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .file-header {
                flex-direction: column;
                gap: 1rem;
            }

            .file-actions {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
                justify-content: center;
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