<?php
session_start();
error_reporting(0);
include("dbconnection.php");

if(isset($_POST['submit'])) {
    $row1 = mysqli_query($con, "SELECT email, password FROM user WHERE email='".$_POST['email']."'");
    $row2 = mysqli_fetch_array($row1);
    
    if($row2 > 0) {
        $email = $row2['email'];
        $subject = "CRM - Your Password Recovery";
        $password = $row2['password'];
        $message = "Your password is: " . $password;
        
        // In a real application, you would send an email here
        // mail($email, $subject, $message, "From: noreply@yourdomain.com");
        
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Your password has been sent to your email id.'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Email not registered with us.'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM | Forgot Password</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .forgot-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
            width: 100%;
            max-width: 400px;
            padding: 2rem;
            margin: 2rem;
        }

        .forgot-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .forgot-header h2 {
            font-size: 1.75rem;
            color: #212529;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .forgot-header p {
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .forgot-header a {
            color: #4361ee;
            text-decoration: none;
            font-weight: 600;
        }

        .forgot-header a:hover {
            text-decoration: underline;
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
            width: 100%;
        }

        .btn-primary {
            background-color: #4361ee;
            color: white;
        }

        .btn-primary:hover {
            background-color: #3a56d4;
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

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #dee2e6;
        }

        .login-link a {
            color: #4361ee;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 576px) {
            .forgot-container {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-header">
            <h2>🔑 Forgot Password</h2>
            <p>Remember your password? <a href="login.php">Login here</a></p>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="message-alert message-<?= $_SESSION['message']['type'] ?>">
                <?= htmlspecialchars($_SESSION['message']['text']) ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <form id="forgot-form" action="" method="post">
            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-input" id="email" name="email" required 
                       placeholder="Enter your registered email">
            </div>

            <button type="submit" name="submit" class="btn btn-primary">Recover Password</button>
        </form>

        <div class="login-link">
            <p>Back to <a href="login.php">Login</a></p>
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

            document.getElementById('email').focus();
        });
    </script>
</body>
</html>