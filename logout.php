<?php
// logout.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destroi a sessão
session_unset();
session_destroy();

// Remove o cookie configurando para o passado
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/');
}

// Redireciona para o login
header("Location: login.php");
exit;