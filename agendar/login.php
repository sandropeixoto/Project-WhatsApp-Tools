<?php
// agendar/login.php
session_start();
require_once __DIR__ . '/../db.php';

// Check if already logged in via cookie
if (isset($_COOKIE['agendar_session']) && !isset($_SESSION['agendar_user'])) {
    $stmt = $pdo->prepare("
        SELECT u.*, a.instance_name 
        FROM agendar_sessions s
        JOIN agendar_users u ON s.user_id = u.id
        JOIN agendar_accounts a ON u.account_id = a.id
        WHERE s.id = ? AND s.expires_at >= NOW()
    ");
    $stmt->execute([$_COOKIE['agendar_session']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['agendar_user'] = $user;
        header("Location: index.php");
        exit;
    }
}
else if (isset($_SESSION['agendar_user'])) {
    header("Location: index.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Módulo Agendar</title>
    <!-- Mantenha a versão do Bootstrap 5 coerente com o WA Tools -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            max-width: 400px;
            width: 100%;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            background: #fff;
        }

        .wa-primary {
            background-color: #25D366;
            border-color: #25D366;
        }

        .wa-primary:hover {
            background-color: #128C7E;
            border-color: #128C7E;
        }
    </style>
</head>

<body>

    <div class="login-card text-center">
        <h3 class="mb-4">Agendar WhatsApp</h3>
        <p class="text-muted mb-4">Faça login sem senha recebendo um código pelo WhatsApp.</p>

        <div id="alertBox" class="alert d-none"></div>

        <form id="step1Form">
            <div class="mb-3 text-start">
                <label class="form-label" for="phoneInput">Número do WhatsApp</label>
                <input type="text" class="form-control" id="phoneInput" placeholder="+55 (11) 99999-9999" required>
                <small class="text-muted">O código do país (+55) é automático.</small>
            </div>
            <button type="submit" class="btn btn-primary w-100 wa-primary" id="btnSendToken">
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                Enviar Código
            </button>
        </form>

        <form id="step2Form" class="d-none">
            <div class="mb-3 text-start">
                <label class="form-label" for="tokenInput">Código de 6 dígitos</label>
                <input type="text" class="form-control text-center fs-4" id="tokenInput" placeholder="000000"
                    maxlength="6" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 wa-primary" id="btnVerifyToken">
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                Confirmar e Entrar
            </button>
            <button type="button" class="btn btn-link w-100 mt-2 text-decoration-none text-muted" id="btnGoBack">
                Voltar
            </button>
        </form>
    </div>

    <script>
        const step1Form = document.getElementById('step1Form');
        const step2Form = document.getElementById('step2Form');
        const phoneInput = document.getElementById('phoneInput');
        const tokenInput = document.getElementById('tokenInput');
        const btnSendToken = document.getElementById('btnSendToken');
        const btnVerifyToken = document.getElementById('btnVerifyToken');
        const alertBox = document.getElementById('alertBox');
        const btnGoBack = document.getElementById('btnGoBack');

        phoneInput.addEventListener('input', function (e) {
            let val = e.target.value.replace(/\D/g, '');
            if (!val) {
                e.target.value = '';
                return;
            }
            if (!val.startsWith('55')) val = '55' + val;
            val = val.substring(0, 13);

            let res = '+55 ';
            if (val.length > 2) res += '(' + val.substring(2, 4);
            if (val.length > 4) res += ') ' + val.substring(4, 9);
            if (val.length > 9) res += '-' + val.substring(9, 13);

            e.target.value = res;
        });

        let currentPhone = '';

        function showAlert(message, type = 'danger') {
            alertBox.className = `alert alert-${type} mb-3`;
            alertBox.innerText = message;
        }

        step1Form.addEventListener('submit', async (e) => {
            e.preventDefault();
            currentPhone = phoneInput.value.replace(/\D/g, '');
            if (!currentPhone) {
                showAlert('Digite um número válido');
                return;
            }

            btnSendToken.disabled = true;
            btnSendToken.querySelector('.spinner-border').classList.remove('d-none');
            alertBox.classList.add('d-none');

            try {
                const res = await fetch('api/auth.php?action=send_token', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ phone: currentPhone })
                });
                const data = await res.json();

                if (res.ok) {
                    showAlert('Código enviado para seu WhatsApp!', 'success');
                    step1Form.classList.add('d-none');
                    step2Form.classList.remove('d-none');
                    tokenInput.focus();
                } else {
                    showAlert(data.error || 'Falha ao enviar token');
                }
            } catch (error) {
                showAlert('Erro de conexão com o servidor');
            } finally {
                btnSendToken.disabled = false;
                btnSendToken.querySelector('.spinner-border').classList.add('d-none');
            }
        });

        step2Form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const token = tokenInput.value.replace(/\D/g, '');
            if (token.length !== 6) {
                showAlert('O código deve ter 6 dígitos');
                return;
            }

            btnVerifyToken.disabled = true;
            btnVerifyToken.querySelector('.spinner-border').classList.remove('d-none');
            alertBox.classList.add('d-none');

            try {
                const res = await fetch('api/auth.php?action=verify_token', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ phone: currentPhone, token })
                });
                const data = await res.json();

                if (res.ok) {
                    window.location.href = 'index.php'; // Redirect properly
                } else {
                    showAlert(data.error || 'Código inválido');
                }
            } catch (error) {
                showAlert('Erro de conexão com o servidor');
            } finally {
                btnVerifyToken.disabled = false;
                btnVerifyToken.querySelector('.spinner-border').classList.add('d-none');
            }
        });

        btnGoBack.addEventListener('click', () => {
            step2Form.classList.add('d-none');
            step1Form.classList.remove('d-none');
            alertBox.classList.add('d-none');
            tokenInput.value = '';
        });
    </script>
</body>

</html>