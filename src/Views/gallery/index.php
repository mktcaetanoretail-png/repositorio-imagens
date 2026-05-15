<?php
$pageTitle = 'Galeria';
require_once __DIR__ . '/../layout/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Galeria</h1>
        <span class="total-count" id="totalCount"><?= e($total) ?> imagens</span>
    </div>
    <div class="page-header-right toolbar" id="bulkToolbar" style="display:none">
        <span class="selection-count" id="selectionCount">0 seleccionadas</span>
        <button class="btn btn-secondary btn-sm" id="selectAllBtn">Seleccionar todas</button>
        <button class="btn btn-secondary btn-sm" id="deselectAllBtn">Limpar</button>
        <button class="btn btn-primary btn-sm" id="bulkDownloadOptBtn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            </svg>
            Transferir optimizadas
        </button>
        <?php if ($auth->can('download_original')): ?>
        <button class="btn btn-secondary btn-sm" id="bulkDownloadOrigBtn">Transferir originais</button>
        <?php endif; ?>
    </div>
</div>

<div class="gallery-layout">
    <!-- Sidebar Filters -->
    <aside class="filter-panel" id="filterPanel">
        <div class="filter-section">
            <h3 class="filter-title">
                Marcas
                <button class="filter-clear" id="clearBrands" title="Limpar filtro de marcas">×</button>
            </h3>
            <div class="filter-list" id="brandFilters">
                <?php foreach ($brands as $brand): ?>
                <label class="filter-checkbox">
                    <input type="checkbox" name="brand_id[]" value="<?= e($brand['id']) ?>"
                        <?= in_array($brand['id'], (array) ($filters['brand_id'] ?? []), false) ? 'checked' : '' ?>>
                    <span class="filter-label"><?= e($brand['name']) ?></span>
                </label>
                <?php endforeach; ?>
                <?php if (empty($brands)): ?>
                <p class="filter-empty">Nenhuma marca disponível.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="filter-section">
            <h3 class="filter-title">
                Localização
                <button class="filter-clear" id="clearLocations" title="Limpar filtro de localizações">×</button>
            </h3>
            <div class="filter-pills" id="locationFilters">
                <?php foreach ($locations as $loc): ?>
                <button class="filter-pill <?= in_array($loc['id'], (array) ($filters['location_id'] ?? []), false) ? 'active' : '' ?>"
                        data-location-id="<?= e($loc['id']) ?>">
                    <?= e($loc['name']) ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="filter-section">
            <h3 class="filter-title">Ordenação</h3>
            <select class="form-select form-select--sm" id="sortSelect">
                <option value="newest" <?= ($filters['sort'] ?? 'newest') === 'newest' ? 'selected' : '' ?>>Mais recentes</option>
                <option value="oldest" <?= ($filters['sort'] ?? '') === 'oldest' ? 'selected' : '' ?>>Mais antigas</option>
                <option value="name_asc" <?= ($filters['sort'] ?? '') === 'name_asc' ? 'selected' : '' ?>>Nome (A-Z)</option>
                <option value="name_desc" <?= ($filters['sort'] ?? '') === 'name_desc' ? 'selected' : '' ?>>Nome (Z-A)</option>
                <option value="size_desc" <?= ($filters['sort'] ?? '') === 'size_desc' ? 'selected' : '' ?>>Tamanho (maior)</option>
                <option value="size_asc" <?= ($filters['sort'] ?? '') === 'size_asc' ? 'selected' : '' ?>>Tamanho (menor)</option>
            </select>
        </div>

        <?php if ($auth->can('restore_images')): ?>
        <div class="filter-section">
            <label class="filter-checkbox">
                <input type="checkbox" id="showDeletedToggle"
                    <?= !empty($filters['show_deleted']) ? 'checked' : '' ?>>
                <span class="filter-label">Mostrar eliminadas</span>
            </label>
        </div>
        <?php endif; ?>

        <button class="btn btn-secondary btn-sm btn-block" id="resetFilters">Limpar todos os filtros</button>
    </aside>

    <!-- Gallery Grid -->
    <div class="gallery-main">
        <div class="gallery-topbar">
            <div class="view-toggle">
                <button class="view-btn active" data-view="grid" title="Grelha" id="viewGrid">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                </button>
                <button class="view-btn" data-view="list" title="Lista" id="viewList">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/>
                        <line x1="8" y1="18" x2="21" y2="18"/>
                        <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/>
                        <line x1="3" y1="18" x2="3.01" y2="18"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Image Grid -->
        <div class="image-grid" id="imageGrid" data-view="grid">
            <?php if (empty($images)): ?>
            <div class="empty-state" id="emptyState">
                <svg class="empty-state-svg" viewBox="0 0 200 160" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <rect x="20" y="20" width="160" height="120" rx="8" fill="#f1f5f9"/>
                    <rect x="40" y="40" width="50" height="50" rx="4" fill="#e2e8f0"/>
                    <rect x="100" y="40" width="60" height="20" rx="3" fill="#e2e8f0"/>
                    <rect x="100" y="68" width="40" height="12" rx="3" fill="#e2e8f0"/>
                    <rect x="40" y="100" width="120" height="12" rx="3" fill="#e2e8f0"/>
                    <circle cx="65" cy="65" r="12" fill="#cbd5e1"/>
                    <path d="M58 70 L65 61 L72 70" fill="#94a3b8"/>
                    <circle cx="70" cy="58" r="4" fill="#94a3b8"/>
                </svg>
                <h3 class="empty-state-title">Sem imagens</h3>
                <p class="empty-state-text">
                    <?php if (!empty($filters['search']) || !empty($filters['brand_id']) || !empty($filters['location_id'])): ?>
                    Nenhuma imagem corresponde aos filtros activos. <a href="<?= url('/') ?>" class="link">Limpar filtros</a>
                    <?php elseif ($auth->can('upload')): ?>
                    Ainda não existem imagens no repositório. Carregue a primeira!
                    <?php else: ?>
                    Ainda não existem imagens no repositório.
                    <?php endif; ?>
                </p>
                <?php if ($auth->can('upload')): ?>
                <button class="btn btn-primary" id="emptyStateUpload">Carregar imagens</button>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <?php foreach ($images as $image): ?>
            <div class="image-card" data-id="<?= e($image['id']) ?>">
                <div class="image-card-thumb">
                    <img src="<?= e($image['thumb_url']) ?>"
                         alt="<?= e($image['original_filename']) ?>"
                         loading="lazy"
                         class="image-thumb"
                         data-id="<?= e($image['id']) ?>">
                    <div class="image-card-overlay">
                        <button class="overlay-btn" data-lightbox="<?= e($image['id']) ?>" title="Visualizar">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                        <a href="<?= e($image['download_url']) ?>" class="overlay-btn" title="Transferir" download>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            </svg>
                        </a>
                    </div>
                    <label class="image-select-label">
                        <input type="checkbox" class="image-select" value="<?= e($image['id']) ?>">
                        <span class="image-select-custom"></span>
                    </label>
                    <?php if (!empty($image['deleted_at'])): ?>
                    <div class="image-deleted-badge">Eliminada</div>
                    <?php endif; ?>
                </div>
                <div class="image-card-info">
                    <p class="image-filename" title="<?= e($image['original_filename']) ?>"><?= e($image['original_filename']) ?></p>
                    <div class="image-badges">
                        <span class="badge badge-brand"><?= e($image['brand_name']) ?></span>
                        <span class="badge badge-location"><?= e($image['location_name']) ?></span>
                    </div>
                    <p class="image-meta-line">
                        <?= e($image['width']) ?>×<?= e($image['height']) ?>
                        · <?= e($image['optimized_filesize_human'] ?? $image['filesize_human']) ?>
                        <?php if (!empty($image['optimization_ratio']) && $image['optimization_ratio'] > 0): ?>
                        · <span class="saving-badge">-<?= e(number_format((float)$image['optimization_ratio'], 1)) ?>%</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
        <nav class="pagination" aria-label="Paginação" id="pagination">
            <?php if ($pagination['has_prev']): ?>
            <button class="pagination-btn" data-page="<?= e($pagination['prev_page']) ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="m15 18-6-6 6-6"/>
                </svg>
                Anterior
            </button>
            <?php endif; ?>

            <div class="pagination-pages">
                <?php
                $current = $pagination['current_page'];
                $total   = $pagination['total_pages'];
                $range   = 2;
                for ($i = 1; $i <= $total; $i++):
                    if ($i === 1 || $i === $total || abs($i - $current) <= $range):
                ?>
                <button class="pagination-page <?= $i === $current ? 'active' : '' ?>" data-page="<?= $i ?>"><?= $i ?></button>
                <?php
                    elseif (abs($i - $current) === $range + 1):
                ?>
                <span class="pagination-ellipsis">…</span>
                <?php
                    endif;
                endfor;
                ?>
            </div>

            <?php if ($pagination['has_next']): ?>
            <button class="pagination-btn" data-page="<?= e($pagination['next_page']) ?>">
                Seguinte
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="m9 18 6-6-6-6"/>
                </svg>
            </button>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Bulk download form (hidden, submitted via JS) -->
<form id="bulkDownloadForm" method="post" action="<?= url('/download/bulk') ?>" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
    <input type="hidden" name="version" id="bulkVersion" value="optimized">
    <div id="bulkIdsContainer"></div>
</form>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
