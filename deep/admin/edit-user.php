<?php
session_start();
include("dbconnection.php");
include("checklogin.php");
check_login();

// Get user ID from URL
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch user data
$user_query = mysqli_query($con, "SELECT * FROM user WHERE id='$user_id'");
$user = mysqli_fetch_array($user_query);

if (!$user) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'User not found!'];
    header('Location: manage-users.php');
    exit;
}

// Handle form submission
if (isset($_POST['update_user'])) {
    $name = mysqli_real_escape_string($con, $_POST['name']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $alt_email = mysqli_real_escape_string($con, $_POST['alt_email']);
    $mobile = mysqli_real_escape_string($con, $_POST['mobile']);
    $gender = mysqli_real_escape_string($con, $_POST['gender']);
    $address = mysqli_real_escape_string($con, $_POST['address']);

    // Check if email already exists (excluding current user)
    $email_check = mysqli_query($con, "SELECT id FROM user WHERE email='$email' AND id != '$user_id'");
    if (mysqli_num_rows($email_check) > 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Email already exists!'];
    } else {
        $update_query = mysqli_query($con, "UPDATE user SET 
            name='$name', 
            email='$email', 
            alt_email='$alt_email', 
            mobile='$mobile', 
            gender='$gender', 
            address='$address' 
            WHERE id='$user_id'");

        if ($update_query) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'User updated successfully!'];
            header('Location: manage-users.php');
            exit;
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to update user!'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Edit User</title>
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
            max-width: 800px;
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

        .edit-form-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #495057;
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

        textarea.form-input {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
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

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
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

        .user-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .user-info p {
            margin-bottom: 0.5rem;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h2>✏️ Edit User</h2>
            <p>Update user information</p>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="message-alert message-<?= $_SESSION['message']['type'] ?>">
                <?= htmlspecialchars($_SESSION['message']['text']) ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <div class="edit-form-container">
            <div class="user-info">
                <p><strong>User ID:</strong> <?php echo $user['id']; ?></p>
                <p><strong>Registration Date:</strong> <?php echo $user['posting_date']; ?></p>
            </div>

            <form method="post" action="">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Alternate Email</label>
                    <input type="email" name="alt_email" class="form-input" value="<?php echo htmlspecialchars($user['alt_email']); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="mobile" class="form-input" value="<?php echo htmlspecialchars($user['mobile']); ?>" maxlength="15" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-input" required>
                        <option value="male" <?= $user['gender'] == 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= $user['gender'] == 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other" <?= $user['gender'] == 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-input"><?php echo htmlspecialchars($user['address']); ?></textarea>
                </div>

                <div class="form-actions">
                    <a href="manage-users.php" class="btn btn-secondary">← Cancel</a>
                    <button type="submit" name="update_user" class="btn btn-primary">💾 Update User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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