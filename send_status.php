<?php
$pageTitle = 'Enviar Status';
require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<div class="app-content">
    <main class="flex-grow-1 overflow-auto bg-light p-4">
        <div class="container-fluid" style="max-width: 800px; margin: 0 auto;">

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                    <h5 class="card-title fw-bold mb-0"><i class="bi bi-broadcast text-primary me-2"></i>Criar Status /
                        Story</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Ação desejada</label>
                        <select id="status-type" class="form-select form-select-lg fs-6 bg-light border-0 mb-3 shadow-none"
                            onchange="toggleStatusFields()">
                            <option value="text">Status de Texto (com fundo colorido)</option>
                            <option value="image">Imagem Local (Upload via Link)</option>
                            <option value="audio">Áudio (Gravação)</option>
                            <option value="video">Vídeo</option>
                        </select>
                    </div>

                    <!-- Text Status Options -->
                    <div id="status-text-settings" class="row gx-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Cor de Fundo (1-20)</label>
                            <input type="number" id="status-bg" class="form-control bg-light border-0 shadow-none" value="19" min="1"
                                max="21">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Estilo da Fonte</label>
                            <select id="status-font" class="form-select bg-light border-0 shadow-none">
                                <option value="1">Serif</option>
                                <option value="2">Norican Regular</option>
                                <option value="3">Bryndan Write</option>
                                <option value="4">Oswald Heavy</option>
                            </select>
                        </div>
                    </div>

                    <!-- Media Link -->
                    <div id="status-media-container" class="mb-3" style="display: none;">
                        <label class="form-label small fw-semibold text-muted">Link Público do Arquivo
                            (Imagem/Vídeo/Áudio)</label>
                        <input type="url" id="status-file" class="form-control bg-light border-0 shadow-none"
                            placeholder="https://exemplo.com/imagem.png">
                    </div>

                    <!-- Text Content -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Texto ou Legenda</label>
                        <textarea id="status-text" class="form-control bg-light border-0 shadow-none" rows="3"
                            placeholder="O que você está pensando?"></textarea>
                    </div>

                    <!-- Schedule Option -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted"><i class="bi bi-clock me-1"></i>Agendar
                                Publicação</label>
                            <select id="status-schedule" class="form-select bg-light border-0 shadow-none">
                                <option value="now">Publicar Agora</option>
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

                    <div id="status-feedback" class="mb-3 small fw-semibold text-center" style="display:none;"></div>

                    <div class="d-grid pt-2">
                        <button class="btn btn-wa btn-lg fw-bold rounded-pill shadow-sm" onclick="sendStatus()">
                            <i class="bi bi-send-fill me-2"></i>Publicar Status
                        </button>
                    </div>
                </div>
            </div>

            <!-- Recentes Agendamentos de Status -->
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div
                    class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="card-title fw-bold mb-0 text-muted"><i class="bi bi-clock-history me-2"></i>Agendamentos
                        Recentes</h6>
                    <button class="btn btn-sm btn-light text-primary fw-semibold" onclick="loadLocalSchedules('status')"><i
                            class="bi bi-arrow-repeat"></i></button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle small">
                            <thead class="table-light">
                                <tr>
                                    <th class="border-0 px-4">Data/Hora</th>
                                    <th class="border-0">Tipo</th>
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
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            const selector = document.getElementById('instance-selector');
            if (selector && selector.value) {
                loadLocalSchedules('status');
            }
        }, 500);
    });
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
