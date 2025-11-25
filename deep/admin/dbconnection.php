<?php
// admin/dbconnection.php
$con = mysqli_connect("localhost", "root", "", "alphasonix_crm");
if(mysqli_connect_errno()) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>