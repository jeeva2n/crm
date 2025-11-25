<?php
session_start();
require_once 'functions.php';

// Log pipeline page access
logActivity('Pipeline Access', 'User accessed the production pipeline page');

// Function to save activity to history file/database
function saveActivityToHistory($activityEntry) {
    $logFile = 'activity_logs.json';
    $logs = [];
    
    if (file_exists($logFile)) {
        $existingLogs = file_get_contents($logFile);
        $logs = json_decode($existingLogs, true) ?? [];
    }
    
    // Add new log entry at the beginning (newest first)
    array_unshift($logs, $activityEntry);
    
    // Keep only last 1000 entries to prevent file from growing too large
    if (count($logs) > 1000) {
        $logs = array_slice($logs, 0, 1000);
    }
    
    // Save to file
    $result = file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
    
    // Debug: Check if writing is successful
    if ($result === false) {
        error_log("Failed to write to activity log file: " . $logFile);
    }
    
    return $result !== false;
}

// Debug function to check logging
function debugLogging() {
    $logFile = 'activity_logs.json';
    if (file_exists($logFile)) {
        $logs = json_decode(file_get_contents($logFile), true) ?? [];
        error_log("Total logs in file: " . count($logs));
        if (!empty($logs)) {
            error_log("Latest log: " . json_encode($logs[0]));
        }
    } else {
        error_log("Activity log file does not exist: " . $logFile);
    }
}

// Call this after important actions to debug
// debugLogging();

// Define standard statuses to be used throughout the system
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

// Get current user info
$currentUser = $_SESSION['username'] ?? 'System';
$userId = $_SESSION['user_id'] ?? $currentUser;
$userRole = $_SESSION['role'] ?? 'employee';

// Reusable function to delete files
function deleteFile($filePath)
{
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
}

// Reusable function to handle file upload
function uploadFile($file, $uploadDir, $prefix)
{
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $original_name = basename($file['name']);
    $newFilename = $prefix . '_' . time() . '_' . $original_name;

    if (move_uploaded_file($file['tmp_name'], $uploadDir . $newFilename)) {
        return ['filename' => $newFilename, 'original' => $original_name];
    }
    return false;
}

// Reusable function to handle multiple file uploads
function uploadMultipleFiles($files, $uploadDir, $prefix)
{
    $uploadedFiles = [];
    $originalNames = [];

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $total_files = count($files['name']);
    for ($i = 0; $i < $total_files; $i++) {
        if ($files['error'][$i] == 0) {
            $original_name = basename($files['name'][$i]);
            $newFilename = $prefix . '_' . time() . '_' . $i . '_' . $original_name;
            if (move_uploaded_file($files['tmp_name'][$i], $uploadDir . $newFilename)) {
                $uploadedFiles[] = $newFilename;
                $originalNames[] = $original_name;
            }
        }
    }

    return ['files' => $uploadedFiles, 'originals' => $originalNames];
}

// Reusable function to update order data
function updateOrderData($orderId, $items, $status = null)
{
    // Use database function instead of CSV
    return updateOrderItems($orderId, $items, $status);
}

// Reusable function to set success message and redirect
function setMessageAndRedirect($type, $message)
{
    $_SESSION['message'] = ['type' => $type, 'text' => $message];
    header('Location: pipeline.php');
    exit;
}

// --- Handle Order Deletion ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    $orderId = sanitize_input($_POST['order_id']);

    if (!empty($orderId)) {
        $orderToDelete = getOrderById($orderId);

        if ($orderToDelete) {
            // Get order items to delete associated files
            $items = getOrderItems($orderId);
            
            // Delete associated files
            foreach ($items as $item) {
                // Delete item drawings
                if (!empty($item['drawing_filename'])) {
                    deleteFile(__DIR__ . '/uploads/drawings/' . $item['drawing_filename']);
                }

                // Delete machining documents
                if (!empty($item['machining_processes'])) {
                    foreach ($item['machining_processes'] as $process) {
                        if (!empty($process['documents'])) {
                            foreach ($process['documents'] as $doc) {
                                deleteFile(__DIR__ . '/uploads/machining_docs/' . $doc);
                            }
                        }
                    }
                }

                // Delete inspection documents
                if (!empty($item['inspection_data']) && is_array($item['inspection_data'])) {
                    foreach ($item['inspection_data'] as $inspection) {
                        if (!empty($inspection['documents'])) {
                            foreach ($inspection['documents'] as $doc) {
                                deleteFile(__DIR__ . '/uploads/inspection_reports/' . $doc);
                            }
                        }
                    }
                }

                // Delete packaging photos
                if (!empty($item['packaging_lots'])) {
                    foreach ($item['packaging_lots'] as $lot) {
                        if (!empty($lot['photos'])) {
                            foreach ($lot['photos'] as $photo) {
                                deleteFile(__DIR__ . '/uploads/packaging_photos/' . $photo);
                            }
                        }

                        // Delete shipping documents
                        if (!empty($lot['shipping_documents'])) {
                            foreach ($lot['shipping_documents'] as $doc) {
                                deleteFile(__DIR__ . '/uploads/shipping_docs/' . $doc);
                            }
                        }
                    }
                }
            }

            // Delete the order from database
            if (deleteOrder($orderId)) {
                logChange($orderId, 'Order Management', "User '$currentUser' ($userRole) deleted order #$orderId");
                setMessageAndRedirect('success', "Order #$orderId has been deleted successfully by $currentUser.");
            } else {
                setMessageAndRedirect('error', 'Failed to delete the order.');
            }
        } else {
            setMessageAndRedirect('error', 'Order not found.');
        }
    } else {
        setMessageAndRedirect('error', 'Invalid order ID.');
    }
}

// --- Handle Item Deletion from Order ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int) $_POST['item_index'];

    $items = getOrderItems($orderId);
    
    if (isset($items[$itemIndex])) {
        $itemName = $items[$itemIndex]['Name'] ?? 'Unknown';
        $itemSNo = $items[$itemIndex]['S.No'] ?? '';

        // Delete item drawing if exists
        if (!empty($items[$itemIndex]['drawing_filename'])) {
            deleteFile(__DIR__ . '/uploads/drawings/' . $items[$itemIndex]['drawing_filename']);
        }

        // Remove the item from the array
        array_splice($items, $itemIndex, 1);

        // Update the order items in database
        if (updateOrderItems($orderId, $items)) {
            logChange($orderId, 'Order Management', "User '$currentUser' ($userRole) deleted item '$itemName' at index $itemIndex", $itemIndex);
            setMessageAndRedirect('success', "Item deleted successfully by $currentUser.");
        } else {
            setMessageAndRedirect('error', 'Failed to delete item.');
        }
    } else {
        setMessageAndRedirect('error', 'Item not found.');
    }
}

// --- Handle Item Drawing Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item_drawing'])) {
    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int) $_POST['item_index'];

    if (isset($_FILES['item_drawing']) && $_FILES['item_drawing']['error'] == 0) {
        $items = getOrderItems($orderId);

        if (isset($items[$itemIndex])) {
            // Delete old drawing if exists
            if (!empty($items[$itemIndex]['drawing_filename'])) {
                deleteFile(__DIR__ . '/uploads/drawings/' . $items[$itemIndex]['drawing_filename']);
            }

            // Upload new drawing
            $uploadResult = uploadFile(
                $_FILES['item_drawing'],
                __DIR__ . '/uploads/drawings/',
                $orderId . '_item' . $itemIndex
            );

            if ($uploadResult) {
                $items[$itemIndex]['drawing_filename'] = $uploadResult['filename'];
                $items[$itemIndex]['original_filename'] = $uploadResult['original'];

                if (updateOrderItems($orderId, $items)) {
                    $itemName = $items[$itemIndex]['Name'] ?? 'Unknown Item';
                    logChange($orderId, 'Item Update', "User '$currentUser' ($userRole) updated drawing for '$itemName' - File: {$uploadResult['original']}", $itemIndex);
                    setMessageAndRedirect('success', "Drawing updated successfully by $currentUser.");
                } else {
                    setMessageAndRedirect('error', 'Failed to update drawing in database.');
                }
            } else {
                setMessageAndRedirect('error', 'Failed to upload drawing.');
            }
        } else {
            setMessageAndRedirect('error', 'Item not found.');
        }
    } else {
        setMessageAndRedirect('error', 'No drawing file uploaded or upload error.');
    }
}

// --- Handle Individual Item Status Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item_status'])) {
    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int) $_POST['item_index'];
    $newItemStatus = sanitize_input($_POST['new_item_status']);

    global $STANDARD_STATUSES;

    if (!empty($orderId) && in_array($newItemStatus, $STANDARD_STATUSES)) {
        $items = getOrderItems($orderId);
        if (isset($items[$itemIndex])) {
            $oldStatus = $items[$itemIndex]['item_status'] ?? 'Pending';
            $itemName = $items[$itemIndex]['Name'] ?? 'Unknown Item';
            $items[$itemIndex]['item_status'] = $newItemStatus;
            
            if (updateOrderItems($orderId, $items)) {
                // Enhanced logging with user details
                $changeDescription = "User '$currentUser' ($userRole) changed ITEM STATUS for '$itemName' from '$oldStatus' to '$newItemStatus'";
                logChange($orderId, 'Item Status Update', $changeDescription, $itemIndex);
                
                // Additional activity log
                logActivity('Item Status Change', "Order #$orderId - Item '$itemName' status changed from '$oldStatus' to '$newItemStatus'");
                
                setMessageAndRedirect('success', "Item status updated to $newItemStatus by $currentUser.");
            } else {
                setMessageAndRedirect('error', 'Failed to update item status in database.');
            }
        } else {
            setMessageAndRedirect('error', 'Item not found.');
        }
    }
}

// --- STAGE 7: Handle Dispatch Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_dispatch_details'])) {
    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int) $_POST['item_index'];
    $lotIndex = (int) $_POST['lot_index'];

    $items = getOrderItems($orderId);

    if (isset($items[$itemIndex]['packaging_lots'][$lotIndex])) {
        $lot = &$items[$itemIndex]['packaging_lots'][$lotIndex];
        $lot['dispatch_status'] = 'Shipped';
        $lot['dispatch_date'] = sanitize_input($_POST['dispatch_date']);
        $lot['transport_mode'] = sanitize_input($_POST['transport_mode']);
        $lot['tracking_number'] = sanitize_input($_POST['tracking_number']);
        $lot['dispatch_remarks'] = sanitize_input($_POST['dispatch_remarks']);

        // Check if all lots for this item are shipped
        $all_item_lots_shipped = true;
        if (isset($items[$itemIndex]['packaging_lots'])) {
            foreach ($items[$itemIndex]['packaging_lots'] as $check_lot) {
                if (($check_lot['dispatch_status'] ?? '') !== 'Shipped') {
                    $all_item_lots_shipped = false;
                    break;
                }
            }
        }

        // If all lots for this item are shipped, update item status
        if ($all_item_lots_shipped) {
            $items[$itemIndex]['item_status'] = 'Shipped';
        }

        // Check if all lots in the entire order are shipped
        $all_lots_shipped = true;
        foreach ($items as $item) {
            if (isset($item['packaging_lots'])) {
                foreach ($item['packaging_lots'] as $check_lot) {
                    if (($check_lot['dispatch_status'] ?? '') !== 'Shipped') {
                        $all_lots_shipped = false;
                        break 2;
                    }
                }
            }
        }

        // If all lots shipped, update order status
        $orderStatus = $all_lots_shipped ? 'Shipped' : null;

        if (updateOrderItems($orderId, $items, $orderStatus)) {
            logChange($orderId, 'Dispatch', "User '$currentUser' ($userRole) dispatched via {$lot['transport_mode']} - Tracking: {$lot['tracking_number']}", $itemIndex);
            setMessageAndRedirect('success', "Dispatch details updated successfully by $currentUser. Client notified.");
        } else {
            setMessageAndRedirect('error', 'Failed to update dispatch details in database.');
        }
    } else {
        setMessageAndRedirect('error', 'Could not find the specified lot.');
    }
}

// --- STAGE 6: Handle Shipping Documentation Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_shipping_documents'])) {
    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int) $_POST['item_index'];
    $lotIndex = (int) $_POST['lot_index'];

    $items = getOrderItems($orderId);

    if (isset($items[$itemIndex]['packaging_lots'][$lotIndex])) {
        $uploadResult = uploadMultipleFiles(
            $_FILES['shipping_docs'],
            __DIR__ . '/uploads/shipping_docs/',
            $orderId . '_item' . $itemIndex . '_lot' . $lotIndex
        );

        if (!empty($uploadResult['files'])) {
            if (!isset($items[$itemIndex]['packaging_lots'][$lotIndex]['shipping_documents'])) {
                $items[$itemIndex]['packaging_lots'][$lotIndex]['shipping_documents'] = [];
            }
            if (!isset($items[$itemIndex]['packaging_lots'][$lotIndex]['shipping_original_filenames'])) {
                $items[$itemIndex]['packaging_lots'][$lotIndex]['shipping_original_filenames'] = [];
            }

            $items[$itemIndex]['packaging_lots'][$lotIndex]['shipping_documents'] = array_merge(
                $items[$itemIndex]['packaging_lots'][$lotIndex]['shipping_documents'],
                $uploadResult['files']
            );
            $items[$itemIndex]['packaging_lots'][$lotIndex]['shipping_original_filenames'] = array_merge(
                $items[$itemIndex]['packaging_lots'][$lotIndex]['shipping_original_filenames'],
                $uploadResult['originals']
            );

            // Update item status to Ready for Dispatch
            $items[$itemIndex]['item_status'] = 'Ready for Dispatch';

            // Update order status to Ready for Dispatch if this is the first item ready
            $orderStatus = null;
            $currentOrder = getOrderById($orderId);
            if ($currentOrder && $currentOrder['status'] !== 'Ready for Dispatch' && $currentOrder['status'] !== 'Shipped') {
                $orderStatus = 'Ready for Dispatch';
            }

            if (updateOrderItems($orderId, $items, $orderStatus)) {
                logChange($orderId, 'Shipping Documents', "User '$currentUser' ($userRole) uploaded shipping documents for Lot #" . ($lotIndex + 1), $itemIndex);
                setMessageAndRedirect('success', "Shipping documents uploaded by $currentUser. Logistics team notified.");
            } else {
                setMessageAndRedirect('error', 'Failed to update shipping documents in database.');
            }
        } else {
            setMessageAndRedirect('error', 'No documents were uploaded.');
        }
    } else {
        setMessageAndRedirect('error', 'Could not find the specified lot.');
    }
}

// --- STAGE 5: Handle Packaging Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_packaging_lot'])) {
    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int) $_POST['item_index'];

    // Verify fumigation is checked
    if (!isset($_POST['fumigation_completed']) || $_POST['fumigation_completed'] != '1') {
        setMessageAndRedirect('error', 'Fumigation must be completed before packaging. Please confirm fumigation completion.');
    }

    // Handle the "other" packaging type option
    $packagingType = sanitize_input($_POST['packaging_type']);
    if ($packagingType === 'other') {
        $packagingType = sanitize_input($_POST['packaging_type_other']);
    }

    $items = getOrderItems($orderId);

    // Handle multiple photo uploads
    $uploadResult = uploadMultipleFiles(
        $_FILES['product_photos'],
        __DIR__ . '/uploads/packaging_photos/',
        $orderId . '_' . $itemIndex . '_lot'
    );

    // Create the new lot data structure with fumigation data
    $newLot = [
        'photos' => $uploadResult['files'],
        'original_photo_names' => $uploadResult['originals'],
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
        'fumigation_agency' => sanitize_input($_POST['fumigation_agency']),
    ];

    // Initialize the packaging_lots array if it doesn't exist
    if (!isset($items[$itemIndex]['packaging_lots'])) {
        $items[$itemIndex]['packaging_lots'] = [];
    }
    $items[$itemIndex]['packaging_lots'][] = $newLot;

    // Update item status to Packaging
    $items[$itemIndex]['item_status'] = 'Packaging';

    // Update order status if needed
    $orderStatus = null;
    $currentOrder = getOrderById($orderId);
    if ($currentOrder && $currentOrder['status'] === 'QC Completed') {
        $orderStatus = 'Packaging';
    }

    if (updateOrderItems($orderId, $items, $orderStatus)) {
        $lotNumber = count($items[$itemIndex]['packaging_lots']);
        $fumigationInfo = "Fumigation cert: {$newLot['fumigation_certificate_number']}";
        logChange($orderId, 'Packaging', "User '$currentUser' ($userRole) added packaging lot #$lotNumber: {$newLot['products_in_lot']} products. $fumigationInfo", $itemIndex);
        setMessageAndRedirect('success', "Packaging lot #$lotNumber added with fumigation confirmation by $currentUser. Documentation team notified.");
    } else {
        setMessageAndRedirect('error', 'Failed to add packaging lot to database.');
    }
}

// --- STAGE 4: Handle Quality Inspection Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_inspection_report'])) {
    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int) $_POST['item_index'];

    $items = getOrderItems($orderId);

    // Initialize inspection_data array if it doesn't exist
    if (!isset($items[$itemIndex]['inspection_data'])) {
        $items[$itemIndex]['inspection_data'] = [];
    }

    // Handle inspection type
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

    // Handle file upload
    if (isset($_FILES['qc_document']) && $_FILES['qc_document']['error'] == 0) {
        $uploadResult = uploadFile(
            $_FILES['qc_document'],
            __DIR__ . '/uploads/inspection_reports/',
            $orderId . '_' . $itemIndex . '_qc'
        );

        if ($uploadResult) {
            $newInspection['documents'][] = $uploadResult['filename'];
            $newInspection['original_filenames'][] = $uploadResult['original'];
        }
    }

    // Add the new inspection to the array
    $items[$itemIndex]['inspection_data'][] = $newInspection;

    logChange($orderId, 'Quality Inspection', "User '$currentUser' ($userRole) submitted QC Result: {$newInspection['status']} - {$newInspection['type']}", $itemIndex);

    // Check inspection results
    $hasRework = false;
    $allPassed = true;
    foreach ($items[$itemIndex]['inspection_data'] as $inspection) {
        if ($inspection['status'] === 'Rework Required') {
            $hasRework = true;
            $allPassed = false;
        } elseif ($inspection['status'] !== 'QC Passed') {
            $allPassed = false;
        }
    }

    if ($hasRework) {
        $items[$itemIndex]['item_status'] = 'In Production';
        $orderStatus = 'In Production';
        $message = "Item marked for rework by $currentUser. Production team notified.";
    } elseif ($allPassed) {
        $items[$itemIndex]['item_status'] = 'QC Completed';

        // Check if all items have passed QC
        $all_items_passed = true;
        foreach ($items as $item) {
            if (empty($item['inspection_data'])) {
                $all_items_passed = false;
                break;
            }
            $hasPassedInspection = false;
            foreach ($item['inspection_data'] as $insp) {
                if ($insp['status'] === 'QC Passed') {
                    $hasPassedInspection = true;
                    break;
                }
            }
            if (!$hasPassedInspection) {
                $all_items_passed = false;
                break;
            }
        }

        $orderStatus = $all_items_passed ? 'QC Completed' : null;
        $message = $all_items_passed ? "All items passed QC by $currentUser. Order ready for packaging." : "Inspection completed successfully by $currentUser.";
    } else {
        $orderStatus = null;
        $message = "Inspection recorded with minor issues by $currentUser.";
    }

    if (updateOrderItems($orderId, $items, $orderStatus)) {
        setMessageAndRedirect('success', $message);
    } else {
        setMessageAndRedirect('error', 'Failed to save inspection report to database.');
    }
}

// --- STAGE 3: Handle Machining Process Forms ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_machining_process'])) {
    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int) $_POST['item_index'];

    // Handle the "other" process name option
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
    if (!isset($items[$itemIndex]['machining_processes'])) {
        $items[$itemIndex]['machining_processes'] = [];
    }
    $items[$itemIndex]['machining_processes'][] = $newProcess;

    // Update item status to In Production
    $items[$itemIndex]['item_status'] = 'In Production';
    $orderStatus = null;
    $currentOrder = getOrderById($orderId);
    if ($currentOrder && $currentOrder['status'] !== 'In Production') {
        $orderStatus = 'In Production';
    }

    usort($items[$itemIndex]['machining_processes'], function ($a, $b) {
        return $a['sequence'] <=> $b['sequence'];
    });

    if (updateOrderItems($orderId, $items, $orderStatus)) {
        logChange($orderId, 'Machining Process', "User '$currentUser' ($userRole) added process: {$newProcess['name']} (Seq: {$newProcess['sequence']})", $itemIndex);
        setMessageAndRedirect('success', "Machining process added to production schedule by $currentUser.");
    } else {
        setMessageAndRedirect('error', 'Failed to add machining process to database.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_machining_process'])) {
    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int) $_POST['item_index'];
    $processIndex = (int) $_POST['process_index'];

    $items = getOrderItems($orderId);
    $process = &$items[$itemIndex]['machining_processes'][$processIndex];

    // Update machining process
    $process['actual_completion'] = sanitize_input($_POST['actual_completion']);
    $process['status'] = sanitize_input($_POST['process_status']);
    $process['remarks'] = sanitize_input($_POST['remarks']);

    // Handle file upload
    if (isset($_FILES['process_document']) && $_FILES['process_document']['error'] === 0) {
        $uploadResult = uploadFile(
            $_FILES['process_document'],
            __DIR__ . '/uploads/machining_docs/',
            $orderId . '_' . $itemIndex . '_' . $processIndex
        );

        if ($uploadResult) {
            if (!isset($process['documents']) || !is_array($process['documents'])) {
                $process['documents'] = [];
            }
            if (!isset($process['original_filenames']) || !is_array($process['original_filenames'])) {
                $process['original_filenames'] = [];
            }
            $process['documents'][] = $uploadResult['filename'];
            $process['original_filenames'][] = $uploadResult['original'];
        }
    }

    logChange($orderId, 'Machining Process', "User '$currentUser' ($userRole) updated process status to: {$process['status']}", $itemIndex);

    $orderStatus = null;
    $message = "Machining process updated by $currentUser.";

    // If process marked completed, check status of item & order
    if ($process['status'] === 'Completed') {
        $all_processes_complete = true;
        foreach ($items[$itemIndex]['machining_processes'] as $p) {
            if ($p['status'] !== 'Completed') {
                $all_processes_complete = false;
                break;
            }
        }

        if ($all_processes_complete) {
            $items[$itemIndex]['item_status'] = 'Ready for QC';

            // Check if all items are ready for QC
            $all_items_ready_for_qc = true;
            foreach ($items as $item) {
                if (
                    !in_array($item['item_status'], [
                        'Ready for QC',
                        'QC Completed',
                        'Packaging',
                        'Ready for Dispatch',
                        'Shipped'
                    ])
                ) {
                    $all_items_ready_for_qc = false;
                    break;
                }
            }

            if ($all_items_ready_for_qc) {
                $orderStatus = 'Ready for QC';
            }

            $message = "All processes completed by $currentUser. Item ready for QC. Inspection team notified.";
        } else {
            $message = "Process marked as completed by $currentUser.";
        }
    }

    if (updateOrderItems($orderId, $items, $orderStatus)) {
        setMessageAndRedirect('success', $message);
    } else {
        setMessageAndRedirect('error', 'Failed to update machining process in database.');
    }
}

// --- STAGE 2: Handle Raw Material Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_raw_material'])) {
    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int) $_POST['item_index'];

    // Handle the "other" material type option
    $materialType = sanitize_input($_POST['raw_material_type']);
    if ($materialType === 'other') {
        $materialType = sanitize_input($_POST['raw_material_type_other']);
    }

    $newMaterial = [
        'type' => $materialType,
        'grade' => sanitize_input($_POST['raw_material_grade']),
        'dimensions' => sanitize_input($_POST['raw_material_dimensions']),
        'vendor' => sanitize_input($_POST['vendor_name']),
        'purchase_date' => sanitize_input($_POST['purchase_date']),
    ];

    $items = getOrderItems($orderId);

    if (!isset($items[$itemIndex]['raw_materials'])) {
        $items[$itemIndex]['raw_materials'] = [];
    }

    $items[$itemIndex]['raw_materials'][] = $newMaterial;
    $items[$itemIndex]['item_status'] = 'Sourcing Material';

    // Update order status if not already in advanced state
    $orderStatus = null;
    $currentOrder = getOrderById($orderId);
    $advancedStatuses = [
        'Sourcing Material',
        'In Production',
        'Ready for QC',
        'QC Completed',
        'Packaging',
        'Ready for Dispatch',
        'Shipped'
    ];
    if ($currentOrder && !in_array($currentOrder['status'], $advancedStatuses)) {
        $orderStatus = 'Sourcing Material';
    }

    if (updateOrderItems($orderId, $items, $orderStatus)) {
        logChange($orderId, 'Raw Materials', "User '$currentUser' ($userRole) added raw material: {$newMaterial['type']} - {$newMaterial['grade']}", $itemIndex);
        setMessageAndRedirect('success', "Raw material added by $currentUser. Production team notified.");
    } else {
        setMessageAndRedirect('error', 'Failed to add raw material to database.');
    }
}

// --- Handle Global Status Updates ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = sanitize_input($_POST['order_id']);
    $newStatus = sanitize_input($_POST['new_status']);

    global $STANDARD_STATUSES;

    if (!empty($orderId) && in_array($newStatus, $STANDARD_STATUSES)) {
        $currentOrder = getOrderById($orderId);
        $oldStatus = $currentOrder['status'] ?? 'Unknown';

        if (updateOrder($orderId, ['status' => $newStatus])) {
            // Enhanced logging with user details
            $changeDescription = "User '$currentUser' ($userRole) changed ORDER STATUS from '$oldStatus' to '$newStatus'";
            logChange($orderId, 'Order Status Update', $changeDescription);
            
            // Additional activity log
            logActivity('Order Status Change', "Order #$orderId status changed from '$oldStatus' to '$newStatus'");
            
            setMessageAndRedirect('success', "Order #$orderId status updated to $newStatus by $currentUser.");
        } else {
            setMessageAndRedirect('error', 'Failed to update order status in database.');
        }
    } else {
        setMessageAndRedirect('error', 'Invalid status update request.');
    }
}

// --- Get data for display ---
$orders = getOrders();
$customers = getCustomers();
$customerMap = array_column($customers, 'name', 'id');

// --- Prepare data for dashboard ---
// Ensure $STANDARD_STATUSES is properly defined and accessible
if (!isset($STANDARD_STATUSES) || !is_array($STANDARD_STATUSES)) {
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
}

// Initialize status counts
$statusCounts = array_fill_keys($STANDARD_STATUSES, 0);
$statusCounts['Total'] = count($orders);

foreach ($orders as $order) {
    $status = $order['status'] ?? 'Pending';
    if (isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    }
}

if (isset($_SESSION['order_created'])) {
    echo '<div id="toast-notification" class="toast toast-success">Order Created Successfully!</div>';
    unset($_SESSION['order_created']);
}

if (isset($_SESSION['message'])) {
    $message_type = $_SESSION['message']['type'];
    $message_class = $message_type === 'success' ? 'toast-success' : 'toast-error';
    echo "<div class='toast {$message_class}'>{$_SESSION['message']['text']}</div>";
    unset($_SESSION['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Pipeline - Alphasonix CRM</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
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
        
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.25rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            text-align:            center;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
        }
        
        .stat-card-total {
            border-left: 4px solid var(--bs-primary);
        }
        
        .stat-card-pending {
            border-left: 4px solid var(--bs-secondary);
        }
        
        .stat-card-sourcing-material {
            border-left: 4px solid var(--bs-warning);
        }
        
        .stat-card-in-production {
            border-left: 4px solid var(--bs-info);
        }
        
        .stat-card-ready-for-qc {
            border-left: 4px solid #6f42c1;
        }
        
        .stat-card-qc-completed {
            border-left: 4px solid #20c997;
        }
        
        .stat-card-packaging {
            border-left: 4px solid #fd7e14;
        }
        
        .stat-card-ready-for-dispatch {
            border-left: 4px solid #0dcaf0;
        }
        
        .stat-card-shipped {
            border-left: 4px solid var(--bs-success);
        }
        
        .stat-card .count {
            display: block;
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .stat-card .label {
            font-size: 0.875rem;
            color: var(--bs-secondary);
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
                </div>
            </div>

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
                        // Deadline calculation logic
                        $deadline_class = '';
                        $days_remaining_text = '';
                        if (!empty($order['delivery_date']) && $order['status'] !== 'Shipped') {
                            $delivery_date = new DateTime($order['delivery_date']);
                            $today = new DateTime();
                            $interval = $today->diff($delivery_date);
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
                                            <span class="order-info-value"><?= htmlspecialchars($order['po_date']) ?></span>
                                        </div>
                                        <?php if (!empty($order['delivery_date'])): ?>
                                            <div class="order-info-item">
                                                <span class="order-info-label">Delivery:</span>
                                                <span class="order-info-value"><?= htmlspecialchars($order['delivery_date']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?= $days_remaining_text ?>

                                    <!-- Buttons -->
                                    <div class="no-print mt-3 d-flex flex-column gap-2">
                                        <a href="print_order.php?order_id=<?= htmlspecialchars($order['order_id']) ?>" target="_blank" class="btn btn-primary text-center">
                                            <i class="bi bi-printer"></i> Print Order
                                        </a>
                                        <!-- <a href="invoice.php?order_id=<?= htmlspecialchars($order['order_id']) ?>" class="btn btn-secondary text-center">
                                            <i class="bi bi-receipt"></i> Print Invoice
                                        </a> -->
                                        <a href="order-stages-view.php?order_id=<?= htmlspecialchars($order['order_id']) ?>"
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
                                    if (is_array($items))
                                        foreach ($items as $itemIndex => $item):
                                            ?>
                                            <div class="item-card">
                                                <!-- Item Header (Collapsible) -->
                                                <div class="item-header">
                                                    <h4 class="item-title"><span
                                                            class="toggle-icon"><i class="bi bi-caret-right-fill"></i></span><?= htmlspecialchars($item['Name'] ?? 'N/A') ?></h4>
                                                    <div class="d-flex gap-2 align-items-center">
                                                        <div class="item-quantity">Qty: <?= htmlspecialchars($item['quantity']) ?></div>
                                                        <form action="pipeline.php" method="post" class="m-0"
                                                            onsubmit="return confirm('Delete this item from the order?');" class="no-print">
                                                            <input type="hidden" name="order_id"
                                                                value="<?= htmlspecialchars($order['order_id']) ?>">
                                                            <input type="hidden" name="item_index" value="<?= $itemIndex ?>">
                                                            <button type="submit" name="delete_item" class="btn btn-danger btn-small no-print"
                                                                title="Delete this item"><i class="bi bi-trash"></i></button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <!-- Item Body (Collapsible) -->
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

                                                    <!-- Enhanced Item Drawing Section -->
                                                    <div class="mb-3">
                                                        <?php if (!empty($item['drawing_filename'])): ?>
                                                            <div class="drawing-preview">
                                                                <h5 class="mt-0"><i class="bi bi-file-earmark"></i> Product Drawing</h5>

                                                                <!-- File information display -->
                                                                <div class="file-info-display">
                                                                    <div class="d-flex align-items-center gap-2">
                                                                        <?php
                                                                        $fileExt = strtolower(pathinfo($item['drawing_filename'], PATHINFO_EXTENSION));
                                                                        $fileIcon = 'bi-file-earmark-text';
                                                                        if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif'])) {
                                                                            $fileIcon = 'bi-file-image';
                                                                        } elseif ($fileExt === 'pdf') {
                                                                            $fileIcon = 'bi-file-earmark-pdf';
                                                                        } elseif ($fileExt === 'dwg') {
                                                                            $fileIcon = 'bi-pencil-square';
                                                                        }
                                                                        ?>
                                                                        <i class="bi <?= $fileIcon ?>" style="font-size: 1.5rem;"></i>
                                                                        <div class="flex-grow-1">
                                                                            <div class="fw-semibold text-primary">
                                                                                <?= htmlspecialchars($item['original_filename'] ?? $item['drawing_filename']) ?>
                                                                            </div>
                                                                            <div class="small text-muted">
                                                                                Order #<?= htmlspecialchars($order['order_id']) ?> -
                                                                                <?php if (file_exists(__DIR__ . '/uploads/drawings/' . $item['drawing_filename'])): ?>
                                                                                    <?= date('M j, Y', filemtime(__DIR__ . '/uploads/drawings/' . $item['drawing_filename'])) ?>
                                                                                <?php else: ?>
                                                                                    File not found
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <!-- File preview for images -->
                                                                <?php if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif']) && file_exists(__DIR__ . '/uploads/drawings/' . $item['drawing_filename'])): ?>
                                                                    <img src="uploads/drawings/<?= htmlspecialchars($item['drawing_filename']) ?>"
                                                                        alt="Drawing Preview"
                                                                        class="preview-drawing mt-2 img-thumbnail cursor-pointer"
                                                                        style="max-width: 200px; max-height: 150px;"
                                                                        data-src="uploads/drawings/<?= htmlspecialchars($item['drawing_filename']) ?>"
                                                                        onclick="showDrawingModal(this)">
                                                                <?php endif; ?>

                                                                <div class="mt-2 d-flex gap-2 no-print">
                                                                    <!-- View Button (for supported formats) -->
                                                                    <?php if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'pdf'])): ?>
                                                                        <a href="uploads/drawings/<?= htmlspecialchars($item['drawing_filename']) ?>"
                                                                            target="_blank" class="btn btn-small btn-primary">
                                                                            <i class="bi bi-eye"></i> View
                                                                        </a>
                                                                    <?php endif; ?>

                                                                    <!-- Replace Button -->
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

                                                        <!-- Enhanced Drawing Upload Form -->
                                                        <div id="drawing-form-<?= $order['order_id'] ?>-<?= $itemIndex ?>"
                                                            class="drawing-upload-form no-print" style="display: none;">
                                                            <form action="pipeline.php" method="post" enctype="multipart/form-data">
                                                                <input type="hidden" name="order_id"
                                                                    value="<?= htmlspecialchars($order['order_id']) ?>">
                                                                <input type="hidden" name="item_index" value="<?= $itemIndex ?>">
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-semibold">Select New Drawing File:</label>
                                                                    <input type="file" name="item_drawing" accept=".jpg,.jpeg,.png,.pdf,.dwg"
                                                                        required class="form-control" onchange="previewUploadFile(this)">
                                                                    <div class="form-text">
                                                                        Supported formats: JPG, PNG, PDF, DWG (Max 10MB)
                                                                    </div>
                                                                </div>

                                                                <!-- File preview for upload -->
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
                                                        <span
                                                            class="item-status-badge item-status-<?= strtolower(str_replace(' ', '-', $item['item_status'] ?? 'pending')) ?>">
                                                            <?= htmlspecialchars($item['item_status'] ?? 'Pending') ?>
                                                        </span>
                                                        <form action="pipeline.php" method="post"
                                                            class="d-inline-flex gap-2 align-items-center ms-3">
                                                            <input type="hidden" name="order_id"
                                                                value="<?= htmlspecialchars($order['order_id']) ?>">
                                                            <input type="hidden" name="item_index" value="<?= $itemIndex ?>">
                                                            <select name="new_item_status" class="form-select" style="min-width: 180px;">
                                                                <?php foreach ($STANDARD_STATUSES as $status): ?>
                                                                    <option value="<?= $status ?>" <?= ($item['item_status'] ?? 'Pending') == $status ? 'selected' : '' ?>><?= $status ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <button type="submit" name="update_item_status"
                                                                class="btn btn-primary btn-small">Update</button>
                                                        </form>
                                                        <button type="button" class="btn btn-reset btn-small ms-2" onclick="resetAllItemForms(this)"
                                                            title="Reset ALL forms for this item"><i class="bi bi-arrow-repeat"></i> Reset Forms</button>
                                                    </div>

                                                    <!-- Stage 2: Raw Materials -->
                                                    <?php if (!empty($item['raw_materials']) || (($order['status'] ?? '') === 'Sourcing Material' || ($item['item_status'] ?? '') === 'Sourcing Material' || ($item['item_status'] ?? '') === 'Pending')): ?>
                                                        <div class="stage-section">
                                                            <div class="stage-header">
                                                                <span><i class="bi bi-tools"></i> Stage 2: Raw Materials Sourcing</span>
                                                                <small>Person in charge: Procurement Head</small>
                                                            </div>
                                                            <div class="stage-content">
                                                                                                                                <?php if (!empty($item['raw_materials'])): ?>
                                                                    <table class="data-table">
                                                                        <thead>
                                                                            <tr>
                                                                                <th>Type</th>
                                                                                <th>Grade</th>
                                                                                <th>Dimensions</th>
                                                                                <th>Vendor</th>
                                                                                <th>Date</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php foreach ($item['raw_materials'] as $mat): ?>
                                                                                <tr>
                                                                                    <td><?= htmlspecialchars($mat['type']) ?></td>
                                                                                    <td><?= htmlspecialchars($mat['grade']) ?></td>
                                                                                    <td><?= htmlspecialchars($mat['dimensions']) ?></td>
                                                                                    <td><?= htmlspecialchars($mat['vendor']) ?></td>
                                                                                    <td><?= htmlspecialchars($mat['purchase_date']) ?></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        </tbody>
                                                                    </table>
                                                                <?php endif; ?>

                                                                <?php if (($order['status'] ?? '') === 'Sourcing Material' || ($order['status'] ?? '') === 'Pending' || ($item['item_status'] ?? '') === 'Sourcing Material' || ($item['item_status'] ?? '') === 'Pending'): ?>
                                                                    <form action="pipeline.php" method="post" class="stage-form no-print"
                                                                        id="raw-material-form-<?= $order['order_id'] ?>-<?= $itemIndex ?>">
                                                                        <input type="hidden" name="order_id"
                                                                            value="<?= htmlspecialchars($order['order_id']) ?>">
                                                                        <input type="hidden" name="item_index" value="<?= $itemIndex ?>">
                                                                        <input type="hidden" name="submission_timestamp" value="<?= date('Y-m-d H:i:s') ?>">
                                                                        <div class="form-grid">
                                                                            <div class="form-group">
                                                                                <select name="raw_material_type" class="form-select"
                                                                                    onchange="toggleOtherField(this, 'raw_material_type')" required>
                                                                                    <option value="">-- Select Material Type --</option>
                                                                                    <option value="Plate">Plate</option>
                                                                                    <option value="Bar">Bar</option>
                                                                                    <option value="Pipe">Pipe</option>
                                                                                    <option value="Ground Bar">Ground Bar</option>
                                                                                    <option value="Flat Bar">Flat Bar</option>
                                                                                    <option value="other">Other (specify)</option>
                                                                                </select>
                                                                                <input type="text" name="raw_material_type_other" placeholder="Specify Material Type" class="form-control mt-1"
                                                                                    style="display: none;">
                                                                            </div>
                                                                            <input type="text" name="raw_material_grade" placeholder="Material Grade"
                                                                                class="form-control" required>
                                                                            <input type="text" name="raw_material_dimensions"
                                                                                placeholder="Dimensions (L×W×T, diameter, etc.)" class="form-control"
                                                                                required>
                                                                            <input type="text" name="vendor_name" placeholder="Vendor Name"
                                                                                class="form-control" required>
                                                                            <input type="date" name="purchase_date" title="Purchase Date" class="form-control"
                                                                                required>
                                                                        </div>
                                                                        <div class="d-flex gap-2 justify-content-end">
                                                                            <button type="button" class="btn btn-reset btn-small"
                                                                                onclick="resetForm('raw-material-form-<?= $order['order_id'] ?>-<?= $itemIndex ?>')"
                                                                                title="Clear this form"><i class="bi bi-arrow-repeat"></i> Clear Form</button>
                                                                            <button type="submit" name="add_raw_material" class="btn btn-success"><i class="bi bi-plus-circle"></i> Add
                                                                                Material & Notify Production</button>
                                                                        </div>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Stage 3: Machining Processes -->
                                                    <?php if (!empty($item['machining_processes']) || (($order['status'] ?? '') === 'In Production' || ($item['item_status'] ?? '') === 'In Production' || ($item['item_status'] ?? '') === 'Sourcing Material')): ?>
                                                        <div class="stage-section">
                                                            <div class="stage-header">
                                                                <span><i class="bi bi-gear"></i> Stage 3: Machining Processes</span>
                                                                <small>Person in charge: Production Manager</small>
                                                            </div>
                                                            <div class="stage-content">
                                                                <?php if (!empty($item['machining_processes'])): ?>
                                                                    <?php foreach ($item['machining_processes'] as $processIndex => $process): ?>
                                                                        <div class="process-entry">
                                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                <h6 class="m-0 text-primary">
                                                                                    Seq #<?= htmlspecialchars($process['sequence']) ?>:
                                                                                    <?= htmlspecialchars($process['name']) ?>
                                                                                    <span class="process-status-badge ms-2"
                                                                                        style="padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.8rem; font-weight: bold; background: #e2e8f0;">
                                                                                        <?= htmlspecialchars($process['status']) ?>
                                                                                    </span>
                                                                                </h6>
                                                                            </div>

                                                                            <div class="process-details small mb-2">
                                                                                <div><strong>Vendor:</strong> <?= htmlspecialchars($process['vendor']) ?></div>
                                                                                <div><strong>Start Date:</strong>
                                                                                    <?= htmlspecialchars($process['start_date']) ?></div>
                                                                                <div><strong>Expected Completion:</strong>
                                                                                    <?= htmlspecialchars($process['expected_completion']) ?></div>
                                                                                <?php if (!empty($process['actual_completion'])): ?>
                                                                                    <div><strong>Actual Completion:</strong>
                                                                                        <?= htmlspecialchars($process['actual_completion']) ?></div>
                                                                                <?php endif; ?>
                                                                                <?php if (!empty($process['remarks'])): ?>
                                                                                    <div><strong>Remarks:</strong> <?= htmlspecialchars($process['remarks']) ?>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                            </div>

                                                                            <!-- Process Documents Display -->
                                                                            <?php if (!empty($process['documents'])): ?>
                                                                                <div class="process-documents mt-2">
                                                                                    <strong>Documents:</strong>
                                                                                    <div class="d-flex flex-wrap gap-2 mt-1">
                                                                                        <?php foreach ($process['documents'] as $docIndex => $doc): ?>
                                                                                            <div class="document-item">
                                                                                                <i class="bi bi-file-earmark"></i>
                                                                                                <span class="small"><?= htmlspecialchars($process['original_filenames'][$docIndex] ?? $doc) ?></span>
                                                                                                <a href="uploads/machining_docs/<?= htmlspecialchars($doc) ?>" download class="btn btn-small btn-outline-primary">
                                                                                                    <i class="bi bi-download"></i>
                                                                                                </a>
                                                                                            </div>
                                                                                        <?php endforeach; ?>
                                                                                    </div>
                                                                                </div>
                                                                            <?php endif; ?>

                                                                            <!-- Process Update Form -->
                                                                            <?php if ($process['status'] !== 'Completed'): ?>
                                                                                <form action="pipeline.php" method="post" enctype="multipart/form-data"
                                                                                    class="stage-form no-print mt-3">
                                                                                    <input type="hidden" name="order_id"
                                                                                        value="<?= htmlspecialchars($order['order_id']) ?>">
                                                                                    <input type="hidden" name="item_index" value="<?= $itemIndex ?>">
                                                                                    <input type="hidden" name="process_index" value="<?= $processIndex ?>">
                                                                                    <div class="form-grid">
                                                                                        <input type="date" name="actual_completion"
                                                                                            placeholder="Actual Completion Date" class="form-control">
                                                                                        <select name="process_status" class="form-select" required>
                                                                                            <option value="Not Started" <?= $process['status'] == 'Not Started' ? 'selected' : '' ?>>Not Started</option>
                                                                                            <option value="In Progress" <?= $process['status'] == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                                                                            <option value="Completed" <?= $process['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                                                                            <option value="On Hold" <?= $process['status'] == 'On Hold' ? 'selected' : '' ?>>On Hold</option>
                                                                                        </select>
                                                                                        <input type="file" name="process_document"
                                                                                            accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" class="form-control"
                                                                                            title="Upload process document">
                                                                                        <textarea name="remarks" placeholder="Process remarks/notes"
                                                                                            class="form-control" rows="3"><?= htmlspecialchars($process['remarks']) ?></textarea>
                                                                                    </div>
                                                                                    <div class="text-end mt-2">
                                                                                        <button type="submit" name="update_machining_process"
                                                                                            class="btn btn-success">Update Process</button>
                                                                                    </div>
                                                                                </form>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                <?php endif; ?>

                                                                <!-- Add New Process Form -->
                                                                <?php if (($order['status'] ?? '') === 'In Production' || ($order['status'] ?? '') === 'Sourcing Material' || ($item['item_status'] ?? '') === 'In Production' || ($item['item_status'] ?? '') === 'Sourcing Material'): ?>
                                                                    <form action="pipeline.php" method="post" class="stage-form no-print">
                                                                        <input type="hidden" name="order_id"
                                                                            value="<?= htmlspecialchars($order['order_id']) ?>">
                                                                        <input type="hidden" name="item_index" value="<?= $itemIndex ?>">
                                                                        <div class="form-grid">
                                                                            <div class="form-group">
                                                                                <select name="process_name" class="form-select"
                                                                                    onchange="toggleOtherField(this, 'process_name')" required>
                                                                                    <option value="">-- Select Process --</option>
                                                                                    <option value="Cutting">Cutting</option>
                                                                                    <option value="Turning">Turning</option>
                                                                                    <option value="Milling">Milling</option>
                                                                                    <option value="Drilling">Drilling</option>
                                                                                    <option value="Grinding">Grinding</option>
                                                                                    <option value="Welding">Welding</option>
                                                                                    <option value="Heat Treatment">Heat Treatment</option>
                                                                                    <option value="Surface Treatment">Surface Treatment</option>
                                                                                    <option value="other">Other (specify)</option>
                                                                                </select>
                                                                                <input type="text" name="process_name_other" placeholder="Specify Process" class="form-control mt-1"
                                                                                    style="display: none;">
                                                                            </div>
                                                                            <input type="number" name="sequence_number" placeholder="Sequence #"
                                                                                class="form-control" min="1" required>
                                                                            <input type="text" name="vendor_name" placeholder="Vendor/Department"
                                                                                class="form-control" required>
                                                                            <input type="date" name="start_date" title="Start Date" class="form-control"
                                                                                required>
                                                                            <input type="date" name="expected_completion" title="Expected Completion"
                                                                                class="form-control" required>
                                                                        </div>
                                                                        <div class="text-end">
                                                                            <button type="submit" name="add_machining_process" class="btn btn-success"><i class="bi bi-plus-circle"></i> Add
                                                                                Process to Schedule</button>
                                                                        </div>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Stage 4: Quality Inspection -->
                                                    <?php if (!empty($item['inspection_data']) || (($order['status'] ?? '') === 'Ready for QC' || ($item['item_status'] ?? '') === 'Ready for QC')): ?>
                                                        <div class="stage-section">
                                                            <div class="stage-header">
                                                                <span><i class="bi bi-search"></i> Stage 4: Quality Inspection</span>
                                                                <small>Person in charge: QC Manager</small>
                                                            </div>
                                                            <div class="stage-content">
                                                                <?php if (!empty($item['inspection_data']) && is_array($item['inspection_data'])): ?>
                                                                    <?php
                                                                    // Sort inspections by date (newest first)
                                                                    $inspections = $item['inspection_data'];
                                                                    usort($inspections, function ($a, $b) {
                                                                        return strtotime($b['inspection_date']) - strtotime($a['inspection_date']);
                                                                    });
                                                                    ?>

                                                                    <?php foreach ($inspections as $inspIndex => $inspection): ?>
                                                                        <div class="inspection-entry"
                                                                            style="background: <?= ($inspection['status'] == 'QC Passed') ? '#d4edda' : (($inspection['status'] == 'Rework Required') ? '#f8d7da' : '#fff3cd') ?>; padding: 15px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid <?= ($inspection['status'] == 'QC Passed') ? '#28a745' : (($inspection['status'] == 'Rework Required') ? '#dc3545' : '#ffc107') ?>;">
                                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                <h6 class="m-0 text-primary">
                                                                                    Inspection #<?= (count($inspections) - $inspIndex) ?>:
                                                                                    <?= htmlspecialchars($inspection['type']) ?>
                                                                                    <span class="inspection-status-badge ms-2"
                                                                                        style="background: <?= ($inspection['status'] == 'QC Passed') ? '#28a745' : (($inspection['status'] == 'Rework Required') ? '#dc3545' : '#ffc107') ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 0.75rem; font-size: 0.9rem;">
                                                                                        <?= htmlspecialchars($inspection['status']) ?>
                                                                                    </span>
                                                                                </h6>
                                                                                <small class="text-muted">
                                                                                    <?= date('M j, Y', strtotime($inspection['inspection_date'])) ?>
                                                                                </small>
                                                                            </div>

                                                                            <div class="inspection-details small mb-2">
                                                                                <div class="d-grid gap-2" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                                                                                    <div><strong>Technician:</strong>
                                                                                        <?= htmlspecialchars($inspection['technician_name']) ?></div>
                                                                                    <div><strong>Inspection Date:</strong>
                                                                                        <?= htmlspecialchars($inspection['inspection_date']) ?></div>
                                                                                    <div><strong>Inspection ID:</strong>
                                                                                        <?= htmlspecialchars($inspection['inspection_id'] ?? 'N/A') ?></div>
                                                                                </div>
                                                                                <?php if (!empty($inspection['remarks'])): ?>
                                                                                    <div class="mt-2"><strong>Remarks:</strong>
                                                                                        <?= htmlspecialchars($inspection['remarks']) ?></div>
                                                                                <?php endif; ?>
                                                                            </div>

                                                                            <!-- Inspection Documents Display -->
                                                                            <?php if (!empty($inspection['documents'])): ?>
                                                                                <div class="inspection-documents mt-2">
                                                                                    <strong>QC Reports/Documents:</strong>
                                                                                    <div class="d-flex flex-wrap gap-2 mt-1">
                                                                                        <?php foreach ($inspection['documents'] as $docIndex => $doc): ?>
                                                                                            <div class="document-item">
                                                                                                <i class="bi bi-file-earmark"></i>
                                                                                                <span class="small"><?= htmlspecialchars($inspection['original_filenames'][$docIndex] ?? $doc) ?></span>
                                                                                                <a href="uploads/inspection_reports/<?= htmlspecialchars($doc) ?>" download class="btn btn-small btn-outline-primary">
                                                                                                    <i class="bi bi-download"></i>
                                                                                                </a>
                                                                                            </div>
                                                                                        <?php endforeach; ?>
                                                                                    </div>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    <?php endforeach; ?>

                                                                    <!-- Summary of inspections -->
                                                                    <div class="bg-info bg-opacity-10 p-3 rounded border border-info mb-3">
                                                                        <h6 class="m-0 mb-2 text-primary"><i class="bi bi-graph-up"></i> Inspection Summary</h6>
                                                                        <div class="small">
                                                                            <?php
                                                                            $passCount = 0;
                                                                            $reworkCount = 0;
                                                                            $minorCount = 0;
                                                                            foreach ($item['inspection_data'] as $insp) {
                                                                                if ($insp['status'] === 'QC Passed')
                                                                                    $passCount++;
                                                                                elseif ($insp['status'] === 'Rework Required')
                                                                                    $reworkCount++;
                                                                                else
                                                                                    $minorCount++;
                                                                            }
                                                                            ?>
                                                                            <div class="d-flex gap-3">
                                                                                <div><span class="text-success"><i class="bi bi-check-circle"></i> Passed:</span> <?= $passCount ?></div>
                                                                                <div><span class="text-danger"><i class="bi bi-arrow-repeat"></i> Rework:</span> <?= $reworkCount ?>
                                                                                </div>
                                                                                <div><span class="text-warning"><i class="bi bi-exclamation-triangle"></i> Minor Issues:</span> <?= $minorCount ?>
                                                                                </div>
                                                                            </div>
                                                                            <div class="mt-2">
                                                                                <strong>Total Inspections:</strong> <?= count($item['inspection_data']) ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <!-- QC Form for new inspection -->
                                                                <?php
                                                                // Show form if item is ready for QC or if there was a rework required
                                                                $showForm = ($item['item_status'] ?? '') === 'Ready for QC';
                                                                if (!empty($item['inspection_data'])) {
                                                                    foreach ($item['inspection_data'] as $insp) {
                                                                        if ($insp['status'] === 'Rework Required') {
                                                                            $showForm = true;
                                                                            break;
                                                                        }
                                                                    }
                                                                }
                                                                ?>

                                                                <?php if ($showForm): ?>
                                                                    <div class="bg-light p-3 rounded border border-dashed">
                                                                        <h6 class="m-0 mb-3 text-primary"><i class="bi bi-plus-circle"></i> Add New Inspection</h6>
                                                                        <form action="pipeline.php" method="post" enctype="multipart/form-data"
                                                                            class="stage-form no-print">
                                                                            <input type="hidden" name="order_id"
                                                                                value="<?= htmlspecialchars($order['order_id']) ?>">
                                                                            <input type="hidden" name="item_index" value="<?= $itemIndex ?>">
                                                                            <div class="form-grid">
                                                                                <select name="inspection_status" class="form-select" required>
                                                                                    <option value="">-- Inspection Result --</option>
                                                                                    <option value="QC Passed">QC Passed</option>
                                                                                    <option value="Rework Required">Rework Required</option>
                                                                                    <option value="Minor Issues">Minor Issues</option>
                                                                                </select>
                                                                                <div class="form-group">
                                                                                    <select name="inspection_type" class="form-select"
                                                                                        onchange="toggleOtherField(this, 'inspection_type')" required>
                                                                                        <option value="">-- Inspection Type --</option>
                                                                                        <option value="Dimensional Check">Dimensional Check</option>
                                                                                        <option value="Visual Inspection">Visual Inspection</option>
                                                                                        <option value="Material Test">Material Test</option>
                                                                                        <option value="Functional Test">Functional Test</option>
                                                                                        <option value="Surface Quality">Surface Quality</option>
                                                                                        <option value="Final Inspection">Final Inspection</option>
                                                                                        <option value="Re-inspection">Re-inspection</option>
                                                                                        <option value="other">Other (specify)</option>
                                                                                    </select>
                                                                                    <input type="text" name="inspection_type_other" placeholder="Specify Inspection Type" class="form-control mt-1"
                                                                                        style="display: none;">
                                                                                </div>
                                                                                <input type="text" name="technician_name" placeholder="QC Technician Name"
                                                                                    class="form-control" required>
                                                                                <input type="date" name="inspection_date" title="Inspection Date"
                                                                                    class="form-control" required value="<?= date('Y-m-d') ?>">
                                                                                <input type="file" name="qc_document"
                                                                                    accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" class="form-control"
                                                                                    title="Upload QC Report/Document">
                                                                                <textarea name="remarks" placeholder="QC Remarks/Notes"
                                                                                    class="form-control" rows="3" style="grid-column: 1 / -1;"></textarea>
                                                                            </div>
                                                                            <div class="text-end mt-3">
                                                                                <button type="submit" name="submit_inspection_report"
                                                                                    class="btn btn-success"><i class="bi bi-clipboard-check"></i> Submit Inspection Report</button>
                                                                            </div>
                                                                        </form>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Stage 5: Packaging -->
                                                    <?php if (!empty($item['packaging_lots']) || (($order['status'] ?? '') === 'QC Completed' || ($item['item_status'] ?? '') === 'QC Completed' || ($item['item_status'] ?? '') === 'Packaging')): ?>
                                                        <div class="stage-section">
                                                            <div class="stage-header">
                                                                <span><i class="bi bi-box-seam"></i> Stage 5: Packaging</span>
                                                                <small>Person in charge: Packaging Team</small>
                                                            </div>
                                                            <div class="stage-content">
                                                                <?php if (!empty($item['packaging_lots'])): ?>
                                                                    <?php foreach ($item['packaging_lots'] as $lotIndex => $lot): ?>
                                                                        <div class="packaging-lot">
                                                                            <h6 class="m-0 mb-2 text-primary"><i class="bi bi-box"></i> Lot #<?= ($lotIndex + 1) ?> -
                                                                                <?= htmlspecialchars($lot['products_in_lot']) ?> products
                                                                            </h6>

                                                                            <div class="d-grid gap-2 small" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                                                                                <div><strong>Packaging Type:</strong>
                                                                                    <?= htmlspecialchars($lot['packaging_type']) ?></div>
                                                                                <div><strong>Date:</strong> <?= htmlspecialchars($lot['packaging_date']) ?>
                                                                                </div>
                                                                                <div><strong>Packages:</strong> <?= htmlspecialchars($lot['num_packages']) ?>
                                                                                </div>
                                                                                <div><strong>Net Weight:</strong> <?= htmlspecialchars($lot['net_weight']) ?> kg
                                                                                </div>
                                                                                <div><strong>Gross Weight:</strong>
                                                                                    <?= htmlspecialchars($lot['gross_weight']) ?> kg</div>
                                                                                <div><strong>Docs Included:</strong>
                                                                                    <?= htmlspecialchars($lot['docs_included']) ?></div>
                                                                            </div>

                                                                            <!-- Fumigation Information Display -->
                                                                            <?php if (!empty($lot['fumigation_completed']) && $lot['fumigation_completed'] === 'Yes'): ?>
                                                                                <div class="mt-2 p-2 bg-success bg-opacity-10 rounded border border-success">
                                                                                    <strong><i class="bi bi-check-circle text-success"></i> Fumigation Details:</strong>
                                                                                    <div class="mt-1 small">
                                                                                        Certificate
                                                                                        #<?= htmlspecialchars($lot['fumigation_certificate_number'] ?? 'N/A') ?> |
                                                                                        Date: <?= htmlspecialchars($lot['fumigation_date'] ?? 'N/A') ?> |
                                                                                        Agency: <?= htmlspecialchars($lot['fumigation_agency'] ?? 'N/A') ?>
                                                                                    </div>
                                                                                </div>
                                                                            <?php endif; ?>

                                                                            <!-- Packaging Photos Display -->
                                                                            <?php if (!empty($lot['photos'])): ?>
                                                                                <div class="mt-2">
                                                                                    <strong>Product Photos:</strong>
                                                                                    <div class="d-flex flex-wrap gap-2 mt-1">
                                                                                        <?php foreach ($lot['photos'] as $photoIndex => $photo): ?>
                                                                                            <div class="position-relative">
                                                                                                <img src="uploads/packaging_photos/<?= htmlspecialchars($photo) ?>"
                                                                                                    alt="Product Photo"
                                                                                                    class="img-thumbnail object-fit-cover rounded cursor-pointer"
                                                                                                    style="width: 100px; height: 100px; object-fit: cover;"
                                                                                                    onclick="showDrawingModal(this)"
                                                                                                    data-src="uploads/packaging_photos/<?= htmlspecialchars($photo) ?>">
                                                                                                <a href="uploads/packaging_photos/<?= htmlspecialchars($photo) ?>" download class="btn btn-small btn-outline-primary position-absolute top-0 end-0">
                                                                                                    <i class="bi bi-download"></i>
                                                                                                </a>
                                                                                            </div>
                                                                                        <?php endforeach; ?>
                                                                                    </div>
                                                                                </div>
                                                                            <?php endif; ?>

                                                                            <!-- Shipping Documents Display -->
                                                                            <?php if (!empty($lot['shipping_documents'])): ?>
                                                                                <div class="mt-2">
                                                                                    <strong>Shipping Documents:</strong>
                                                                                    <div class="d-flex flex-wrap gap-2 mt-1">
                                                                                        <?php foreach ($lot['shipping_documents'] as $docIndex => $doc): ?>
                                                                                            <div class="document-item">
                                                                                                <i class="bi bi-file-earmark"></i>
                                                                                                <span class="small"><?= htmlspecialchars($lot['shipping_original_filenames'][$docIndex] ?? $doc) ?></span>
                                                                                                <a href="uploads/shipping_docs/<?= htmlspecialchars($doc) ?>" download class="btn btn-small btn-outline-primary">
                                                                                                    <i class="bi bi-download"></i>
                                                                                                </a>
                                                                                            </div>
                                                                                        <?php endforeach; ?>
                                                                                    </div>
                                                                                </div>
                                                                            <?php endif; ?>

                                                                            <!-- Dispatch Status -->
                                                                            <?php if (!empty($lot['dispatch_status'])): ?>
                                                                                <div class="dispatch-details mt-3">
                                                                                    <h6 class="m-0 mb-2 text-success"><i class="bi bi-truck"></i> Dispatch Details</h6>
                                                                                    <div class="small">
                                                                                        <div><strong>Status:</strong> <?= htmlspecialchars($lot['dispatch_status']) ?></div>
                                                                                        <div><strong>Dispatch Date:</strong> <?= htmlspecialchars($lot['dispatch_date'] ?? 'N/A') ?></div>
                                                                                        <div><strong>Transport Mode:</strong> <?= htmlspecialchars($lot['transport_mode'] ?? 'N/A') ?></div>
                                                                                        <div><strong>Tracking Number:</strong> <?= htmlspecialchars($lot['tracking_number'] ?? 'N/A') ?></div>
                                                                                        <?php if (!empty($lot['dispatch_remarks'])): ?>
                                                                                            <div><strong>Remarks:</strong> <?= htmlspecialchars($lot['dispatch_remarks']) ?></div>
                                                                                        <?php endif; ?>
                                                                                    </div>
                                                                                </div>
                                                                            <?php endif; ?>

                                                                            <!-- Shipping Documents Form -->
                                                                            <?php if (empty($lot['shipping_documents']) && ($lot['dispatch_status'] ?? '') !== 'Shipped'): ?>
                                                                                <form action="pipeline.php" method="post" enctype="multipart/form-data"
                                                                                    class="stage-form no-print mt-3">
                                                                                    <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
                                                                                    <input type="hidden" name="item_index" value="<?= $itemIndex ?>">
                                                                                    <input type="hidden" name="lot_index" value="<?= $lotIndex ?>">
                                                                                    <div class="form-grid">
                                                                                        <div style="grid-column: 1 / -1;">
                                                                                            <label class="form-label">Shipping Documents (Packing List, Commercial Invoice, etc.):</label>
                                                                                            <input type="file" name="shipping_docs[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx" class="form-control">
                                                                                            <div class="form-text">Multiple files can be selected</div>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="text-end mt-3">
                                                                                        <button type="submit" name="add_shipping_documents" class="btn btn-success"><i class="bi bi-file-earmark-arrow-up"></i> Upload Shipping Documents</button>
                                                                                    </div>
                                                                                </form>
                                                                            <?php endif; ?>

                                                                            <!-- Dispatch Form -->
                                                                            <?php if (!empty($lot['shipping_documents']) && empty($lot['dispatch_status'])): ?>
                                                                                <form action="pipeline.php" method="post" class="stage-form no-print mt-3">
                                                                                    <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
                                                                                    <input type="hidden" name="item_index" value="<?= $itemIndex ?>">
                                                                                    <input type="hidden" name="lot_index" value="<?= $lotIndex ?>">
                                                                                    <h6 class="m-0 mb-3 text-primary"><i class="bi bi-truck"></i> Dispatch This Lot</h6>
                                                                                    <div class="form-grid">
                                                                                        <input type="date" name="dispatch_date" title="Dispatch Date" class="form-control" required value="<?= date('Y-m-d') ?>">
                                                                                        <select name="transport_mode" class="form-select" required>
                                                                                            <option value="">-- Transport Mode --</option>
                                                                                            <option value="Air Freight">Air Freight</option>
                                                                                            <option value="Sea Freight">Sea Freight</option>
                                                                                            <option value="Road Transport">Road Transport</option>
                                                                                            <option value="Courier">Courier</option>
                                                                                            <option value="Express">Express</option>
                                                                                        </select>
                                                                                        <input type="text" name="tracking_number" placeholder="Tracking Number" class="form-control" required>
                                                                                        <textarea name="dispatch_remarks" placeholder="Dispatch Remarks" class="form-control" rows="3" style="grid-column: 1 / -1;"></textarea>
                                                                                    </div>
                                                                                    <div class="text-end mt-3">
                                                                                        <button type="submit" name="update_dispatch_details" class="btn btn-success"><i class="bi bi-truck"></i> Mark as Shipped</button>
                                                                                    </div>
                                                                                </form>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                <?php endif; ?>
 <!-- Add New Packaging Lot Form -->
                                                                <?php if (($order['status'] ?? '') === 'QC Completed' || ($item['item_status'] ?? '') === 'QC Completed' || ($item['item_status'] ?? '') === 'Packaging'): ?>
                                                                    <div class="bg-light p-3 rounded border border-dashed">
                                                                        <h6 class="m-0 mb-3 text-primary"><i class="bi bi-plus-circle"></i> Add New Packaging Lot</h6>
                                                                        <form action="pipeline.php" method="post" enctype="multipart/form-data" class="stage-form no-print">
                                                                            <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
                                                                            <input type="hidden" name="item_index" value="<?= $itemIndex ?>">
                                                                            
                                                                            <div class="form-grid">
                                                                                <!-- Product Photos -->
                                                                                <div style="grid-column: 1 / -1;">
                                                                                    <label class="form-label">Product Photos:</label>
                                                                                    <input type="file" name="product_photos[]" multiple accept=".jpg,.jpeg,.png" class="form-control" required>
                                                                                    <div class="form-text">Multiple photos can be selected to show different angles</div>
                                                                                </div>

                                                                                <!-- Basic Packaging Info -->
                                                                                <input type="text" name="products_in_lot" placeholder="Number of Products in Lot" class="form-control" required>
                                                                                
                                                                                <div class="form-group">
                                                                                    <select name="packaging_type" class="form-select" onchange="toggleOtherField(this, 'packaging_type')" required>
                                                                                        <option value="">-- Packaging Type --</option>
                                                                                        <option value="Wooden Crate">Wooden Crate</option>
                                                                                        <option value="Cardboard Box">Cardboard Box</option>
                                                                                        <option value="Pallet">Pallet</option>
                                                                                        <option value="Steel Frame">Steel Frame</option>
                                                                                        <option value="Shrink Wrap">Shrink Wrap</option>
                                                                                        <option value="other">Other (specify)</option>
                                                                                    </select>
                                                                                    <input type="text" name="packaging_type_other" placeholder="Specify Packaging Type" class="form-control mt-1" style="display: none;">
                                                                                </div>

                                                                                <input type="date" name="packaging_date" title="Packaging Date" class="form-control" required value="<?= date('Y-m-d') ?>">
                                                                                <input type="number" name="num_packages" placeholder="Number of Packages" class="form-control" required min="1">
                                                                                <input type="text" name="weight_per_package" placeholder="Weight per Package (kg)" class="form-control" required>
                                                                                <input type="text" name="dimensions_per_package" placeholder="Dimensions per Package" class="form-control" required>
                                                                                <input type="text" name="net_weight" placeholder="Net Weight (kg)" class="form-control" required>
                                                                                <input type="text" name="gross_weight" placeholder="Gross Weight (kg)" class="form-control" required>

                                                                                <!-- Fumigation Section -->
                                                                                <div style="grid-column: 1 / -1; background: #fff3cd; padding: 15px; border-radius: 6px; border: 1px solid #ffeaa7;">
                                                                                    <h6 class="m-0 mb-2 text-warning"><i class="bi bi-tree"></i> Fumigation Requirements</h6>
                                                                                    <div class="form-grid">
                                                                                        <div style="grid-column: 1 / -1;">
                                                                                            <label class="d-flex align-items-center gap-2">
                                                                                                <input type="checkbox" name="fumigation_completed" value="1" required class="form-check-input">
                                                                                                <span class="fw-semibold">Fumigation has been completed as per requirements</span>
                                                                                            </label>
                                                                                        </div>
                                                                                        <input type="text" name="fumigation_certificate_number" placeholder="Fumigation Certificate Number" class="form-control" required>
                                                                                        <input type="date" name="fumigation_date" title="Fumigation Date" class="form-control" required value="<?= date('Y-m-d') ?>">
                                                                                        <input type="text" name="fumigation_agency" placeholder="Fumigation Agency" class="form-control" required>
                                                                                    </div>
                                                                                </div>

                                                                                <!-- Documentation -->
                                                                                <div style="grid-column: 1 / -1;">
                                                                                    <label class="d-flex align-items-center gap-2">
                                                                                        <input type="checkbox" name="docs_included" value="1" class="form-check-input">
                                                                                        <span>All required documents included in package</span>
                                                                                    </label>
                                                                                </div>
                                                                            </div>

                                                                            <div class="text-end mt-3">
                                                                                <button type="submit" name="add_packaging_lot" class="btn btn-success"><i class="bi bi-box-seam"></i> Create Packaging Lot</button>
                                                                            </div>
                                                                        </form>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
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
                                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
                                        <select name="new_status" class="form-select mb-2">
                                            <?php foreach ($STANDARD_STATUSES as $status): ?>
                                                <option value="<?= $status ?>" <?= ($order['status'] ?? 'Pending') == $status ? 'selected' : '' ?>><?= $status ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="update_status" class="btn btn-primary w-100">Update Order Status</button>
                                    </form>

                                    <!-- Delete Order Button -->
                                    <form action="pipeline.php" method="post" class="no-print mt-3"
                                        onsubmit="return confirm('Are you sure you want to delete this order? This action cannot be undone.');">
                                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
                                        <button type="submit" name="delete_order" class="btn btn-danger w-100"><i class="bi bi-trash"></i> Delete Order</button>
                                    </form>
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

    <!-- Drawing Modal -->
    <div id="drawingModal" class="drawing-modal">
        <span class="drawing-modal-close">&times;</span>
        <img id="modalDrawing" src="" alt="Drawing Preview">
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
                
                // Update toggle icons using Bootstrap icons
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
                if (selectElement.value !== 'other') {
                    otherInput.value = '';
                }
            }
        }

        // Initialize other fields
        document.querySelectorAll('select').forEach(select => {
            if (select.name.includes('_type') || select.name.includes('_name')) {
                toggleOtherField(select, select.name);
            }
        });

        // File upload preview
        function previewUploadFile(input) {
            const preview = input.parentNode.querySelector('.upload-preview');
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
            // Reset form
            const form = document.getElementById(formId).querySelector('form');
            if (form) {
                form.reset();
                const preview = form.querySelector('.upload-preview');
                if (preview) {
                    preview.style.display = 'none';
                }
            }
        }

        // Drawing modal functionality
        function showDrawingModal(imgElement) {
            const modal = document.getElementById('drawingModal');
            const modalImg = document.getElementById('modalDrawing');
            modalImg.src = imgElement.getAttribute('data-src');
            modal.style.display = 'block';
        }

        // Close modals
        document.querySelectorAll('.modal-close, .drawing-modal-close').forEach(closeBtn => {
            closeBtn.addEventListener('click', function() {
                this.closest('.modal').style.display = 'none';
                document.getElementById('drawingModal').style.display = 'none';
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const historyModal = document.getElementById('historyModal');
            const drawingModal = document.getElementById('drawingModal');
            
            if (event.target === historyModal) {
                historyModal.style.display = 'none';
            }
            if (event.target === drawingModal) {
                drawingModal.style.display = 'none';
            }
        });

        // Back to top button
        const backToTopButton = document.getElementById('backToTop');
        window.addEventListener('scroll', () => {
            backToTopButton.style.display = window.pageYOffset > 300 ? 'block' : 'none';
        });

        backToTopButton.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Order history modal
        document.querySelectorAll('.view-history-btn').forEach(button => {
            button.addEventListener('click', function() {
                const orderId = this.getAttribute('data-order-id');
                fetchOrderHistory(orderId);
            });
        });

        function fetchOrderHistory(orderId) {
            // This would typically fetch from an API endpoint
            // For now, we'll show a placeholder
            document.getElementById('historyContent').innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <p>Loading history for order #${orderId}...</p>
                    <p><em>History tracking feature would display all changes made to this order.</em></p>
                </div>
            `;
            document.getElementById('historyModal').style.display = 'block';
        }

        // Reset form functionality
        function resetForm(formId) {
            const form = document.getElementById(formId);
            if (form) {
                form.reset();
                // Hide any "other" input fields
                form.querySelectorAll('select').forEach(select => {
                    toggleOtherField(select, select.name);
                });
            }
        }

        // Reset ALL forms for an item
        function resetAllItemForms(button) {
            const itemBody = button.closest('.item-body');
            if (itemBody && confirm('This will clear ALL forms for this item. Continue?')) {
                itemBody.querySelectorAll('form').forEach(form => {
                    form.reset();
                    // Hide any "other" input fields
                    form.querySelectorAll('select').forEach(select => {
                        toggleOtherField(select, select.name);
                    });
                    // Hide file previews
                    form.querySelectorAll('.upload-preview').forEach(preview => {
                        preview.style.display = 'none';
                    });
                });
            }
        }

        // Auto-expand items with active forms or overdue status
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.order-card.overdue .item-header').forEach(header => {
                const body = header.nextElementSibling;
                const toggleIcon = header.querySelector('.toggle-icon i');
                
                body.style.display = 'block';
                header.classList.add('expanded');
                toggleIcon.className = 'bi bi-caret-down-fill';
            });

            // Show toast notification if exists
            const toast = document.getElementById('toast-notification');
            if (toast) {
                setTimeout(() => {
                    toast.style.display = 'none';
                }, 5000);
            }
        });

        // Print functionality enhancement
        window.addEventListener('beforeprint', function() {
            // Expand all items for printing
            document.querySelectorAll('.item-header').forEach(header => {
                const body = header.nextElementSibling;
                const toggleIcon = header.querySelector('.toggle-icon i');
                
                if (body.style.display !== 'block') {
                    body.style.display = 'block';
                    header.classList.add('expanded');
                    toggleIcon.className = 'bi bi-caret-down-fill';
                }
            });
        });

        // Log search/filter activities
        function logFilterActivity(searchTerm, statusFilter, clientFilter) {
            // Send to server via AJAX
            fetch('log_activity.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=Pipeline Search&details=' + encodeURIComponent(
                    'Search: "' + searchTerm + '" | Status: "' + statusFilter + '" | Client: "' + clientFilter + '"'
                )
            });
        }

        // Then modify your existing filterOrders function to include logging:
        function filterOrders() {
            const searchTerm = document.getElementById('orderSearch').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const clientFilter = document.getElementById('clientFilter').value;

            // Log the filter activity
            if (searchTerm || statusFilter || clientFilter) {
                logFilterActivity(searchTerm, statusFilter, clientFilter);
            }

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
    </script>
</body>
</html>