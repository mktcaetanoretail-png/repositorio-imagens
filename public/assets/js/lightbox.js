/**
 * lightbox.js — Full image preview with metadata panel, keyboard nav, delete
 */

'use strict';

(function () {
    const lightbox   = document.getElementById('lightbox');
    const backdrop   = document.getElementById('lightboxBackdrop');
    const img        = document.getElementById('lightboxImg');
    const loading    = document.getElementById('lightboxLoading');
    const closeBtn   = document.getElementById('lightboxClose');
    const prevBtn    = document.getElementById('lightboxPrev');
    const nextBtn    = document.getElementById('lightboxNext');
    const filenameEl = document.getElementById('lightboxFilename');
    const deleteBtn  = document.getElementById('lbDelete');
    const dlOptBtn   = document.getElementById('lbDownloadOpt');
    const dlOrigBtn  = document.getElementById('lbDownloadOrig');

    if (!lightbox) return;

    let currentId  = null;
    let imageIds   = [];

    // ─── Open ─────────────────────────────────────────────────────────────────

    window.openLightbox = function (id) {
        currentId = id;
        imageIds  = window.getGalleryImages ? window.getGalleryImages() : [];
        lightbox.hidden = false;
        document.body.classList.add('lightbox-open');
        loadImage(id);
    };

    function open(id) {
        window.openLightbox(id);
    }

    // ─── Close ────────────────────────────────────────────────────────────────

    function close() {
        lightbox.hidden = true;
        document.body.classList.remove('lightbox-open');
        img.src    = '';
        currentId  = null;
    }

    closeBtn?.addEventListener('click', close);
    backdrop?.addEventListener('click', close);

    // ─── Navigation ───────────────────────────────────────────────────────────

    prevBtn?.addEventListener('click', () => navigate(-1));
    nextBtn?.addEventListener('click', () => navigate(1));

    function navigate(direction) {
        if (!imageIds.length || currentId === null) return;
        const idx    = imageIds.indexOf(currentId);
        const newIdx = (idx + direction + imageIds.length) % imageIds.length;
        loadImage(imageIds[newIdx]);
    }

    document.addEventListener('keydown', (e) => {
        if (lightbox.hidden) return;
        if (e.key === 'ArrowLeft')  navigate(-1);
        if (e.key === 'ArrowRight') navigate(1);
    });

    // ─── Load Image Data ──────────────────────────────────────────────────────

    async function loadImage(id) {
        currentId = id;
        loading.hidden  = false;
        img.style.opacity = '0';

        // Update nav visibility
        const idx = imageIds.indexOf(id);
        prevBtn.style.display = imageIds.length > 1 ? '' : 'none';
        nextBtn.style.display = imageIds.length > 1 ? '' : 'none';

        try {
            const res  = await fetch(`/image/${id}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!res.ok) throw new Error('HTTP ' + res.status);

            const data = await res.json();

            // Update image
            const newImg = new Image();
            newImg.onload = () => {
                img.src = newImg.src;
                img.alt = data.original_filename || '';
                img.style.opacity = '1';
                loading.hidden = true;
            };
            newImg.onerror = () => {
                loading.hidden = true;
                img.style.opacity = '1';
            };
            newImg.src = data.optimized_url;

            // Update metadata
            setText('lightboxFilename', data.original_filename);
            setText('lbBrand',      data.brand_name);
            setText('lbLocation',   data.location_name);
            setText('lbDimensions', `${data.width} × ${data.height} px`);
            setText('lbOrigSize',   data.original_filesize_human);
            setText('lbOptSize',    data.optimized_filesize_human);
            setText('lbRatio',      data.optimization_ratio ? `-${parseFloat(data.optimization_ratio).toFixed(1)}%` : '—');
            setText('lbUploader',   data.uploader_name);
            setText('lbDate',       formatDate(data.created_at));

            // Download links
            if (dlOptBtn) {
                dlOptBtn.href     = data.download_url;
                dlOptBtn.download = data.original_filename || data.filename;
            }
            if (dlOrigBtn) {
                dlOrigBtn.href     = data.download_url + '?version=original';
                dlOrigBtn.download = 'original_' + (data.original_filename || data.filename);
            }

            // Delete button
            if (deleteBtn) {
                deleteBtn.dataset.imageId      = data.id;
                deleteBtn.dataset.uploadedBy   = data.uploaded_by;
            }

        } catch (err) {
            loading.hidden = true;
            window.toast?.error('Erro ao carregar dados da imagem.');
            console.error('Lightbox load failed:', err);
        }
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    deleteBtn?.addEventListener('click', async () => {
        const id = currentId;
        if (!id) return;

        const confirmed = await window.confirm2(
            'Eliminar esta imagem? A acção pode ser revertida por um administrador.',
            'Eliminar imagem'
        );
        if (!confirmed) return;

        try {
            const res = await fetch(`/image/${id}/delete`, {
                method : 'POST',
                headers: {
                    'Content-Type'    : 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: `csrf_token=${encodeURIComponent(window.getCsrfToken())}`,
            });

            let data;
            try { data = await res.json(); } catch { data = {}; }

            if (res.ok && data.success) {
                // Remove from imageIds and advance
                const idx = imageIds.indexOf(id);
                imageIds.splice(idx, 1);

                // Remove card from gallery
                const card = document.querySelector(`.image-card[data-id="${id}"]`);
                card?.remove();

                window.toast?.success('Imagem eliminada.');

                if (imageIds.length === 0) {
                    close();
                } else {
                    const nextIdx = Math.min(idx, imageIds.length - 1);
                    loadImage(imageIds[nextIdx]);
                }
            } else {
                window.toast?.error('Erro: ' + (data.error || 'Não foi possível eliminar.'));
            }
        } catch {
            window.toast?.error('Erro de comunicação.');
        }
    });

    // ─── Event delegation from gallery grid ──────────────────────────────────

    document.getElementById('imageGrid')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-lightbox]');
        if (!btn) return;
        e.preventDefault();
        open(parseInt(btn.dataset.lightbox, 10));
    });

    // ─── Utilities ───────────────────────────────────────────────────────────

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value || '—';
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        try {
            return new Date(dateStr).toLocaleDateString('pt-PT', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit',
            });
        } catch {
            return dateStr;
        }
    }
})();
