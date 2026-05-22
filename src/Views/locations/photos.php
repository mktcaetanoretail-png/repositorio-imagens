<?php require_once __DIR__ . '/../layout/header.php'; ?>

<?php
$slotNames = [
    1 => 'Foto da Fachada',
    2 => 'Foto do Showroom',
    3 => 'Foto de Oficina — Exterior',
    4 => 'Foto de Oficina — Interior',
];
?>

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
                <h1 class="brand-header-name"><?= e($location['name']) ?></h1>
                <p class="brand-header-meta"><?= e(count($images)) ?> / <?= e($max_photos) ?> fotos</p>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($flash_ok)): ?>
<div class="alert alert-success" role="alert"><?= e($flash_ok) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
<div class="alert alert-error" role="alert"><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="brand-layout">
<?php $currentLocationId = $location['id']; require_once __DIR__ . '/../layout/_brand_sidebar.php'; ?>

<div class="brand-content">
<div class="photo-slots" id="photoSlots">
    <?php
    $uploadUrl = url('/brand/' . $brand['id'] . '/location/' . $location['id'] . '/upload');
    $canUpload = $auth->can('upload') && count($images) < $max_photos; // $images is slot map (assoc array)
    $canDelete = $auth->can('delete_any') || $auth->can('delete_own');
    ?>

    <?php for ($s = 1; $s <= $max_photos; $s++): ?>
    <?php if (isset($images[$s])): $img = $images[$s]; ?>
    <div class="photo-slot photo-slot--filled" data-image-id="<?= e($img['id']) ?>">
        <div class="photo-slot-inner">
            <div class="photo-slot-thumb">
                <img src="<?= e($img['thumb_url']) ?>"
                     alt="<?= e($img['original_filename']) ?>"
                     class="photo-slot-img" loading="lazy">
                <div class="photo-slot-overlay">
                    <button class="overlay-btn" title="Ver imagem"
                            data-lightbox="<?= e($img['optimized_url']) ?>"
                            data-caption="<?= e($slotNames[$s] ?? 'Slot ' . $s) ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>
                        </svg>
                    </button>
                    <a href="<?= e($img['download_url']) ?>" class="overlay-btn" title="Transferir">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        </svg>
                    </a>
                    <?php if ($canDelete): ?>
                    <button class="overlay-btn overlay-btn--danger" title="Eliminar"
                            data-delete-image="<?= e($img['id']) ?>"
                            data-filename="<?= e($img['original_filename']) ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                        </svg>
                    </button>
                    <?php endif; ?>
                </div>
                <span class="photo-slot-number"><?= $s ?></span>
            </div>
            <div class="photo-slot-meta">
                <span class="photo-slot-label"><?= e($slotNames[$s] ?? 'Slot ' . $s) ?></span>
                <span class="photo-slot-size"><?= e($img['filesize_human']) ?></span>
            </div>
        </div>
    </div>
    <?php elseif ($canUpload): ?>
    <div class="photo-slot photo-slot--empty" data-slot="<?= $s ?>" id="slot-<?= $s - 1 ?>">
        <input type="file" id="fileInput-<?= $s - 1 ?>" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
        <div class="photo-slot-uploading" id="uploading-<?= $s - 1 ?>">
            <div class="spinner" style="border-top-color: var(--accent)"></div>
            <span style="font-size:.8rem;color:var(--text-muted)">A carregar...</span>
        </div>
        <div class="photo-slot-upload-content" id="uploadContent-<?= $s - 1 ?>">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/>
                <line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            <p class="photo-slot-upload-text"><?= e($slotNames[$s] ?? 'Slot ' . $s) ?></p>
            <p class="photo-slot-upload-hint">Clique ou arraste</p>
            <p class="photo-slot-upload-hint" style="font-size:.72rem;margin-top:.15rem">Máx. <?= e(env('UPLOAD_MAX_SIZE_MB', 4)) ?> MB</p>
        </div>
    </div>
    <?php else: ?>
    <div class="photo-slot photo-slot--empty photo-slot--readonly">
        <div class="photo-slot-upload-content">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <rect x="3" y="3" width="18" height="18" rx="2"/>
                <circle cx="8.5" cy="8.5" r="1.5"/>
                <polyline points="21 15 16 10 5 21"/>
            </svg>
            <p class="photo-slot-upload-text"><?= e($slotNames[$s] ?? 'Slot ' . $s) ?></p>
            <p class="photo-slot-upload-hint">Vazio</p>
        </div>
    </div>
    <?php endif; ?>
    <?php endfor; ?>
</div>
</div><!-- /.brand-content -->
</div><!-- /.brand-layout -->

<div id="lightbox" class="lightbox" role="dialog" aria-modal="true">
    <button class="lightbox-close" aria-label="Fechar">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
    </button>
    <div class="lightbox-inner">
        <img id="lightboxImg" src="" alt="" class="lightbox-img">
        <p id="lightboxCaption" class="lightbox-caption"></p>
    </div>
</div>

<style>
.lightbox {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.85);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}
.lightbox--open { display: flex; }
.lightbox-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    max-width: 90vw;
    max-height: 90vh;
    gap: .75rem;
}
.lightbox-img {
    max-width: 100%;
    max-height: calc(90vh - 3rem);
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 8px 40px rgba(0,0,0,.5);
}
.lightbox-caption {
    color: rgba(255,255,255,.75);
    font-size: .85rem;
    text-align: center;
    margin: 0;
}
.lightbox-close {
    position: fixed;
    top: 1.25rem;
    right: 1.25rem;
    background: rgba(255,255,255,.15);
    border: none;
    border-radius: 50%;
    width: 2.5rem;
    height: 2.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #fff;
    transition: background .15s;
}
.lightbox-close:hover { background: rgba(255,255,255,.3); }
</style>

<script>
(function () {
    const uploadUrl        = '<?= e($uploadUrl) ?>';
    const useDirectUpload  = <?= json_encode($use_direct_upload) ?>;
    const uploadSignUrl    = '<?= e($upload_sign_url) ?>';
    const uploadConfirmUrl = '<?= e($upload_confirm_url) ?>';
    const csrfToken        = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    function showToast(msg, type) {
        const tc = document.getElementById('toastContainer');
        if (!tc) { alert(msg); return; }
        const t = document.createElement('div');
        t.className = 'toast toast--' + type;
        t.innerHTML = '<span class="toast-message">' + msg + '</span>';
        tc.appendChild(t);
        requestAnimationFrame(() => t.classList.add('toast--visible'));
        setTimeout(() => { t.classList.remove('toast--visible'); setTimeout(() => t.remove(), 300); }, 3500);
    }

    async function optimiseImage(file) {
        const MAX_PX   = 1920;
        const MAX_BYTES = uploadMaxMb * 1024 * 1024;
        const MIME     = 'image/jpeg';

        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Não foi possível ler a imagem.')); };
            img.onload  = () => {
                URL.revokeObjectURL(url);

                // Calculate new dimensions (never upscale)
                let w = img.naturalWidth;
                let h = img.naturalHeight;
                if (w > MAX_PX) { h = Math.round(h * MAX_PX / w); w = MAX_PX; }

                const canvas = document.createElement('canvas');
                canvas.width  = w;
                canvas.height = h;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, w, h);

                // Always compress at good quality first, then reduce if over limit
                const qualities = [0.85, 0.75, 0.62, 0.50, 0.40];
                let idx = 0;

                function tryQuality() {
                    const q = qualities[idx++];
                    canvas.toBlob(blob => {
                        if (!blob) { reject(new Error('Erro ao processar imagem.')); return; }
                        if (blob.size <= MAX_BYTES || idx >= qualities.length) {
                            const name = file.name.replace(/\.[^.]+$/, '.jpg');
                            resolve(new File([blob], name, { type: MIME }));
                        } else {
                            tryQuality();
                        }
                    }, MIME, q);
                }
                tryQuality();
            };
            img.src = url;
        });
    }

    function getImageDimensions(file) {
        return new Promise((resolve) => {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload  = () => { resolve({ width: img.naturalWidth, height: img.naturalHeight }); URL.revokeObjectURL(url); };
            img.onerror = () => resolve({ width: 0, height: 0 });
            img.src = url;
        });
    }

    async function uploadDirect(file, slotIndex, slotNumber) {
        // Step 1 — get signed URL from PHP
        const signFd = new FormData();
        signFd.append('mime',       file.type);
        signFd.append('slot',       slotNumber);
        signFd.append('csrf_token', csrfToken);

        const signRes  = await fetch(uploadSignUrl, { method: 'POST', body: signFd });
        const signData = await signRes.json();
        if (!signData.success) throw new Error(signData.error || 'Erro ao iniciar upload.');
        if (!signData.signed_url || !signData.signed_url.startsWith('http')) {
            throw new Error('URL de upload inválido: ' + JSON.stringify(signData));
        }

        // Step 2 — upload directly to Supabase (bypasses Vercel body limit)
        let putRes;
        try {
            putRes = await fetch(signData.signed_url, {
                method:  'PUT',
                headers: { 'Content-Type': file.type },
                body:    file,
            });
        } catch (corsErr) {
            throw new Error('CORS bloqueado ao enviar para o Supabase Storage. Verifica as definições de CORS no Supabase Dashboard → Storage → Policies. Detalhe: ' + corsErr.message);
        }
        if (!putRes.ok) {
            const errText = await putRes.text().catch(() => '');
            throw new Error('Supabase Storage recusou o ficheiro (' + putRes.status + '): ' + errText);
        }

        // Step 3 — get dimensions client-side
        const dims = await getImageDimensions(file);

        // Step 4 — confirm to PHP so it saves to DB
        const confirmFd = new FormData();
        confirmFd.append('public_url',        signData.public_url);
        confirmFd.append('filename',          signData.filename);
        confirmFd.append('original_filename', file.name);
        confirmFd.append('filesize',          file.size);
        confirmFd.append('width',             dims.width);
        confirmFd.append('height',            dims.height);
        confirmFd.append('mime',              file.type);
        confirmFd.append('slot',              slotNumber);
        confirmFd.append('csrf_token',        csrfToken);

        const confirmRes  = await fetch(uploadConfirmUrl, { method: 'POST', body: confirmFd });
        const confirmData = await confirmRes.json();
        if (!confirmData.success) throw new Error(confirmData.error || 'Erro ao registar imagem.');

        return confirmData;
    }

    const uploadMaxMb = <?= (int) env('UPLOAD_MAX_SIZE_MB', 4) ?>;

    async function uploadFile(file, slotIndex, slotNumber) {
        const uploading = document.getElementById('uploading-' + slotIndex);
        const content   = document.getElementById('uploadContent-' + slotIndex);
        if (uploading) { uploading.style.display = 'flex'; }
        if (content)   { content.style.display   = 'none'; }

        // Always optimise: resize to max 1920px and compress to JPEG at good quality
        if (file.type.startsWith('image/') && file.type !== 'image/gif') {
            try {
                const original = file;
                file = await optimiseImage(file);
                if (file.size > uploadMaxMb * 1024 * 1024) {
                    showToast('Não foi possível comprimir a imagem abaixo de ' + uploadMaxMb + ' MB.', 'error');
                    if (uploading) uploading.style.display = 'none';
                    if (content)   content.style.display   = '';
                    return;
                }
            } catch (e) {
                showToast(e.message || 'Erro ao optimizar imagem.', 'error');
                if (uploading) uploading.style.display = 'none';
                if (content)   content.style.display   = '';
                return;
            }
        }

        try {
            let data;
            if (useDirectUpload) {
                data = await uploadDirect(file, slotIndex, slotNumber);
            } else {
                const fd = new FormData();
                fd.append('image', file);
                fd.append('slot',  slotNumber);
                fd.append('csrf_token', csrfToken);
                const res = await fetch(uploadUrl, { method: 'POST', body: fd });
                if (res.status === 413) throw new Error('Ficheiro demasiado grande para o servidor. Máximo: ' + uploadMaxMb + ' MB.');
                data = await res.json();
            }

            if (data.success) {
                showToast('Foto carregada com sucesso.', 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(data.error || 'Erro no upload.', 'error');
                if (uploading) uploading.style.display = 'none';
                if (content)   content.style.display   = '';
            }
        } catch (e) {
            showToast(e.message || 'Erro de comunicação.', 'error');
            if (uploading) uploading.style.display = 'none';
            if (content)   content.style.display   = '';
        }
    }

    document.querySelectorAll('.photo-slot--empty:not(.photo-slot--readonly)').forEach((slot) => {
        const slotNum = parseInt(slot.dataset.slot, 10);
        const idx     = slotNum - 1;
        const input   = document.getElementById('fileInput-' + idx);

        slot.addEventListener('click', (e) => {
            if (!e.target.closest('input')) input?.click();
        });

        input?.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) uploadFile(file, idx, slotNum);
        });

        slot.addEventListener('dragover', (e) => { e.preventDefault(); slot.classList.add('drag-over'); });
        slot.addEventListener('dragleave', () => slot.classList.remove('drag-over'));
        slot.addEventListener('drop', (e) => {
            e.preventDefault();
            slot.classList.remove('drag-over');
            const file = e.dataTransfer.files[0];
            if (file) uploadFile(file, idx, slotNum);
        });
    });

    // Lightbox
    const lb        = document.getElementById('lightbox');
    const lbImg     = document.getElementById('lightboxImg');
    const lbCaption = document.getElementById('lightboxCaption');

    document.querySelectorAll('[data-lightbox]').forEach(btn => {
        btn.addEventListener('click', () => {
            lbImg.src             = btn.dataset.lightbox;
            lbCaption.textContent = btn.dataset.caption ?? '';
            lb.classList.add('lightbox--open');
            document.body.style.overflow = 'hidden';
        });
    });

    lb?.addEventListener('click', (e) => {
        if (e.target === lb || e.target.closest('.lightbox-close')) {
            lb.classList.remove('lightbox--open');
            document.body.style.overflow = '';
            lbImg.src = '';
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && lb?.classList.contains('lightbox--open')) {
            lb.classList.remove('lightbox--open');
            document.body.style.overflow = '';
            lbImg.src = '';
        }
    });

    document.querySelectorAll('[data-delete-image]').forEach(btn => {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            const id       = this.dataset.deleteImage;
            const filename = this.dataset.filename;
            if (!confirm('Eliminar "' + filename + '"? Esta acção pode ser revertida pela administração.')) return;

            try {
                const res  = await fetch('/image/' + id + '/delete', {
                    method : 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body   : 'csrf_token=' + encodeURIComponent(csrfToken),
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Foto eliminada.', 'success');
                    setTimeout(() => location.reload(), 600);
                } else {
                    showToast(data.error || 'Erro ao eliminar.', 'error');
                }
            } catch (e) {
                showToast('Erro de comunicação.', 'error');
            }
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
