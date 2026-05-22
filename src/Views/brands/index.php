<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="repo-hero">
    <span class="repo-hero-wordmark">caetano</span>
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
    <a href="<?= url('/brand/' . $brand['id']) ?>" class="brand-card">
        <div class="brand-card-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <rect x="2" y="3" width="20" height="14" rx="2"/>
                <path d="M8 21h8M12 17v4"/>
            </svg>
        </div>
        <div class="brand-card-name"><?= e($brand['name']) ?></div>
        <div class="brand-card-meta"><?= e($brand['image_count']) ?> <?= $brand['image_count'] === 1 ? 'foto' : 'fotos' ?></div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
