# Visão Geral
Este projeto adiciona um sistema de autenticação à aplicação principal para proteger as páginas das instâncias. Os usuários farão login com um e-mail e senha. Todos os usuários autenticados terão permissões iguais (sem perfis/cargos restritos). A sessão será mantida por 30 dias sem necessidade de um novo login. O módulo `agendar` permanecerá totalmente intocado e funcional como está.

# Tipo de Projeto
WEB

# Critérios de Sucesso
- O acesso à página principal de instâncias e a todas as outras telas principais exigirá um login válido.
- Usuários não autenticados tentando acessar as páginas principais via URL serão redirecionados para a página de login.
- Existe um processo de cadastro de usuários para usuários internos.
- O primeiro usuário "master" é criado diretamente através de um script SQL INSERT.
- Todos os usuários têm o mesmo nível de acesso (sem perfis ou cargos).
- O módulo `agendar` continua funcionando perfeitamente sem modificações.
- As sessões de login persistem por 30 dias sem exigir reautenticação.
- Um botão "Sair" no menu encerra a sessão com sucesso.

# Stack Tecnológico
- **PHP**: Lógica do lado do servidor para autenticação, gerenciamento de sessão e verificação de rotas.
- **MySQL**: Banco de dados para armazenar credenciais de usuário com segurança (senhas criptografadas por hash).
- **HTML/CSS/JS (Bootstrap 5)**: Para a interface de login e formulário de cadastro de usuário.
- **Sessões/Cookies do PHP**: Para lidar com a funcionalidade de "lembrar-me" de 30 dias.

# Estrutura de Arquivos
- `login.php`: Página de login.
- `logout.php`: Encerramento da sessão e redirecionamento.
- `register.php`: Visualização de cadastro de usuários internos.
- `api/auth.php`: Script de middleware de verificação de autenticação para incluir em páginas protegidas.
- `database/auth_setup.sql`: Cria a tabela `system_users` e insere o primeiro usuário.
- `index.php` / `instances.php` / `group.php` / `feed.php` etc.: Inclusão do `auth.php` no topo para proteção.
- `components/sidebar.php` (ou arquivos de layout existentes): Adicionar o botão de menu "Sair".

# Divisão de Tarefas

## Tarefa 1: Configuração do Banco de Dados para Autenticação
- **Agente**: `database-architect`
- **Habilidade**: `database-design`
- **Ação**: Criar o script `database/auth_setup.sql`.
- **ENTRADA**: Definição de tabela SQL simples para usuários (`id`, `email`, `password_hash`, `created_at`).
- **SAÍDA**: Um arquivo `.sql` que cria a tabela `system_users` e insere o usuário master inicial.
- **VERIFICAÇÃO**: A tabela pode ser importada com sucesso e contém a linha de usuário inicial.

## Tarefa 2: Implementar Middleware de Autenticação
- **Agente**: `backend-specialist`
- **Habilidade**: `clean-code`
- **Ação**: Criar `api/auth.php` para verificar a sessão ou o cookie permanente de 30 dias. Redirecionar requisições não autenticadas para `login.php`.
- **ENTRADA**: Lógica de sessão ativa ou cookie de 30 dias.
- **SAÍDA**: Um arquivo PHP reutilizável protegendo qualquer página em que seja incluído.
- **VERIFICAÇÃO**: Incluir este arquivo no topo de uma página de teste protegida bloqueia o acesso quando não estiver logado.

## Tarefa 3: Implementar Login, Logout e Gerenciamento de Sessão
- **Agente**: `backend-specialist`
- **Habilidade**: `api-patterns`
- **Ação**: Criar `login.php` (interface e lógica) e `logout.php`. Implementar uma lógica de sessão baseada em cookie de 30 dias juntamente com sessões PHP padrão.
- **ENTRADA**: Conexão com banco de dados, credenciais de usuário do POST.
- **SAÍDA**: Fluxo de login funcional que define cookies persistentes (por 30 dias) e sessão PHP, e um script de logout que os destrói.
- **VERIFICAÇÃO**: Fazer login com as credenciais do usuário master é bem-sucedido e mantém o usuário conectado mesmo após reiniciar o navegador.

## Tarefa 4: Proteger as Páginas Principais e Adicionar Tela de Cadastro
- **Agente**: `backend-specialist`
- **Habilidade**: `clean-code`
- **Ação**: Exigir `api/auth.php` estritamente no topo de todas as páginas principais (como `index.php`, `instances.php`, etc.), excluindo inteiramente o módulo `agendar`. Implementar `register.php` para adição de usuários internos.
- **ENTRADA**: Páginas PHP existentes, novo middleware de autenticação.
- **SAÍDA**: Páginas seguras que evitam acesso direto por URL digitada. Uma página de cadastro de usuário funcional usando `password_hash()`.
- **VERIFICAÇÃO**: Tentar acessar `index.php` diretamente via URL sem login redireciona para `login.php`. O módulo Agendar permanece intocado.

## Tarefa 5: Atualizações de Menu
- **Agente**: `frontend-specialist`
- **Habilidade**: `frontend-design`
- **Ação**: Adicionar o botão "Sair" ao menu de navegação principal, apontando para `logout.php`. Adicionar também um link para `register.php`.
- **ENTRADA**: Arquivos de layout da IU principal (ex: `components/sidebar.php`).
- **SAÍDA**: Links visíveis de sair e registrar no menu lateral.
- **VERIFICAÇÃO**: Clicar em "Sair" desconecta o usuário e o redireciona para `login.php`.

## ✅ FASE X CONCLUÍDA
- [ ] Linting: Verificar a sintaxe de todos os novos arquivos PHP (`php -l`).
- [ ] Segurança: As senhas devem ser hash usando `password_hash()` e verificadas com `password_verify()`. Sem armazenamento em texto puro.
- [ ] Segurança: Garantir a prevenção contra injeção de SQL (prepared statements) no login e cadastro.
- [ ] Verificação: O usuário master consegue fazer login com sucesso.
- [ ] Verificação: O acesso direto por URL a `index.php` e outras ferramentas redireciona para login.
- [ ] Verificação: O módulo `agendar` continua funcionando sem interrupção de login.
- [ ] Verificação: A sessão persiste por 30 dias (verifique a data de expiração do cookie).
- [ ] Verificação: Clicar em "Sair" encerra a sessão corretamente.
