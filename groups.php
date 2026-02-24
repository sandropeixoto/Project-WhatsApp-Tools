<?php
$pageTitle = 'Grupos';
require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>
<main class="flex-grow-1 overflow-auto bg-light chat-bg p-3">
    <div class="container-fluid max-w-1200">
        <div class="d-flex justify-content-between align-items-center mb-3 bg-white p-3 rounded shadow-sm">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-people-fill text-primary me-2"></i>Meus Grupos</h5>
            <button class="btn btn-primary btn-sm px-3 fw-semibold shadow-sm" onclick="syncGroups()">
                <i class="bi bi-arrow-repeat me-1"></i>Sincronizar
            </button>
        </div>
        <div id="groups-list" class="row g-3"></div>
    </div>
</main>
<?php require_once __DIR__ . '/components/footer.php'; ?>