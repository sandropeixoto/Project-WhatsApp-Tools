<?php
// login.php

require_once 'api/auth.php'; // Usa o middleware para check, mas vamos lidar com a lógica aqui

// Se já estiver logado, manda pro index
if (isAuthenticated($pdo)) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'Por favor, preencha todos os campos.';
    }
    else {
        try {
            $stmt = $pdo->prepare("SELECT id, password_hash FROM system_users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Sucesso no login
                $_SESSION['user_id'] = $user['id'];

                if ($remember) {
                    // Cookie para 30 dias
                    $token = hash_hmac('sha256', $user['id'], $user['password_hash']);
                    $cookie_value = $user['id'] . ':' . $token;
                    setcookie('remember_me', $cookie_value, time() + (86400 * 30), "/"); // 86400 = 1 dia
                }

                header("Location: index.php");
                exit;
            }
            else {
                $error = 'E-mail ou senha inválidos.';
            }
        }
        catch (PDOException $e) {
            $error = 'Erro ao processar login. Tente novamente mais tarde.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — WhatsApp Tools</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .login-header {
            background-color: #128C7E;
            color: white;
            padding: 24px;
            border-radius: 12px 12px 0 0;
            text-align: center;
        }

        .btn-wa {
            background-color: #25D366;
            color: white;
            font-weight: 600;
        }

        .btn-wa:hover {
            background-color: #128C7E;
            color: white;
        }
    </style>
</head>

<body>

    <div class="card login-card">
        <div class="login-header">
            <h4 class="mb-0 fw-bold">WhatsApp Tools</h4>
            <p class="mb-0 mt-2 opacity-75 small">Acesso Restrito</p>
        </div>
        <div class="card-body p-4">
            <?php if ($error): ?>
            <div class="alert alert-danger py-2 px-3 small">
                <?= htmlspecialchars($error)?>
            </div>
            <?php
endif; ?>

            <form method="POST" action="login.php">
                <div class="mb-3">
                    <label for="email" class="form-label text-muted small fw-semibold">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email" required autofocus
                        placeholder="nome@empresa.com">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label text-muted small fw-semibold">Senha</label>
                    <input type="password" class="form-control" id="password" name="password" required
                        placeholder="Sua senha">
                </div>
                <div class="mb-4 form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label text-muted small" for="remember">Permanecer conectado (30
                        dias)</label>
                </div>
                <button type="submit" class="btn btn-wa w-100 py-2">Entrar</button>
            </form>
        </div>
    </div>

</body>

</html>