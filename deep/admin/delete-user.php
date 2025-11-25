<?php
session_start();
include("dbconnection.php");
include("checklogin.php");
check_login();

// Get user ID from URL
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id > 0) {
    // Check if user exists
    $user_query = mysqli_query($con, "SELECT * FROM user WHERE id='$user_id'");
    $user = mysqli_fetch_array($user_query);
    
    if ($user) {
        // Check if user has any related records (optional - for data integrity)
        $tickets_check = mysqli_query($con, "SELECT COUNT(*) as ticket_count FROM ticket WHERE email_id='{$user['email']}'");
        $tickets_count = mysqli_fetch_array($tickets_check)['ticket_count'];
        
        $requests_check = mysqli_query($con, "SELECT COUNT(*) as request_count FROM prequest WHERE email='{$user['email']}'");
        $requests_count = mysqli_fetch_array($requests_check)['request_count'];
        
        if ($tickets_count > 0 || $requests_count > 0) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Cannot delete user. User has related records in the system.'];
        } else {
            // Delete user login history first (if exists)
            mysqli_query($con, "DELETE FROM usercheck WHERE user_id='$user_id'");
            
            // Delete the user
            $delete_query = mysqli_query($con, "DELETE FROM user WHERE id='$user_id'");
            
            if ($delete_query) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'User deleted successfully!'];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to delete user!'];
            }
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'User not found!'];
    }
} else {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid user ID!'];
}

header('Location: manage-users.php');
exit;
?>