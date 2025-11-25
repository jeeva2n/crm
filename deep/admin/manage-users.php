<?php
session_start();
include("dbconnection.php");
include("checklogin.php");
check_login();

// Handle password reset
if (isset($_POST['reset_password'])) {
    $user_id = mysqli_real_escape_string($con, $_POST['user_id']);
    $new_password = mysqli_real_escape_string($con, $_POST['new_password']);

    $update_query = mysqli_query($con, "UPDATE user SET password='$new_password' WHERE id='$user_id'");
    if ($update_query) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Password reset successfully!'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to reset password.'];
    }
    header('Location: manage-users.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Manage Users</title>
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .page-header h2 {
            font-size: 1.75rem;
            color: #212529;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .page-header p {
            color: #6c757d;
            margin-bottom: 0;
        }

        .users-table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
            padding: 1.5rem;
            overflow: hidden;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
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

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: #4361ee;
            color: white;
        }

        .btn-primary:hover {
            background-color: #3a56d4;
        }

        .btn-danger {
            background-color: #e63946;
            color: white;
        }

        .btn-danger:hover {
            background-color: #d32f3c;
        }

        .btn-warning {
            background-color: #f4a261;
            color: white;
        }

        .btn-warning:hover {
            background-color: #e76f51;
        }

        .btn-success {
            background-color: #2a9d8f;
            color: white;
        }

        .btn-success:hover {
            background-color: #21867a;
        }

        .actions-cell {
            display: flex;
            gap: 0.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .empty-state .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #4361ee;
            display: block;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .message-alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 0.375rem;
            font-weight: 600;
        }

        .message-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.show {
            opacity: 1;
            display: flex;
        }

        .modal-content {
            width: 90%;
            max-width: 500px;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }

        .modal.show .modal-content {
            transform: translateY(0);
        }

        .modal h3 {
            margin-bottom: 1rem;
            color: #212529;
            font-weight: 600;
        }

        .modal-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .actions-cell {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .stats-bar {
                grid-template-columns: 1fr 1fr;
            }

            .modal-buttons {
                flex-direction: column;
            }
        }

        @media (max-width: 576px) {
            .stats-bar {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="page-header">
            <h2>👥 Manage Users</h2>
            <p>View and manage all registered users in the system</p>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="message-alert message-<?= $_SESSION['message']['type'] ?>">
                <?= htmlspecialchars($_SESSION['message']['text']) ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <?php
        // Get user statistics
        $total_users = mysqli_query($con, "SELECT COUNT(*) as total FROM user");
        $total_users_count = mysqli_fetch_array($total_users)['total'];

        $active_today = mysqli_query($con, "SELECT COUNT(DISTINCT user_id) as active FROM usercheck WHERE logindate = CURDATE()");
        $active_today_count = mysqli_fetch_array($active_today)['active'];

        $new_this_week = mysqli_query($con, "SELECT COUNT(*) as new_users FROM user WHERE posting_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
        $new_this_week_count = mysqli_fetch_array($new_this_week)['new_users'];
        ?>

        <div class="stats-bar">
            <div class="stat-item">
                <span class="stat-number"><?php echo $total_users_count; ?></span>
                <span class="stat-label">Total Users</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo $active_today_count; ?></span>
                <span class="stat-label">Active Today</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo $new_this_week_count; ?></span>
                <span class="stat-label">New This Week</span>
            </div>
        </div>

        <div class="users-table-container">
            <?php
            $ret = mysqli_query($con, "SELECT * FROM user ORDER BY posting_date DESC");
            if (mysqli_num_rows($ret) > 0):
            ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>👤 Full Name</th>
                                <th>📧 Email ID</th>
                                <th>📞 Contact No</th>
                                <th>👤 Gender</th>
                                <th>📅 Registration Date</th>
                                <th>🛠️ Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $cnt = 1;
                            while ($row = mysqli_fetch_array($ret)) {
                            ?>
                                <tr>
                                    <td><?php echo $cnt; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                        <?php if ($row['alt_email']): ?>
                                            <br><small class="text-muted">Alt: <?php echo htmlspecialchars($row['alt_email']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                                    <td>
                                        <span style="text-transform: capitalize;">
                                            <?php echo htmlspecialchars($row['gender']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['posting_date']); ?></td>
                                    <td class="actions-cell">
                                        <button type="button" onclick="resetPassword(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>')"
                                            class="btn btn-success" title="Reset Password">
                                            🔑 Reset
                                        </button>
                                        <a href="edit-user.php?id=<?php echo $row['id']; ?>" class="btn btn-primary" title="Edit User">
                                            ✏️ Edit
                                        </a>
                                        <a href="delete-user.php?id=<?php echo $row['id']; ?>" class="btn btn-danger"
                                            onclick="return confirm('Are you sure you want to delete user: <?php echo addslashes($row['name']); ?>?')" title="Delete User">
                                            🗑️ Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php
                                $cnt++;
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <h3>No Users Found</h3>
                    <p>There are no registered users in the system yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <div style="margin-top: 2rem; text-align: center;">
            <a href="home.php" class="btn btn-secondary" style="padding: 0.75rem 1.5rem;">
                ← Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <h3>🔑 Reset User Password</h3>
            <p>Reset password for: <strong id="userName"></strong></p>
            <form id="resetPasswordForm" method="post">
                <input type="hidden" name="user_id" id="userId">
                <div style="margin-bottom: 1rem;">
                    <label for="new_password" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">New Password:</label>
                    <input type="password" name="new_password" id="new_password" class="form-input" required
                        style="width: 100%; padding: 0.75rem; border: 1px solid #ced4da; border-radius: 0.375rem;">
                </div>
                <div class="modal-buttons">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" name="reset_password" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function resetPassword(userId, userName) {
            document.getElementById('userId').value = userId;
            document.getElementById('userName').textContent = userName;
            const modal = document.getElementById('resetPasswordModal');
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
        }

        function closeModal() {
            const modal = document.getElementById('resetPasswordModal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('.table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px)';
                    this.style.transition = 'transform 0.2s ease';
                });

                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                });
            });

            const deleteButtons = document.querySelectorAll('.btn-danger');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            });

            // Close modal when clicking outside
            document.getElementById('resetPasswordModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });

            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal();
                }
            });

            // Auto-hide messages
            const messageAlerts = document.querySelectorAll('.message-alert');
            messageAlerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>

</html>