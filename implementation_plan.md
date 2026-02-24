# Plano: Separação de Páginas + Bootstrap 5

## Contexto

Separar a tela de seleção de instâncias em arquivo próprio e adotar Bootstrap 5 via CDN com tema WhatsApp.

---

## Arquitetura

```
instances.php → Seleciona instância → index.php?instance=nome
```

- `instances.php` = tela de seleção (landing)
- `index.php?instance=nome` = feed da instância
- Sem parâmetro → redirect para `instances.php`

## Framework CSS

**Bootstrap 5.3 via CDN** — razões:
- Funciona com PHP vanilla (sem build)
- Grid, cards, badges, buttons prontos
- Dark mode nativo via `data-bs-theme`
- Reduz 70%+ do CSS custom existente

## Tema WhatsApp

| Token | Valor | Uso |
|-------|-------|-----|
| `--wa-primary` | `#00a884` | Header, botões principais |
| `--wa-dark` | `#075e54` | Header escuro, hover |
| `--wa-teal` | `#128c7e` | Acentos, links |
| `--wa-accent` | `#25d366` | Status online, sucesso |
| `--wa-bg` | `#f0f2f5` | Fundo geral |
| `--wa-chat-bg` | `#efeae2` | Fundo do chat |

---

## Arquivos

### [NEW] [instances.php](file:///Users/sandropeixoto/Dev/Project-WhatsApp-Tools/instances.php)
- Bootstrap 5 + tema WhatsApp
- Grid de cards com perfil (foto, nome, telefone, status badges)
- Botão "Atualizar Instâncias" (chama sync_instances via fetch, re-renderiza)
- Clique card → `window.location = 'index.php?instance=nome'`

### [MODIFY] [index.php](file:///Users/sandropeixoto/Dev/Project-WhatsApp-Tools/index.php)
- Remover `screen-select` HTML e CSS
- Adicionar guard no topo: se `!$_GET['instance']` → redirect `instances.php`
- Substituir CSS custom por Bootstrap 5 classes + variáveis WhatsApp
- Top bar com Bootstrap `navbar` + botão "Trocar" → link `instances.php`
- Sidebar usa Bootstrap offcanvas
- Tab bar usa Bootstrap `nav-tabs`
- Tool boxes usam Bootstrap `card`
