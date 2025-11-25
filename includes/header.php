<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="css/document-manager.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Additional inline styles */
        .site-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
            z-index: 999;
        }

        .logo-link {
            color: white;
            text-decoration: none;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .header-user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-user-info a {
            color: white;
            text-decoration: none;
            padding: 5px 15px;
            border: 1px solid white;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .header-user-info a:hover {
            background: white;
            color: #667eea;
        }

        .main-content-wrapper {
            display: flex;
            min-height: calc(100vh - 80px);
        }

        .content-area {
            flex: 1;
            padding: 20px;
            background: #f8f9fa;
            margin-left: 280px; /* Account for sidebar */
        }

        /* Toast Notification */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateX(400px);
            transition: transform 0.3s ease;
            z-index: 10000;
        }

        .toast-notification.show {
            transform: translateX(0);
        }

        .toast-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Footer */
        .site-footer {
            background: #1e293b;
            color: white;
            padding: 1.5rem 2rem;
            margin-left: 280px;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-links {
            display: flex;
            gap: 20px;
        }

        .footer-links a {
            color: #94a3b8;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: white;
        }

        /* Mobile responsive */
        @media (max-width: 1024px) {
            .content-area {
                margin-left: 0;
            }
            
            .site-footer {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div id="app-container">
        <header class="site-header">
            <a href="home.php" class="logo-link">
                
            </a>
            <?php if (isset($_SESSION['username'])): ?>
                <div class="header-user-info">
                    <span>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>
                    <span>(<?= ucfirst($_SESSION['role'] ?? 'user') ?>)</span>
                    <a href="dlogout.php" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
                </div>
            <?php endif; ?>
        </header>

        <div class="main-content-wrapper">
            <?php include('sidebar.php'); ?>
            <main class="content-area">
                <!-- Content will be inserted here -->