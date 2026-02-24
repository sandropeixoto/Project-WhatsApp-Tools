# Memória de Mensagens para Agentes de IA

Adicionar memória contextual aos agentes de IA para que as mensagens geradas não sejam repetitivas ou similares. A IA receberá as últimas 10 mensagens geradas como contexto para diversificar o conteúdo.

## Abordagem

A forma mais eficiente é buscar as últimas 10 mensagens já enviadas (do `uazapi_schedule`) e injetá-las no prompt da IA como histórico de conversação, sem necessidade de criar tabela nova.

---

## Proposed Changes

### Database (sem alterações)

As mensagens já estão armazenadas na tabela `uazapi_schedule` com `agent_id` no payload JSON. Basta consultar os registros existentes.

---

### Core: opencode_api.php

#### [MODIFY] [opencode_api.php](file:///Users/sandropeixoto/Dev/Project-WhatsApp-Tools/opencode_api.php)

- Alterar a assinatura de `generateOpenCodeMessage($prompt)` → `generateOpenCodeMessage($prompt, $previousMessages = [])`
- Se `$previousMessages` não estiver vazio, injetar as mensagens anteriores no array `messages` como role `assistant` (mensagens que a IA "já escreveu")
- Adicionar instrução no `system` prompt para evitar repetição

**Exemplo do novo payload de mensagens:**
```
system → "Você é um assistente... Não repita mensagens anteriores."
assistant → "Mensagem anterior 1"
assistant → "Mensagem anterior 2"
...
assistant → "Mensagem anterior 10"
user → prompt original do agente
```

---

### Callers: cron.php + api.php

#### [MODIFY] [cron.php](file:///Users/sandropeixoto/Dev/Project-WhatsApp-Tools/cron.php)

- **FASE 1 (linha ~30)** e **FASE 2 (linha ~155)**: antes de chamar `generateOpenCodeMessage()`, buscar as últimas 10 mensagens do agente
- Passar o array resultante como segundo parâmetro

#### [MODIFY] [api.php](file:///Users/sandropeixoto/Dev/Project-WhatsApp-Tools/api.php)

- **`create_agent` (linha ~529)**: primeira mensagem, sem histórico → sem mudança
- **`force_generate_agent_message` (linha ~605)**: buscar últimas 10 mensagens e passar como segundo parâmetro

---

## Resumo dos Pontos de Chamada

| Local | Arquivo | Precisa de memória? |
|-------|---------|-------------------|
| FASE 1 - agentes órfãos | `cron.php:30` | ✅ Sim |
| FASE 2 - próxima msg após envio | `cron.php:155` | ✅ Sim |
| Criar agente (1ª msg) | `api.php:529` | ❌ Não (é a primeira) |
| Forçar geração manual | `api.php:605` | ✅ Sim |

---

## Verification Plan

### Teste Manual
1. Criar um agente de IA e gerar 2-3 mensagens via botão "Forçar Geração"
2. Verificar nos logs do cron que as mensagens geradas são distintas
3. Inspecionar o payload enviado à API para confirmar que as mensagens anteriores estão sendo passadas no contexto
