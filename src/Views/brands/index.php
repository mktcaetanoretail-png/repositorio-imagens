<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="repo-hero">
    <img src="<?= e(url('assets/img/caetano-logo.svg')) ?>" alt="Caetano" class="repo-hero-logo">
    <h1 class="repo-hero-title">Repositório de Imagens</h1>
    <p class="repo-hero-sub"><?= e(count($brands)) ?> <?= count($brands) === 1 ? 'marca disponível' : 'marcas disponíveis' ?></p>
</div>

<?php if (empty($brands)): ?>
<div class="empty-state">
    <h2 class="empty-state-title">Nenhuma marca disponível</h2>
    <p class="empty-state-text">As marcas são criadas pela administração.</p>
    <?php if ($auth->can('manage_brands')): ?>
    <a href="<?= url('/admin/brands') ?>" class="btn btn-primary">Gerir marcas</a>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="brands-grid">
    <?php foreach ($brands as $brand): ?>
    <?php
        $logoFile = __DIR__ . '/../../../public/assets/img/brands/' . $brand['slug'] . '.svg';
        $logoUrl  = url('assets/img/brands/' . $brand['slug'] . '.svg');
        $hasLogo  = file_exists($logoFile);
    ?>
    <a href="<?= url('/brand/' . $brand['id']) ?>" class="brand-card">
        <div class="brand-card-icon">
            <?php if ($hasLogo): ?>
            <img src="<?= e($logoUrl) ?>" alt="<?= e($brand['name']) ?>" class="brand-card-logo">
            <?php else: ?>
            <div class="brand-card-monogram"><?= e(mb_strtoupper(mb_substr($brand['name'], 0, 2))) ?></div>
            <?php endif; ?>
        </div>
        <div class="brand-card-body">
            <div class="brand-card-name"><?= e($brand['name']) ?></div>
            <div class="brand-card-meta"><?= e($brand['location_count']) ?> <?= $brand['location_count'] === 1 ? 'localização' : 'localizações' ?></div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
