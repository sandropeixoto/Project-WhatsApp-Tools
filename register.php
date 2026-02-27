<?php
// register.php
require_once 'api/auth.php'; // Somente usuários logados podem registrar outros
require_once 'db.php'; // Usar o novo arquivo de conexão se existir, ou pode ser redundante com auth.php

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($email) || empty($password) || empty($password_confirm)) {
        $error = 'Por favor, preencha todos os campos.';
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Formato de e-mail inválido.';
    }
    elseif ($password !== $password_confirm) {
        $error = 'As senhas não coincidem.';
    }
    elseif (strlen($password) < 6) {
        $error = 'A senha deve ter pelo menos 6 caracteres.';
    }
    else {
        try {
            // Verifica se e-mail já existe
            $stmt = $pdo->prepare("SELECT id FROM system_users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Este e-mail já está cadastrado.';
            }
            else {
                // Insere usuário
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO system_users (email, password_hash) VALUES (?, ?)");
                $stmt->execute([$email, $hash]);
                $success = 'Usuário cadastrado com sucesso!';
            }
        }
        catch (PDOException $e) {
            $error = 'Erro ao cadastrar usuário. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro Oficial — WhatsApp Tools</title>
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

        .register-card {
            width: 100%;
            max-width: 450px;
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .register-header {
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

        .btn-wa-outline {
            border: 1px solid #25D366;
            color: #128C7E;
            font-weight: 600;
            background-color: transparent;
        }

        .btn-wa-outline:hover {
            background-color: #f0fdf4;
            color: #128C7E;
        }
    </style>
</head>

<body>

    <div class="card register-card">
        <div class="register-header">
            <h4 class="mb-0 fw-bold">WhatsApp Tools</h4>
            <p class="mb-0 mt-2 opacity-75 small">Cadastrar Novo Usuário</p>
        </div>
        <div class="card-body p-4">
            <?php if ($error): ?>
            <div class="alert alert-danger py-2 px-3 small">
                <?= htmlspecialchars($error)?>
            </div>
            <?php
endif; ?>
            <?php if ($success): ?>
            <div class="alert alert-success py-2 px-3 small">
                <?= htmlspecialchars($success)?>
            </div>
            <?php
endif; ?>

            <form method="POST" action="register.php">
                <div class="mb-3">
                    <label for="email" class="form-label text-muted small fw-semibold">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email" required autofocus
                        placeholder="nome@empresa.com">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label text-muted small fw-semibold">Senha</label>
                    <input type="password" class="form-control" id="password" name="password" required
                        placeholder="Crie uma senha forte">
                </div>
                <div class="mb-4">
                    <label for="password_confirm" class="form-label text-muted small fw-semibold">Confirme a
                        Senha</label>
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required
                        placeholder="Repita a senha">
                </div>
                <button type="submit" class="btn btn-wa w-100 py-2 mb-2">Cadastrar Usuário</button>
                <a href="index.php" class="btn btn-wa-outline w-100 py-2">Voltar ao Painel</a>
            </form>
        </div>
    </div>

</body>

</html>