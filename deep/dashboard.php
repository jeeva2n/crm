<?php
session_start();
include("checklogin.php");
check_login();
include("dbconnection.php");

// Get user stats
$user_id = $_SESSION['id'];
$tickets_count = mysqli_query($con, "SELECT COUNT(*) as total FROM ticket WHERE email_id='" . $_SESSION['login'] . "'");
$tickets_total = mysqli_fetch_array($tickets_count)['total'];

$requests_count = mysqli_query($con, "SELECT COUNT(*) as total FROM prequest WHERE email='" . $_SESSION['login'] . "'");
$requests_total = mysqli_fetch_array($requests_count)['total'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM | User Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .dashboard-header {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .dashboard-header h1 {
            font-size: 2.5rem;
            color: #212529;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .dashboard-header p {
            font-size: 1.125rem;
            color: #6c757d;
            margin-bottom: 0;
        }

        .welcome-section {
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            color: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .welcome-section h2 {
            font-size: 1.75rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .welcome-section p {
            font-size: 1.125rem;
            opacity: 0.9;
            margin-bottom: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #4361ee;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #212529;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .quick-actions {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .quick-actions h3 {
            font-size: 1.5rem;
            color: #212529;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s ease;
            text-align: center;
        }

        .action-btn:hover {
            background: #4361ee;
            color: white;
            border-color: #4361ee;
            transform: translateY(-2px);
            text-decoration: none;
        }

        .action-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .action-text {
            font-weight: 600;
            font-size: 0.875rem;
        }

        .user-info {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
            padding: 1.5rem;
            text-align: center;
        }

        .user-info h4 {
            font-size: 1.25rem;
            color: #212529;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .user-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .user-detail {
            text-align: left;
        }

        .user-detail strong {
            color: #495057;
        }

        .user-detail span {
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }

            .dashboard-header h1 {
                font-size: 2rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }

            .user-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>🚀 User Dashboard</h1>
            <p>Welcome to your Customer Relationship Management system</p>
        </div>

        <div class="welcome-section">
            <h2>👋 Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h2>
            <p>We're glad to see you again. Here's what's happening with your CRM today.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-value"><?php echo $tickets_total; ?></div>
                <div class="stat-label">My Tickets</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📞</div>
                <div class="stat-value"><?php echo $requests_total; ?></div>
                <div class="stat-label">Service Requests</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">👤</div>
                <div class="stat-value">User</div>
                <div class="stat-label">Account Type</div>
            </div>
        </div>

        <div class="quick-actions">
            <h3>🚀 Quick Actions</h3>
            <div class="action-buttons">
                <a href="profile.php" class="action-btn">
                    <div class="action-icon">👤</div>
                    <div class="action-text">My Profile</div>
                </a>

                <a href="change-password.php" class="action-btn">
                    <div class="action-icon">🔒</div>
                    <div class="action-text">Change Password</div>
                </a>

                <a href="logout.php" class="action-btn">
                    <div class="action-icon">🚪</div>
                    <div class="action-text">Logout</div>
                </a>
                
                <a href="document-manager-employee.php" class="action-btn">
                    <div class="action-icon">📁</div>
                    <div class="action-text">Document Manager</div>
                </a>
                <a href="../home.php" class="action-btn">
                    <div class="action-icon">🔑</div>
                    <div class="action-text">Dashboard</div>
                </a>
            </div>
        </div>

        <div class="user-info">
            <h4>📋 Your Information</h4>

            <div class="user-details">
                <div class="user-detail">
                    <strong>Name:</strong> <span><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                </div>
                <div class="user-detail">
                    <strong>Email:</strong> <span><?php echo htmlspecialchars($_SESSION['login']); ?></span>
                </div>
                <div class="user-detail">
                    <strong>User ID:</strong> <span><?php echo htmlspecialchars($_SESSION['id']); ?></span>
                </div>
                <div class="user-detail">
                    <strong>Last Login:</strong> <span><?php echo date('Y-m-d H:i:s'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            statCards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            });
        });
    </script>
</body>

</html>