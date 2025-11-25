<?php
session_start();
include("dbconnection.php");
include("checklogin.php");
check_login();

if (isset($_POST['update'])) {
    $name = $_POST['name'];
    $aemail = $_POST['alt_email'];
    $mobile = $_POST['phone'];
    $gender = $_POST['gender'];
    $address = $_POST['address'];

    $update_query = mysqli_query($con, "UPDATE user SET name='$name', mobile='$mobile', gender='$gender', alt_email='$aemail', address='$address' WHERE email='" . $_SESSION['login'] . "'");
    if ($update_query) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Your profile updated successfully.'];
        $_SESSION['name'] = $name; // Update session name
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to update profile.'];
    }
    header('Location: profile.php');
    exit;
}

$query = mysqli_query($con, "SELECT * FROM user WHERE email='" . $_SESSION['login'] . "'");
$user = mysqli_fetch_array($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM | User Profile</title>
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

        .profile-header {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .profile-header h3 {
            font-size: 1.75rem;
            color: #212529;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .profile-header p {
            color: #6c757d;
            margin-bottom: 0;
        }

        .profile-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #495057;
            display: block;
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

        .form-input:disabled {
            background-color: #e9ecef;
            opacity: 1;
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
        <div class="profile-header">
            <h3>👤 <?php echo htmlspecialchars($_SESSION['name']); ?>'s Profile</h3>
            <p>📅 Registration Date: <?php echo htmlspecialchars($user['posting_date']); ?></p>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="message-alert message-<?= $_SESSION['message']['type'] ?>">
                <?= htmlspecialchars($_SESSION['message']['text']) ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <div class="profile-card">
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" class="form-input" required />
                </div>

                <div class="form-group">
                    <label class="form-label">Primary Email</label>
                    <input type="text" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled class="form-input" />
                </div>

                <div class="form-group">
                    <label class="form-label">Alternate Email</label>
                    <input type="email" name="alt_email" value="<?php echo htmlspecialchars($user['alt_email']); ?>" class="form-input" />
                </div>

                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['mobile']); ?>" maxlength="11" class="form-input" required />
                </div>

                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-input" required>
                        <option value="male" <?= $user['gender'] == 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= $user['gender'] == 'female' ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" rows="5" class="form-input"><?php echo htmlspecialchars($user['address']); ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="reset" class="btn btn-secondary">Clear Form</button>
                    <button type="submit" name="update" class="btn btn-primary">Update Profile</button>
                     <a href="./dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
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