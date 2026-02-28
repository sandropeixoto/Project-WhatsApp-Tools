<?php
$pageTitle = 'Grupos';
require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<div class="app-content">
    <main class="flex-grow-1 overflow-auto bg-light p-4">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold"><i class="bi bi-people-fill text-success me-2"></i> Meus Grupos</h3>
                <div class="d-flex gap-2">
                    <span id="sync-groups-status" class="small align-self-center"></span>
                    <button class="btn btn-wa px-4 rounded-pill shadow-sm" id="btn-sync-groups" onclick="syncGroups()">
                        <i class="bi bi-arrow-repeat me-2"></i> Atualizar Grupos
                    </button>
                </div>
            </div>

            <div id="groups-list" class="bg-white rounded-3 shadow-sm overflow-hidden">
                <div class="text-center py-5">
                    <div class="spinner-border text-success spinner-border-sm" role="status"></div>
                    <div class="mt-2 text-muted small">Carregando grupos...</div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Aguarda a inicialização do app.js para ter o activeInstanceName
        setTimeout(() => {
            const selector = document.getElementById('instance-selector');
            if (selector && selector.value) {
                loadGroups();
            }
        }, 500);
    });
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
