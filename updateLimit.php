<?php
session_start();

// Update the session with the selected limit
$_SESSION['limit'] = isset($_POST['limit']) ? intval($_POST['limit']) : 10;

// Redirect back to the main page
header('Location: index.php');
exit;
