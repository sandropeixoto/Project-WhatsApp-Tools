<?php
require_once __DIR__ . '/../api/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_GET['instance']) && !empty(trim($_GET['instance']))) {
    $_SESSION['active_instance'] = trim($_GET['instance']);
    // Optional: redirect to remove "?instance=..." from URL for cleaner address bar
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

if (!isset($_SESSION['active_instance'])) {
    header('Location: instances.php');
    exit;
}

$activeInstanceName = $_SESSION['active_instance'];
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Tools —
        <?php echo $pageTitle ?? 'Painel'; ?>
    </title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-wa shadow-sm px-3 py-2 flex-shrink-0">
        <div class="d-flex align-items-center w-100 justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light btn-sm fw-semibold px-2 text-success shadow-sm" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#sidebar">
                    <i class="bi bi-list fs-5"></i>
                </button>
            </div>

            <div class="d-flex align-items-center">
                <div class="text-end me-3 d-none d-sm-block">
                    <div class="fw-bold lh-1 mb-1 fs-6" id="topbar-name">Carregando...</div>
                    <div class="small text-white-50 lh-1" id="topbar-phone"></div>
                </div>
                <img id="topbar-avatar" src="https://ui-avatars.com/api/?name=WA&background=128c7e&color=fff"
                    class="rounded-circle border border-2 border-white border-opacity-50"
                    style="width: 44px; height: 44px; object-fit: cover;" alt="">
            </div>
        </div>
    </nav>
    <input type="hidden" id="instance-selector" value="<?php echo htmlspecialchars($activeInstanceName); ?>">