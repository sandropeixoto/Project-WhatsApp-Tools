# Projeto: Módulo Agendar

## Overview
Criação de um novo módulo ("agendar") segregado da plataforma principal. O objetivo é servir como uma ferramenta de disparo de mensagens agendadas de WhatsApp. A interface principal contará com um calendário interativo (Drag & Drop para mover mensagens entre dias), permitindo a criação, edição e exclusão de envios de diferentes tipos de mídias (texto, imagem, pdf, vídeo), com o texto operando como legenda nas mídias enviadas.
O módulo trará gerenciamento de múltiplas contas (cada conta pode dar acesso a múltiplos usuários convidados) validando login via token por WhatsApp (sessão estendida para 30 dias na máquina sem senha). Por fim, haverá um script cron exclusivo rodando a cada minuto para processar os envios no exato momento programado.

## Project Type
**WEB** (com rotina BACKEND paralela)

## Success Criteria
- [ ] Módulo agendar acessível em uma página apartada.
- [ ] Login funcional enviando token via WhatsApp para validação, com sessão de 30 dias salva.
- [ ] Calendário interativo renderizando mensagens, permitindo arrastar, clicar para editar e navegação por dia/semana/mês.
- [ ] Formulário de agendamento permitindo anexar mídias e setar data/hora exata.
- [ ] Mídias armazenadas localmente em pastas exclusivas (separadas pela conta do usuário principal).
- [ ] Script Cron dispara a cada minuto sem travamentos, processando mensagens das contas utilizando a instância configurada em código.
- [ ] Envio das mensagens utilizando a API do sistema existente de forma transparente.

## Tech Stack
- **Frontend UI:** Bootstrap 5 (padrão atual do projeto), FullCalendar.js (para calendário interativo drag&drop).
- **Backend API:** PHP 8+ com integração às funções do WhatsApp Tools existente. 
- **Database:** MySQL correspondente ao projeto atual, acessado pelo db.php existente, usando tabelas com prefixo `agendar_`.

## File Structure
- `agendar/` - Diretório principal do módulo.
- `agendar/index.php` - Interface principal do calendário e sistema logado.
- `agendar/login.php` - Autenticação por token (frontend e backend handler).
- `agendar/config.php` - Configurações da conta (cadastro de contas extras com acesso, número/grupo de envio).
- `agendar/storage/` - Pasta base para armazenamento de mídias divididas por ID de conta.
- `agendar/api/` - Endpoints REST/JSON para crud de eventos e gestão (Drag&Drop updates).
- `agendar/cron_agendar.php` - Cronjob exclusivo a ser configurado executando a cada minuto.

## Task Breakdown

### Task 1: Banco de Dados e Modelagem (agendar_*)
- **Agent:** `database-architect`
- **Skills:** `database-design`
- **Priority:** P0
- **Dependencies:** None
- **INPUT→OUTPUT→VERIFY:** 
  - **Input:** Requisitos de usuários, sessões logadas e mensagens programadas.
  - **Output:** Query de criação de tabelas (ex: `agendar_accounts`, `agendar_users`, `agendar_sessions`, `agendar_messages`, `agendar_config`).
  - **Verify:** Rodar o SQL gerado diretamente no banco e verificar as constrições criadas.

### Task 2: Sistema de Login Sem Senha (WhatsApp Token)
- **Agent:** `backend-specialist`
- **Skills:** `api-patterns`, `security-auditor`
- **Priority:** P1
- **Dependencies:** Task 1
- **INPUT→OUTPUT→VERIFY:** 
  - **Input:** Construção das rotinas de auth por celular em `agendar/login.php`.
  - **Output:** Geração de token na tabela temporária, envio usando instância hardcoded, endpoint de verificação de token preenchendo o cookie de 30 dias no lado do cliente com sessão na base atrelada ao celular.
  - **Verify:** Simular requisição de login que cria token validado salvando os states em Session/Cookie.

### Task 3: API do Calendário e Uploads Conta/Role
- **Agent:** `backend-specialist`
- **Skills:** `api-patterns`
- **Priority:** P1
- **Dependencies:** Task 1, Task 2
- **INPUT→OUTPUT→VERIFY:** 
  - **Input:** Endpoints protegidos (`/api/agendar/...`) para carregar o mês atual, inserir no banco (salvar media em subpasta local para a account ID associada), e endpoint para mover data (Drag and Drop update).
  - **Output:** Mapeamentos funcionais JSON garantindo que usuário logado veja/altere apensas da conta vinculada.
  - **Verify:** Criar mensagem `agendar_message` disparando no backend com fake upload checando a pasta correspondente.

### Task 4: Frontend - Interface do FullCalendar e CRUD
- **Agent:** `frontend-specialist`
- **Skills:** `frontend-design`
- **Priority:** P2
- **Dependencies:** Task 3
- **INPUT→OUTPUT→VERIFY:** 
  - **Input:** Layout responsivo Bootstrap 5 inserindo o script FullCalendar.js em página nova `index.php`. Modais form (add/edit) e actions baseados na API.
  - **Output:** Arquitetura do painel conectada em JS isolados que chamem os endpoints fetch. Ações para renderizar mídias na preview da legenda.
  - **Verify:** Painel carrega sem erros UI, testes simulados alterando blocks com o mouse batem PUT no terminal 200.

### Task 5: Interface de Configurações Administrativas
- **Agent:** `frontend-specialist`, `backend-specialist`
- **Skills:** `clean-code`
- **Priority:** P2
- **Dependencies:** Task 2
- **INPUT→OUTPUT→VERIFY:** 
  - **Input:** Tela `agendar/config.php` gerenciando array de contatos, convites de membros via celular e as variáveis globais da conta.
  - **Output:** Tabela na UI apontando quais usuários adicionais estão vinculados àquela mesma instância Conta.
  - **Verify:** Simular usuário Adicionando Novo e verificando BD salvo vinculado ao id primário.

### Task 6: Cron de Disparo a cada Minuto (cron_agendar.php)
- **Agent:** `backend-specialist`
- **Skills:** `server-management`, `clean-code`
- **Priority:** P1
- **Dependencies:** Task 3, Task 1
- **INPUT→OUTPUT→VERIFY:** 
  - **Input:** Arquivo PHP stand-alone de background checando: `WHERE status = 'PENDING' AND data_hora <= NOW() LIMIT X`.
  - **Output:** Looping passando as informações do arquivo de storage e o texto associado, chamando as funções internas de WA base para mídia e/ou texto. Marcar mensagens enviadas.
  - **Verify:** Mock no DB de mensagens no passado devendo ter update de status instantâneo via curl trigger com logs apurados no arquivo (para cron log trace).

## ✅ Phase X: Final Verification
- [ ] Modelagem de dados normalizada usando prefixo isolado.
- [ ] Bootstrap adere às especificações atuais do master project (sem template).
- [ ] Controle multi-usuário (Owner/Convidado) testado e cookie de 30 dias respeitado.
- [ ] Arquivos testados armazenados com pasta relativa de conta base.
- [ ] Disparo por cronjob concluído em simulacro provando chamada à API de WA correta.
- [ ] Socratic Gate was respected.
