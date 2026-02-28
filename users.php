<?php
$pageTitle = 'Gestão de Usuários';
require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
require_once __DIR__ . '/db.php';

$success = '';
$error = '';

// Ação de remover usuário
if (isset($_GET['delete'])) {
    $idToDelete = (int)$_GET['delete'];
    
    // Evita deletar o próprio usuário logado
    if ($idToDelete === (int)$_SESSION['user_id']) {
        $error = "Você não pode remover sua própria conta.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM system_users WHERE id = ?");
            $stmt->execute([$idToDelete]);
            $success = "Usuário removido com sucesso.";
        } catch (PDOException $e) {
            $error = "Erro ao remover usuário: " . $e->getMessage();
        }
    }
}

// Busca todos os usuários
try {
    $stmt = $pdo->query("SELECT id, email, created_at FROM system_users ORDER BY email ASC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Erro ao carregar usuários: " . $e->getMessage();
    $users = [];
}
?>

<main class="flex-grow-1 overflow-auto bg-light p-4">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold"><i class="bi bi-people-fill text-success me-2"></i> Gestão de Usuários</h3>
            <a href="register.php" class="btn btn-wa px-4 rounded-pill shadow-sm">
                <i class="bi bi-person-plus-fill me-2"></i> Novo Usuário
            </a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>E-mail</th>
                            <th>Data de Cadastro</th>
                            <th class="text-end pe-4">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    Nenhum usuário encontrado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td class="ps-4 fw-medium text-muted">#<?= $u['id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary-subtle text-primary rounded-circle p-2 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                                <?= strtoupper(substr($u['email'], 0, 1)) ?>
                                            </div>
                                            <span class="fw-bold"><?= htmlspecialchars($u['email']) ?></span>
                                            <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                                <span class="badge bg-success-subtle text-success-emphasis ms-2 small">Você</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-muted small">
                                        <?= date('d/m/Y H:i', strtotime($u['created_at'])) ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" 
                                                    onclick="if(confirm('Tem certeza que deseja remover este usuário?')) window.location.href='users.php?delete=<?= $u['id'] ?>'">
                                                <i class="bi bi-trash3 me-1"></i> Remover
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 disabled">
                                                <i class="bi bi-lock"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/components/footer.php'; ?>
