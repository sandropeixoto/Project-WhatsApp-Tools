<?php
$pageTitle = 'Enviar Mensagem';
require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>
<main class="flex-grow-1 overflow-auto bg-light p-3">
    <div class="container-fluid max-w-800" style="max-width: 800px; margin: 0 auto;">

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                <h5 class="card-title fw-bold mb-0"><i class="bi bi-send-fill text-success me-2"></i>Nova Mensagem</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted">Número (DDNNNNNNNNN)</label>
                    <input type="text" id="msg-number" class="form-control form-control-lg fs-6 bg-light border-0"
                        placeholder="Ex: 5511999999999">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted">Mensagem</label>
                    <textarea id="msg-text" class="form-control bg-light border-0" rows="4"
                        placeholder="Digite sua mensagem..."></textarea>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted"><i class="bi bi-clock me-1"></i>Agendar
                            Envio</label>
                        <select id="msg-schedule" class="form-select bg-light border-0">
                            <option value="now">Enviar Agora</option>
                            <option value="+5min">Daqui a 5 minutos</option>
                            <option value="+10min">Daqui a 10 minutos</option>
                            <option value="+30min">Daqui a 30 minutos</option>
                            <option value="+1hour">Daqui a 1 hora</option>
                            <option value="+2hours">Daqui a 2 horas</option>
                            <option value="tomorrow_8">Amanhã às 08:00</option>
                            <option value="tomorrow_same">Amanhã neste horário</option>
                        </select>
                    </div>
                </div>

                <div id="send-status" class="mb-3 small fw-semibold"></div>

                <div class="d-grid pt-2">
                    <button class="btn btn-success btn-lg fw-semibold" onclick="sendMessage()">
                        <i class="bi bi-paperclip me-2" style="rotate: -45deg;"></i>Enviar Mensagem
                    </button>
                </div>
            </div>
        </div>

        <!-- Recentes Agendamentos de Mensagem -->
        <div class="card border-0 shadow-sm">
            <div
                class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                <h6 class="card-title fw-bold mb-0 text-muted"><i class="bi bi-clock-history me-2"></i>Agendamentos
                    Recentes (Mensagens)</h6>
                <button class="btn btn-sm btn-light text-primary fw-semibold" onclick="loadLocalSchedules('message')"><i
                        class="bi bi-arrow-repeat"></i></button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle small">
                        <thead class="table-light">
                            <tr>
                                <th class="border-0 px-4">Data/Hora</th>
                                <th class="border-0">Destino</th>
                                <th class="border-0">Status</th>
                                <th class="border-0 text-end px-4">Ação</th>
                            </tr>
                        </thead>
                        <tbody id="local-schedules-list">
                            <tr>
                                <td colspan="4" class="text-center py-3 text-muted">Carregando...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</main>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // As a fallback/delayed execution after Vue/jQuery if present, but since it's vanilla:
        setTimeout(() => {
            if (activeInstanceName) {
                loadLocalSchedules('message');
            } else {
                // If the app.js initFeed hasn't set it yet, the UI might show 'Select an instance'
                // app.js has its own hook, we can let it trigger or add a listener
            }
        }, 500);
    });
</script>
<?php require_once __DIR__ . '/components/footer.php'; ?>