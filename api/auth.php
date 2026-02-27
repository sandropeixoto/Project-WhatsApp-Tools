<?php
// api/auth.php

// Inicia a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Requer conexão com o banco de dados
require_once __DIR__ . '/../db.php';

function isAuthenticated($pdo)
{
    // 1. Verifica sessão PHP
    if (isset($_SESSION['user_id'])) {
        return true;
    }

    // 2. Verifica cookie de "lembrar-me" de 30 dias
    if (isset($_COOKIE['remember_me'])) {
        $cookie_parts = explode(':', $_COOKIE['remember_me']);
        if (count($cookie_parts) === 2) {
            $user_id = (int)$cookie_parts[0];
            $token = $cookie_parts[1];

            try {
                $stmt = $pdo->prepare("SELECT password_hash FROM system_users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                if ($user) {
                    // Valida o token do cookie (assinatura usando hash da senha)
                    $expected_token = hash_hmac('sha256', $user_id, $user['password_hash']);
                    if (hash_equals($expected_token, $token)) {
                        // Loga o usuário de volta na sessão
                        $_SESSION['user_id'] = $user_id;
                        return true;
                    }
                }
            }
            catch (PDOException $e) {
                // Erro na consulta
                return false;
            }
        }
    }
    return false;
}

if (!isAuthenticated($pdo)) {
    // Redireciona para o login se não estiver autenticado
    header("Location: login.php");
    exit;
}