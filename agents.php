<?php
$pageTitle = 'Agentes de IA';
require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>
<main class="flex-grow-1 overflow-auto bg-light chat-bg p-3">
    <div class="container-fluid max-w-1200">
        <div class="d-flex justify-content-between align-items-center mb-3 bg-white p-3 rounded shadow-sm">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-robot text-warning me-2"></i>Agentes de IA (OpenCode)
            </h5>
            <div>
                <button class="btn btn-outline-secondary btn-sm px-3 fw-semibold shadow-sm me-2" onclick="loadAgents()">
                    <i class="bi bi-arrow-repeat me-1"></i>Atualizar
                </button>
                <button class="btn btn-primary btn-sm px-3 fw-semibold shadow-sm" data-bs-toggle="modal"
                    data-bs-target="#newAgentModal">
                    <i class="bi bi-plus-lg me-1"></i>Novo Agente
                </button>
            </div>
        </div>

        <div class="bg-white rounded shadow-sm overflow-hidden">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="border-0 text-muted small fw-semibold px-3">Nome</th>
                        <th class="border-0 text-muted small fw-semibold">Destinatário</th>
                        <th class="border-0 text-muted small fw-semibold">Intervalo (min)</th>
                        <th class="border-0 text-muted small fw-semibold">Revisar?</th>
                        <th class="border-0 text-muted small fw-semibold">Status</th>
                        <th class="border-0 text-muted small fw-semibold text-end px-3">Ações</th>
                    </tr>
                </thead>
                <tbody id="agents-list">
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted small">Carregando agentes...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal Novo Agente -->
<div class="modal fade" id="newAgentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-robot me-2 text-warning"></i>Criar Novo Agente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light">
                <form id="new-agent-form">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1 text-uppercase">Nome do Agente
                                *</label>
                            <input type="text" class="form-control bg-white" id="agent-name"
                                placeholder="Ex: Versículos Diários" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1 text-uppercase">Destinatário
                                (Número/JID) *</label>
                            <input type="text" class="form-control bg-white" id="agent-recipient"
                                placeholder="Ex: 5511999999999" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted mb-1 text-uppercase">Tema / Prompt para IA
                                *</label>
                            <textarea class="form-control bg-white" id="agent-prompt" rows="3"
                                placeholder="Ex: Escreva uma curta mensagem matinal animadora com um versículo bíblico."
                                required></textarea>
                            <div class="form-text small"><i class="bi bi-info-circle me-1"></i>Seja criativo e direto no
                                que deseja gerar a cada ciclo.</div>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label small fw-bold text-muted mb-1 text-uppercase">Ciclo/Intervalo</label>
                            <div class="input-group">
                                <input type="number" class="form-control bg-white" id="agent-interval" value="60"
                                    min="1" required>
                                <span class="input-group-text bg-white">min</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1 text-uppercase">Não disparar
                                entre:</label>
                            <input type="text" class="form-control bg-white" id="agent-restricted"
                                placeholder="Ex: 22:00-08:00">
                            <div class="form-text small">Horário restrito (Opcional)</div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="agent-review" checked>
                                <label class="form-check-label fw-semibold" for="agent-review">Requer Revisão Antes do
                                    Envio</label>
                                <div class="form-text small mt-0">A msg vai para "Agendamentos" pausada.</div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 bg-white">
                <button type="button" class="btn btn-light shadow-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary fw-semibold shadow-sm px-4" id="btn-save-agent">
                    <i class="bi bi-check2-circle me-1"></i>Criar Agente
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            if (activeInstanceName) {
                loadAgents();
            }
        }, 500);

        const btnSave = document.getElementById('btn-save-agent');
        if (btnSave) {
            btnSave.addEventListener('click', createAgent);
        }
    });
</script>
<?php require_once __DIR__ . '/components/footer.php'; ?>