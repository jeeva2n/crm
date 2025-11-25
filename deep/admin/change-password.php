<?php
session_start();
error_reporting(0);
include("checklogin.php");
check_login();
include("dbconnection.php");

if(isset($_POST['change'])) {
    $oldpas = mysqli_real_escape_string($con, $_POST['oldpass']);
    $adminid = $_SESSION['id'];
    $newpassword = mysqli_real_escape_string($con, $_POST['newpass']);
    
    $sql = mysqli_query($con, "SELECT `password` FROM `admin` WHERE `password`='$oldpas' AND id='$adminid'");
    $num = mysqli_fetch_array($sql);
    
    if($num > 0) {
        mysqli_query($con, "UPDATE admin SET password='$newpassword' WHERE id='$adminid'");
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Password has been updated successfully.'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Old Password does not match!'];
    }
    header('Location: change-password.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM | Change Password</title>
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

        .password-header {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .password-header h3 {
            font-size: 1.75rem;
            color: #212529;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .password-card {
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
        <div class="password-header">
            <h3>🔒 Change Admin Password</h3>
            <p>Update your administrator password to keep the system secure</p>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="message-alert message-<?= $_SESSION['message']['type'] ?>">
                <?= htmlspecialchars($_SESSION['message']['text']) ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <div class="password-card">
            <form name="form1" method="post" action="" onsubmit="return valid()">
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="oldpass" id="oldpass" class="form-input" placeholder="Enter current password" required>
                </div>

                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="newpass" id="newpass" class="form-input" placeholder="Enter new password" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirmpassword" id="confirmpassword" class="form-input" placeholder="Confirm new password" required>
                </div>

                <div class="form-actions">
                    <button type="reset" class="btn btn-secondary">Clear Form</button>
                    <button type="submit" name="change" class="btn btn-primary">Change Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function valid() {
            const oldpass = document.form1.oldpass.value;
            const newpass = document.form1.newpass.value;
            const confirmpassword = document.form1.confirmpassword.value;

            if (!oldpass) {
                alert("Current Password field is empty!");
                document.form1.oldpass.focus();
                return false;
            }

            if (!newpass) {
                alert("New Password field is empty!");
                document.form1.newpass.focus();
                return false;
            }

            if (newpass.length < 6) {
                alert("Password must be at least 6 characters long!");
                document.form1.newpass.focus();
                return false;
            }

            if (!confirmpassword) {
                alert("Confirm Password field is empty!");
                document.form1.confirmpassword.focus();
                return false;
            }

            if (newpass !== confirmpassword) {
                alert("New Password and Confirm Password do not match!");
                document.form1.confirmpassword.focus();
                return false;
            }

            return true;
        }

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