<?php
// index.php - Main entry point
session_start();

// Redirect to login if not authenticated, otherwise to dashboard
if (isset($_SESSION['username']) && isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
} else {
    header('Location: login.php');
    exit;
}
?>