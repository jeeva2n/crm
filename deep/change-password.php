<?php
session_start();
error_reporting(0);
include("checklogin.php");
check_login();
include("dbconnection.php");

if(isset($_POST['change'])) {
    $sql = mysqli_query($con, "SELECT password FROM user WHERE password='".$_POST['oldpass']."' && email='".$_SESSION['login']."'");
    $num = mysqli_fetch_array($sql);
    
    if($num > 0) {
        $con = mysqli_query($con, "UPDATE user SET password='".$_POST['newpass']."' WHERE email='".$_SESSION['login']."'");
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Password Changed Successfully!'];
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

        .form-input.is-invalid {
            border-color: #e63946;
            box-shadow: 0 0 0 0.2rem rgba(230, 57, 70, 0.25);
        }

        .input-group {
            position: relative;
        }

        .input-group-addon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .input-group .form-input {
            padding-left: 3rem;
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

        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }

        .strength-weak {
            color: #e63946;
        }

        .strength-medium {
            color: #f4a261;
        }

        .strength-strong {
            color: #2a9d8f;
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
            <h3>🔒 Change Password</h3>
            <p>Update your password to keep your account secure</p>
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
                    <div class="input-group">
                        <span class="input-group-addon">🔒</span>
                        <input type="password" name="oldpass" id="oldpass" class="form-input" placeholder="Enter current password" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <div class="input-group">
                        <span class="input-group-addon">🔑</span>
                        <input type="password" name="newpass" id="newpass" class="form-input" placeholder="Enter new password" required onkeyup="checkPasswordStrength(this.value)">
                    </div>
                    <div id="password-strength" class="password-strength"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-addon">✅</span>
                        <input type="password" name="confirmpassword" id="confirmpassword" class="form-input" placeholder="Confirm new password" required onkeyup="checkPasswordMatch()">
                    </div>
                    <div id="password-match" class="password-strength"></div>
                </div>

                <div class="form-actions">
                    <button type="reset" class="btn btn-secondary">Clear Form</button>
                    <button type="submit" name="change" class="btn btn-primary">Change Password</button>
                     <a href="./dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
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

        function checkPasswordStrength(password) {
            const strengthElement = document.getElementById('password-strength');
            let strength = '';
            let strengthClass = '';

            if (password.length === 0) {
                strengthElement.innerHTML = '';
                return;
            }

            if (password.length < 6) {
                strength = 'Weak - Password must be at least 6 characters';
                strengthClass = 'strength-weak';
            } else if (password.length < 8) {
                strength = 'Medium';
                strengthClass = 'strength-medium';
            } else {
                const hasUpperCase = /[A-Z]/.test(password);
                const hasLowerCase = /[a-z]/.test(password);
                const hasNumbers = /\d/.test(password);
                const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>]/.test(password);

                const complexityScore = [hasUpperCase, hasLowerCase, hasNumbers, hasSpecialChar].filter(Boolean).length;

                if (complexityScore >= 3) {
                    strength = 'Strong';
                    strengthClass = 'strength-strong';
                } else if (complexityScore >= 2) {
                    strength = 'Medium';
                    strengthClass = 'strength-medium';
                } else {
                    strength = 'Weak - Add uppercase, numbers, or special characters';
                    strengthClass = 'strength-weak';
                }
            }

            strengthElement.innerHTML = `<span class="${strengthClass}">${strength}</span>`;
        }

        function checkPasswordMatch() {
            const password = document.getElementById('newpass').value;
            const confirmPassword = document.getElementById('confirmpassword').value;
            const matchElement = document.getElementById('password-match');

            if (confirmPassword.length === 0) {
                matchElement.innerHTML = '';
                return;
            }

            if (password === confirmPassword) {
                matchElement.innerHTML = '<span class="strength-strong">✓ Passwords match</span>';
            } else {
                matchElement.innerHTML = '<span class="strength-weak">✗ Passwords do not match</span>';
            }
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