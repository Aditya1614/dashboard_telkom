<?php
// db_connect.php
$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'dashboard_telkom';

// Create a connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
