<?php
$pageTitle = 'Agentes de IA';
require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<div class="app-content">
    <main class="flex-grow-1 overflow-auto bg-light p-4">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold"><i class="bi bi-robot text-success me-2"></i> Agentes de IA</h3>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary px-4 rounded-pill shadow-sm" onclick="loadAgents()">
                        <i class="bi bi-arrow-repeat me-2"></i> Atualizar
                    </button>
                    <button class="btn btn-wa px-4 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#newAgentModal">
                        <i class="bi bi-plus-lg me-2"></i> Novo Agente
                    </button>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Nome / Prompt</th>
                                <th>Destinatário</th>
                                <th class="text-center">Intervalo</th>
                                <th class="text-center">Revisar?</th>
                                <th class="text-center">Status</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="agents-list">
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <div class="spinner-border text-success spinner-border-sm me-2" role="status"></div>
                                    Carregando agentes...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal Novo Agente (Mantido layout original ajustado) -->
<div class="modal fade" id="newAgentModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-wa-gradient text-white p-4" style="background: linear-gradient(135deg, var(--wa-primary), var(--wa-teal));">
                <h5 class="modal-title fw-bold"><i class="bi bi-robot me-2"></i><span id="agent-modal-title">Criar Novo Agente</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <form id="new-agent-form">
                    <input type="hidden" id="agent-id" value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1 text-uppercase">Nome do Agente *</label>
                            <input type="text" class="form-control border-0 shadow-sm py-2 px-3" id="agent-name" placeholder="Ex: Versículos Diários" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1 text-uppercase">Destinatário (Número/JID) *</label>
                            <input type="text" class="form-control border-0 shadow-sm py-2 px-3" id="agent-recipient" placeholder="Ex: 5511999999999" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted mb-1 text-uppercase">Tema / Prompt para IA *</label>
                            <textarea class="form-control border-0 shadow-sm py-2 px-3" id="agent-prompt" rows="3" placeholder="Ex: Escreva uma curta mensagem matinal animadora com um versículo bíblico." required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1 text-uppercase">Intervalo</label>
                            <div class="input-group shadow-sm rounded">
                                <input type="number" class="form-control border-0 py-2 px-3" id="agent-interval" value="60" min="1" required>
                                <span class="input-group-text border-0 bg-white text-muted">min</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1 text-uppercase">Horário Restrito</label>
                            <input type="text" class="form-control border-0 shadow-sm py-2 px-3" id="agent-restricted" placeholder="Ex: 22:00-08:00">
                        </div>
                        <div class="col-md-4 d-flex align-items-end pb-1">
                            <div class="form-check form-switch custom-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="agent-review" checked>
                                <label class="form-check-label fw-semibold text-muted small" for="agent-review">Requer Revisão</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 bg-white p-4">
                <button type="button" class="btn btn-light px-4 rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-wa px-4 rounded-pill shadow-sm" id="btn-save-agent">
                    <i class="bi bi-check2-circle me-2"></i><span id="agent-btn-text">Criar Agente</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        loadAgents();

        const newAgentModal = document.getElementById('newAgentModal');
        if (newAgentModal) {
            newAgentModal.addEventListener('hidden.bs.modal', function () {
                document.getElementById('new-agent-form').reset();
                document.getElementById('agent-id').value = '';
                document.getElementById('agent-modal-title').textContent = 'Criar Novo Agente';
                document.getElementById('agent-btn-text').textContent = 'Criar Agente';
            });
        }

        const btnSave = document.getElementById('btn-save-agent');
        if (btnSave) {
            btnSave.addEventListener('click', createAgent);
        }
    });
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
