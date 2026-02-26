<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/whatsapp/agendar/index.php';

// Mock the user so auth_middleware passes
session_start();
$_SESSION['agendar_user'] = ['id' => 1, 'account_id' => 1, 'role' => 'owner'];

require 'agendar/index.php';
