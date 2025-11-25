<?php 
session_start();
include("dbconnection.php");
include("checklogin.php");
check_login();

// Get statistics
$users_count = mysqli_query($con, "SELECT COUNT(*) as total FROM user");
$users_total = mysqli_fetch_array($users_count)['total'];

$tickets_count = mysqli_query($con, "SELECT COUNT(*) as total FROM ticket");
$tickets_total = mysqli_fetch_array($tickets_count)['total'];

$requests_count = mysqli_query($con, "SELECT COUNT(*) as total FROM prequest");
$requests_total = mysqli_fetch_array($requests_count)['total'];

$recent_logins = mysqli_query($con, "SELECT * FROM usercheck ORDER BY id DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM | Admin Dashboard</title>
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
        }

        .stat-users .stat-icon { color: #4361ee; }
        .stat-tickets .stat-icon { color: #2a9d8f; }
        .stat-requests .stat-icon { color: #f4a261; }
        .stat-admin .stat-icon { color: #e63946; }

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

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .content-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
            padding: 1.5rem;
        }

        .content-card h3 {
            font-size: 1.5rem;
            color: #212529;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #4361ee;
        }

        .quick-actions {
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

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .table th {
            background-color: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 1px solid #dee2e6;
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }

        .table tr:hover {
            background-color: #f8f9fa;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }

        .empty-state .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>🚀 Admin Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($_SESSION['alogin']); ?>!</p>
        </div>

        <div class="welcome-section">
            <h2>👋 System Overview</h2>
            <p>Manage your CRM system efficiently with the tools below.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card stat-users">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo $users_total; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            
            <div class="stat-card stat-tickets">
                <div class="stat-icon">📋</div>
                <div class="stat-value"><?php echo $tickets_total; ?></div>
                <div class="stat-label">Support Tickets</div>
            </div>
            
            <div class="stat-card stat-requests">
                <div class="stat-icon">📞</div>
                <div class="stat-value"><?php echo $requests_total; ?></div>
                <div class="stat-label">Service Requests</div>
            </div>
            
            <div class="stat-card stat-admin">
                <div class="stat-icon">⚙️</div>
                <div class="stat-value">Admin</div>
                <div class="stat-label">System Role</div>
            </div>
        </div>

        <div class="content-grid">
            <div class="content-card">
                <h3>📊 Recent User Activity</h3>
                <?php if(mysqli_num_rows($recent_logins) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Login Date</th>
                                <th>Login Time</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($login = mysqli_fetch_array($recent_logins)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($login['username']); ?></td>
                                    <td><?php echo htmlspecialchars($login['email']); ?></td>
                                    <td><?php echo htmlspecialchars($login['logindate']); ?></td>
                                    <td><?php echo htmlspecialchars($login['logintime']); ?></td>
                                    <td><?php echo htmlspecialchars($login['city'] . ', ' . $login['country']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <h4>No recent activity</h4>
                        <p>User login activity will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="content-card">
                <h3>⚡ Quick Stats</h3>
                <div style="display: grid; gap: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #f8f9fa; border-radius: 0.5rem;">
                        <span>👥 Registered Users</span>
                        <strong><?php echo $users_total; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #f8f9fa; border-radius: 0.5rem;">
                        <span>📋 Open Tickets</span>
                        <strong><?php echo $tickets_total; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #f8f9fa; border-radius: 0.5rem;">
                        <span>📞 Pending Requests</span>
                        <strong><?php echo $requests_total; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #f8f9fa; border-radius: 0.5rem;">
                        <span>🔄 System Status</span>
                        <strong style="color: #2a9d8f;">Online</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="quick-actions">
            <h3>🚀 Admin Actions</h3>
            <div class="action-buttons">
                <a href="./document-manager-admin.php" class="action-btn">
                    <div class="action-icon">📙</div>
                    <div class="action-text">Document Manager</div>
                </a>
                
                <a href="manage-users.php" class="action-btn">
                    <div class="action-icon">🔗</div>
                    <div class="action-text">Manage Users</div>
                </a>
                
                <a href="manage-requests.php" class="action-btn">
                    <div class="action-icon">📞</div>
                    <div class="action-text">Service Requests</div>
                </a>
                
                <a href="change-password.php" class="action-btn">
                    <div class="action-icon">🔒</div>
                    <div class="action-text">Change Password</div>
                </a>
                
                <a href="logout.php" class="action-btn">
                    <div class="action-icon">🚪</div>
                    <div class="action-text">Logout</div>
                </a>
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