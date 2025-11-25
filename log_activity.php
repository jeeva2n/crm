<?php
session_start();
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'Unknown Action';
    $details = $_POST['details'] ?? 'No details provided';
    
    // Log the activity to database
    $success = logActivity($action, $details);
    
    if ($success) {
        echo 'Activity logged successfully';
    } else {
        echo 'Failed to log activity';
    }
    exit;
} else {
    echo 'Invalid request method';
}
?>