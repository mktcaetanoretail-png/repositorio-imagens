<?php
$pageTitle = 'Marcas';
require_once __DIR__ . '/../../layout/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Marcas</h1>
        <span class="total-count"><?= e(count($brands)) ?> marcas</span>
    </div>
    <div class="page-header-right">
        <a href="<?= url('/admin/localizacoes/importar') ?>" class="btn btn-secondary">
            Importar localizações
        </a>
        <a href="<?= url('/admin/marcas/criar') ?>" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Nova marca
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
                    <th>Slug</th>
                    <th>Pasta de armazenamento</th>
                    <th>Criado em</th>
                    <th class="table-actions-col">Acções</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($brands)): ?>
                <tr>
                    <td colspan="5" class="table-empty">Nenhuma marca encontrada.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($brands as $brand): ?>
                <tr>
                    <td><strong><?= e($brand['name']) ?></strong></td>
                    <td><code class="code-slug"><?= e($brand['slug']) ?></code></td>
                    <td><code class="code-path">/storage/images/<?= e($brand['slug']) ?>/</code></td>
                    <td class="table-date"><?= e(date('d/m/Y', strtotime($brand['created_at']))) ?></td>
                    <td class="table-actions">
                        <a href="<?= url('/admin/marcas/' . $brand['id'] . '/localizacoes') ?>" class="btn btn-xs btn-secondary">
                            Localizações
                        </a>
                        <a href="<?= url('/admin/marcas/' . $brand['id'] . '/editar') ?>" class="btn btn-xs btn-secondary">
                            Editar
                        </a>
                        <button class="btn btn-xs btn-danger"
                                data-delete-brand="<?= e($brand['id']) ?>"
                                data-name="<?= e($brand['name']) ?>">
                            Apagar
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.querySelectorAll('[data-delete-brand]').forEach(btn => {
    btn.addEventListener('click', async function () {
        const id   = this.dataset.deleteBrand;
        const name = this.dataset.name;
        const row  = this.closest('tr');

        const ok = await window.confirm2(
            `Apagar a marca "${name}"? Esta acção é irreversível.\n\nSó é possível apagar marcas sem imagens associadas.`,
            'Apagar marca'
        );
        if (!ok) return;

        this.disabled = true;
        try {
            const res  = await fetch(`/admin/marcas/${id}/eliminar`, {
                method : 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body   : `csrf_token=${encodeURIComponent(window.APP?.csrfToken ?? '')}`,
            });
            const data = await res.json();
            if (data.success) {
                row.style.transition = 'opacity 0.25s';
                row.style.opacity    = '0';
                setTimeout(() => {
                    row.remove();
                    const tbody = document.querySelector('.admin-table tbody');
                    if (tbody && !tbody.querySelector('tr:not([style*="opacity"])')) {
                        tbody.innerHTML = '<tr><td colspan="5" class="table-empty">Nenhuma marca encontrada.</td></tr>';
                    }
                    const counter = document.querySelector('.total-count');
                    if (counter) {
                        const n = (parseInt(counter.textContent) || 1) - 1;
                        counter.textContent = `${n} marca${n !== 1 ? 's' : ''}`;
                    }
                }, 260);
                window.toast?.success(`Marca "${name}" apagada.`);
            } else {
                this.disabled = false;
                window.toast?.error(data.error || 'Não foi possível apagar a marca.');
            }
        } catch (e) {
            this.disabled = false;
            window.toast?.error('Erro de comunicação.');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>
