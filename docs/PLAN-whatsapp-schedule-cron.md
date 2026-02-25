# PLAN: Módulo Extra - Cron Escala do Pão

## Overview
Criação de um módulo apartado (executado via Cron Job) para o "Projeto WhatsApp Tools" que consome a API de escala de pão e dispara notificações geradas por IA no WhatsApp.
O módulo operará em dois cenários: "Spoiler" (às 16:00) e "O Chamado" (às 07:30).

**Project Type**: BACKEND

## Success Criteria
- [ ] O script deve consumir a API `https://escala-do-pao.web.app/api/schedule?days=5` com sucesso.
- [ ] Deve processar as datas corretamente usando `DateTime` comparado à data atual, identificando o responsável e próximos da fila (Spoiler) ou responsável de hoje (Chamado).
- [ ] Deve integrar-se corretamente com a IA de geração de texto usando os prompts específicos definidos.
- [ ] O texto final deve ser enviado com sucesso via função `sendWhatsApp($mensagem)`.
- [ ] O módulo deverá residir em uma pasta separada e não deve alterar código já existente e funcional.

## Tech Stack
- **PHP**: Linguagem backend do projeto. Uso de `cURL` ou `file_get_contents` e a classe `DateTime`.
- **Cron Job**: Tarefa agendada para chamar o script nos horários definidos.
- **Integrações Existentes**: Aproveitamento do ecossistema atual do WhatsApp Tools: AI Engine de geração de textos já implementada, e função `sendWhatsApp()` preexistente.

## File Structure
- `modules/bread_schedule/` - Novo diretório isolado para não afetar os demais recursos (ou na raiz, como `cron_escala_pao.php`, dependendo da arquitetura final).
- `docs/PLAN-whatsapp-schedule-cron.md` (Este arquivo)

## Task Breakdown

### Task 1: Setup e Fetch da API da Escala
- **Agent**: `backend-specialist`
- **Skills**: `api-patterns`, `clean-code`
- **Priority**: P1
- **Description**: Criar o script isolado e a lógica de requisição para buscar o JSON da escala (`escala-do-pao.web.app`). Efetuar tratamento de exceções para evitar quebra do cron.
- **Dependencies**: Nenhuma
- **INPUT**: `https://escala-do-pao.web.app/api/schedule?days=5`
- **OUTPUT**: Array estruturado em PHP contendo os próximos 5 dias salvos em memória.
- **VERIFY**: Executar script no terminal e imprimir a lista parseada corretamente.

### Task 2: Processamento de Datas e Condições (07:30 vs 16:00)
- **Agent**: `backend-specialist`
- **Skills**: `clean-code`
- **Priority**: P1
- **Description**: Comparar `date('Y-m-d')` com as datas recebidas. Verificar hora de execução para determinar se é o disparo "Spoiler" (buscar responsável amanhã + próximos 2) ou "O Chamado" (buscar responsável hoje).
- **Dependencies**: Task 1
- **INPUT**: Array da Escala + Hora atual/Servidor (Cron Trigger)
- **OUTPUT**: Variáveis preenchidas com nome(s) do(s) responsável(eis) ou `null` caso caia fora dos dias/horários definidos.
- **VERIFY**: Realizar stub do DateTime manipulando a hora e garantindo que cada if/else traga o nome correto.

### Task 3: Integração IA e Helper WhatsApp
- **Agent**: `backend-specialist`
- **Skills**: `api-patterns`
- **Priority**: P1
- **Description**: Importar as funções do projeto principal (AI Builder / Envio). Montar as strings dos prompts originais enviadas pelo usuário, substituindo placeholders, chamar IA e repassar string de resposta pro WhatsApp.
- **Dependencies**: Task 2
- **INPUT**: Prop de contexto e Prompt (Depende do cron: spoiler com os três nomes ou apenas do de hoje).
- **OUTPUT**: Mensagem gerada pela IA enviada para o canal de WhatsApp.
- **VERIFY**: Recebimento no WhatsApp da mensagem em tom exato (descontraído com emojis).

## Phase X: Final Verification
- [x] Parser JSON testado com sucesso (sem Notice/Warning/Fatal Error de chaves indefinidas).
- [x] Teste de DateTime e Fuso Horário operando corretamente.
- [x] Nenhuma interferência estrutural nas regras, instâncias e UI do atual 'WhatsApp Tools'.
- [x] Lint verificado (`npm run lint` ou equivalente).
- [x] Confirmação visual de chegada da mensagem após execução via terminal/CLI `php modules/bread_schedule/cron_escala.php`.

## ✅ PHASE X COMPLETE
- Date: 2026-02-25

