<?php require_once __DIR__ . '/../layout/header.php'; ?>

<div class="brand-header">
    <a href="<?= url('/') ?>" class="brand-header-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="m15 18-6-6 6-6"/>
        </svg>
        Marcas
    </a>
    <div class="brand-header-body">
        <div class="brand-header-identity">
            <div class="brand-header-monogram"><?= e(mb_substr($brand['name'], 0, 1)) ?></div>
            <div>
                <h1 class="brand-header-name"><?= e($brand['name']) ?></h1>
                <p class="brand-header-meta">
                    <?= e(count($locations)) ?> <?= count($locations) === 1 ? 'localização' : 'localizações' ?>
                </p>
            </div>
        </div>
        <?php if ($auth->can('manage_brands')): ?>
        <a href="<?= url('/admin/brands/' . $brand['id'] . '/locations') ?>" class="btn btn-secondary btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
            </svg>
            Gerir localizações
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($locations)): ?>
<div class="empty-state empty-state--brand">
    <div class="empty-state-icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
            <circle cx="12" cy="10" r="3"/>
        </svg>
    </div>
    <h2 class="empty-state-title">Nenhuma localização configurada</h2>
    <p class="empty-state-text">As localizações da marca <strong><?= e($brand['name']) ?></strong> ainda não foram criadas.</p>
    <?php if ($auth->can('manage_brands')): ?>
    <a href="<?= url('/admin/brands/' . $brand['id'] . '/locations') ?>" class="btn btn-primary">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Criar localizações
    </a>
    <?php endif; ?>
</div>
<?php else: ?>
<?php $brandLocations = $locations; ?>
<div class="brand-layout">
    <?php require_once __DIR__ . '/../layout/_brand_sidebar.php'; ?>

    <div class="brand-content">
        <div class="locations-grid">
            <?php foreach ($locations as $loc): ?>
            <a href="<?= url('/brand/' . $brand['id'] . '/location/' . $loc['id']) ?>" class="location-card">
                <div class="location-card-thumbnails">
                    <?php
                    $previews = $loc['preview_images'] ?? [];
                    for ($i = 0; $i < 3; $i++):
                        if (!empty($previews[$i])):
                    ?>
                    <img src="<?= e($previews[$i]['thumb_url']) ?>"
                         alt="<?= e($previews[$i]['original_filename'] ?? '') ?>"
                         class="location-card-thumb" loading="lazy">
                    <?php else: ?>
                    <div class="location-card-empty-thumb">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                    </div>
                    <?php endif; endfor; ?>
                </div>
                <div class="location-card-info">
                    <div class="location-card-name"><?= e($loc['name']) ?></div>
                    <div class="location-card-count">
                        <span><?= e($loc['image_count']) ?> / 4</span>
                        <div class="location-count-bar">
                            <div class="location-count-fill <?= $loc['image_count'] >= 4 ? 'location-count-fill--full' : '' ?>"
                                 style="width:<?= e(($loc['image_count'] / 4) * 100) ?>%"></div>
                        </div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
