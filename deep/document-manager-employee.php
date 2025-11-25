<?php
session_start();

// Enhanced session check that works with both systems
if (!isset($_SESSION['login']) && !isset($_SESSION['username'])) {
    $_SESSION['redirect_url'] = 'document-manager-employee.php';
    header('Location: ../login.php');
    exit;
}

// Map session variables for compatibility
if (isset($_SESSION['login']) && !isset($_SESSION['username'])) {
    $_SESSION['username'] = $_SESSION['login'];
    $_SESSION['user_id'] = $_SESSION['id'];
    $_SESSION['role'] = 'employee';
}

// Include functions - FIXED PATH
require_once __DIR__ . '/../functions.php';

// ===== ADD THESE CONSTANTS =====
if (!defined('UPLOAD_BASE_PATH')) {
    define('UPLOAD_BASE_PATH', __DIR__ . '/../uploads/documents/');
}

if (!defined('ALLOWED_EXTENSIONS')) {
    define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg']);
}

if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
}
// ===== END CONSTANTS =====

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rest of your existing code continues...

// Get user details and role
$currentUser = $_SESSION['username'];
$userRole = $_SESSION['role'] ?? 'employee';
$userId = $_SESSION['user_id'];

// Ensure base upload directory exists
if (!is_dir(UPLOAD_BASE_PATH)) {
    mkdir(UPLOAD_BASE_PATH, 0777, true);
}

// Ensure folder types exist
$folderTypes = ['jobsheets', 'invoices'];
foreach ($folderTypes as $type) {
    $typePath = UPLOAD_BASE_PATH . $type . '/';
    if (!is_dir($typePath)) {
        mkdir($typePath, 0777, true);
    }
}

// Handle folder creation (Employee can only create private folders)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_folder'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Security token mismatch.'];
        header('Location: document-manager-employee.php');
        exit;
    }

    $folderName = sanitize_input($_POST['folder_name'] ?? '');
    $folderType = sanitize_input($_POST['folder_type'] ?? '');

    if (!empty($folderName)) {
        $safeFolderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $folderName);
        $safeFolderName = $userId . '_' . $safeFolderName; // Always prefix with user ID

        $fullFolderPath = UPLOAD_BASE_PATH . $folderType . '/' . $safeFolderName;

        if (!is_dir($fullFolderPath)) {
            if (mkdir($fullFolderPath, 0777, true)) {
                $folderLog = [
                    'folder_id' => uniqid('FOLDER_'),
                    'folder_name' => $safeFolderName,
                    'folder_type' => $folderType,
                    'created_by' => $currentUser,
                    'created_by_id' => $userId,
                    'access_type' => 'private',
                    'folder_owner' => $userId,
                    'created_date' => date('Y-m-d H:i:s')
                ];

                ensureCsvFile('folder_permissions.csv', ['folder_id', 'folder_name', 'folder_type', 'created_by', 'created_by_id', 'access_type', 'folder_owner', 'created_date']);
                appendCsvData('folder_permissions.csv', $folderLog);

                $_SESSION['message'] = ['type' => 'success', 'text' => "Folder '$folderName' created successfully!"];
                logActivity('Folder Creation', "Created folder: $folderType/$safeFolderName");
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Error creating folder.'];
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Folder already exists.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Please provide a folder name.'];
    }

    header('Location: document-manager-employee.php');
    exit;
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_files'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Security token mismatch.'];
        header('Location: document-manager-employee.php');
        exit;
    }

    $targetFolder = sanitizeFilePath($_POST['target_folder'] ?? '');
    $documentType = sanitize_input($_POST['document_type'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');

    if (empty($targetFolder)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Please select a folder.'];
        header('Location: document-manager-employee.php');
        exit;
    }

    if (!hasAccessToFolder($targetFolder, $userRole, $userId)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'You do not have permission to upload to this folder.'];
        header('Location: document-manager-employee.php');
        exit;
    }

    $uploadPath = UPLOAD_BASE_PATH . $targetFolder;
    $uploadedFiles = [];
    $errors = [];

    if (isset($_FILES['documents'])) {
        $totalFiles = count($_FILES['documents']['name']);

        for ($i = 0; $i < $totalFiles; $i++) {
            if ($_FILES['documents']['error'][$i] === 0) {
                $fileName = basename($_FILES['documents']['name'][$i]);
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $fileSize = $_FILES['documents']['size'][$i];

                if (!in_array($fileExt, ALLOWED_EXTENSIONS)) {
                    $errors[] = "$fileName: Invalid file type.";
                    continue;
                }

                if ($fileSize > MAX_FILE_SIZE) {
                    $errors[] = "$fileName: File size exceeds limit.";
                    continue;
                }

                $newFileName = date('Y-m-d_His') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
                $targetPath = $uploadPath . '/' . $newFileName;

                if (move_uploaded_file($_FILES['documents']['tmp_name'][$i], $targetPath)) {
                    $uploadedFiles[] = $fileName;

                    $logEntry = [
                        'upload_id' => uniqid('DOC_'),
                        'folder' => $targetFolder,
                        'filename' => $newFileName,
                        'original_filename' => $fileName,
                        'document_type' => $documentType,
                        'description' => $description,
                        'uploaded_by' => $currentUser,
                        'uploaded_by_id' => $userId,
                        'upload_date' => date('Y-m-d H:i:s'),
                        'file_size' => $fileSize
                    ];
                    appendCsvData('document_uploads.csv', $logEntry);

                    logActivity('File Upload', "Uploaded file: $fileName to folder: $targetFolder");
                } else {
                    $errors[] = "$fileName: Upload failed.";
                }
            }
        }

        if (!empty($uploadedFiles) && empty($errors)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => count($uploadedFiles) . ' file(s) uploaded successfully!'];
        } elseif (!empty($uploadedFiles) && !empty($errors)) {
            $_SESSION['message'] = ['type' => 'warning', 'text' => count($uploadedFiles) . ' file(s) uploaded. ' . count($errors) . ' error(s): ' . implode(' ', $errors)];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Upload failed: ' . implode(' ', $errors)];
        }
    }

    header('Location: document-manager-employee.php');
    exit;
}

// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Security token mismatch.'];
        header('Location: document-manager-employee.php');
        exit;
    }

    $filePath = sanitizeFilePath($_POST['file_path'] ?? '');

    if (!hasModifyPermission($filePath, $userRole, $userId)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'You do not have permission to delete this file.'];
        header('Location: document-manager-employee.php');
        exit;
    }

    $fullPath = UPLOAD_BASE_PATH . $filePath;

    if (file_exists($fullPath) && is_file($fullPath)) {
        if (unlink($fullPath)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'File deleted successfully!'];
            logActivity('File Deletion', "Deleted file: $filePath");
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error deleting file.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'File not found.'];
    }

    header('Location: document-manager-employee.php');
    exit;
}

// Get folders based on user role and permissions
$jobsheetFolders = getFoldersWithAccess(UPLOAD_BASE_PATH, 'jobsheets', $userRole, $userId);
$invoiceFolders = getFoldersWithAccess(UPLOAD_BASE_PATH, 'invoices', $userRole, $userId);
$allFolders = array_merge($jobsheetFolders, $invoiceFolders);

// Get files if folder is selected
$selectedFolder = $_GET['folder'] ?? '';
$filesInFolder = [];
if (!empty($selectedFolder) && in_array($selectedFolder, $allFolders)) {
    $filesInFolder = getFilesInFolder($selectedFolder, $userRole, $userId);
}

// Get recent uploads (user only)
$recentUploads = getUserUploadHistory($userId, $userRole, 5);

$current_page = 'document-manager-employee.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Manager - Alphasonix CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #7209b7;
            --success: #4cc9f0;
            --light: #f8f9fa;
            --card-bg: #ffffff;
            --border-light: #e9ecef;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: #f5f7fb;
            margin-left: 280px;
            color: #495057;
        }

        .dashboard-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary);
        }

        .content-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: none;
            margin-bottom: 1.5rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .content-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.08);
        }

        .card-header-custom {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid var(--border-light);
            padding: 1.25rem 1.5rem;
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600;
        }

        .card-body-custom {
            padding: 1.5rem;
        }

        .folder-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
        }

        .folder-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem;
            border: 1px solid var(--border-light);
            border-radius: 10px;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s ease;
            background: #ffffff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
        }

        .folder-item:hover {
            background: linear-gradient(135deg, var(--primary), #3a0ca3);
            color: white;
            text-decoration: none;
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(67, 97, 238, 0.15);
        }

        .folder-item.active {
            background: linear-gradient(135deg, var(--primary), #3a0ca3);
            color: white;
            border-color: var(--primary);
        }

        .file-actions .btn {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            margin: 0 0.125rem;
            border-radius: 6px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #3a0ca3);
            border: none;
        }

        .btn-success {
            background: linear-gradient(135deg, #4cc9f0, #4895ef);
            border: none;
        }

        .btn-danger {
            background: linear-gradient(135deg, #e63946, #d00000);
            border: none;
        }

        .form-control,
        .form-select {
            border-radius: 8px;
            border: 1px solid var(--border-light);
            padding: 0.75rem 1rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.15);
        }

        .table {
            border-radius: 10px;
            overflow: hidden;
        }

        .table th {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: none;
            font-weight: 600;
            padding: 1rem;
        }

        .table td {
            padding: 1rem;
            border-color: var(--border-light);
            vertical-align: middle;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }

        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.5rem;
        }

        .badge {
            border-radius: 6px;
            font-weight: 500;
            padding: 0.5rem 0.75rem;
        }

        .list-group-item {
            border: 1px solid var(--border-light);
            border-radius: 8px;
            margin-bottom: 0.5rem;
            padding: 1rem 1.25rem;
        }

        @media (max-width: 1024px) {
            body {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>


    <div class="main-content">
        <div class="container-fluid py-4">
            <?php if (isset($_SESSION['message'])): ?>
                <?php
                $messageType = $_SESSION['message']['type'];
                $alertClass = $messageType === 'success' ? 'alert-success' : ($messageType === 'error' ? 'alert-danger' : ($messageType === 'warning' ? 'alert-warning' : 'alert-info'));
                echo "<div class='alert $alertClass alert-dismissible fade show' role='alert'>" .
                    htmlspecialchars($_SESSION['message']['text']) .
                    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' .
                    "</div>";
                unset($_SESSION['message']);
                ?>
            <?php endif; ?>

            <div class="dashboard-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="h3 mb-2">
                            <i class="bi bi-folder me-2"></i>Document Manager
                        </h1>
                        <p class="text-muted mb-0">Manage your documents and folders</p>
                        <div class="user-info mt-2">
                            <i class="bi bi-person"></i> <?= htmlspecialchars($currentUser) ?>
                            <span class="badge bg-secondary ms-2">
                                <?= ucfirst($userRole) ?>
                            </span>
                        </div>
                    </div>
                    <div class="text-end">
                        <a href="../home.php" class="btn btn-outline-primary btn-sm me-2">
                            <i class="bi bi-house me-1"></i>Dashboard
                        </a>
                        <a href="logout.php" class="btn btn-outline-danger btn-sm"
                            onclick="return confirm('Are you sure you want to log out?');">
                            <i class="bi bi-box-arrow-right me-1"></i>Log Out
                        </a>
                    </div>
                </div>
            </div>

            <div class="alert alert-warning">
                <div class="d-flex align-items-center">
                    <i class="bi bi-shield me-2"></i>
                    <div>
                        <strong>Limited Access:</strong> You can only access your own folders and public folders.
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="content-card">
                        <div class="card-header-custom">
                            <h5 class="mb-0">
                                <i class="bi bi-folder-plus me-2"></i>Create New Folder
                            </h5>
                        </div>
                        <div class="card-body-custom">
                            <form method="post" action="document-manager-employee.php">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                                <div class="mb-3">
                                    <label for="folder_name" class="form-label">Folder Name</label>
                                    <input type="text" id="folder_name" name="folder_name" class="form-control"
                                        placeholder="Enter folder name (e.g., Project_2024)" required
                                        oninput="this.value = this.value.replace(/[^\w-]/g, '_')">
                                    <div class="form-text">Your user ID will be automatically prefixed to the folder name.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="folder_type" class="form-label">Folder Type</label>
                                    <select id="folder_type" name="folder_type" class="form-select" required>
                                        <option value="">-- Select Type --</option>
                                        <option value="jobsheets">Jobsheets</option>
                                        <option value="invoices">Invoices</option>
                                    </select>
                                </div>

                                <button type="submit" name="create_folder" class="btn btn-success w-100">
                                    <i class="bi bi-plus-circle me-2"></i>Create Folder
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="content-card">
                        <div class="card-header-custom">
                            <h5 class="mb-0">
                                <i class="bi bi-cloud-upload me-2"></i>Upload Documents
                            </h5>
                        </div>
                        <div class="card-body-custom">
                            <form method="post" enctype="multipart/form-data" action="document-manager-employee.php">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                                <div class="mb-3">
                                    <label for="target_folder" class="form-label">Select Folder</label>
                                    <select id="target_folder" name="target_folder" class="form-select" required>
                                        <option value="">-- Select Folder --</option>
                                        <?php if (!empty($allFolders)): ?>
                                            <?php if (!empty($jobsheetFolders)): ?>
                                                <optgroup label="Jobsheets">
                                                    <?php foreach ($jobsheetFolders as $folder): ?>
                                                        <?php
                                                        $folderName = basename($folder);
                                                        $displayName = $folderName;
                                                        if (strpos($folderName, $userId . '_') === 0) {
                                                            $displayName = substr($folderName, strlen($userId) + 1);
                                                        }
                                                        ?>
                                                        <option value="<?= htmlspecialchars($folder) ?>" <?= $selectedFolder === $folder ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($displayName) ?>
                                                            <?php if (strpos($folderName, $userId . '_') === 0): ?>
                                                                <small>(My Folder)</small>
                                                            <?php else: ?>
                                                                <small>(Public)</small>
                                                            <?php endif; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endif; ?>
                                            <?php if (!empty($invoiceFolders)): ?>
                                                <optgroup label="Invoices">
                                                    <?php foreach ($invoiceFolders as $folder): ?>
                                                        <?php
                                                        $folderName = basename($folder);
                                                        $displayName = $folderName;
                                                        if (strpos($folderName, $userId . '_') === 0) {
                                                            $displayName = substr($folderName, strlen($userId) + 1);
                                                        }
                                                        ?>
                                                        <option value="<?= htmlspecialchars($folder) ?>" <?= $selectedFolder === $folder ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($displayName) ?>
                                                            <?php if (strpos($folderName, $userId . '_') === 0): ?>
                                                                <small>(My Folder)</small>
                                                            <?php else: ?>
                                                                <small>(Public)</small>
                                                            <?php endif; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="document_type" class="form-label">Document Type</label>
                                    <select id="document_type" name="document_type" class="form-select" required>
                                        <option value="">-- Select Type --</option>
                                        <option value="jobsheet">Jobsheet</option>
                                        <option value="invoice">Invoice</option>
                                        <option value="purchase_order">Purchase Order</option>
                                        <option value="delivery_note">Delivery Note</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description (Optional)</label>
                                    <input type="text" id="description" name="description" class="form-control"
                                        placeholder="Brief description of documents">
                                </div>

                                <div class="mb-3">
                                    <label for="documents" class="form-label">Select Files</label>
                                    <input type="file" id="documents" name="documents[]" class="form-control"
                                        multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg" required>
                                    <div class="form-text">
                                        <strong>Allowed formats:</strong> PDF, DOC, DOCX, XLS, XLSX, PNG, JPG, JPEG<br>
                                        <strong>Max file size:</strong> 10MB per file
                                    </div>
                                </div>

                                <button type="submit" name="upload_files" class="btn btn-primary w-100">
                                    <i class="bi bi-upload me-2"></i>Upload Files
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-card mb-4">
                <div class="card-header-custom">
                    <h5 class="mb-0">
                        <i class="bi bi-folder-fill me-2"></i>Your Folders
                    </h5>
                </div>
                <div class="card-body-custom">
                    <?php if (!empty($allFolders)): ?>
                        <div class="folder-list">
                            <?php foreach ($allFolders as $folder): ?>
                                <?php
                                $folderName = basename($folder);
                                $displayName = $folderName;
                                $isMyFolder = false;

                                if (strpos($folderName, $userId . '_') === 0) {
                                    $displayName = substr($folderName, strlen($userId) + 1);
                                    $isMyFolder = true;
                                }
                                ?>
                                <a href="?folder=<?= urlencode($folder) ?>"
                                    class="folder-item <?= $selectedFolder === $folder ? 'active' : '' ?>">
                                    <div class="folder-info">
                                        <i class="bi bi-folder<?= $isMyFolder ? '-fill' : '' ?> me-3 fs-4"></i>
                                        <div>
                                            <div class="folder-name fw-semibold"><?= htmlspecialchars($displayName) ?></div>
                                            <div class="folder-owner small">
                                                <?php if ($isMyFolder): ?>
                                                    My Folder
                                                <?php else: ?>
                                                    Public Folder
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="badge <?= strpos($folder, 'jobsheets') === 0 ? 'bg-success' : 'bg-info' ?>">
                                        <?= strpos($folder, 'jobsheets') === 0 ? 'Jobsheets' : 'Invoices' ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-folder-x fs-1 text-muted"></i>
                            <h6>No Folders Available</h6>
                            <p class="mb-0">Create your first folder using the form above.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($selectedFolder)): ?>
                <div class="content-card mb-4">
                    <div class="card-header-custom">
                        <h5 class="mb-0">
                            <i class="bi bi-files me-2"></i>Files in: <?= htmlspecialchars(basename($selectedFolder)) ?>
                        </h5>
                    </div>
                    <div class="card-body-custom p-0">
                        <?php if (!empty($filesInFolder)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>File Name</th>
                                            <th>Size</th>
                                            <th>Modified</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($filesInFolder as $file): ?>
                                            <?php
                                            $fileInfo = getFileDisplayInfo($file['path'], $userRole, $userId);
                                            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                                            $icon = match ($fileExt) {
                                                'pdf' => 'bi-file-earmark-pdf',
                                                'doc', 'docx' => 'bi-file-earmark-word',
                                                'xls', 'xlsx' => 'bi-file-earmark-excel',
                                                'png', 'jpg', 'jpeg' => 'bi-file-earmark-image',
                                                default => 'bi-file-earmark'
                                            };
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi <?= $icon ?> text-primary me-2 fs-5"></i>
                                                        <span class="fw-medium"><?= htmlspecialchars($file['name']) ?></span>
                                                    </div>
                                                </td>
                                                <td><?= number_format($file['size'] / 1024, 2) ?> KB</td>
                                                <td><?= date('Y-m-d H:i', $file['modified']) ?></td>
                                                <td>
                                                    <div class="file-actions">
                                                        <a href="<?= UPLOAD_BASE_PATH ?><?= htmlspecialchars($file['path']) ?>"
                                                            target="_blank" class="btn btn-primary btn-sm" title="View File">
                                                            <i class="bi bi-eye"></i>
                                                        </a>

                                                        <a href="<?= UPLOAD_BASE_PATH ?><?= htmlspecialchars($file['path']) ?>"
                                                            download class="btn btn-success btn-sm" title="Download File">
                                                            <i class="bi bi-download"></i>
                                                        </a>

                                                        <?php if ($fileInfo['can_delete']): ?>
                                                            <form method="post" action="document-manager-employee.php"
                                                                onsubmit="return confirm('Are you sure you want to delete this file?');" style="display: inline;">
                                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                                <input type="hidden" name="file_path" value="<?= htmlspecialchars($file['path']) ?>">
                                                                <button type="submit" name="delete_file" class="btn btn-danger btn-sm" title="Delete File">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-file-earmark fs-1 text-muted"></i>
                                <h6>Folder is Empty</h6>
                                <p class="mb-0">Upload files using the form above.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif (!empty($_GET['folder'])): ?>
                <div class="content-card">
                    <div class="card-body-custom text-center">
                        <i class="bi bi-exclamation-triangle text-warning fs-1"></i>
                        <h5 class="mt-3">Access Denied</h5>
                        <p class="mb-0">You do not have permission to access this folder.</p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="content-card">
                <div class="card-header-custom">
                    <h5 class="mb-0">
                        <i class="bi bi-clock me-2"></i>Your Recent Uploads
                    </h5>
                </div>
                <div class="card-body-custom">
                    <?php if (!empty($recentUploads)): ?>
                        <div class="list-group">
                            <?php foreach ($recentUploads as $upload): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= htmlspecialchars($upload['original_filename']) ?></strong>
                                            <div class="text-muted small">
                                                <i class="bi bi-folder"></i> <?= htmlspecialchars(basename($upload['folder'])) ?>
                                                <i class="bi bi-calendar ms-2"></i> <?= htmlspecialchars($upload['upload_date']) ?>
                                            </div>
                                        </div>
                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars($upload['document_type']) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-clock-history fs-1 text-muted"></i>
                            <h6>No Recent Uploads</h6>
                            <p class="mb-0">Start by uploading your first document.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    bootstrap.Alert.getOrCreateInstance(alert).close();
                }, 5000);
            });
        });
    </script>
</body>

</html>