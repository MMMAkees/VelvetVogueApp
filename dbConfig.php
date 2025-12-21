<?php
$servername = "sql301.byetcluster.com";
$username   = "if0_40730252";  
$password   = "akees200416039";
$dbname     = "if0_40730252_velvetvoguedb";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>
