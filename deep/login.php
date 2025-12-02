<?php
session_start();
error_reporting(0);
include("dbconnection.php");

if(isset($_POST['login'])) {
    $ret = mysqli_query($con, "SELECT * FROM user WHERE email='".$_POST['email']."' and password='".$_POST['password']."'");
    $num = mysqli_fetch_array($ret);
    
    if($num > 0) {
        $_SESSION['login'] = $_POST['email'];
        $_SESSION['id'] = $num['id'];
        $_SESSION['name'] = $num['name'];
        
        // Also set document manager session variables
        $_SESSION['username'] = $_POST['email'];
        $_SESSION['user_id'] = $num['id'];
        $_SESSION['role'] = 'employee';
        
        // Log login activity
        $val3 = date("Y/m/d");
        date_default_timezone_set("Asia/Calcutta");
        $time = date("h:i:sa");
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $geopluginURL = 'http://www.geoplugin.net/php.gp?ip='.$ip_address;
        $addrDetailsArr = unserialize(file_get_contents($geopluginURL)); 
        $city = $addrDetailsArr['geoplugin_city'] ?? 'Unknown'; 
        $country = $addrDetailsArr['geoplugin_countryName'] ?? 'Unknown';
        
        mysqli_query($con, "INSERT INTO usercheck(logindate, logintime, user_id, username, email, ip, city, country) VALUES('$val3','$time','".$_SESSION['id']."','".$_SESSION['name']."','".$_SESSION['login']."','$ip_address','$city','$country')");

        $_SESSION['message'] = ['type' => 'success', 'text' => 'Login successful!'];
        header('Location: dashboard.php');
        exit();
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid username or password'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM | User Login</title>
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

        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
            width: 100%;
            max-width: 400px;
            padding: 2rem;
            margin: 2rem;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h2 {
            font-size: 1.75rem;
            color: #212529;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .login-header a {
            color: #4361ee;
            text-decoration: none;
            font-weight: 600;
        }

        .login-header a:hover {
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

        .registration-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #dee2e6;
        }

        .registration-link a {
            color: #4361ee;
            text-decoration: none;
            font-weight: 600;
        }

        .registration-link a:hover {
            text-decoration: underline;
        }

        .forgot-password {
            text-align: center;
            margin-top: 1rem;
        }

        .forgot-password a {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.875rem;
        }

        .forgot-password a:hover {
            color: #4361ee;
            text-decoration: underline;
        }

        .admin-link {
            text-align: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
        }

        .admin-link a {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.875rem;
        }

        .admin-link a:hover {
            color: #4361ee;
            text-decoration: underline;
        }

        @media (max-width: 576px) {
            .login-container {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2>🔐 User Login</h2>
            <p>Don't have an account? <a href="registration.php">Sign up here</a></p>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="message-alert message-<?= $_SESSION['message']['type'] ?>">
                <?= htmlspecialchars($_SESSION['message']['text']) ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <form id="login-form" class="login-form" action="" method="post">
            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-input" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-input" id="password" name="password" required>
            </div>

            <button type="submit" name="login" class="btn btn-primary">Login</button>

            <div class="forgot-password">
                <a href="forgot-password.php">Forgot your password?</a>
            </div>
        </form>

        <div class="registration-link">
            <p>New to CRM? <a href="registration.php">Create an account</a></p>
        </div>

        <div class="admin-link">
            <p>Are you an admin? <a href="admin/index.php">Login here</a></p>
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