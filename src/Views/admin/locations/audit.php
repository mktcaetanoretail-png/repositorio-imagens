<?php
$pageTitle = 'Auditoria de localizações';
require_once __DIR__ . '/../../layout/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Auditoria de localizações</h1>
        <span class="total-count">
            <?= e($missing_count) ?> de <?= e($total_count) ?> localizações com fotos em falta
        </span>
    </div>
    <div class="page-header-right">
        <?php if ($only_missing): ?>
        <a href="<?= url('/admin/localizacoes/auditoria?apenas_incompletas=0') ?>" class="btn btn-secondary">
            Mostrar todas
        </a>
        <?php else: ?>
        <a href="<?= url('/admin/localizacoes/auditoria?apenas_incompletas=1') ?>" class="btn btn-secondary">
            Mostrar só incompletas
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Marca</th>
                    <th>Localização</th>
                    <th>Fotos</th>
                    <th>Em falta</th>
                    <th class="table-actions-col">Acções</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($locations)): ?>
                <tr>
                    <td colspan="5" class="table-empty">
                        <?= $only_missing ? 'Todas as localizações têm o número completo de fotos.' : 'Nenhuma localização encontrada.' ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($locations as $loc): ?>
                <tr>
                    <td><span class="badge badge-brand"><?= e($loc['brand_name']) ?></span></td>
                    <td><?= e($loc['name']) ?></td>
                    <td>
                        <span class="badge <?= $loc['photo_count'] >= $max_photos ? 'badge-viewer' : 'badge-admin' ?>">
                            <?= e($loc['photo_count']) ?> / <?= e($max_photos) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($loc['missing'] > 0): ?>
                        <span class="badge badge-admin"><?= e($loc['missing']) ?> em falta</span>
                        <?php else: ?>
                        <span class="badge badge-viewer">Completa</span>
                        <?php endif; ?>
                    </td>
                    <td class="table-actions">
                        <a href="<?= url('/marcas/' . $loc['brand_slug'] . '/' . $loc['slug']) ?>"
                           class="btn btn-xs btn-secondary" target="_blank">
                            Ver fotos
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>
