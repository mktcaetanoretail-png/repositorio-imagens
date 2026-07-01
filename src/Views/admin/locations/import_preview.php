<?php
$pageTitle = 'Pré-visualização da importação';
require_once __DIR__ . '/../../layout/header.php';

$statusLabels = [
    'ok'        => ['Pronto a criar', 'badge-location'],
    'duplicate' => ['Já existe', 'badge-editor'],
    'no_brand'  => ['Marca não encontrada', 'badge-admin'],
    'invalid'   => ['Formato inválido', 'badge-admin'],
];

$okCount = count(array_filter($rows, fn($r) => $r['status'] === 'ok'));
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Pré-visualização da importação</h1>
        <span class="total-count"><?= e(count($rows)) ?> linha(s) analisada(s), <?= e($okCount) ?> pronta(s) a criar</span>
    </div>
    <div class="page-header-right">
        <a href="<?= url('/admin/localizacoes/importar') ?>" class="btn btn-secondary">Voltar e corrigir</a>
    </div>
</div>

<?php if ($okCount === 0): ?>
<div class="alert alert-error" role="alert">Nenhuma linha está pronta a ser criada. Corrige os dados e tenta novamente.</div>
<?php endif; ?>

<div class="card">
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Linha original</th>
                    <th>Marca</th>
                    <th>Localização</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <?php [$label, $badgeClass] = $statusLabels[$row['status']] ?? ['—', 'badge-viewer']; ?>
                <tr>
                    <td><code class="code-slug"><?= e($row['raw']) ?></code></td>
                    <td><?= e($row['brand_name'] ?? $row['brand_text'] ?? '—') ?></td>
                    <td><?= e($row['location_text'] ?? $row['location_name'] ?? '—') ?></td>
                    <td>
                        <span class="badge <?= e($badgeClass) ?>" title="<?= e($row['message'] ?? '') ?>">
                            <?= e($label) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($okCount > 0): ?>
<div class="form-actions" style="margin-top: 1.5rem;">
    <form method="post" action="<?= e(url('/admin/localizacoes/importar/confirmar')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="data" value="<?= e($raw) ?>">
        <button type="submit" class="btn btn-primary">
            Confirmar e criar <?= e($okCount) ?> localização(ões)
        </button>
    </form>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>
