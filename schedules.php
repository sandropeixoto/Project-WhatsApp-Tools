<?php
$pageTitle = 'Agendamentos';
require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>
<main class="flex-grow-1 overflow-auto bg-light chat-bg p-3">
    <div class="container-fluid max-w-1200">
        <div class="d-flex justify-content-between align-items-center mb-3 bg-white p-3 rounded shadow-sm">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-calendar-check text-primary me-2"></i>Agendamentos</h5>
            <button class="btn btn-primary btn-sm px-3 fw-semibold shadow-sm" onclick="loadSchedules()">
                <i class="bi bi-arrow-repeat me-1"></i>Atualizar
            </button>
        </div>
        <div class="bg-white rounded shadow-sm overflow-hidden">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="border-0 text-muted small fw-semibold text-uppercase px-3">Data Agendada</th>
                        <th class="border-0 text-muted small fw-semibold text-uppercase">Tipo</th>
                        <th class="border-0 text-muted small fw-semibold text-uppercase">Detalhes</th>
                        <th class="border-0 text-muted small fw-semibold text-uppercase">Status</th>
                        <th class="border-0 text-muted small fw-semibold text-uppercase text-end px-3">Ações</th>
                    </tr>
                </thead>
                <tbody id="schedules-list">
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted small">Carregando agendamentos...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            if (activeInstanceName) {
                loadSchedules();
            }
        }, 500);
    });
</script>
<?php require_once __DIR__ . '/components/footer.php'; ?>