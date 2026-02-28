<?php
$pageTitle = 'Agendamentos';
require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<div class="app-content">
    <main class="flex-grow-1 overflow-auto bg-light p-4">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold"><i class="bi bi-calendar-check text-success me-2"></i> Agendamentos</h3>
                <button class="btn btn-wa px-4 rounded-pill shadow-sm" onclick="loadSchedules()">
                    <i class="bi bi-arrow-repeat me-2"></i> Atualizar
                </button>
            </div>

            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>Status</th>
                                <th>Tipo</th>
                                <th>Agendado Para</th>
                                <th>Detalhes</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="schedules-list">
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <div class="spinner-border text-success spinner-border-sm me-2" role="status"></div>
                                    Carregando agendamentos...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            const selector = document.getElementById('instance-selector');
            if (selector && selector.value) {
                loadSchedules();
            }
        }, 500);
    });
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
