<?php
// instances.php — Tela de Seleção de Instâncias
require_once __DIR__ . '/db.php';

$API_BASE_URL = "https://sspeixoto.uazapi.com";
$ADMIN_TOKEN = "4cFCOnaDoBvSuhytYRRT5RaTRNSxP0ornjJDv9TdLvxmmaHDFO";

// --- ROTA: Sync Instâncias ---
if (isset($_GET['action']) && $_GET['action'] === 'sync_instances') {
    header('Content-Type: application/json');
    $ch = curl_init("{$API_BASE_URL}/instance/all");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'token: ' . $ADMIN_TOKEN]);
    $response = curl_exec($ch);
    curl_close($ch);

    $instances = json_decode($response, true);
    if (!is_array($instances)) {
        echo json_encode(['error' => 'Resposta inválida da API', 'count' => 0]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO uazapi_instances
        (name, token, status, profile_name, profile_pic_url, phone_number, is_business, platform)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            token = VALUES(token), status = VALUES(status), profile_name = VALUES(profile_name),
            profile_pic_url = VALUES(profile_pic_url), phone_number = VALUES(phone_number),
            is_business = VALUES(is_business), platform = VALUES(platform)");

    foreach ($instances as $inst) {
        $name = $inst['name'] ?? '';
        if (empty($name))
            continue;
        $token = $inst['token'] ?? '';
        $status = $inst['status'] ?? 'disconnected';
        $profileName = $inst['profileName'] ?? '';
        $profilePicUrl = $inst['profilePicUrl'] ?? '';
        $owner = $inst['owner'] ?? '';
        $phone = preg_replace('/[^0-9]/', '', explode('@', $owner)[0] ?? '');
        $isBusiness = !empty($inst['isBusiness']) ? 1 : 0;
        $platform = $inst['plataform'] ?? '';
        $stmt->execute([$name, $token, $status, $profileName, $profilePicUrl, $phone, $isBusiness, $platform]);
    }

    echo json_encode(['success' => true, 'count' => count($instances)]);
    exit;
}

// --- ROTA: Get Instâncias ---
if (isset($_GET['action']) && $_GET['action'] === 'get_instances') {
    header('Content-Type: application/json');
    $stmt = $pdo->query("SELECT name, status, profile_name, profile_pic_url, phone_number, is_business, platform FROM uazapi_instances ORDER BY name ASC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Tools — Instâncias</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --wa-primary: #00a884;
            --wa-dark: #075e54;
            --wa-teal: #128c7e;
            --wa-accent: #25d366;
            --wa-bg: #f0f2f5;
            --wa-chat-bg: #efeae2;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--wa-bg);
            min-height: 100vh;
        }

        .navbar-wa {
            background: linear-gradient(135deg, var(--wa-dark), var(--wa-teal));
        }

        .btn-wa {
            background-color: var(--wa-primary);
            border-color: var(--wa-primary);
            color: #fff;
        }

        .btn-wa:hover {
            background-color: var(--wa-dark);
            border-color: var(--wa-dark);
            color: #fff;
        }

        .btn-wa:disabled {
            background-color: var(--wa-teal);
            border-color: var(--wa-teal);
            opacity: 0.7;
        }

        .instance-card {
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            border: 2px solid transparent;
        }

        .instance-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 168, 132, 0.18);
            border-color: var(--wa-primary);
        }

        .card-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--wa-bg);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            border: 2px solid #fff;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.1);
        }

        .status-dot.online {
            background-color: var(--wa-accent);
        }

        .status-dot.offline {
            background-color: #dc3545;
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 48px;
            color: var(--wa-primary);
            margin-bottom: 16px;
        }

        footer {
            color: #adb5bd;
            font-size: 12px;
        }
    </style>
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar navbar-wa py-3 shadow-sm">
        <div class="container">
            <span class="navbar-brand text-white d-flex align-items-center gap-2 fw-bold">
                <i class="bi bi-whatsapp fs-4"></i>
                WhatsApp Tools
            </span>
            <button class="btn btn-wa btn-sm d-flex align-items-center gap-2" id="btn-sync" onclick="syncAndRefresh()">
                <i class="bi bi-arrow-clockwise"></i>
                <span>Atualizar Instâncias</span>
            </button>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container py-4">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1">Suas Instâncias</h4>
                <p class="text-muted mb-0 small">Selecione uma instância para gerenciar</p>
            </div>
            <span class="badge bg-secondary" id="count-badge">—</span>
        </div>

        <div class="row g-3" id="instances-grid">
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-success" role="status"></div>
                <div class="mt-2 text-muted">Carregando instâncias...</div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="text-center py-3">
        WhatsApp Tools &copy; 2025
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function escapeHTML(str) {
            if (!str) return '';
            return str.replace(/[&<>'"]/g, t => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[t]));
        }

        function renderCards(instances) {
            const grid = document.getElementById('instances-grid');
            document.getElementById('count-badge').textContent = instances.length + ' instância(s)';

            if (instances.length === 0) {
                grid.innerHTML = `
                    <div class="col-12 empty-state">
                        <i class="bi bi-phone"></i>
                        <h5>Nenhuma instância encontrada</h5>
                        <p class="mb-3">Clique em <strong>"Atualizar Instâncias"</strong> para sincronizar com a API.</p>
                        <button class="btn btn-wa" onclick="syncAndRefresh()">
                            <i class="bi bi-arrow-clockwise me-1"></i> Sincronizar Agora
                        </button>
                    </div>`;
                return;
            }

            let html = '';
            instances.forEach(inst => {
                const pic = inst.profile_pic_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(inst.profile_name || inst.name)}&background=128c7e&color=fff&size=56`;
                const isOnline = inst.status === 'connected';
                const statusDot = `<span class="status-dot ${isOnline ? 'online' : 'offline'}" title="${isOnline ? 'Online' : 'Offline'}"></span>`;
                const statusText = isOnline
                    ? '<span class="text-success fw-semibold small">Online</span>'
                    : '<span class="text-danger small">Offline</span>';
                const phone = inst.phone_number ? `+${inst.phone_number}` : '';
                const profileName = inst.profile_name || '';
                const bizBadge = inst.is_business == 1
                    ? '<span class="badge bg-primary-subtle text-primary-emphasis ms-1"><i class="bi bi-building"></i> Business</span>'
                    : '';

                html += `
                <div class="col-sm-6 col-md-4 col-lg-3">
                    <div class="card instance-card h-100" onclick="window.location='index.php?instance=${encodeURIComponent(inst.name)}'">
                        <div class="card-body d-flex align-items-start gap-3">
                            <div class="position-relative flex-shrink-0">
                                <img class="card-avatar" src="${pic}" alt=""
                                     onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(inst.name)}&background=128c7e&color=fff'">
                                <span class="position-absolute bottom-0 end-0 status-dot ${isOnline ? 'online' : 'offline'}"></span>
                            </div>
                            <div class="flex-grow-1 min-width-0">
                                <div class="fw-bold text-truncate" title="${escapeHTML(inst.name)}">${escapeHTML(inst.name)}</div>
                                ${profileName ? `<div class="text-muted small text-truncate">${escapeHTML(profileName)}</div>` : ''}
                                ${phone ? `<div class="text-muted small"><i class="bi bi-telephone"></i> ${phone}</div>` : ''}
                                <div class="mt-1 d-flex align-items-center gap-1 flex-wrap">
                                    ${statusText}${bizBadge}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
            });
            grid.innerHTML = html;
        }

        async function loadInstances() {
            try {
                const res = await fetch('instances.php?action=get_instances');
                const data = await res.json();
                renderCards(data);
            } catch (e) {
                document.getElementById('instances-grid').innerHTML = `
                    <div class="col-12 text-center py-4">
                        <div class="text-danger"><i class="bi bi-exclamation-triangle fs-3"></i></div>
                        <p class="text-danger mt-2">Erro ao carregar instâncias.</p>
                    </div>`;
            }
        }

        async function syncAndRefresh() {
            const btn = document.getElementById('btn-sync');
            const icon = btn.querySelector('i');
            const text = btn.querySelector('span');
            btn.disabled = true;
            icon.classList.add('spin');
            text.textContent = 'Sincronizando...';

            try {
                await fetch('instances.php?action=sync_instances');
                await loadInstances();
            } catch (e) { /* silent */ }
            finally {
                btn.disabled = false;
                icon.classList.remove('spin');
                text.textContent = 'Atualizar Instâncias';
            }
        }

        // Spin animation for sync icon
        const style = document.createElement('style');
        style.textContent = `@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}.spin{animation:spin 1s linear infinite}`;
        document.head.appendChild(style);

        loadInstances();
    </script>
</body>

</html>