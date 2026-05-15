    </main>
<?php if ($auth->check()): ?>
</div><!-- /.app-layout -->

<!-- Upload Modal -->
<?php if ($auth->can('upload')): ?>
<div class="modal-overlay" id="uploadModal" hidden>
    <div class="modal modal--upload" role="dialog" aria-modal="true" aria-labelledby="uploadModalTitle">
        <div class="modal-header">
            <h2 class="modal-title" id="uploadModalTitle">Carregar Imagens</h2>
            <button class="modal-close" id="closeUploadModal" aria-label="Fechar">&times;</button>
        </div>
        <div class="modal-body">
            <div class="upload-meta-fields">
                <div class="form-group">
                    <label class="form-label" for="uploadBrand">Marca <span class="required">*</span></label>
                    <select class="form-select" id="uploadBrand" name="brand_id" required>
                        <option value="">Seleccionar marca...</option>
                        <?php foreach ($brands ?? [] as $b): ?>
                        <option value="<?= e($b['id']) ?>"><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="uploadLocation">Localização <span class="required">*</span></label>
                    <select class="form-select" id="uploadLocation" name="location_id" required>
                        <option value="">Seleccionar localização...</option>
                        <?php foreach ($locations ?? [] as $l): ?>
                        <option value="<?= e($l['id']) ?>"><?= e($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="dropzone" id="dropzone">
                <div class="dropzone-content">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="dropzone-icon">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    <p class="dropzone-text">Arraste imagens para aqui ou</p>
                    <label class="btn btn-secondary" for="fileInput">
                        Escolher ficheiros
                        <input type="file" id="fileInput" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden>
                    </label>
                    <p class="dropzone-hint">JPG, PNG, WEBP, GIF · Máx. <?= e(env('UPLOAD_MAX_SIZE_MB', 20)) ?> MB por ficheiro · Máx. <?= e(env('UPLOAD_MAX_FILES', 20)) ?> ficheiros</p>
                </div>
            </div>

            <div class="upload-file-list" id="uploadFileList" hidden></div>

            <div class="upload-results" id="uploadResults" hidden>
                <h3 class="upload-results-title">Resultados do Upload</h3>
                <div class="upload-results-list" id="uploadResultsList"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="cancelUpload">Cancelar</button>
            <button class="btn btn-primary" id="startUpload" disabled>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                Iniciar upload
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" hidden role="dialog" aria-modal="true" aria-label="Visualizador de imagem">
    <div class="lightbox-backdrop" id="lightboxBackdrop"></div>
    <div class="lightbox-container">
        <button class="lightbox-close" id="lightboxClose" aria-label="Fechar">&times;</button>
        <button class="lightbox-nav lightbox-prev" id="lightboxPrev" aria-label="Anterior">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="m15 18-6-6 6-6"/>
            </svg>
        </button>
        <button class="lightbox-nav lightbox-next" id="lightboxNext" aria-label="Seguinte">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="m9 18 6-6-6-6"/>
            </svg>
        </button>
        <div class="lightbox-image-wrap">
            <img class="lightbox-img" id="lightboxImg" src="" alt="">
            <div class="lightbox-loading" id="lightboxLoading">
                <div class="spinner"></div>
            </div>
        </div>
        <div class="lightbox-panel" id="lightboxPanel">
            <div class="lightbox-panel-header">
                <h3 class="lightbox-filename" id="lightboxFilename">—</h3>
            </div>
            <div class="lightbox-meta" id="lightboxMeta">
                <div class="meta-row"><span class="meta-label">Marca</span><span class="meta-value" id="lbBrand">—</span></div>
                <div class="meta-row"><span class="meta-label">Localização</span><span class="meta-value" id="lbLocation">—</span></div>
                <div class="meta-row"><span class="meta-label">Dimensões</span><span class="meta-value" id="lbDimensions">—</span></div>
                <div class="meta-row"><span class="meta-label">Tamanho original</span><span class="meta-value" id="lbOrigSize">—</span></div>
                <div class="meta-row"><span class="meta-label">Tamanho optimizado</span><span class="meta-value" id="lbOptSize">—</span></div>
                <div class="meta-row"><span class="meta-label">Redução</span><span class="meta-value" id="lbRatio">—</span></div>
                <div class="meta-row"><span class="meta-label">Carregado por</span><span class="meta-value" id="lbUploader">—</span></div>
                <div class="meta-row"><span class="meta-label">Data</span><span class="meta-value" id="lbDate">—</span></div>
            </div>
            <div class="lightbox-actions">
                <a href="#" class="btn btn-primary btn-block" id="lbDownloadOpt" download>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Transferir
                </a>
                <?php if ($auth->can('download_original')): ?>
                <a href="#" class="btn btn-secondary btn-block" id="lbDownloadOrig" download>
                    Transferir original
                </a>
                <?php endif; ?>
                <?php if ($auth->can('delete_any') || $auth->can('delete_own')): ?>
                <button class="btn btn-danger btn-block" id="lbDelete">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                    </svg>
                    Eliminar
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Modal -->
<div class="modal-overlay" id="confirmModal" hidden>
    <div class="modal modal--confirm" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h3 class="modal-title" id="confirmTitle">Confirmar acção</h3>
        </div>
        <div class="modal-body">
            <p id="confirmMessage"></p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="confirmCancel">Cancelar</button>
            <button class="btn btn-danger" id="confirmOk">Confirmar</button>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- Toast container -->
<div class="toast-container" id="toastContainer" aria-live="polite"></div>

<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<?php if ($auth->check()): ?>
<script src="<?= e(url('assets/js/gallery.js')) ?>"></script>
<script src="<?= e(url('assets/js/upload.js')) ?>"></script>
<script src="<?= e(url('assets/js/lightbox.js')) ?>"></script>
<script>
// Page bootstrap data
window.APP = window.APP || {};
window.APP.canUpload        = <?= json_encode($auth->can('upload')) ?>;
window.APP.canDeleteAny     = <?= json_encode($auth->can('delete_any')) ?>;
window.APP.canDeleteOwn     = <?= json_encode($auth->can('delete_own')) ?>;
window.APP.canDownloadOrig  = <?= json_encode($auth->can('download_original')) ?>;
window.APP.canConvert       = <?= json_encode($auth->can('convert')) ?>;
window.APP.csrfToken        = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
window.APP.baseUrl          = '<?= e(rtrim(env('APP_URL', ''), '/')) ?>';
window.APP.maxUploadMb      = <?= (int) env('UPLOAD_MAX_SIZE_MB', 20) ?>;
window.APP.maxUploadFiles   = <?= (int) env('UPLOAD_MAX_FILES', 20) ?>;
</script>
<?php endif; ?>
</body>
</html>
