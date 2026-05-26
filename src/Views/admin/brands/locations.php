<?php require_once __DIR__ . '/../../layout/header.php'; ?>

<div class="brand-header">
    <a href="<?= url('/brand/' . $brand['id']) ?>" class="brand-header-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="m15 18-6-6 6-6"/>
        </svg>
        <?= e($brand['name']) ?>
    </a>
    <div class="brand-header-body">
        <div class="brand-header-identity">
            <div class="brand-header-monogram"><?= e(mb_substr($brand['name'], 0, 1)) ?></div>
            <div>
                <h1 class="brand-header-name">Localizações</h1>
                <p class="brand-header-meta"><?= e(count($locations)) ?> <?= count($locations) === 1 ? 'localização' : 'localizações' ?></p>
            </div>
        </div>
        <a href="<?= url('/admin/brands/' . $brand['id'] . '/locations/create') ?>" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Nova localização
        </a>
    </div>
</div>

<?php if (!empty($flash_ok)): ?>
<div class="alert alert-success"><?= e($flash_ok) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
<div class="alert alert-error"><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Slug</th>
                    <th>Fotos</th>
                    <th class="table-actions-col">Acções</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($locations)): ?>
                <tr><td colspan="4" class="table-empty">Nenhuma localização criada ainda.</td></tr>
                <?php else: ?>
                <?php foreach ($locations as $loc): ?>
                <tr>
                    <td><strong><?= e($loc['name']) ?></strong></td>
                    <td><code class="code-slug"><?= e($loc['slug']) ?></code></td>
                    <td>
                        <span class="badge <?= $loc['image_count'] >= 4 ? 'badge-brand' : 'badge-viewer' ?>">
                            <?= e($loc['image_count']) ?> / 4
                        </span>
                    </td>
                    <td class="table-actions">
                        <a href="<?= url('/brand/' . $brand['id'] . '/location/' . $loc['id']) ?>" class="btn btn-xs btn-secondary" target="_blank">
                            Ver fotos
                        </a>
                        <button class="btn btn-xs btn-danger"
                                data-delete-location="<?= e($loc['id']) ?>"
                                data-brand-id="<?= e($brand['id']) ?>"
                                data-name="<?= e($loc['name']) ?>"
                                data-count="<?= e($loc['image_count']) ?>">
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
document.querySelectorAll('[data-delete-location]').forEach(btn => {
    btn.addEventListener('click', async function () {
        const id      = this.dataset.deleteLocation;
        const brandId = this.dataset.brandId;
        const name    = this.dataset.name;
        const count   = parseInt(this.dataset.count, 10);
        const row     = this.closest('tr');

        if (count > 0) {
            window.toast?.error(`Não é possível apagar "${name}": tem ${count} foto(s) associada(s). Elimina primeiro as fotos.`);
            return;
        }

        const ok = await window.confirm2(`Apagar a localização "${name}"? Esta acção é irreversível.`, 'Apagar localização');
        if (!ok) return;

        this.disabled = true;
        try {
            const res  = await fetch(`/admin/brands/${brandId}/locations/${id}/delete`, {
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
                        tbody.innerHTML = '<tr><td colspan="4" class="table-empty">Nenhuma localização criada ainda.</td></tr>';
                    }
                    const meta = document.querySelector('.brand-header-meta');
                    if (meta) {
                        const n = (parseInt(meta.textContent) || 1) - 1;
                        meta.textContent = `${n} localização${n !== 1 ? 'ões' : ''}`;
                    }
                }, 260);
                window.toast?.success(`Localização "${name}" apagada.`);
            } else {
                this.disabled = false;
                window.toast?.error(data.error || 'Não foi possível apagar a localização.');
            }
        } catch (e) {
            this.disabled = false;
            window.toast?.error('Erro de comunicação.');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>
