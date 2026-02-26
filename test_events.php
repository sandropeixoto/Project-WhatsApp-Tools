<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'list';
$skip_auth_redirect = true;

// Mock the user so auth_middleware passes
session_start();
$_SESSION['agendar_user'] = ['id' => 1, 'account_id' => 1, 'role' => 'owner'];

// Capture output
ob_start();
try {
    require 'agendar/api/events.php';
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
$output = ob_get_clean();
echo "OUTPUT:\n" . $output;
