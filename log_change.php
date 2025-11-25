<?php
session_start();
require_once 'functions.php';

// Handle AJAX change logging for order history
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($input && isset($input['action'])) {
        $orderId = $input['order_id'] ?? '';
        $stage = $input['stage'] ?? '';
        $description = $input['change_description'] ?? $input['action'];
        $itemIndex = $input['item_index'] ?? null;

        // Use the database logging function
        if (!empty($orderId)) {
            $success = logChange($orderId, $stage, $description, $itemIndex);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Change logged successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to log change to database']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Order ID is required']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>