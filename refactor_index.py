import re

with open('index.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Extract PHP block
php_block_match = re.search(r'(<\?php.*?\?>)\s*<!DOCTYPE html>', content, re.DOTALL)
if not php_block_match:
    print("Could not find PHP block")
    exit(1)
php_block = php_block_match.group(1)

# 2. Extract Javascript
script_block_match = re.search(r'<script>(.*?)</script>\s*</body>', content, re.DOTALL)
if not script_block_match:
    print("Could not find script block")
    exit(1)
script_block = script_block_match.group(1)

# Clean up Javascript
script_block = script_block.replace(
    "function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }",
    "// toggleSidebar handled by Bootstrap offcanvas"
)

# Update switchInstance
script_block = re.sub(
    r'function switchInstance\(\) \{.*?\n        \}',
    "function switchInstance() {\n            window.location.href = 'instances.php';\n        }",
    script_block,
    flags=re.DOTALL
)

# Replace loadInstances initialization
init_feed_script = """        async function initFeed() {
            try {
                const res = await fetch('index.php?action=get_instances');
                instancesData = await res.json();
                const instanceName = "<?= htmlspecialchars($activeInstanceName) ?>";
                selectInstance(instanceName);
            } catch (e) {
                console.error("Erro ao carregar metadados da instância.");
            }
        }
        initFeed();"""

script_block = script_block.replace("loadInstances();", init_feed_script)

# Remove screen manipulation from selectInstance
script_block = re.sub(
    r"document\.getElementById\('screen-select'\)\.classList\.add\('hidden'\);",
    "// document.getElementById('screen-select').classList.add('hidden');",
    script_block
)
script_block = re.sub(
    r"document\.getElementById\('screen-feed'\)\.classList\.remove\('hidden'\);",
    "// document.getElementById('screen-feed').classList.remove('hidden');",
    script_block
)

# HTML and CSS Template
new_html = f"""{php_block}
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Tools — Feed</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {{
            --wa-primary: #00a884;
            --wa-dark: #075e54;
            --wa-teal: #128c7e;
            --wa-bg: #f0f2f5;
            --wa-chat-bg: #efeae2;
        }}
        body {{
            font-family: 'Inter', sans-serif;
            background-color: var(--wa-bg);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }}
        .navbar-wa {{
            background: var(--wa-primary);
            color: white;
            z-index: 1040;
        }}
        
        /* Chat Wrapper Background */
        .chat-bg {{
            background-color: var(--wa-chat-bg);
            background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png');
            background-repeat: repeat;
        }}

        /* Keep existing chat bubble CSS */
        .chat-container {{
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }}
        .msg-row {{ display: flex; margin-bottom: 12px; width: 100%; }}
        .msg-row.in {{ justify-content: flex-start; }}
        .msg-row.out {{ justify-content: flex-end; }}
        .bubble {{
            max-width: 65%;
            padding: 6px 7px 8px 9px;
            border-radius: 7.5px;
            font-size: 14px;
            box-shadow: 0 1px 0.5px rgba(11, 20, 26, .13);
            display: flex;
            flex-direction: column;
        }}
        .bubble.in {{ background-color: #ffffff; border-top-left-radius: 0; }}
        .bubble.out {{ background-color: #d9fdd3; border-top-right-radius: 0; }}
        .group-header-block {{ background: rgba(0, 0, 0, 0.04); border-radius: 6px; padding: 6px; margin-bottom: 6px; }}
        .info-line {{ display: flex; align-items: center; margin-bottom: 4px; }}
        .tiny-avatar {{ width: 20px; height: 20px; border-radius: 50%; margin-right: 8px; object-fit: cover; }}
        .group-name {{ font-size: 12px; font-weight: 600; color: #53bdeb; }}
        .sender-name {{ font-size: 12px; font-weight: 600; color: #128C7E; }}
        .sender-phone {{ font-size: 11px; color: #667781; margin-left: 4px; }}
        .msg-text {{ color: #111b21; word-wrap: break-word; white-space: pre-wrap; }}
        .msg-image {{ max-width: 100%; border-radius: 6px; margin-bottom: 4px; cursor: pointer; transition: opacity 0.2s; }}
        .msg-image:hover {{ opacity: 0.9; }}
        .msg-sticker {{ max-width: 150px; max-height: 150px; margin-bottom: 4px; }}
        .msg-video {{ max-width: 100%; border-radius: 6px; margin-bottom: 4px; background: #000; }}
        .msg-audio {{ width: 100%; margin-bottom: 4px; height: 36px; }}
        .msg-document {{ display: flex; align-items: center; gap: 10px; background: rgba(0, 0, 0, 0.04); border-radius: 6px; padding: 10px; margin-bottom: 4px; text-decoration: none; color: #111b21; transition: background 0.2s; }}
        .msg-document:hover {{ background: rgba(0, 0, 0, 0.08); }}
        .doc-icon {{ font-size: 28px; flex-shrink: 0; }}
        .doc-info {{ flex: 1; min-width: 0; }}
        .doc-name {{ font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }}
        .doc-meta {{ font-size: 11px; color: #667781; }}
        .media-placeholder {{ background: rgba(0, 0, 0, 0.06); border-radius: 6px; padding: 12px; text-align: center; font-size: 12px; color: #667781; margin-bottom: 4px; }}
        .media-placeholder span {{ font-size: 20px; display: block; margin-bottom: 4px; }}
        .media-wrapper {{ position: relative; display: inline-block; max-width: 100%; }}
        .btn-save-media {{ position: absolute; top: 6px; right: 6px; background: rgba(0, 0, 0, 0.55); color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s; z-index: 2; }}
        .media-wrapper:hover .btn-save-media {{ opacity: 1; }}
        .btn-save-media:hover {{ background: rgba(0, 168, 132, 0.85); }}
        .btn-save-media.saved {{ opacity: 1; background: rgba(0, 168, 132, 0.85); cursor: default; }}
        .btn-save-inline {{ background: rgba(0, 0, 0, 0.06); border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 11px; color: #00a884; font-weight: 600; margin-left: 8px; transition: background 0.2s; }}
        .btn-save-inline:hover {{ background: rgba(0, 168, 132, 0.15); }}
        .btn-save-inline.saved {{ color: #16a34a; cursor: default; }}
        .time {{ font-size: 11px; color: #667781; align-self: flex-end; margin-top: -10px; margin-left: 15px; float: right; }}
        
        /* Groups styling compatibility */
        .groups-table {{ width: 100%; border-collapse: collapse; font-size: 13px; background: white; border-radius: 8px; overflow: hidden; }}
        .groups-table th {{ background: var(--wa-bg); padding: 12px; text-align: left; font-weight: 600; color: #667781; border-bottom: 1px solid #e0e0e0; }}
        .groups-table td {{ padding: 12px; border-bottom: 1px solid #f0f2f5; color: #111b21; vertical-align: middle; }}
        .groups-table tr:hover {{ background: #f7f8fa; }}
        .jid-cell {{ font-family: monospace; font-size: 12px; color: var(--wa-primary); cursor: pointer; display: flex; align-items: center; gap: 6px; }}
        .jid-cell:hover {{ text-decoration: underline; }}
        
        .tab-content {{ display: none; flex: 1; overflow-y: auto; }}
        .tab-content.active {{ display: flex; flex-direction: column; }}
        
        .custom-nav-tabs .nav-link {{
            color: #6c757d;
            border: none;
            border-bottom: 3px solid transparent;
            font-weight: 500;
            padding: 12px 20px;
        }}
        .custom-nav-tabs .nav-link:hover {{
            border-color: transparent;
            color: var(--wa-primary);
        }}
        .custom-nav-tabs .nav-link.active {{
            color: var(--wa-primary);
            border-color: var(--wa-primary);
            background: transparent;
        }}
    </style>
</head>
<body>

    <!-- Top Navbar -->
    <nav class="navbar navbar-wa shadow-sm px-3 py-2 flex-shrink-0">
        <div class="d-flex align-items-center w-100 justify-content-between">
            <div class="d-flex align-items-center">
                <img id="topbar-avatar" src="https://ui-avatars.com/api/?name=WA&background=128c7e&color=fff" 
                     class="rounded-circle border border-2 border-white border-opacity-50 me-3" 
                     style="width: 44px; height: 44px; object-fit: cover;" alt="">
                <div>
                    <div class="fw-bold lh-1 mb-1 fs-6" id="topbar-name">Carregando...</div>
                    <div class="small text-white-50 lh-1" id="topbar-phone"></div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-light btn-sm fw-semibold px-3" onclick="switchInstance()">
                    <i class="bi bi-arrow-return-left me-1"></i> Trocar
                </button>
                <button class="btn btn-light btn-sm fw-semibold px-3 text-success shadow-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
                    <i class="bi bi-tools me-1"></i> Ferramentas
                </button>
            </div>
        </div>
    </nav>

    <!-- Hidden selector for backward compat -->
    <select id="instance-selector" class="d-none"></select>

    <!-- Main Content -->
    <main class="d-flex flex-column flex-grow-1 overflow-hidden" style="background: white;">
        
        <!-- Default Bootstrap Nav Tabs styled nicely -->
        <ul class="nav custom-nav-tabs tab-bar border-bottom w-100" style="background: var(--wa-bg);">
            <li class="nav-item flex-fill text-center">
                <button class="nav-link w-100 active" onclick="switchTab('chat', this)">
                    <i class="bi bi-chat-dots me-2"></i> Conversas
                </button>
            </li>
            <li class="nav-item flex-fill text-center">
                <button class="nav-link w-100" onclick="switchTab('groups', this)">
                    <i class="bi bi-people me-2"></i> Grupos
                </button>
            </li>
        </ul>

        <!-- Tab: Conversas -->
        <div class="tab-content active chat-bg" id="tab-chat">
            <div class="chat-container w-100 mx-auto" id="monitor" style="max-width: 900px;">
                <div class="text-center text-muted my-4 small">Carregando mensagens...</div>
            </div>
        </div>

        <!-- Tab: Grupos -->
        <div class="tab-content" id="tab-groups">
            <div class="container-fluid py-4 w-100 mx-auto" style="max-width: 1000px;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0 fw-bold">Grupos da Instância</h4>
                    <button class="btn btn-success btn-sm w-auto fw-semibold rounded-pill px-3 shadow-sm" id="btn-sync-groups" onclick="syncGroups()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Atualizar Grupos
                    </button>
                </div>
                <div id="sync-groups-status" class="small mb-3 text-center"></div>
                <div id="groups-list" class="table-responsive shadow-sm rounded-3">
                    <div class="text-center text-muted py-5 bg-white border rounded">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        Clique em "Atualizar Grupos" para carregar
                    </div>
                </div>
            </div>
        </div>

    </main>

    <!-- Sidebar Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="sidebar">
        <div class="offcanvas-header text-white" style="background: var(--wa-primary);">
            <h5 class="offcanvas-title fw-bold"><i class="bi bi-sliders text-white-50 me-2"></i> Painel de Controle</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body" style="background: var(--wa-bg);">
            
            <!-- Send Message Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="card-title fw-bold mb-3 d-flex align-items-center border-bottom pb-2">
                        <i class="bi bi-chat-text text-success me-2 fs-5"></i> Enviar Mensagem
                    </h6>
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold">Número Destino</label>
                        <input type="text" class="form-control form-control-sm" id="send-number" placeholder="Ex: 5511999999999">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold">Mensagem</label>
                        <textarea class="form-control form-control-sm" id="send-text" rows="3" placeholder="Sua mensagem..."></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small text-muted fw-semibold"><i class="bi bi-clock me-1"></i> Agendamento</label>
                        <select class="form-select form-select-sm" id="msg-schedule">
                            <option value="now">🟢 Enviar Agora</option>
                            <option value="+5min">Daqui a 5 minutos</option>
                            <option value="+10min">Daqui a 10 minutos</option>
                            <option value="+30min">Daqui a 30 minutos</option>
                            <option value="+1hour">Daqui a 1 hora</option>
                            <option value="+2hours">Daqui a 2 horas</option>
                            <option value="tomorrow_8">🌅 Amanhã às 8h</option>
                            <option value="tomorrow_same">🔄 Amanhã neste horário</option>
                        </select>
                    </div>
                    <button class="btn btn-success w-100 fw-bold shadow-sm" onclick="sendMessage()" id="btn-send">
                        <i class="bi bi-send me-1"></i> Enviar
                    </button>
                    <div id="send-status" class="mt-2 text-center small fw-medium"></div>
                </div>
            </div>

            <!-- Send Status Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="card-title fw-bold mb-3 d-flex align-items-center border-bottom pb-2">
                        <i class="bi bi-circle text-primary me-2 fs-5"></i> Publicar Status
                    </h6>
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold">Tipo</label>
                        <select class="form-select form-select-sm" id="status-type" onchange="toggleStatusFields()">
                            <option value="text">Texto</option>
                            <option value="image">Imagem</option>
                            <option value="video">Vídeo</option>
                            <option value="audio">Áudio</option>
                        </select>
                    </div>

                    <div id="status-text-fields" class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small text-muted fw-semibold">Fundo</label>
                            <select class="form-select form-select-sm" id="status-bg-color">
                                <option value="1">Amarelo 1</option>
                                <option value="4">Verde 1</option>
                                <option value="7">Azul 1</option>
                                <option value="10">Lilás 1</option>
                                <option value="13">Magenta</option>
                                <option value="16">Marrom</option>
                                <option value="19" selected>Cinza Escuro</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted fw-semibold">Fonte</label>
                            <select class="form-select form-select-sm" id="status-font">
                                <option value="0">Padrão</option>
                                <option value="1" selected>Estilo 1</option>
                                <option value="2">Estilo 2</option>
                            </select>
                        </div>
                    </div>

                    <div id="status-media-fields" class="mb-3 d-none">
                        <label class="form-label small text-muted fw-semibold">URL da Mídia</label>
                        <input type="text" class="form-control form-control-sm" id="status-file" placeholder="https://exemplo.com/midia.jpg">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold">Texto / Legenda</label>
                        <textarea class="form-control form-control-sm" id="status-text" rows="2" placeholder="O que está acontecendo?"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small text-muted fw-semibold"><i class="bi bi-clock me-1"></i> Agendamento</label>
                        <select class="form-select form-select-sm" id="status-schedule">
                            <option value="now">🟢 Publicar Agora</option>
                            <option value="+5min">Daqui a 5 minutos</option>
                            <option value="+30min">Daqui a 30 minutos</option>
                            <option value="+1hour">Daqui a 1 hora</option>
                            <option value="tomorrow_8">🌅 Amanhã às 8h</option>
                        </select>
                    </div>

                    <button class="btn btn-primary w-100 fw-bold shadow-sm" onclick="sendStatus()" id="btn-send-status">
                        <i class="bi bi-broadcast me-1"></i> Publicar Status
                    </button>
                    <div id="status-send-status" class="mt-2 text-center small fw-medium"></div>
                </div>
            </div>

            <!-- Schedules Sync Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title fw-bold mb-3 d-flex align-items-center border-bottom pb-2">
                        <i class="bi bi-calendar-event text-info me-2 fs-5"></i> Agendamentos Ativos
                    </h6>
                    <button class="btn btn-outline-info btn-sm w-100 fw-semibold mb-3" onclick="loadSchedules()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Atualizar Lista
                    </button>
                    <div id="schedules-list" class="small text-muted text-center pt-2">Clique em Atualizar para buscar.</div>
                </div>
            </div>

        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
{script_block}
    </script>
</body>
</html>
"""

new_html = new_html.replace('class="hidden"', 'class="d-none"')

with open('index.php', 'w', encoding='utf-8') as f:
    f.write(new_html)

print("index.php refactored successfully.")
