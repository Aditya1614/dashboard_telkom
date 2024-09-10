<?php
session_start();

// Switch between tables 'days' and 'hours'
$_SESSION['currentTable'] = isset($_POST['table']) ? $_POST['table'] : 'days';

// Redirect back to the main page
header('Location: dashboard.php');
exit;
