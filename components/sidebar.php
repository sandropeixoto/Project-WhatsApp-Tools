
    <!-- Sidebar Offcanvas Menu -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebar">
        <div class="offcanvas-header text-white" style="background: var(--wa-primary);">
            <h5 class="offcanvas-title fw-bold"><i class="bi bi-whatsapp text-white-50 me-2"></i> Menu</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
                aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-0" style="background: var(--wa-bg);">
            <div class="list-group list-group-flush border-bottom mb-3">
                <a href="index.php" class="list-group-item list-group-item-action py-3 fw-semibold <?php echo ($pageTitle ?? '') === 'Feed' ? 'active' : ''; ?>">
                    <i class="bi bi-chat-dots me-3 fs-5 text-secondary"></i> Feed de Mensagens
                </a>
                <a href="groups.php" class="list-group-item list-group-item-action py-3 fw-semibold <?php echo ($pageTitle ?? '') === 'Grupos' ? 'active' : ''; ?>">
                    <i class="bi bi-people me-3 fs-5 text-secondary"></i> Lista de Grupos
                </a>
                <a href="schedules.php" class="list-group-item list-group-item-action py-3 fw-semibold <?php echo ($pageTitle ?? '') === 'Agendamentos' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-event me-3 fs-5 text-secondary"></i> Agendamentos Gerais
                </a>
            </div>

            <div class="px-3 mb-2 text-muted small fw-bold text-uppercase">Envio de Mensagens</div>
            <div class="list-group list-group-flush border-bottom mb-3">
                <a href="send_message.php" class="list-group-item list-group-item-action py-3 fw-semibold <?php echo ($pageTitle ?? '') === 'Enviar Mensagem' ? 'active' : ''; ?>">
                    <i class="bi bi-send-arrow-up me-3 fs-5 text-success"></i> Nova Mensagem
                </a>
                <a href="send_status.php" class="list-group-item list-group-item-action py-3 fw-semibold <?php echo ($pageTitle ?? '') === 'Enviar Status' ? 'active' : ''; ?>">
                    <i class="bi bi-broadcast me-3 fs-5 text-primary"></i> Novo Status / Story
                </a>
            </div>

            <div class="px-3 mb-2 mt-auto text-muted small fw-bold text-uppercase">Sistema</div>
            <div class="list-group list-group-flush">
                <a href="instances.php" class="list-group-item list-group-item-action py-3 fw-semibold text-danger">
                    <i class="bi bi-arrow-return-left me-3 fs-5"></i> Trocar Instância
                </a>
            </div>
        </div>
    </div>
