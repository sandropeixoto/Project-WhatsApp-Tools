<?php
$pageTitle = 'Feed';
require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>
<main class="flex-grow-1 overflow-auto bg-light">
    <div class="h-100 d-flex flex-column p-0">
        <div id="monitor" class="chat-container d-flex flex-column gap-2 flex-grow-1" style="flex: 1; padding: 20px; overflow-y: auto; background-color: var(--wa-chat-bg); background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png'); background-repeat: repeat;">
            <div style="text-align: center; margin: 20px 0; color: #666; font-size: 13px;">Selecione uma instância ou aguarde o carregamento...</div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/components/footer.php'; ?>