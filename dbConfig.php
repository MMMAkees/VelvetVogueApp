<?php
$servername = "sqlXXX.infinityfree.com";
$username = "if0_12345";
$password = "your_password";
$dbname = "if0_12345_velvetvogue_db";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
