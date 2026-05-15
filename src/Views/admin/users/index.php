<?php
$pageTitle = 'Utilizadores';
require_once __DIR__ . '/../../layout/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Utilizadores</h1>
        <span class="total-count"><?= e(count($users)) ?> utilizadores</span>
    </div>
    <div class="page-header-right">
        <a href="<?= url('/admin/users/create') ?>" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Novo utilizador
        </a>
    </div>
</div>

<?php if (!empty($flash_ok)): ?>
<div class="alert alert-success" role="alert"><?= e($flash_ok) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
<div class="alert alert-error" role="alert"><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Função</th>
                    <th>Estado</th>
                    <th>Criado em</th>
                    <th class="table-actions-col">Acções</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="6" class="table-empty">Nenhum utilizador encontrado.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($users as $user): ?>
                <tr class="<?= !$user['active'] ? 'row-inactive' : '' ?>">
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar user-avatar--sm"><?= e(mb_substr($user['name'], 0, 1)) ?></div>
                            <?= e($user['name']) ?>
                        </div>
                    </td>
                    <td><?= e($user['email']) ?></td>
                    <td>
                        <span class="badge badge-role badge-<?= e($user['role']) ?>">
                            <?= e(match($user['role']) {
                                'admin'  => 'Administrador',
                                'editor' => 'Editor',
                                default  => 'Visualizador',
                            }) ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-dot <?= $user['active'] ? 'status-active' : 'status-inactive' ?>">
                            <?= $user['active'] ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </td>
                    <td class="table-date"><?= e(date('d/m/Y', strtotime($user['created_at']))) ?></td>
                    <td class="table-actions">
                        <a href="<?= url('/admin/users/' . $user['id'] . '/edit') ?>" class="btn btn-xs btn-secondary">
                            Editar
                        </a>
                        <?php if ((int)$user['id'] !== (int)$auth->user()['id']): ?>
                        <button class="btn btn-xs <?= $user['active'] ? 'btn-warning' : 'btn-success' ?>"
                                data-toggle-user="<?= e($user['id']) ?>"
                                data-active="<?= (int)$user['active'] ?>">
                            <?= $user['active'] ? 'Desactivar' : 'Activar' ?>
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

<script>
document.querySelectorAll('[data-toggle-user]').forEach(btn => {
    btn.addEventListener('click', async function () {
        const userId = this.dataset.toggleUser;
        const isActive = this.dataset.active === '1';
        const action = isActive ? 'desactivar' : 'activar';

        if (!confirm(`Tem a certeza que deseja ${action} este utilizador?`)) return;

        try {
            const res = await fetch(`/admin/users/${userId}/toggle`, {
                method : 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body   : `csrf_token=${encodeURIComponent(window.APP?.csrfToken ?? '')}`,
            });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                alert('Erro: ' + (data.error || 'Operação falhada.'));
            }
        } catch (e) {
            alert('Erro de comunicação.');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>
