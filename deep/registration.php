<?php
session_start();
error_reporting(0);
include("dbconnection.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $mobile = $_POST['phone'];
    $gender = $_POST['gender'];

    $query = mysqli_query($con, "SELECT email FROM user WHERE email='$email'");
    $num = mysqli_fetch_array($query);

    if ($num > 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Email already registered with us. Please try with different email.'];
    } else {
        mysqli_query($con, "INSERT INTO user(name, email, password, mobile, gender) VALUES('$name','$email','$password','$mobile','$gender')");
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Your account has been created successfully!'];
        header('Location: login.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM | Registration</title>
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

        .registration-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid #dee2e6;
            width: 100%;
            max-width: 500px;
            padding: 2rem;
            margin: 2rem;
        }

        .registration-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .registration-header h2 {
            font-size: 1.75rem;
            color: #212529;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .registration-header p {
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .registration-header a {
            color: #4361ee;
            text-decoration: none;
            font-weight: 600;
        }

        .registration-header a:hover {
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

        .form-input.is-invalid {
            border-color: #e63946;
            box-shadow: 0 0 0 0.2rem rgba(230, 57, 70, 0.25);
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
            .registration-container {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="registration-container">
        <div class="registration-header">
            <h2>👤 Create User Account</h2>
            <p>Already have an account? <a href="login.php">Login Here!</a></p>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="message-alert message-<?= $_SESSION['message']['type'] ?>">
                <?= htmlspecialchars($_SESSION['message']['text']) ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <form id="signup" name="signup" onsubmit="return checkpass()" method="post">
            <div class="form-group">
                <label for="name" class="form-label">Full Name</label>
                <input type="text" class="form-input" id="name" name="name" required>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-input" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-input" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="cpassword" class="form-label">Confirm Password</label>
                <input type="password" class="form-input" id="cpassword" name="cpassword" required>
            </div>

            <div class="form-group">
                <label for="phone" class="form-label">Contact Number</label>
                <input type="text" pattern="[0-9]{11}" class="form-input" id="phone" name="phone" required>
            </div>

            <div class="form-group">
                <label for="gender" class="form-label">Gender</label>
                <select class="form-input" name="gender" id="gender" required>
                    <option value="">Select Gender</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Create Account</button>
        </form>

        <div class="login-link">
            <p>Already have an account? <a href="login.php">Sign in here</a></p>
        </div>
    </div>

    <script>
        function checkpass() {
            const password = document.signup.password.value;
            const cpassword = document.signup.cpassword.value;

            if (password !== cpassword) {
                alert('New Password and Confirm Password field does not match');
                document.signup.cpassword.focus();
                return false;
            }

            if (password.length < 6) {
                alert('Password must be at least 6 characters long');
                document.signup.password.focus();
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