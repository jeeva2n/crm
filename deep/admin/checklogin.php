<?php
// admin/checklogin.php
function check_login() {
    if(!isset($_SESSION['id']) || strlen($_SESSION['id']) == 0) {	
        $host = $_SERVER['HTTP_HOST'];
        $uri  = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $extra = "index.php";		
        header("Location: http://$host$uri/$extra");
        exit;
    }
}
?>