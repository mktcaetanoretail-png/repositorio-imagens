    </main>
<?php if ($auth->check()): ?>
</div><!-- /.app-layout -->
<?php endif; ?>

<!-- Confirm Modal (universal) -->
<?php if ($auth->check()): ?>
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

<script src="<?= e(url('assets/js/app.js')) ?>?v=<?= filemtime(__DIR__ . '/../../../public/assets/js/app.js') ?>"></script>
<?php if ($auth->check()): ?>
<script>
window.APP = window.APP || {};
window.APP.canUpload       = <?= json_encode($auth->can('upload')) ?>;
window.APP.canDeleteAny    = <?= json_encode($auth->can('delete_any')) ?>;
window.APP.canDeleteOwn    = <?= json_encode($auth->can('delete_own')) ?>;
window.APP.canDownloadOrig = <?= json_encode($auth->can('download_original')) ?>;
window.APP.canConvert      = <?= json_encode($auth->can('convert')) ?>;
window.APP.csrfToken       = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
window.APP.baseUrl         = '<?= e(rtrim(env('APP_URL', ''), '/')) ?>';
window.APP.maxUploadMb     = <?= (int) env('UPLOAD_MAX_SIZE_MB', 20) ?>;
</script>
<?php endif; ?>
</body>
</html>
