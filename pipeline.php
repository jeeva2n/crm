<?php
session_start();
require_once 'functions.php';
require_once 'db.php';

// Check authentication
// requireAuth();

// Check rate limiting
if (!checkRateLimit('pipeline_page', $_SESSION['user_id'] ?? 0, 100, 60)) {
    die('Rate limit exceeded. Please try again later.');
}

// Define standard statuses
$STANDARD_STATUSES = [
    'Pending',
    'Sourcing Material',
    'In Production',
    'Ready for QC',
    'QC Completed',
    'Packaging',
    'Ready for Dispatch',
    'Shipped'
];

// ==========================================
// MISSING FUNCTION DECLARATIONS
// ==========================================

if (!function_exists('hasAccessToOrder')) {
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
}

if (!function_exists('sendShippingNotification')) {
    function sendShippingNotification($customerId, $orderId, $trackingNumber) {
        logActivity("Shipping Notification", "Order $orderId shipped with tracking: $trackingNumber");
        return true;
    }
}

if (!function_exists('handleStageFileUpload')) {
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
}

if (!function_exists('handleMultipleFileUpload')) {
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
}

// ==========================================
// STAGE DISPLAY FUNCTIONS
// ==========================================

function showPendingStage($order, $item, $itemIndex, $csrfToken)
{
    $html = '
    <div class="stage-section">
        <div class="stage-header">
            <h5 class="mb-0"><i class="bi bi-clock"></i> Stage 1: Order Review</h5>
            <span class="badge bg-secondary">Pending</span>
        </div>
        <div class="stage-content">
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> This order is pending review. Please verify all details before proceeding.
            </div>
            
            <div class="stage-form no-print">
                <h6>Ready to Start Production?</h6>
                <form action="pipeline.php" method="post">
                    <input type="hidden" name="csrf_token" value="' . $csrfToken . '">
                    <input type="hidden" name="order_id" value="' . htmlspecialchars($order['order_id']) . '">
                    <input type="hidden" name="item_index" value="' . $itemIndex . '">
                    <input type="hidden" name="new_item_status" value="Sourcing Material">
                    <button type="submit" name="update_item_status" class="btn btn-success">
                        <i class="bi bi-play-circle"></i> Start Material Sourcing
                    </button>
                </form>
            </div>
        </div>
    </div>';

    return $html;
}

function showSourcingStage($order, $item, $itemIndex, $csrfToken)
{
    $materials = $item['raw_materials'] ?? [];

    $html = '
    <div class="stage-section">
        <div class="stage-header">
            <h5 class="mb-0"><i class="bi bi-box-seam"></i> Stage 2: Material Sourcing</h5>
            <span class="badge bg-warning">Active</span>
        </div>
        <div class="stage-content">
            <!-- Current Materials -->
            ' . (!empty($materials) ? showMaterialsTable($materials) : '<div class="alert alert-warning">No materials added yet.</div>') . '
            
            <!-- Add Material Form -->
            <div class="stage-form no-print">
                <h6>Add Raw Material</h6>
                <form action="pipeline.php" method="post">
                    <input type="hidden" name="csrf_token" value="' . $csrfToken . '">
                    <input type="hidden" name="order_id" value="' . htmlspecialchars($order['order_id']) . '">
                    <input type="hidden" name="item_index" value="' . $itemIndex . '">
                    
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Material Type *</label>
                            <select name="raw_material_type" class="form-select" required onchange="toggleOtherField(this, \'raw_material_type\')">
                                <option value="">Select Type</option>
                                <option value="Steel">Steel</option>
                                <option value="Aluminum">Aluminum</option>
                                <option value="Copper">Copper</option>
                                <option value="Plastic">Plastic</option>
                                <option value="other">Other</option>
                            </select>
                            <input type="text" name="raw_material_type_other" class="form-control mt-1" placeholder="Specify material type" style="display: none;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Grade/Quality *</label>
                            <input type="text" name="raw_material_grade" class="form-control" required placeholder="e.g., 304 Stainless">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dimensions</label>
                            <input type="text" name="raw_material_dimensions" class="form-control" placeholder="e.g., 100x50x20mm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Vendor Name</label>
                            <input type="text" name="vendor_name" class="form-control" placeholder="Supplier name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Purchase Date</label>
                            <input type="date" name="purchase_date" class="form-control">
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" name="add_raw_material" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Add Material
                        </button>
                        
                        ' . (!empty($materials) ? '
                        <button type="submit" name="update_item_status" value="In Production" 
                                class="btn btn-success ms-2" onclick="return confirm(\'Move to production stage?\')">
                            <i class="bi bi-arrow-right"></i> Start Production
                        </button>' : '') . '
                    </div>
                </form>
            </div>
        </div>
    </div>';

    return $html;
}

function showProductionStage($order, $item, $itemIndex, $csrfToken)
{
    $processes = $item['machining_processes'] ?? [];

    $html = '
    <div class="stage-section">
        <div class="stage-header">
            <h5 class="mb-0"><i class="bi bi-gear"></i> Stage 3: Production</h5>
            <span class="badge bg-info">Active</span>
        </div>
        <div class="stage-content">
            <!-- Current Processes -->
            ' . (!empty($processes) ? showProcessesTable($order, $item, $itemIndex, $processes, $csrfToken) : '<div class="alert alert-warning">No processes added yet.</div>') . '
            
            <!-- Add Process Form -->
            <div class="stage-form no-print">
                <h6>Add Machining Process</h6>
                <form action="pipeline.php" method="post">
                    <input type="hidden" name="csrf_token" value="' . $csrfToken . '">
                    <input type="hidden" name="order_id" value="' . htmlspecialchars($order['order_id']) . '">
                    <input type="hidden" name="item_index" value="' . $itemIndex . '">
                    
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Process Name *</label>
                            <select name="process_name" class="form-select" required onchange="toggleOtherField(this, \'process_name\')">
                                <option value="">Select Process</option>
                                <option value="Cutting">Cutting</option>
                                <option value="Milling">Milling</option>
                                <option value="Turning">Turning</option>
                                <option value="Drilling">Drilling</option>
                                <option value="Welding">Welding</option>
                                <option value="Grinding">Grinding</option>
                                <option value="other">Other</option>
                            </select>
                            <input type="text" name="process_name_other" class="form-control mt-1" placeholder="Specify process" style="display: none;">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sequence *</label>
                            <input type="number" name="sequence_number" class="form-control" required min="1" value="' . (count($processes) + 1) . '">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Vendor</label>
                            <input type="text" name="vendor_name" class="form-control" placeholder="If outsourced">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expected Completion</label>
                            <input type="date" name="expected_completion" class="form-control">
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" name="add_machining_process" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Add Process
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    return $html;
}

function showQCStage($order, $item, $itemIndex, $csrfToken)
{
    $inspections = $item['inspection_data'] ?? [];

    $html = '
    <div class="stage-section">
        <div class="stage-header">
            <h5 class="mb-0"><i class="bi bi-clipboard-check"></i> Stage 4: Quality Control</h5>
            <span class="badge bg-primary">Active</span>
        </div>
        <div class="stage-content">
            <!-- Inspection History -->
            ' . (!empty($inspections) ? showInspectionsTable($inspections) : '<div class="alert alert-warning">No inspections conducted yet.</div>') . '
            
            <!-- QC Form -->
            <div class="stage-form no-print">
                <h6>Submit Inspection Report</h6>
                <form action="pipeline.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="' . $csrfToken . '">
                    <input type="hidden" name="order_id" value="' . htmlspecialchars($order['order_id']) . '">
                    <input type="hidden" name="item_index" value="' . $itemIndex . '">
                    
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Inspection Type *</label>
                            <select name="inspection_type" class="form-select" required onchange="toggleOtherField(this, \'inspection_type\')">
                                <option value="">Select Type</option>
                                <option value="Dimensional Check">Dimensional Check</option>
                                <option value="Visual Inspection">Visual Inspection</option>
                                <option value="Material Verification">Material Verification</option>
                                <option value="Functional Test">Functional Test</option>
                                <option value="other">Other</option>
                            </select>
                            <input type="text" name="inspection_type_other" class="form-control mt-1" placeholder="Specify inspection type" style="display: none;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Result *</label>
                            <select name="inspection_status" class="form-select" required>
                                <option value="QC Passed">QC Passed</option>
                                <option value="Rework Required">Rework Required</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Technician Name</label>
                            <input type="text" name="technician_name" class="form-control" placeholder="Inspector name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Inspection Date</label>
                            <input type="date" name="inspection_date" class="form-control" value="' . date('Y-m-d') . '">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2" placeholder="Inspection notes..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">QC Document</label>
                            <input type="file" name="qc_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <div class="form-text">Upload inspection report or photos</div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" name="submit_inspection_report" class="btn btn-primary">
                            <i class="bi bi-clipboard-check"></i> Submit Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    return $html;
}

function showPackagingStage($order, $item, $itemIndex, $csrfToken)
{
    $lots = $item['packaging_lots'] ?? [];

    $html = '
    <div class="stage-section">
        <div class="stage-header">
            <h5 class="mb-0"><i class="bi bi-box"></i> Stage 5: Packaging</h5>
            <span class="badge bg-warning">Active</span>
        </div>
        <div class="stage-content">
            <!-- Packaging Lots -->
            ' . (!empty($lots) ? showPackagingLots($order, $item, $itemIndex, $lots, $csrfToken) : '<div class="alert alert-warning">No packaging lots created yet.</div>') . '
            
            <!-- Packaging Form -->
            <div class="stage-form no-print">
                <h6>Create Packaging Lot</h6>
                <form action="pipeline.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="' . $csrfToken . '">
                    <input type="hidden" name="order_id" value="' . htmlspecialchars($order['order_id']) . '">
                    <input type="hidden" name="item_index" value="' . $itemIndex . '">
                    
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Fumigation Details</h6>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="fumigation_completed" value="1" id="fumigationCheck" required>
                            <label class="form-check-label" for="fumigationCheck">
                                Fumigation has been completed as per requirements
                            </label>
                        </div>
                        
                        <div class="row g-2 mt-2">
                            <div class="col-md-4">
                                <label class="form-label">Certificate Number</label>
                                <input type="text" name="fumigation_certificate_number" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Fumigation Date</label>
                                <input type="date" name="fumigation_date" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Agency</label>
                                <input type="text" name="fumigation_agency" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <h6 class="mt-3">Packaging Details</h6>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Packaging Type *</label>
                            <select name="packaging_type" class="form-select" required onchange="toggleOtherField(this, \'packaging_type\')">
                                <option value="">Select Type</option>
                                <option value="Wooden Crate">Wooden Crate</option>
                                <option value="Cardboard Box">Cardboard Box</option>
                                <option value="Pallet">Pallet</option>
                                <option value="other">Other</option>
                            </select>
                            <input type="text" name="packaging_type_other" class="form-control mt-1" placeholder="Specify packaging type" style="display: none;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Packaging Date</label>
                            <input type="date" name="packaging_date" class="form-control" value="' . date('Y-m-d') . '">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Products in Lot *</label>
                            <input type="number" name="products_in_lot" class="form-control" required min="1" max="' . $item['quantity'] . '" value="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Number of Packages</label>
                            <input type="number" name="num_packages" class="form-control" min="1" value="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Weight per Package (kg)</label>
                            <input type="number" name="weight_per_package" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dimensions per Package</label>
                            <input type="text" name="dimensions_per_package" class="form-control" placeholder="L x W x H">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Net Weight (kg)</label>
                            <input type="number" name="net_weight" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Gross Weight (kg)</label>
                            <input type="number" name="gross_weight" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="docs_included" value="1" id="docsCheck">
                                <label class="form-check-label" for="docsCheck">
                                    All required documents included in package
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Product Photos</label>
                            <input type="file" name="product_photos[]" class="form-control" multiple accept=".jpg,.jpeg,.png">
                            <div class="form-text">Upload photos of packaged products</div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" name="add_packaging_lot" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Create Packaging Lot
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    return $html;
}

function showDispatchStage($order, $item, $itemIndex, $csrfToken)
{
    $lots = $item['packaging_lots'] ?? [];

    $html = '
    <div class="stage-section">
        <div class="stage-header">
            <h5 class="mb-0"><i class="bi bi-truck"></i> Stage 6: Ready for Dispatch</h5>
            <span class="badge bg-info">Ready</span>
        </div>
        <div class="stage-content">
            <!-- Shipping Preparation -->
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Products are packaged and ready for shipping. Add shipping documents and dispatch details.
            </div>
            
            <!-- Packaging Lots for Dispatch -->
            ' . showDispatchLots($order, $item, $itemIndex, $lots, $csrfToken) . '
        </div>
    </div>';

    return $html;
}

function showShippedStage($order, $item, $itemIndex, $csrfToken)
{
    $lots = $item['packaging_lots'] ?? [];

    $html = '
    <div class="stage-section">
        <div class="stage-header">
            <h5 class="mb-0"><i class="bi bi-check-circle"></i> Stage 7: Shipped</h5>
            <span class="badge bg-success">Completed</span>
        </div>
        <div class="stage-content">
            <!-- Shipping Summary -->
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> This item has been shipped successfully.
            </div>
            
            <!-- Shipping Details -->
            ' . showShippingSummary($lots) . '
        </div>
    </div>';

    return $html;
}

// Helper functions for displaying tables
function showMaterialsTable($materials)
{
    $html = '<div class="table-responsive"><table class="table table-sm table-bordered">
        <thead><tr><th>Type</th><th>Grade</th><th>Dimensions</th><th>Vendor</th><th>Purchase Date</th></tr></thead>
        <tbody>';

    foreach ($materials as $material) {
        $html .= '<tr>
            <td>' . htmlspecialchars($material['type']) . '</td>
            <td>' . htmlspecialchars($material['grade']) . '</td>
            <td>' . htmlspecialchars($material['dimensions']) . '</td>
            <td>' . htmlspecialchars($material['vendor']) . '</td>
            <td>' . htmlspecialchars($material['purchase_date']) . '</td>
        </tr>';
    }

    $html .= '</tbody></table></div>';
    return $html;
}

function showProcessesTable($order, $item, $itemIndex, $processes, $csrfToken)
{
    $html = '<div class="table-responsive"><table class="table table-sm table-bordered">
        <thead><tr><th>Seq</th><th>Process</th><th>Vendor</th><th>Start Date</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>';

    foreach ($processes as $processIndex => $process) {
        $statusClass = '';
        switch ($process['status']) {
            case 'Completed':
                $statusClass = 'bg-success';
                break;
            case 'In Progress':
                $statusClass = 'bg-warning';
                break;
            default:
                $statusClass = 'bg-secondary';
        }

        $html .= '<tr>
            <td>' . htmlspecialchars($process['sequence']) . '</td>
            <td>' . htmlspecialchars($process['name']) . '</td>
            <td>' . htmlspecialchars($process['vendor']) . '</td>
            <td>' . htmlspecialchars($process['start_date']) . '</td>
            <td><span class="badge ' . $statusClass . '">' . htmlspecialchars($process['status']) . '</span></td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-primary" 
                    onclick="showProcessModal(' . $itemIndex . ', ' . $processIndex . ')">
                    <i class="bi bi-pencil"></i>
                </button>
            </td>
        </tr>';
    }

    $html .= '</tbody></table></div>';

    // Add process update modals
    foreach ($processes as $processIndex => $process) {
        $html .= '
        <div id="processModal-' . $itemIndex . '-' . $processIndex . '" class="modal">
            <div class="modal-content">
                <span class="modal-close" onclick="closeProcessModal(' . $itemIndex . ', ' . $processIndex . ')">&times;</span>
                <h4>Update Process: ' . htmlspecialchars($process['name']) . '</h4>
                <form action="pipeline.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="' . $GLOBALS['csrfToken'] . '">
                    <input type="hidden" name="order_id" value="' . htmlspecialchars($order['order_id']) . '">
                    <input type="hidden" name="item_index" value="' . $itemIndex . '">
                    <input type="hidden" name="process_index" value="' . $processIndex . '">
                    
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Actual Completion</label>
                            <input type="date" name="actual_completion" class="form-control" value="' . htmlspecialchars($process['actual_completion']) . '">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="process_status" class="form-select" required>
                                <option value="Not Started" ' . ($process['status'] == 'Not Started' ? 'selected' : '') . '>Not Started</option>
                                <option value="In Progress" ' . ($process['status'] == 'In Progress' ? 'selected' : '') . '>In Progress</option>
                                <option value="Completed" ' . ($process['status'] == 'Completed' ? 'selected' : '') . '>Completed</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="3">' . htmlspecialchars($process['remarks']) . '</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Process Document</label>
                            <input type="file" name="process_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" name="update_machining_process" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Update Process
                        </button>
                    </div>
                </form>
            </div>
        </div>';
    }

    return $html;
}

function showInspectionsTable($inspections)
{
    $html = '<div class="table-responsive"><table class="table table-sm table-bordered">
        <thead><tr><th>Date</th><th>Type</th><th>Technician</th><th>Result</th><th>Remarks</th></tr></thead>
        <tbody>';

    foreach ($inspections as $inspection) {
        $statusClass = '';
        switch ($inspection['status']) {
            case 'QC Passed':
                $statusClass = 'bg-success';
                break;
            case 'Rework Required':
                $statusClass = 'bg-warning';
                break;
            case 'Rejected':
                $statusClass = 'bg-danger';
                break;
            default:
                $statusClass = 'bg-secondary';
        }

        $html .= '<tr>
            <td>' . htmlspecialchars($inspection['inspection_date']) . '</td>
            <td>' . htmlspecialchars($inspection['type']) . '</td>
            <td>' . htmlspecialchars($inspection['technician_name']) . '</td>
            <td><span class="badge ' . $statusClass . '">' . htmlspecialchars($inspection['status']) . '</span></td>
            <td>' . htmlspecialchars(substr($inspection['remarks'], 0, 50)) . '...</td>
        </tr>';
    }

    $html .= '</tbody></table></div>';
    return $html;
}

function showPackagingLots($order, $item, $itemIndex, $lots, $csrfToken)
{
    $html = '<div class="packaging-lots">';

    foreach ($lots as $lotIndex => $lot) {
        $html .= '
        <div class="packaging-lot border rounded p-3 mb-3">
            <h6>Lot #' . ($lotIndex + 1) . ' - ' . htmlspecialchars($lot['packaging_type']) . '</h6>
            <div class="row">
                <div class="col-md-6">
                    <strong>Products:</strong> ' . htmlspecialchars($lot['products_in_lot']) . '<br>
                    <strong>Packages:</strong> ' . htmlspecialchars($lot['num_packages']) . '<br>
                    <strong>Net Weight:</strong> ' . htmlspecialchars($lot['net_weight']) . ' kg<br>
                    <strong>Gross Weight:</strong> ' . htmlspecialchars($lot['gross_weight']) . ' kg
                </div>
                <div class="col-md-6">
                    <strong>Packaging Date:</strong> ' . htmlspecialchars($lot['packaging_date']) . '<br>
                    <strong>Fumigation:</strong> ' . htmlspecialchars($lot['fumigation_completed']) . '<br>
                    <strong>Documents Included:</strong> ' . htmlspecialchars($lot['docs_included']) . '
                </div>
            </div>';

        // Show photos if available
        if (!empty($lot['photos'])) {
            $html .= '<div class="mt-2"><strong>Photos:</strong><div class="d-flex flex-wrap gap-2 mt-1">';
            foreach ($lot['photos'] as $photoIndex => $photo) {
                $html .= '<img src="uploads/packaging_photos/thumbs/' . htmlspecialchars($photo) . '" 
                    class="rounded" style="width: 80px; height: 80px; object-fit: cover; cursor: pointer"
                    onclick="showImageModal(\'uploads/packaging_photos/' . htmlspecialchars($photo) . '\')">';
            }
            $html .= '</div></div>';
        }

        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}

function showDispatchLots($order, $item, $itemIndex, $lots, $csrfToken)
{
    $html = '<div class="dispatch-lots">';

    foreach ($lots as $lotIndex => $lot) {
        $hasShippingDocs = !empty($lot['shipping_documents']);
        $isShipped = ($lot['dispatch_status'] ?? '') === 'Shipped';

        $html .= '
        <div class="dispatch-lot border rounded p-3 mb-3">
            <h6>Lot #' . ($lotIndex + 1) . ' - ' . htmlspecialchars($lot['packaging_type']) . '</h6>
            
            ' . (!$hasShippingDocs ? '
            <div class="alert alert-warning">
                <form action="pipeline.php" method="post" enctype="multipart/form-data" class="no-print">
                    <input type="hidden" name="csrf_token" value="' . $csrfToken . '">
                    <input type="hidden" name="order_id" value="' . htmlspecialchars($order['order_id']) . '">
                    <input type="hidden" name="item_index" value="' . $itemIndex . '">
                    <input type="hidden" name="lot_index" value="' . $lotIndex . '">
                    
                    <div class="mb-2">
                        <label class="form-label">Upload Shipping Documents</label>
                        <input type="file" name="shipping_docs[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    </div>
                    <button type="submit" name="add_shipping_documents" class="btn btn-primary btn-sm">
                        <i class="bi bi-upload"></i> Upload Documents
                    </button>
                </form>
            </div>' : '') . '
            
            ' . ($hasShippingDocs && !$isShipped ? '
            <div class="alert alert-info">
                <form action="pipeline.php" method="post" class="no-print">
                    <input type="hidden" name="csrf_token" value="' . $csrfToken . '">
                    <input type="hidden" name="order_id" value="' . htmlspecialchars($order['order_id']) . '">
                    <input type="hidden" name="item_index" value="' . $itemIndex . '">
                    <input type="hidden" name="lot_index" value="' . $lotIndex . '">
                    
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Dispatch Date *</label>
                            <input type="date" name="dispatch_date" class="form-control" required value="' . date('Y-m-d') . '">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Transport Mode *</label>
                            <select name="transport_mode" class="form-select" required>
                                <option value="">Select Mode</option>
                                <option value="Road">Road</option>
                                <option value="Air">Air</option>
                                <option value="Sea">Sea</option>
                                <option value="Rail">Rail</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tracking Number</label>
                            <input type="text" name="tracking_number" class="form-control" placeholder="Tracking ID">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea name="dispatch_remarks" class="form-control" rows="2" placeholder="Dispatch notes..."></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-2">
                        <button type="submit" name="update_dispatch_details" class="btn btn-success">
                            <i class="bi bi-truck"></i> Mark as Shipped
                        </button>
                    </div>
                </form>
            </div>' : '') . '
            
            ' . ($isShipped ? '
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> This lot has been shipped.<br>
                <strong>Shipped via:</strong> ' . htmlspecialchars($lot['transport_mode']) . '<br>
                <strong>Tracking:</strong> ' . htmlspecialchars($lot['tracking_number']) . '<br>
                <strong>Date:</strong> ' . htmlspecialchars($lot['dispatch_date']) . '
            </div>' : '') . '
        </div>';
    }

    $html .= '</div>';
    return $html;
}

function showShippingSummary($lots)
{
    $html = '<div class="shipping-summary">';

    foreach ($lots as $lotIndex => $lot) {
        if (($lot['dispatch_status'] ?? '') === 'Shipped') {
            $html .= '
            <div class="shipping-lot border rounded p-3 mb-3">
                <h6>Lot #' . ($lotIndex + 1) . '</h6>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Transport:</strong> ' . htmlspecialchars($lot['transport_mode']) . '<br>
                        <strong>Tracking:</strong> ' . htmlspecialchars($lot['tracking_number']) . '<br>
                        <strong>Dispatch Date:</strong> ' . htmlspecialchars($lot['dispatch_date']) . '
                    </div>
                    <div class="col-md-6">
                        <strong>Products Shipped:</strong> ' . htmlspecialchars($lot['products_in_lot']) . '<br>
                        <strong>Weight:</strong> ' . htmlspecialchars($lot['gross_weight']) . ' kg<br>
                        <strong>Packages:</strong> ' . htmlspecialchars($lot['num_packages']) . '
                    </div>
                </div>
            </div>';
        }
    }

    $html .= '</div>';
    return $html;
}

// ==========================================
// REQUEST HANDLING
// ==========================================

// Handle order deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token.'];
        header('Location: pipeline.php');
        exit;
    }

    $orderId = sanitize_input($_POST['order_id']);

    if (hasAccessToOrder($orderId, $_SESSION['user_id'] ?? 0, $_SESSION['role'] ?? 'employee')) {
        if (deleteOrder($orderId)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => "Order #$orderId deleted successfully."];
            logActivity("Order Deleted", "Order #$orderId deleted from pipeline");
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to delete order.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'You do not have permission to delete this order.'];
    }

    header('Location: pipeline.php');
    exit;
}

// Handle item deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token.'];
        header('Location: pipeline.php');
        exit;
    }

    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int)$_POST['item_index'];

    if (hasAccessToOrder($orderId, $_SESSION['user_id'] ?? 0, $_SESSION['role'] ?? 'employee')) {
        $items = getOrderItems($orderId);
        if (isset($items[$itemIndex])) {
            $itemName = $items[$itemIndex]['Name'] ?? 'Unknown';

            // Remove item
            array_splice($items, $itemIndex, 1);

            try {
                if (updateOrderItems($orderId, $items)) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => "Item '$itemName' deleted successfully."];
                    logChange($orderId, 'Item Deletion', "Deleted item: $itemName", $itemIndex);
                } else {
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to delete item.'];
                }
            } catch (Exception $e) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Error deleting item: ' . $e->getMessage()];
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Item not found.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'You do not have permission to delete items from this order.'];
    }

    header('Location: pipeline.php');
    exit;
}

// Handle item drawing update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item_drawing'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token.'];
        header('Location: pipeline.php');
        exit;
    }

    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int)$_POST['item_index'];

    if (isset($_FILES['item_drawing']) && $_FILES['item_drawing']['error'] == 0) {
        $items = getOrderItems($orderId);

        if (isset($items[$itemIndex])) {
            // Handle file upload
            $uploadResult = handleItemFileUpload($_FILES['item_drawing'], 0, $orderId, $items[$itemIndex]['S.No'] ?? 'item');

            if ($uploadResult['filename']) {
                $items[$itemIndex]['drawing_filename'] = $uploadResult['filename'];
                $items[$itemIndex]['original_filename'] = $uploadResult['original'];

                if (updateOrderItems($orderId, $items)) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'Drawing updated successfully.'];
                    logChange($orderId, 'Drawing Update', "Updated drawing for item: " . $items[$itemIndex]['Name'], $itemIndex);
                } else {
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to update drawing.'];
                }
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'File upload failed: ' . ($uploadResult['error'] ?? 'Unknown error')];
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Item not found.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'No file uploaded or upload error.'];
    }

    header('Location: pipeline.php');
    exit;
}

// Handle item status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item_status'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token.'];
        header('Location: pipeline.php');
        exit;
    }

    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int)$_POST['item_index'];
    $newStatus = sanitize_input($_POST['new_item_status']);

    if (in_array($newStatus, $STANDARD_STATUSES)) {
        $items = getOrderItems($orderId);

        if (isset($items[$itemIndex])) {
            $oldStatus = $items[$itemIndex]['item_status'] ?? 'Pending';
            $items[$itemIndex]['item_status'] = $newStatus;

            if (updateOrderItems($orderId, $items)) {
                $_SESSION['message'] = ['type' => 'success', 'text' => "Item status updated to $newStatus."];
                logChange($orderId, 'Item Status', "Changed from $oldStatus to $newStatus", $itemIndex);
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to update item status.'];
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Item not found.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid status.'];
    }

    header('Location: pipeline.php');
    exit;
}

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token.'];
        header('Location: pipeline.php');
        exit;
    }

    $orderId = sanitize_input($_POST['order_id']);
    $newStatus = sanitize_input($_POST['new_status']);

    if (in_array($newStatus, $STANDARD_STATUSES) && hasAccessToOrder($orderId, $_SESSION['user_id'] ?? 0, $_SESSION['role'] ?? 'employee')) {
        $order = getOrderById($orderId);
        $oldStatus = $order['status'] ?? 'Pending';

        if (updateOrder($orderId, ['status' => $newStatus])) {
            $_SESSION['message'] = ['type' => 'success', 'text' => "Order status updated to $newStatus."];
            logChange($orderId, 'Order Status', "Changed from $oldStatus to $newStatus");

            // Send notification if shipped
            if ($newStatus === 'Shipped') {
                sendOrderStatusNotification($orderId, $newStatus);
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to update order status.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid status or insufficient permissions.'];
    }

    header('Location: pipeline.php');
    exit;
}

// Stage 2: Raw Materials
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_raw_material'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token.'];
        header('Location: pipeline.php');
        exit;
    }

    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int)$_POST['item_index'];

    $materialType = sanitize_input($_POST['raw_material_type']);
    if ($materialType === 'other') {
        $materialType = sanitize_input($_POST['raw_material_type_other']);
    }

    $newMaterial = [
        'type' => $materialType,
        'grade' => sanitize_input($_POST['raw_material_grade']),
        'dimensions' => sanitize_input($_POST['raw_material_dimensions']),
        'vendor' => sanitize_input($_POST['vendor_name']),
        'purchase_date' => sanitize_input($_POST['purchase_date'])
    ];

    $items = getOrderItems($orderId);
    if (isset($items[$itemIndex])) {
        if (!isset($items[$itemIndex]['raw_materials'])) {
            $items[$itemIndex]['raw_materials'] = [];
        }

        $items[$itemIndex]['raw_materials'][] = $newMaterial;
        $items[$itemIndex]['item_status'] = 'Sourcing Material';

        if (updateOrderItems($orderId, $items, 'Sourcing Material')) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Raw material added successfully.'];
            logChange($orderId, 'Raw Materials', "Added material: $materialType - {$newMaterial['grade']}", $itemIndex);
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to add raw material.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Item not found.'];
    }

    header('Location: pipeline.php');
    exit;
}

// Stage 3: Machining Process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_machining_process'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token.'];
        header('Location: pipeline.php');
        exit;
    }

    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int)$_POST['item_index'];

    $processName = sanitize_input($_POST['process_name']);
    if ($processName === 'other') {
        $processName = sanitize_input($_POST['process_name_other']);
    }

    $newProcess = [
        'name' => $processName,
        'sequence' => sanitize_input($_POST['sequence_number']),
        'vendor' => sanitize_input($_POST['vendor_name']),
        'start_date' => sanitize_input($_POST['start_date']),
        'expected_completion' => sanitize_input($_POST['expected_completion']),
        'actual_completion' => '',
        'status' => 'Not Started',
        'remarks' => '',
        'documents' => [],
        'original_filenames' => []
    ];

    $items = getOrderItems($orderId);
    if (isset($items[$itemIndex])) {
        if (!isset($items[$itemIndex]['machining_processes'])) {
            $items[$itemIndex]['machining_processes'] = [];
        }

        $items[$itemIndex]['machining_processes'][] = $newProcess;
        $items[$itemIndex]['item_status'] = 'In Production';

        // Sort by sequence
        usort($items[$itemIndex]['machining_processes'], function ($a, $b) {
            return $a['sequence'] <=> $b['sequence'];
        });

        if (updateOrderItems($orderId, $items, 'In Production')) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Machining process added successfully.'];
            logChange($orderId, 'Machining', "Added process: $processName (Seq: {$newProcess['sequence']})", $itemIndex);
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to add machining process.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Item not found.'];
    }

    header('Location: pipeline.php');
    exit;
}

// Update machining process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_machining_process'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token.'];
        header('Location: pipeline.php');
        exit;
    }

    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int)$_POST['item_index'];
    $processIndex = (int)$_POST['process_index'];

    $items = getOrderItems($orderId);
    if (isset($items[$itemIndex]['machining_processes'][$processIndex])) {
        $process = &$items[$itemIndex]['machining_processes'][$processIndex];

        $process['actual_completion'] = sanitize_input($_POST['actual_completion']);
        $process['status'] = sanitize_input($_POST['process_status']);
        $process['remarks'] = sanitize_input($_POST['remarks']);

        // Handle document upload
        if (isset($_FILES['process_document']) && $_FILES['process_document']['error'] === 0) {
            $uploadResult = handleStageFileUpload($_FILES['process_document'], 0, $orderId, 'machining');
            if ($uploadResult['success']) {
                $process['documents'][] = $uploadResult['filename'];
                $process['original_filenames'][] = $uploadResult['original'];
            }
        }

        // Check if all processes are complete
        $allComplete = true;
        foreach ($items[$itemIndex]['machining_processes'] as $p) {
            if ($p['status'] !== 'Completed') {
                $allComplete = false;
                break;
            }
        }

        if ($allComplete) {
            $items[$itemIndex]['item_status'] = 'Ready for QC';
        }

        if (updateOrderItems($orderId, $items)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Process updated successfully.'];
            logChange($orderId, 'Machining', "Updated process: {$process['name']} - Status: {$process['status']}", $itemIndex);
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to update process.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Process not found.'];
    }

    header('Location: pipeline.php');
    exit;
}

// Stage 4: Quality Control
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_inspection_report'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token.'];
        header('Location: pipeline.php');
        exit;
    }

    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int)$_POST['item_index'];

    $inspectionType = sanitize_input($_POST['inspection_type']);
    if ($inspectionType === 'other') {
        $inspectionType = sanitize_input($_POST['inspection_type_other']);
    }

    $newInspection = [
        'inspection_id' => uniqid('QC_'),
        'status' => sanitize_input($_POST['inspection_status']),
        'type' => $inspectionType,
        'technician_name' => sanitize_input($_POST['technician_name']),
        'inspection_date' => sanitize_input($_POST['inspection_date']),
        'remarks' => sanitize_input($_POST['remarks']),
        'documents' => [],
        'original_filenames' => []
    ];

    // Handle QC document upload
    if (isset($_FILES['qc_document']) && $_FILES['qc_document']['error'] == 0) {
        $uploadResult = handleStageFileUpload($_FILES['qc_document'], 0, $orderId, 'inspection');
        if ($uploadResult['success']) {
            $newInspection['documents'][] = $uploadResult['filename'];
            $newInspection['original_filenames'][] = $uploadResult['original'];

            // Update order's inspection reports
            $order = getOrderById($orderId);
            $inspectionReports = json_decode($order['inspection_reports'] ?? '[]', true);
            $inspectionReports[] = $uploadResult['filename'];
            updateOrder($orderId, ['inspection_reports' => json_encode($inspectionReports)]);
        }
    }

    $items = getOrderItems($orderId);
    if (isset($items[$itemIndex])) {
        if (!isset($items[$itemIndex]['inspection_data'])) {
            $items[$itemIndex]['inspection_data'] = [];
        }

        $items[$itemIndex]['inspection_data'][] = $newInspection;

        // Update item status based on inspection result
        if ($newInspection['status'] === 'Rework Required') {
            $items[$itemIndex]['item_status'] = 'In Production';
            $orderStatus = 'In Production';
        } elseif ($newInspection['status'] === 'QC Passed') {
            $items[$itemIndex]['item_status'] = 'QC Completed';

            // Check if all items passed QC
            $allPassed = true;
            foreach ($items as $item) {
                $hasPassed = false;
                if (!empty($item['inspection_data'])) {
                    foreach ($item['inspection_data'] as $insp) {
                        if ($insp['status'] === 'QC Passed') {
                            $hasPassed = true;
                            break;
                        }
                    }
                }
                if (!$hasPassed) {
                    $allPassed = false;
                    break;
                }
            }

            $orderStatus = $allPassed ? 'QC Completed' : null;
        }

        if (updateOrderItems($orderId, $items, $orderStatus)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Inspection report submitted successfully.'];
            logChange($orderId, 'Quality Control', "QC Result: {$newInspection['status']} - {$newInspection['type']}", $itemIndex);
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to submit inspection report.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Item not found.'];
    }

    header('Location: pipeline.php');
    exit;
}

// Stage 5: Packaging
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_packaging_lot'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token.'];
        header('Location: pipeline.php');
        exit;
    }

    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int)$_POST['item_index'];

    // Verify fumigation
    if (!isset($_POST['fumigation_completed']) || $_POST['fumigation_completed'] != '1') {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Fumigation must be completed before packaging.'];
        header('Location: pipeline.php');
        exit;
    }

    $packagingType = sanitize_input($_POST['packaging_type']);
    if ($packagingType === 'other') {
        $packagingType = sanitize_input($_POST['packaging_type_other']);
    }

    // Handle multiple photo uploads
    $uploadedPhotos = [];
    $originalNames = [];
    if (isset($_FILES['product_photos'])) {
        foreach ($_FILES['product_photos']['tmp_name'] as $index => $tmpName) {
            if (!empty($tmpName)) {
                $uploadResult = handleMultipleFileUpload($_FILES['product_photos'], $index, $orderId, 'packaging');
                if ($uploadResult['success']) {
                    $uploadedPhotos[] = $uploadResult['filename'];
                    $originalNames[] = $uploadResult['original'];
                }
            }
        }
    }

    $newLot = [
        'photos' => $uploadedPhotos,
        'original_photo_names' => $originalNames,
        'products_in_lot' => sanitize_input($_POST['products_in_lot']),
        'docs_included' => isset($_POST['docs_included']) ? 'Yes' : 'No',
        'packaging_type' => $packagingType,
        'packaging_date' => sanitize_input($_POST['packaging_date']),
        'num_packages' => sanitize_input($_POST['num_packages']),
        'weight_per_package' => sanitize_input($_POST['weight_per_package']),
        'dimensions_per_package' => sanitize_input($_POST['dimensions_per_package']),
        'net_weight' => sanitize_input($_POST['net_weight']),
        'gross_weight' => sanitize_input($_POST['gross_weight']),
        'fumigation_completed' => 'Yes',
        'fumigation_certificate_number' => sanitize_input($_POST['fumigation_certificate_number']),
        'fumigation_date' => sanitize_input($_POST['fumigation_date']),
        'fumigation_agency' => sanitize_input($_POST['fumigation_agency'])
    ];

    $items = getOrderItems($orderId);
    if (isset($items[$itemIndex])) {
        if (!isset($items[$itemIndex]['packaging_lots'])) {
            $items[$itemIndex]['packaging_lots'] = [];
        }

        $items[$itemIndex]['packaging_lots'][] = $newLot;
        $items[$itemIndex]['item_status'] = 'Packaging';

        if (updateOrderItems($orderId, $items, 'Packaging')) {
            $lotNumber = count($items[$itemIndex]['packaging_lots']);
            $_SESSION['message'] = ['type' => 'success', 'text' => "Packaging lot #$lotNumber added successfully."];
            logChange($orderId, 'Packaging', "Added lot #$lotNumber: {$newLot['products_in_lot']} products", $itemIndex);
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to create packaging lot.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Item not found.'];
    }

    header('Location: pipeline.php');
    exit;
}

// Stage 6: Shipping Documents
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_shipping_documents'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token.'];
        header('Location: pipeline.php');
        exit;
    }

    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int)$_POST['item_index'];
    $lotIndex = (int)$_POST['lot_index'];

    $items = getOrderItems($orderId);
    if (isset($items[$itemIndex]['packaging_lots'][$lotIndex])) {
        $uploadedDocs = [];
        $originalNames = [];

        if (isset($_FILES['shipping_docs'])) {
            foreach ($_FILES['shipping_docs']['tmp_name'] as $index => $tmpName) {
                if (!empty($tmpName)) {
                    $uploadResult = handleMultipleFileUpload($_FILES['shipping_docs'], $index, $orderId, 'shipping');
                    if ($uploadResult['success']) {
                        $uploadedDocs[] = $uploadResult['filename'];
                        $originalNames[] = $uploadResult['original'];
                    }
                }
            }
        }

        if (!empty($uploadedDocs)) {
            $items[$itemIndex]['packaging_lots'][$lotIndex]['shipping_documents'] = $uploadedDocs;
            $items[$itemIndex]['packaging_lots'][$lotIndex]['shipping_original_filenames'] = $originalNames;
            $items[$itemIndex]['item_status'] = 'Ready for Dispatch';

            if (updateOrderItems($orderId, $items, 'Ready for Dispatch')) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Shipping documents uploaded successfully.'];
                logChange($orderId, 'Shipping Documents', 'Uploaded shipping documents for Lot #' . ($lotIndex + 1), $itemIndex);
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to upload shipping documents.'];
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'No valid documents uploaded.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Packaging lot not found.'];
    }

    header('Location: pipeline.php');
    exit;
}

// Stage 7: Dispatch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_dispatch_details'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid security token.'];
        header('Location: pipeline.php');
        exit;
    }

    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int)$_POST['item_index'];
    $lotIndex = (int)$_POST['lot_index'];

    $items = getOrderItems($orderId);
    if (isset($items[$itemIndex]['packaging_lots'][$lotIndex])) {
        $lot = &$items[$itemIndex]['packaging_lots'][$lotIndex];

        $lot['dispatch_status'] = 'Shipped';
        $lot['dispatch_date'] = sanitize_input($_POST['dispatch_date']);
        $lot['transport_mode'] = sanitize_input($_POST['transport_mode']);
        $lot['tracking_number'] = sanitize_input($_POST['tracking_number']);
        $lot['dispatch_remarks'] = sanitize_input($_POST['dispatch_remarks']);

        // Check if all lots are shipped
        $allShipped = true;
        foreach ($items as $item) {
            if (isset($item['packaging_lots'])) {
                foreach ($item['packaging_lots'] as $checkLot) {
                    if (($checkLot['dispatch_status'] ?? '') !== 'Shipped') {
                        $allShipped = false;
                        break 2;
                    }
                }
            }
        }

        if ($allShipped) {
            $items[$itemIndex]['item_status'] = 'Shipped';
            $orderStatus = 'Shipped';
        } else {
            $orderStatus = null;
        }

        if (updateOrderItems($orderId, $items, $orderStatus)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Dispatch details updated successfully.'];
            logChange($orderId, 'Dispatch', "Shipped via {$lot['transport_mode']} - Tracking: {$lot['tracking_number']}", $itemIndex);

            // Send shipping notification
            if ($orderStatus === 'Shipped') {
                $order = getOrderById($orderId);
                sendShippingNotification($order['customer_id'], $orderId, $lot['tracking_number']);
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to update dispatch details.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Packaging lot not found.'];
    }

    header('Location: pipeline.php');
    exit;
}

// ==========================================
// DATA FETCHING FOR DISPLAY
// ==========================================

// Get data for display
$orders = getOrders(); // Get all orders for pipeline view
$customers = getCustomers();
$customerMap = array_column($customers, 'name', 'id');

// Calculate statistics
$statusCounts = array_fill_keys($STANDARD_STATUSES, 0);
$statusCounts['Total'] = count($orders);

foreach ($orders as $order) {
    $status = $order['status'] ?? 'Pending';
    if (isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    }
}

// Generate CSRF token
$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Pipeline - Alphasonix CRM</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style>
        :root {
            --bs-primary: #0d6efd;
            --bs-secondary: #6c757d;
            --bs-success: #198754;
            --bs-info: #0dcaf0;
            --bs-warning: #ffc107;
            --bs-danger: #dc3545;
            --bs-light: #f8f9fa;
            --bs-dark: #212529;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #212529;
        }
        
        .pipeline-container {
            padding: 20px;
        }
        
        .pipeline-header {
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* Updated Dashboard Styles */
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
            aspect-ratio: 1/1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--card-color, #6c757d);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .stat-card-total {
            --card-color: var(--bs-primary);
        }

        .stat-card-pending {
            --card-color: var(--bs-secondary);
        }

        .stat-card-sourcing-material {
            --card-color: var(--bs-warning);
        }

        .stat-card-in-production {
            --card-color: var(--bs-info);
        }

        .stat-card-ready-for-qc {
            --card-color: #6f42c1;
        }

        .stat-card-qc-completed {
            --card-color: #20c997;
        }

        .stat-card-packaging {
            --card-color: #fd7e14;
        }

        .stat-card-ready-for-dispatch {
            --card-color: #0dcaf0;
        }

        .stat-card-shipped {
            --card-color: var(--bs-success);
        }

        .stat-card .count {
            display: block;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--card-color, #212529);
            line-height: 1;
        }

        .stat-card .label {
            font-size: 0.9rem;
            color: var(--bs-secondary);
            font-weight: 500;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Icon styling for dashboard cards */
        .stat-card::after {
            content: '';
            font-family: 'Bootstrap Icons';
            font-size: 1.5rem;
            color: rgba(0, 0, 0, 0.1);
            position: absolute;
            bottom: 10px;
            right: 15px;
            opacity: 0.7;
        }

        .stat-card-total::after {
            content: '\F479'; /* bi-clipboard-data */
        }

        .stat-card-pending::after {
            content: '\F28A'; /* bi-clock */
        }

        .stat-card-sourcing-material::after {
            content: '\F1C3'; /* bi-box-seam */
        }

        .stat-card-in-production::after {
            content: '\F3B1'; /* bi-gear */
        }

        .stat-card-ready-for-qc::after {
            content: '\F272'; /* bi-clipboard-check */
        }

        .stat-card-qc-completed::after {
            content: '\F26A'; /* bi-check-circle */
        }

        .stat-card-packaging::after {
            content: '\F5CB'; /* bi-box */
        }

        .stat-card-ready-for-dispatch::after {
            content: '\F21E'; /* bi-truck */
        }

        .stat-card-shipped::after {
            content: '\F633'; /* bi-check-lg */
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .dashboard {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            }
            
            .stat-card .count {
                font-size: 2.25rem;
            }
        }

        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 1rem;
            }
            
            .stat-card {
                padding: 1.25rem;
            }
            
            .stat-card .count {
                font-size: 2rem;
            }
            
            .stat-card .label {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 576px) {
            .dashboard {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        .filter-controls {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .order-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .order-card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        
        .order-card.overdue {
            border-left: 4px solid var(--bs-danger);
        }
        
        .order-card.urgent {
            border-left: 4px solid var(--bs-warning);
        }
        
        .order-content {
            display: grid;
            grid-template-columns: 280px 1fr 280px;
            gap: 1.5rem;
            padding: 1.5rem;
            align-items: start;
        }
        
        @media (max-width: 1400px) {
            .order-content {
                grid-template-columns: 250px 1fr 250px;
                gap: 1rem;
                padding: 1.25rem;
            }
        }
        
        @media (max-width: 992px) {
            .order-content {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
        
        .order-details, .status-control {
            background: #f8f9fa;
            padding: 1.25rem;
            border-radius: 0.375rem;
            height: fit-content;
            position: sticky;
            top: 1rem;
        }
        
        .workflow-section {
            min-height: 0;
        }
        
        .order-info {
            margin-bottom: 1rem;
        }
        
        .order-info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .order-info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .order-info-label {
            font-weight: 500;
            color: var(--bs-secondary);
            white-space: nowrap;
            margin-right: 1rem;
        }
        
        .order-info-value {
            text-align: right;
            word-break: break-word;
            font-weight: 600;
        }
        
        .order-details h3, .status-control h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .order-details .no-print.mt-3 {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .order-details .btn {
            width: 100%;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .deadline-indicator {
            padding: 0.5rem;
            border-radius: 0.25rem;
            margin-top: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .deadline-overdue {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .deadline-urgent {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .item-card {
            border: 1px solid #e9ecef;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
            overflow: hidden;
        }
        
        .item-header {
            background: #f8f9fa;
            padding: 1rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid transparent;
            transition: all 0.2s;
        }
        
        .item-header:hover {
            background: #e9ecef;
        }
        
        .item-header.expanded {
            border-bottom: 1px solid #e9ecef;
        }
        
        .item-body {
            padding: 1rem;
            display: none;
        }
        
        .item-title {
            margin: 0;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }
        
        .toggle-icon {
            transition: transform 0.2s;
        }
        
        .item-header.expanded .toggle-icon {
            transform: rotate(90deg);
        }
        
        .item-quantity {
            background: var(--bs-primary);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .item-status-badge {
            display: inline-block;
            padding: 0.35rem 0.65rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }
        
        .item-status-pending {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .item-status-sourcing-material {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .item-status-in-production {
            background-color: #cff4fc;
            color: #055160;
        }
        
        .item-status-ready-for-qc {
            background-color: #e2e3ff;
            color: #484848;
        }
        
        .item-status-qc-completed {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        
        .item-status-packaging {
            background-color: #ffe5d0;
            color: #664d03;
        }
        
        .item-status-ready-for-dispatch {
            background-color: #cff4fc;
            color: #055160;
        }
        
        .item-status-shipped {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        
        .stage-section {
            margin-bottom: 1.5rem;
            border: 1px solid #e9ecef;
            border-radius: 0.375rem;
            overflow: hidden;
        }
        
        .stage-header {
            background: #f8f9fa;
            padding: 0.75rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e9ecef;
        }
        
        .stage-content {
            padding: 1rem;
        }
        
        .stage-form {
            background: white;
            padding: 1rem;
            border-radius: 0.375rem;
            border: 1px solid #e9ecef;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            grid-column: 1 / -1;
        }
        
        .status-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            font-size: 1rem;
            font-weight: 500;
            border-radius: 0.375rem;
            text-align: center;
            width: 100%;
            margin-bottom: 1rem;
            min-height: 2.5rem;
        }
        
        .status-pending {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .status-sourcing-material {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-in-production {
            background-color: #cff4fc;
            color: #055160;
        }
        
        .status-ready-for-qc {
            background-color: #e2e3ff;
            color: #484848;
        }
        
        .status-qc-completed {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        
        .status-packaging {
            background-color: #ffe5d0;
            color: #664d03;
        }
        
        .status-ready-for-dispatch {
            background-color: #cff4fc;
            color: #055160;
        }
        
        .status-shipped {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        
        .process-entry, .inspection-entry, .packaging-lot {
            background: white;
            padding: 1rem;
            border-radius: 0.375rem;
            border: 1px solid #e9ecef;
            margin-bottom: 1rem;
        }
        
        .process-status-badge, .inspection-status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 0.25rem;
        }
        
        .document-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 0.25rem;
            margin-bottom: 0.5rem;
        }
        
        .drawing-preview, .no-drawing-placeholder {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }
        
        .drawing-upload-form {
            background: white;
            padding: 1rem;
            border-radius: 0.375rem;
            border: 1px solid #e9ecef;
            margin-top: 1rem;
        }
        
        .file-info-display {
            background: white;
            padding: 1rem;
            border-radius: 0.375rem;
            border: 1px solid #e9ecef;
            margin-bottom: 1rem;
        }
        
        .upload-preview {
            background: #e8f5e8;
            padding: 0.75rem;
            border-radius: 0.25rem;
            margin-bottom: 1rem;
        }
        
        .data-table {
            width: 100%;
            margin-bottom: 1rem;
            border-collapse: collapse;
        }
        
        .data-table th, .data-table td {
            padding: 0.75rem;
            border: 1px solid #dee2e6;
        }
        
        .data-table th {
            background-color: #f8f9fa;
            font-weight: 500;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 0.5rem;
            width: 80%;
            max-width: 700px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .modal-close {
            color: #aaa;
            float: right;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        
        .modal-close:hover {
            color: #000;
        }
        
        .drawing-modal {
            display: none;
            position: fixed;
            z-index: 1060;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            text-align: center;
        }
        
        .drawing-modal-close {
            position: absolute;
            top: 1rem;
            right: 2rem;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            cursor: pointer;
            z-index: 1070;
        }
        
        #modalDrawing {
            max-width: 90%;
            max-height: 90%;
            margin: auto;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        #backToTop {
            display: none;
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 100;
            border: none;
            outline: none;
            background-color: var(--bs-primary);
            color: white;
            cursor: pointer;
            padding: 0.75rem 1rem;
            border-radius: 50%;
            font-size: 1.25rem;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.2);
        }
        
        #backToTop:hover {
            background-color: #0b5ed7;
        }
        
        .toast {
            position: fixed;
            top: 1rem;
            right: 1rem;
            padding: 1rem 1.5rem;
            border-radius: 0.375rem;
            color: white;
            z-index: 1080;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.2);
            max-width: 350px;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .toast-success {
            background-color: var(--bs-success);
        }
        
        .toast-error {
            background-color: var(--bs-danger);
        }
        
        .btn-reset {
            background-color: var(--bs-secondary);
            color: white;
            border: none;
        }
        
        .btn-reset:hover {
            background-color: #5c636a;
            color: white;
        }
        
        .btn-small {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .cursor-pointer {
            cursor: pointer;
        }

        .object-fit-cover {
            object-fit: cover;
        }
        
        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .order-card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #dee2e6;
            }
            
            .item-body {
                display: block !important;
            }
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="pipeline-container">
            <!-- Header Section -->
            <div class="pipeline-header">
                <h2 class="mb-0"><i class="bi bi-diagram-3"></i> Order Pipeline Process</h2>
                <div class="d-flex gap-2">
                    <a href="orders.php" class="btn btn-primary no-print"><i class="bi bi-plus-circle"></i> Create New Order</a>
                    <a href="print_order.php?order_id=<?= htmlspecialchars($order['order_id'] ?? '') ?>" target="_blank" class="btn btn-primary no-print">
                        <i class="bi bi-printer"></i> Print Invoice
                    </a>
                </div>
            </div>

            <!-- Show Messages -->
            <?php if (isset($_SESSION['message'])): ?>
                <?php
                $messageType = $_SESSION['message']['type'];
                $messageClass = $messageType === 'success' ? 'toast-success' : 'toast-error';
                echo "<div class='toast {$messageClass}'>{$_SESSION['message']['text']}</div>";
                unset($_SESSION['message']);
                ?>
            <?php endif; ?>

            <!-- Kanban Dashboard -->
            <div class="dashboard no-print">
                <div class="stat-card stat-card-total">
                    <span class="count"><?= $statusCounts['Total'] ?? 0 ?></span>
                    <span class="label">Total Orders</span>
                </div>
                <?php foreach ($STANDARD_STATUSES as $status):
                    $statusClass = 'stat-card-' . strtolower(str_replace(' ', '-', $status));
                ?>
                    <div class="stat-card <?= $statusClass ?>">
                        <span class="count"><?= $statusCounts[$status] ?? 0 ?></span>
                        <span class="label"><?= $status ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Filter Controls -->
            <div class="filter-controls no-print">
                <input type="text" id="orderSearch" class="form-control"
                    placeholder="Search by Order ID or Client Name...">
                <select id="statusFilter" class="form-select">
                    <option value="">-- Filter by Status --</option>
                    <?php foreach ($STANDARD_STATUSES as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="clientFilter" class="form-select">
                    <option value="">-- Filter by Client --</option>
                    <?php foreach ($customerMap as $id => $name): ?>
                        <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>
                <button id="clearFilters" class="btn btn-secondary">Clear Filters</button>
            </div>

            <!-- Pipeline Content -->
            <div class="pipeline-content" id="printable-area">
                <?php if (empty($orders)): ?>
                    <div class="text-center py-5 text-muted">
                        <h3><i class="bi bi-inbox"></i> No orders found</h3>
                        <p>Create your first order by clicking the button above.</p>
                    </div>
                <?php else: ?>
                    <?php foreach (array_reverse($orders) as $order):
                        // Deadline calculation
                        $deadline_class = '';
                        $days_remaining_text = '';
                        if (!empty($order['due_date']) && $order['status'] !== 'Shipped') {
                            $due_date = new DateTime($order['due_date']);
                            $today = new DateTime();
                            $interval = $today->diff($due_date);
                            $days_remaining = (int) $interval->format('%r%a');

                            if ($days_remaining < 0) {
                                $deadline_class = 'overdue';
                                $days_remaining_text = "<div class='deadline-indicator deadline-overdue'><i class='bi bi-exclamation-triangle'></i> Overdue by " . abs($days_remaining) . " days</div>";
                            } elseif ($days_remaining <= 7) {
                                $deadline_class = 'urgent';
                                $days_remaining_text = "<div class='deadline-indicator deadline-urgent'><i class='bi bi-clock'></i> {$days_remaining} days remaining</div>";
                            } else {
                                $days_remaining_text = "<div class='deadline-indicator'><i class='bi bi-calendar-event'></i> {$days_remaining} days remaining</div>";
                            }
                        }
                    ?>
                        <div class="order-card <?= $deadline_class ?>" data-status="<?= htmlspecialchars($order['status'] ?? 'Pending') ?>"
                            data-customer-id="<?= htmlspecialchars($order['customer_id']) ?>">
                            <div class="order-content">
                                <!-- Order Details Panel -->
                                <div class="order-details">
                                    <h3><i class="bi bi-clipboard-data"></i> Order Information</h3>
                                    <div class="order-info">
                                        <div class="order-info-item">
                                            <span class="order-info-label">Order #:</span>
                                            <span class="order-info-value"><?= htmlspecialchars($order['order_id']) ?></span>
                                        </div>
                                        <div class="order-info-item">
                                            <span class="order-info-label">Client:</span>
                                            <span class="order-info-value"><?= htmlspecialchars($customerMap[$order['customer_id']] ?? 'N/A') ?></span>
                                        </div>
                                        <div class="order-info-item">
                                            <span class="order-info-label">PO Date:</span>
                                            <span class="order-info-value"><?= date('M j, Y', strtotime($order['po_date'])) ?></span>
                                        </div>
                                        <?php if (!empty($order['delivery_date'])): ?>
                                            <div class="order-info-item">
                                                <span class="order-info-label">Delivery:</span>
                                                <span class="order-info-value"><?= date('M j, Y', strtotime($order['delivery_date'])) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?= $days_remaining_text ?>

                                    <!-- Buttons -->
                                    <div class="no-print mt-3 d-flex flex-column gap-2">
                                        <a href="order-stages-view.php?id=<?= urlencode($order['order_id']) ?>"
                                            class="btn btn-primary text-center">
                                            <i class="bi bi-graph-up"></i> View All Stages
                                        </a>
                                        <button class="btn btn-info view-history-btn"
                                            data-order-id="<?= htmlspecialchars($order['order_id']) ?>">
                                            <i class="bi bi-clock-history"></i> View History
                                        </button>
                                    </div>
                                </div>

                                <!-- Workflow Section -->
                                <div class="workflow-section">
                                    <?php
                                    $items = getOrderItems($order['order_id']);
                                    foreach ($items as $itemIndex => $item):
                                    ?>
                                        <div class="item-card">
                                            <!-- Item Header -->
                                            <div class="item-header">
                                                <h4 class="item-title">
                                                    <span class="toggle-icon"><i class="bi bi-caret-right-fill"></i></span>
                                                    <?= htmlspecialchars($item['Name'] ?? 'N/A') ?>
                                                </h4>
                                                <div class="d-flex gap-2 align-items-center">
                                                    <div class="item-quantity">Qty: <?= htmlspecialchars($item['quantity']) ?></div>
                                                    <form action="pipeline.php" method="post" class="m-0 no-print"
                                                        onsubmit="return confirm('Delete this item from the order?');">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
                                                        <input type="hidden" name="item_index" value="<?= $itemIndex ?>">
                                                        <button type="submit" name="delete_item" class="btn btn-danger btn-small"
                                                            title="Delete this item"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                </div>
                                            </div>

                                            <!-- Item Body -->
                                            <div class="item-body">
                                                <?php if (!empty($item['Dimensions']) || !empty($item['Description'])): ?>
                                                    <div class="mb-3 p-2 bg-light rounded">
                                                        <?php if (!empty($item['Dimensions'])): ?>
                                                            <div><strong>Dimensions:</strong> <?= htmlspecialchars($item['Dimensions']) ?></div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($item['Description'])): ?>
                                                            <div><strong>Description:</strong> <?= htmlspecialchars($item['Description']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Item Drawing Section -->
                                                <div class="mb-3">
                                                    <?php if (!empty($item['drawing_filename'])): ?>
                                                        <div class="drawing-preview">
                                                            <h5 class="mt-0"><i class="bi bi-file-earmark"></i> Product Drawing</h5>
                                                            <div class="file-info-display">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <?php
                                                                    $fileExt = strtolower(pathinfo($item['drawing_filename'], PATHINFO_EXTENSION));
                                                                    $fileIcon = 'bi-file-earmark-text';
                                                                    if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif'])) {
                                                                        $fileIcon = 'bi-file-image';
                                                                    } elseif ($fileExt === 'pdf') {
                                                                        $fileIcon = 'bi-file-earmark-pdf';
                                                                    }
                                                                    ?>
                                                                    <i class="bi <?= $fileIcon ?>" style="font-size: 1.5rem;"></i>
                                                                    <div class="flex-grow-1">
                                                                        <div class="fw-semibold text-primary">
                                                                            <?= htmlspecialchars($item['original_filename'] ?? $item['drawing_filename']) ?>
                                                                        </div>
                                                                        <div class="small text-muted">
                                                                            Order #<?= htmlspecialchars($order['order_id']) ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="mt-2 d-flex gap-2 no-print">
                                                                <?php if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'pdf'])): ?>
                                                                    <a href="uploads/drawings/<?= htmlspecialchars($item['drawing_filename']) ?>"
                                                                        target="_blank" class="btn btn-small btn-primary">
                                                                        <i class="bi bi-eye"></i> View
                                                                    </a>
                                                                <?php endif; ?>
                                                                <button type="button" class="btn btn-small btn-warning"
                                                                    onclick="showDrawingForm('drawing-form-<?= $order['order_id'] ?>-<?= $itemIndex ?>')">
                                                                    <i class="bi bi-arrow-repeat"></i> Replace
                                                                </button>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="no-drawing-placeholder text-center">
                                                            <div class="mb-2"><i class="bi bi-file-earmark" style="font-size: 2rem;"></i></div>
                                                            <p class="text-muted m-0 mb-3">No drawing uploaded for this item</p>
                                                            <button type="button" class="btn btn-primary"
                                                                onclick="showDrawingForm('drawing-form-<?= $order['order_id'] ?>-<?= $itemIndex ?>')">
                                                                <i class="bi bi-cloud-upload"></i> Upload Drawing
                                                            </button>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Drawing Upload Form -->
                                                    <div id="drawing-form-<?= $order['order_id'] ?>-<?= $itemIndex ?>"
                                                        class="drawing-upload-form no-print" style="display: none;">
                                                        <form action="pipeline.php" method="post" enctype="multipart/form-data">
                                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                            <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
                                                            <input type="hidden" name="item_index" value="<?= $itemIndex ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label fw-semibold">Select New Drawing File:</label>
                                                                <input type="file" name="item_drawing" accept=".jpg,.jpeg,.png,.pdf,.dwg"
                                                                    required class="form-control" onchange="previewUploadFile(this)">
                                                                <div class="form-text">
                                                                    Supported formats: JPG, PNG, PDF, DWG (Max 10MB)
                                                                </div>
                                                            </div>
                                                            <div class="upload-preview" style="display: none;">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <i class="bi bi-file-earmark-text"></i>
                                                                    <div class="flex-grow-1">
                                                                        <div class="preview-name fw-semibold"></div>
                                                                        <div class="preview-size small text-muted"></div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="d-flex gap-2 justify-content-end">
                                                                <button type="button" class="btn btn-reset"
                                                                    onclick="hideDrawingForm('drawing-form-<?= $order['order_id'] ?>-<?= $itemIndex ?>')">
                                                                    <i class="bi bi-x-circle"></i> Cancel
                                                                </button>
                                                                <button type="submit" name="update_item_drawing" class="btn btn-primary">
                                                                    <i class="bi bi-cloud-upload"></i> Upload Drawing
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>

                                                <!-- Item Status Control -->
                                                <div class="no-print mb-4">
                                                    <span class="item-status-badge item-status-<?= strtolower(str_replace(' ', '-', $item['item_status'] ?? 'pending')) ?>">
                                                        <?= htmlspecialchars($item['item_status'] ?? 'Pending') ?>
                                                    </span>
                                                    <form action="pipeline.php" method="post" class="d-inline-flex gap-2 align-items-center ms-3">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
                                                        <input type="hidden" name="item_index" value="<?= $itemIndex ?>">
                                                        <select name="new_item_status" class="form-select" style="min-width: 180px;">
                                                            <?php foreach ($STANDARD_STATUSES as $status): ?>
                                                                <option value="<?= $status ?>" <?= ($item['item_status'] ?? 'Pending') == $status ? 'selected' : '' ?>><?= $status ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" name="update_item_status" class="btn btn-primary btn-small">Update</button>
                                                    </form>
                                                </div>

                                                <!-- Stage Content - Dynamic based on item status -->
                                                <div class="stage-content">
                                                    <?php
                                                    $itemStatus = $item['item_status'] ?? 'Pending';

                                                    switch ($itemStatus) {
                                                        case 'Pending':
                                                            echo showPendingStage($order, $item, $itemIndex, $csrfToken);
                                                            break;

                                                        case 'Sourcing Material':
                                                            echo showSourcingStage($order, $item, $itemIndex, $csrfToken);
                                                            break;

                                                        case 'In Production':
                                                            echo showProductionStage($order, $item, $itemIndex, $csrfToken);
                                                            break;

                                                        case 'Ready for QC':
                                                        case 'QC Completed':
                                                            echo showQCStage($order, $item, $itemIndex, $csrfToken);
                                                            break;

                                                        case 'Packaging':
                                                            echo showPackagingStage($order, $item, $itemIndex, $csrfToken);
                                                            break;

                                                        case 'Ready for Dispatch':
                                                            echo showDispatchStage($order, $item, $itemIndex, $csrfToken);
                                                            break;

                                                        case 'Shipped':
                                                            echo showShippedStage($order, $item, $itemIndex, $csrfToken);
                                                            break;

                                                        default:
                                                            echo showPendingStage($order, $item, $itemIndex, $csrfToken);
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Status Control Panel -->
                                <div class="status-control">
                                    <h3><i class="bi bi-bullseye"></i> Order Status</h3>
                                    <div class="status-badge status-<?= strtolower(str_replace(' ', '-', $order['status'] ?? 'pending')) ?>">
                                        <?= htmlspecialchars($order['status'] ?? 'Pending') ?>
                                    </div>

                                    <!-- Status Update Form -->
                                    <form action="pipeline.php" method="post" class="no-print mt-3">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
                                        <select name="new_status" class="form-select mb-2">
                                            <?php foreach ($STANDARD_STATUSES as $status): ?>
                                                <option value="<?= $status ?>" <?= ($order['status'] ?? 'Pending') == $status ? 'selected' : '' ?>><?= $status ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="update_status" class="btn btn-primary w-100">Update Order Status</button>
                                    </form>

                                    <!-- Delete Order Button -->
                                    <?php if (hasRole('admin')): ?>
                                        <form action="pipeline.php" method="post" class="no-print mt-3"
                                            onsubmit="return confirm('Are you sure you want to delete this order? This action cannot be undone.');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
                                            <button type="submit" name="delete_order" class="btn btn-danger w-100">
                                                <i class="bi bi-trash"></i> Delete Order
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div id="historyModal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h3><i class="bi bi-clock-history"></i> Order History</h3>
            <div id="historyContent"></div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="modal">
        <div class="modal-content" style="max-width: 90%; max-height: 90%;">
            <span class="modal-close" onclick="closeImageModal()">&times;</span>
            <img id="modalImage" src="" alt="Preview" style="width: 100%; height: auto;">
        </div>
    </div>

    <!-- Back to Top Button -->
    <button id="backToTop" title="Go to top"><i class="bi bi-arrow-up"></i></button>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Toggle item body visibility
        document.querySelectorAll('.item-header').forEach(header => {
            header.addEventListener('click', function() {
                const body = this.nextElementSibling;
                const isExpanded = body.style.display === 'block';
                const toggleIcon = this.querySelector('.toggle-icon i');

                body.style.display = isExpanded ? 'none' : 'block';
                this.classList.toggle('expanded', !isExpanded);

                if (!isExpanded) {
                    toggleIcon.className = 'bi bi-caret-down-fill';
                } else {
                    toggleIcon.className = 'bi bi-caret-right-fill';
                }
            });
        });

        // Filter functionality
        document.getElementById('orderSearch').addEventListener('input', filterOrders);
        document.getElementById('statusFilter').addEventListener('change', filterOrders);
        document.getElementById('clientFilter').addEventListener('change', filterOrders);
        document.getElementById('clearFilters').addEventListener('click', clearFilters);

        function filterOrders() {
            const searchTerm = document.getElementById('orderSearch').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const clientFilter = document.getElementById('clientFilter').value;

            document.querySelectorAll('.order-card').forEach(card => {
                const orderId = card.querySelector('.order-info-item:nth-child(1) .order-info-value').textContent.toLowerCase();
                const clientName = card.querySelector('.order-info-item:nth-child(2) .order-info-value').textContent.toLowerCase();
                const status = card.getAttribute('data-status');
                const customerId = card.getAttribute('data-customer-id');

                const matchesSearch = orderId.includes(searchTerm) || clientName.includes(searchTerm);
                const matchesStatus = !statusFilter || status === statusFilter;
                const matchesClient = !clientFilter || customerId === clientFilter;

                card.style.display = matchesSearch && matchesStatus && matchesClient ? 'block' : 'none';
            });
        }

        function clearFilters() {
            document.getElementById('orderSearch').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('clientFilter').value = '';
            filterOrders();
        }

        // Toggle other field visibility
        function toggleOtherField(selectElement, fieldName) {
            const otherInput = selectElement.parentNode.querySelector(`[name="${fieldName}_other"]`);
            if (otherInput) {
                otherInput.style.display = selectElement.value === 'other' ? 'block' : 'none';
                otherInput.required = selectElement.value === 'other';
                if (selectElement.value !== 'other') {
                    otherInput.value = '';
                }
            }
        }

        // File upload preview
        function previewUploadFile(input) {
            const preview = input.parentNode.parentNode.querySelector('.upload-preview');
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileName = file.name;
                const fileSize = (file.size / 1024 / 1024).toFixed(2); // MB

                preview.querySelector('.preview-name').textContent = fileName;
                preview.querySelector('.preview-size').textContent = fileSize + ' MB';
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }

        // Drawing form controls
        function showDrawingForm(formId) {
            document.getElementById(formId).style.display = 'block';
        }

        function hideDrawingForm(formId) {
            document.getElementById(formId).style.display = 'none';
            const form = document.getElementById(formId).querySelector('form');
            if (form) {
                form.reset();
                const preview = form.querySelector('.upload-preview');
                if (preview) {
                    preview.style.display = 'none';
                }
            }
        }

        // Process modal controls
        function showProcessModal(itemIndex, processIndex) {
            document.getElementById('processModal-' + itemIndex + '-' + processIndex).style.display = 'block';
        }

        function closeProcessModal(itemIndex, processIndex) {
            document.getElementById('processModal-' + itemIndex + '-' + processIndex).style.display = 'none';
        }

        // Image modal controls
        function showImageModal(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('imageModal').style.display = 'block';
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        // Close modals
        document.querySelectorAll('.modal-close').forEach(closeBtn => {
            closeBtn.addEventListener('click', function() {
                this.closest('.modal').style.display = 'none';
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const historyModal = document.getElementById('historyModal');
            const imageModal = document.getElementById('imageModal');

            if (event.target === historyModal) {
                historyModal.style.display = 'none';
            }
            if (event.target === imageModal) {
                imageModal.style.display = 'none';
            }

            // Close process modals
            document.querySelectorAll('.modal').forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });

        // Back to top button
        const backToTopButton = document.getElementById('backToTop');
        window.addEventListener('scroll', () => {
            backToTopButton.style.display = window.pageYOffset > 300 ? 'block' : 'none';
        });

        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Order history modal
        document.querySelectorAll('.view-history-btn').forEach(button => {
            button.addEventListener('click', function() {
                const orderId = this.getAttribute('data-order-id');
                fetchOrderHistory(orderId);
            });
        });

        function fetchOrderHistory(orderId) {
            const modal = document.getElementById('historyModal');
            const content = document.getElementById('historyContent');

            // Show loading
            content.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            modal.style.display = 'block';

            // Fetch history via AJAX
            fetch(`get_order_history.php?order_id=${orderId}`)
                .then(response => response.text())
                .then(html => {
                    content.innerHTML = html;
                })
                .catch(error => {
                    content.innerHTML = '<div class="alert alert-danger">Failed to load history.</div>';
                });
        }

        // Auto-hide toast messages
        document.addEventListener('DOMContentLoaded', function() {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 300);
                }, 5000);
            });

            // Auto-expand overdue orders
            document.querySelectorAll('.order-card.overdue .item-header').forEach(header => {
                header.click();
            });
        });

        // Print functionality
        window.addEventListener('beforeprint', function() {
            // Expand all items for printing
            document.querySelectorAll('.item-header').forEach(header => {
                const body = header.nextElementSibling;
                if (body.style.display !== 'block') {
                    body.style.display = 'block';
                    header.classList.add('expanded');
                }
            });
        });

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const requiredFields = this.querySelectorAll('[required]');
                let isValid = true;

                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + F for search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                document.getElementById('orderSearch').focus();
            }

            // Escape to close modals
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });
    </script>
</body>

</html>