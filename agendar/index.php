<?php
// agendar/index.php
$is_page_request = true;
require_once __DIR__ . '/api/auth_middleware.php';

// $user is available here
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendário - Módulo Agendar</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <!-- FullCalendar -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>

    <style>
        body {
            background-color: #f8f9fa;
        }

        .navbar-wa {
            background-color: #075E54;
        }

        .navbar-wa .navbar-brand,
        .navbar-wa .nav-link {
            color: white;
        }

        .navbar-wa .nav-link:hover {
            color: #dcf8c6;
        }

        #calendar {
            max-width: 1100px;
            margin: 40px auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .fc-event {
            cursor: pointer;
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-wa sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><i class="bi bi-calendar-check"></i> WA Agendar</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Calendário</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="config.php"><i class="bi bi-gear"></i> Configurações</a>
                    </li>
                </ul>
                <span class="navbar-text text-light me-3">
                    Logado como:
                    <?= htmlspecialchars($user['phone_number'])?>
                </span>
                <a href="api/auth.php?action=logout" class="btn btn-outline-light btn-sm">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div id='calendar'></div>
    </div>

    <!-- Modal Evento -->
    <div class="modal fade" id="eventModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalTitle">Nova Mensagem Agendada</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="eventForm">
                    <div class="modal-body">
                        <input type="hidden" id="eventId">

                        <div id="statusAlert" class="alert d-none"></div>

                        <div class="mb-3">
                            <label class="form-label">Data e Hora</label>
                            <input type="datetime-local" class="form-control" id="eventDate" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tipo de Mensagem</label>
                            <select class="form-select" id="eventType">
                                <option value="text">Texto Simples</option>
                                <option value="image">Imagem</option>
                                <option value="video">Vídeo</option>
                                <option value="document">PDF / Documento</option>
                            </select>
                        </div>

                        <div class="mb-3 d-none" id="mediaUploadBlock">
                            <label class="form-label">Arquivo de Mídia</label>
                            <input class="form-control" type="file" id="eventFile">
                            <input type="hidden" id="eventMediaPath">

                            <div id="mediaPreview" class="mt-2 d-none">
                                <a href="#" target="_blank" id="mediaLink">Ver arquivo atual</a>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" id="textLabel">Mensagem</label>
                            <textarea class="form-control" id="eventText" rows="4"
                                placeholder="Digite sua mensagem"></textarea>
                            <div class="form-text" id="textHelp">Legenda caso seja mídia.</div>
                        </div>

                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <button type="button" class="btn btn-danger d-none" id="btnDelete">Excluir</button>
                        <div>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary" id="btnSave">
                                <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                                Salvar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const calendarEl = document.getElementById('calendar');
            const modal = new bootstrap.Modal(document.getElementById('eventModal'));
            const form = document.getElementById('eventForm');

            const eventType = document.getElementById('eventType');
            const mediaUploadBlock = document.getElementById('mediaUploadBlock');
            const eventFile = document.getElementById('eventFile');
            const eventText = document.getElementById('eventText');
            const textLabel = document.getElementById('textLabel');
            const btnDelete = document.getElementById('btnDelete');
            const statusAlert = document.getElementById('statusAlert');
            let calendar;

            // Form logic
            eventType.addEventListener('change', () => {
                if (eventType.value === 'text') {
                    mediaUploadBlock.classList.add('d-none');
                    textLabel.innerText = "Mensagem";
                } else {
                    mediaUploadBlock.classList.remove('d-none');
                    textLabel.innerText = "Legenda";
                }
            });

            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'pt-br',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                editable: true,
                droppable: true,
                selectable: true,
                events: 'api/events.php?action=list',

                dateClick: function (info) {
                    // Nova mensagem
                    form.reset();
                    document.getElementById('eventId').value = '';

                    // Set default time to 12:00
                    const date = new Date(info.date.getTime() - (info.date.getTimezoneOffset() * 60000));
                    if (info.view.type === 'dayGridMonth') {
                        date.setUTCHours(12);
                    }
                    document.getElementById('eventDate').value = date.toISOString().slice(0, 16);

                    btnDelete.classList.add('d-none');
                    eventType.dispatchEvent(new Event('change'));
                    document.getElementById('mediaPreview').classList.add('d-none');
                    document.getElementById('eventMediaPath').value = '';
                    statusAlert.classList.add('d-none');
                    document.getElementById('btnSave').disabled = false;

                    document.getElementById('eventModalTitle').innerText = "Nova Mensagem Agendada";
                    modal.show();
                },

                eventClick: function (info) {
                    // Editar mensagem
                    form.reset();
                    const props = info.event.extendedProps;

                    document.getElementById('eventId').value = info.event.id;
                    document.getElementById('eventType').value = props.media_type;
                    eventType.dispatchEvent(new Event('change'));

                    const eventDate = info.event.start;
                    const d = new Date(eventDate.getTime() - (eventDate.getTimezoneOffset() * 60000));
                    document.getElementById('eventDate').value = d.toISOString().slice(0, 16);

                    document.getElementById('eventText').value = props.text || '';
                    document.getElementById('eventMediaPath').value = props.media_path || '';

                    if (props.media_path) {
                        document.getElementById('mediaPreview').classList.remove('d-none');
                        const basePath = '<?= rtrim(dirname($_SERVER["PHP_SELF"]), "/\\")?>/';
                        document.getElementById('mediaLink').href = basePath + props.media_path;
                    } else {
                        document.getElementById('mediaPreview').classList.add('d-none');
                    }

                    if (props.status === 'PENDING') {
                        statusAlert.classList.add('d-none');
                        document.getElementById('btnSave').disabled = false;
                        btnDelete.classList.remove('d-none');
                    } else {
                        statusAlert.classList.remove('d-none');
                        statusAlert.className = `alert alert-${props.status === 'SENT' ? 'success' : 'danger'}`;
                        statusAlert.innerText = `Status: ${props.status} ${props.error_message ? '- ' + props.error_message : ''}`;
                        document.getElementById('btnSave').disabled = true;
                        btnDelete.classList.add('d-none');
                    }

                    document.getElementById('eventModalTitle').innerText = "Editar Mensagem Agendada";
                    modal.show();
                },

                eventDrop: async function (info) {
                    // Mover evento
                    const id = info.event.id;
                    const d = new Date(info.event.start.getTime() - (info.event.start.getTimezoneOffset() * 60000));
                    const newDate = d.toISOString().slice(0, 19).replace('T', ' ');

                    try {
                        const res = await fetch('api/events.php?action=update', {
                            method: 'POST',
                            body: JSON.stringify({ id: id, scheduled_at: newDate }),
                            headers: { 'Content-Type': 'application/json' }
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            alert("Erro: " + data.error);
                            info.revert();
                        }
                    } catch (e) {
                        alert("Erro de conexão");
                        info.revert();
                    }
                }
            });
            calendar.render();

            // Form submit
            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                const btnSave = document.getElementById('btnSave');
                btnSave.disabled = true;
                btnSave.querySelector('.spinner-border').classList.remove('d-none');

                let mediaPath = document.getElementById('eventMediaPath').value;

                // Fazer upload primeiro se houver arquivo
                if (eventType.value !== 'text' && eventFile.files.length > 0) {
                    const formData = new FormData();
                    formData.append('file', eventFile.files[0]);

                    try {
                        const upRes = await fetch('api/upload.php', { method: 'POST', body: formData });
                        const upData = await upRes.json();
                        if (upRes.ok) {
                            mediaPath = upData.path;
                        } else {
                            alert("Erro no upload: " + upData.error);
                            btnSave.disabled = false;
                            btnSave.querySelector('.spinner-border').classList.add('d-none');
                            return;
                        }
                    } catch (e) {
                        alert("Erro ao enviar arquivo.");
                        btnSave.disabled = false;
                        btnSave.querySelector('.spinner-border').classList.add('d-none');
                        return;
                    }
                }

                // Agora salva o evento
                const id = document.getElementById('eventId').value;
                const scheduled_at = document.getElementById('eventDate').value.replace('T', ' ') + ':00';
                const text = document.getElementById('eventText').value;

                const payload = {
                    media_type: eventType.value,
                    text: text,
                    media_path: mediaPath,
                    scheduled_at: scheduled_at
                };

                let endpoint = 'api/events.php?action=create';
                if (id) {
                    endpoint = 'api/events.php?action=update';
                    payload.id = id;
                }

                try {
                    const saveRes = await fetch(endpoint, {
                        method: 'POST',
                        body: JSON.stringify(payload),
                        headers: { 'Content-Type': 'application/json' }
                    });

                    const saveData = await saveRes.json();
                    if (saveRes.ok) {
                        modal.hide();
                        calendar.refetchEvents();
                    } else {
                        alert('Erro: ' + saveData.error);
                    }
                } catch (e) {
                    alert('Erro de conexão ao salvar.');
                } finally {
                    btnSave.disabled = false;
                    btnSave.querySelector('.spinner-border').classList.add('d-none');
                }
            });

            btnDelete.addEventListener('click', async () => {
                if (!confirm("Tem certeza que deseja excluir este agendamento?")) return;

                const id = document.getElementById('eventId').value;
                try {
                    const res = await fetch('api/events.php?action=delete', {
                        method: 'POST',
                        body: JSON.stringify({ id: id }),
                        headers: { 'Content-Type': 'application/json' }
                    });
                    if (res.ok) {
                        modal.hide();
                        calendar.refetchEvents();
                    } else {
                        const data = await res.json();
                        alert("Erro: " + data.error);
                    }
                } catch (e) {
                    al"Erxcluir.");
                }
            });

        });
    </script>
</body>

</html>