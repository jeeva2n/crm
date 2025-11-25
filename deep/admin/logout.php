<?php
session_start();
session_unset();
session_destroy();

$_SESSION['message'] = ['type' => 'success', 'text' => 'You have been logged out successfully.'];
header('Location: index.php');
exit;
?>