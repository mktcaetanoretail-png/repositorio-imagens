<?php
$pageTitle = 'Conversor de Imagens';
require_once __DIR__ . '/../layout/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Conversor de Imagens</h1>
    <p class="page-subtitle">Converta e redimensione imagens do repositório</p>
</div>

<div class="converter-layout">
    <!-- Left Panel: Image Selection -->
    <div class="converter-source">
        <div class="converter-panel-header">
            <h2>Seleccionar Imagem</h2>
            <div class="converter-search-bar">
                <div class="form-group-row">
                    <select class="form-select form-select--sm" id="convBrand">
                        <option value="">Todas as marcas</option>
                        <?php foreach ($brands as $b): ?>
                        <option value="<?= e($b['id']) ?>" <?= (string)($filters['brand_id'] ?? '') === (string)$b['id'] ? 'selected' : '' ?>>
                            <?= e($b['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select form-select--sm" id="convLocation">
                        <option value="">Todas as localizações</option>
                        <?php foreach ($locations as $l): ?>
                        <option value="<?= e($l['id']) ?>" <?= (string)($filters['location_id'] ?? '') === (string)$l['id'] ? 'selected' : '' ?>>
                            <?= e($l['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="search" class="form-input form-input--sm" id="convSearch"
                       placeholder="Pesquisar imagens..." value="<?= e($filters['search'] ?? '') ?>">
            </div>
        </div>

        <div class="converter-image-list" id="convImageList">
            <?php if (empty($images)): ?>
            <div class="empty-state empty-state--sm">
                <p>Sem imagens disponíveis.</p>
            </div>
            <?php else: ?>
            <?php foreach ($images as $img): ?>
            <div class="conv-image-item" data-id="<?= e($img['id']) ?>"
                 data-name="<?= e($img['original_filename']) ?>"
                 data-size="<?= e($img['filesize']) ?>"
                 data-size-human="<?= e($img['filesize_human']) ?>">
                <img src="<?= e($img['thumb_url']) ?>" alt="<?= e($img['original_filename']) ?>" class="conv-thumb" loading="lazy">
                <div class="conv-image-info">
                    <p class="conv-filename"><?= e($img['original_filename']) ?></p>
                    <div class="conv-badges">
                        <span class="badge badge-brand badge-sm"><?= e($img['brand_name']) ?></span>
                        <span class="badge badge-location badge-sm"><?= e($img['location_name']) ?></span>
                    </div>
                    <p class="conv-size"><?= e($img['filesize_human']) ?></p>
                </div>
                <div class="conv-select-indicator">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Panel: Options & Results -->
    <div class="converter-options">
        <div class="converter-selected-preview" id="convSelectedPreview">
            <div class="conv-no-selection">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/>
                    <polyline points="21 15 16 10 5 21"/>
                </svg>
                <p>Seleccione uma imagem à esquerda</p>
            </div>
            <div class="conv-preview-content" id="convPreviewContent" hidden>
                <img id="convPreviewImg" src="" alt="" class="conv-preview-img">
                <p class="conv-preview-name" id="convPreviewName"></p>
                <p class="conv-preview-size" id="convPreviewSize"></p>
            </div>
        </div>

        <form id="converterForm" class="converter-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
            <input type="hidden" name="image_id" id="convImageId" value="">

            <div class="form-group">
                <label class="form-label">Formato de saída</label>
                <div class="format-selector" id="formatSelector">
                    <button type="button" class="format-btn active" data-format="jpg">JPG</button>
                    <button type="button" class="format-btn" data-format="png">PNG</button>
                    <button type="button" class="format-btn" data-format="webp">WEBP</button>
                </div>
                <input type="hidden" name="format" id="convFormat" value="jpg">
            </div>

            <div class="form-group">
                <label class="form-label" for="convQuality">
                    Qualidade: <strong id="qualityValue">82</strong>%
                </label>
                <input type="range" id="convQuality" name="quality"
                       min="1" max="100" value="82" class="range-input">
                <div class="range-labels">
                    <span>Menor ficheiro</span>
                    <span>Maior qualidade</span>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="convMaxWidth">Largura máxima (px)</label>
                <div class="input-group">
                    <select class="form-select" id="convMaxWidthPreset">
                        <option value="">Sem redimensionamento</option>
                        <option value="3840">4K (3840px)</option>
                        <option value="2560">2K (2560px)</option>
                        <option value="1920">Full HD (1920px)</option>
                        <option value="1280">HD (1280px)</option>
                        <option value="800">Web (800px)</option>
                        <option value="custom">Personalizado</option>
                    </select>
                    <input type="number" name="max_width" id="convMaxWidth"
                           class="form-input" placeholder="largura em px" min="1" max="10000" style="display:none">
                </div>
            </div>

            <div class="converter-estimate" id="estimateBox" hidden>
                <div class="estimate-row">
                    <span>Tamanho actual:</span>
                    <strong id="estOrigSize">—</strong>
                </div>
                <div class="estimate-row">
                    <span>Estimativa:</span>
                    <strong id="estNewSize">—</strong>
                </div>
                <div class="estimate-row">
                    <span>Poupança:</span>
                    <strong id="estSavings" class="savings-positive">—</strong>
                </div>
            </div>

            <div class="converter-actions">
                <button type="button" class="btn btn-secondary" id="estimateBtn" disabled>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    Estimar tamanho
                </button>
                <button type="submit" class="btn btn-primary" id="convertBtn" disabled>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2a10 10 0 0 1 7.38 16.74M12 22a10 10 0 0 1-7.38-16.74"/>
                        <polyline points="21 2 16 7 11 2"/><polyline points="3 22 8 17 13 22"/>
                    </svg>
                    Converter e transferir
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const csrfToken    = '<?= e($csrf_token) ?>';
    const estimateUrl  = '<?= url('/converter/estimate') ?>';
    const processUrl   = '<?= url('/converter/process') ?>';

    let selectedId   = null;
    let selectedSize = 0;

    // Format selector
    document.querySelectorAll('.format-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.format-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('convFormat').value = this.dataset.format;
            // PNG quality doesn't apply — visual feedback
            const qualityGroup = document.getElementById('convQuality').closest('.form-group');
            qualityGroup.style.opacity = this.dataset.format === 'png' ? '0.5' : '1';
        });
    });

    // Quality slider
    const qualitySlider = document.getElementById('convQuality');
    qualitySlider.addEventListener('input', () => {
        document.getElementById('qualityValue').textContent = qualitySlider.value;
    });

    // Max width preset
    document.getElementById('convMaxWidthPreset').addEventListener('change', function () {
        const customInput = document.getElementById('convMaxWidth');
        if (this.value === 'custom') {
            customInput.style.display = '';
            customInput.value = '';
        } else {
            customInput.style.display = 'none';
            customInput.value = this.value;
        }
    });

    // Image item selection
    document.querySelectorAll('.conv-image-item').forEach(item => {
        item.addEventListener('click', function () {
            document.querySelectorAll('.conv-image-item').forEach(i => i.classList.remove('selected'));
            this.classList.add('selected');
            selectedId   = this.dataset.id;
            selectedSize = parseInt(this.dataset.size, 10) || 0;

            document.getElementById('convImageId').value = selectedId;
            document.getElementById('estimateBtn').disabled = false;
            document.getElementById('convertBtn').disabled = false;

            // Preview
            const img     = this.querySelector('img');
            const previewImg  = document.getElementById('convPreviewImg');
            const previewName = document.getElementById('convPreviewName');
            const previewSize = document.getElementById('convPreviewSize');
            previewImg.src  = img.src;
            previewImg.alt  = this.dataset.name;
            previewName.textContent = this.dataset.name;
            previewSize.textContent = this.dataset.sizeHuman;

            document.querySelector('.conv-no-selection').hidden = true;
            document.getElementById('convPreviewContent').hidden = false;
        });
    });

    // Search/filter
    function filterList() {
        const search   = document.getElementById('convSearch').value.toLowerCase();
        const brandId  = document.getElementById('convBrand').value;
        const locId    = document.getElementById('convLocation').value;
        document.querySelectorAll('.conv-image-item').forEach(item => {
            const name   = (item.dataset.name || '').toLowerCase();
            const brand  = item.querySelector('.badge-brand')?.textContent?.toLowerCase() || '';
            const loc    = item.querySelector('.badge-location')?.textContent?.toLowerCase() || '';
            const matchSearch = !search || name.includes(search) || brand.includes(search);
            const matchBrand  = !brandId || item.dataset.brandId === brandId;
            const matchLoc    = !locId   || item.dataset.locId   === locId;
            item.style.display = (matchSearch && matchBrand && matchLoc) ? '' : 'none';
        });
    }

    document.getElementById('convSearch').addEventListener('input', filterList);
    document.getElementById('convBrand').addEventListener('change', filterList);
    document.getElementById('convLocation').addEventListener('change', filterList);

    // Estimate
    document.getElementById('estimateBtn').addEventListener('click', async function () {
        if (!selectedId) return;
        this.disabled = true;
        this.textContent = 'A calcular…';

        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('image_id', selectedId);
        fd.append('format', document.getElementById('convFormat').value);
        fd.append('quality', document.getElementById('convQuality').value);
        const mw = document.getElementById('convMaxWidth').value;
        if (mw) fd.append('max_width', mw);

        try {
            const res  = await fetch(estimateUrl, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                document.getElementById('estOrigSize').textContent = data.original_human;
                document.getElementById('estNewSize').textContent  = data.estimated_human;
                const savEl = document.getElementById('estSavings');
                savEl.textContent = data.savings_pct + '%';
                savEl.className   = data.savings_pct >= 0 ? 'savings-positive' : 'savings-negative';
                document.getElementById('estimateBox').hidden = false;
            } else {
                alert('Erro: ' + data.error);
            }
        } catch (e) {
            alert('Erro de comunicação com o servidor.');
        } finally {
            this.disabled = false;
            this.textContent = 'Estimar tamanho';
        }
    });

    // Convert & download (submits form as standard POST → file download)
    document.getElementById('converterForm').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!selectedId) return;

        // Build a hidden form and submit to trigger file download
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = processUrl;

        const fields = {
            csrf_token : csrfToken,
            image_id   : selectedId,
            format     : document.getElementById('convFormat').value,
            quality    : document.getElementById('convQuality').value,
            max_width  : document.getElementById('convMaxWidth').value,
        };

        Object.entries(fields).forEach(([k, v]) => {
            if (v !== '') {
                const inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = k;
                inp.value = v;
                form.appendChild(inp);
            }
        });

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });
})();
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
