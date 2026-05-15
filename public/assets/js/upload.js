/**
 * upload.js — Drag-and-drop upload with per-file progress
 */

'use strict';

(function () {
    const dropzone   = document.getElementById('dropzone');
    const fileInput  = document.getElementById('fileInput');
    const fileList   = document.getElementById('uploadFileList');
    const startBtn   = document.getElementById('startUpload');
    const resultsBox = document.getElementById('uploadResults');
    const resultsList= document.getElementById('uploadResultsList');

    if (!dropzone) return;

    const MAX_SIZE_BYTES = (window.APP?.maxUploadMb || 20) * 1024 * 1024;
    const MAX_FILES      = window.APP?.maxUploadFiles || 20;
    const ALLOWED_TYPES  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    let pendingFiles = []; // { file, id }
    let nextId = 0;

    // ─── Drag & Drop ─────────────────────────────────────────────────────────

    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('dropzone--active');
    });

    dropzone.addEventListener('dragleave', () => {
        dropzone.classList.remove('dropzone--active');
    });

    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('dropzone--active');
        const files = Array.from(e.dataTransfer.files);
        addFiles(files);
    });

    dropzone.addEventListener('click', (e) => {
        if (e.target.closest('label') || e.target === fileInput) return;
        fileInput?.click();
    });

    fileInput?.addEventListener('change', () => {
        addFiles(Array.from(fileInput.files));
        fileInput.value = ''; // reset to allow re-selecting same file
    });

    // ─── File Management ─────────────────────────────────────────────────────

    function addFiles(files) {
        let added = 0;

        for (const file of files) {
            if (pendingFiles.length >= MAX_FILES) {
                window.toast?.warning(`Máximo de ${MAX_FILES} ficheiros por upload.`);
                break;
            }

            const err = validateFile(file);
            if (err) {
                window.toast?.error(`${file.name}: ${err}`);
                continue;
            }

            const id = ++nextId;
            pendingFiles.push({ file, id });
            renderFileItem(file, id);
            added++;
        }

        if (added > 0) {
            fileList.hidden = false;
            startBtn.disabled = !isReadyToUpload();
        }
    }

    function validateFile(file) {
        if (!ALLOWED_TYPES.includes(file.type)) {
            return 'Tipo não suportado. Use JPG, PNG, WEBP ou GIF.';
        }
        if (file.size > MAX_SIZE_BYTES) {
            return `Ficheiro demasiado grande (máx. ${window.APP?.maxUploadMb || 20} MB).`;
        }
        return null;
    }

    function renderFileItem(file, id) {
        const item = document.createElement('div');
        item.className = 'upload-file-item';
        item.dataset.id = id;

        const sizeStr = formatBytes(file.size);
        const preview = URL.createObjectURL(file);

        item.innerHTML = `
            <img class="upload-file-thumb" src="${preview}" alt="${escHtml(file.name)}">
            <div class="upload-file-details">
                <p class="upload-file-name">${escHtml(file.name)}</p>
                <p class="upload-file-size">${sizeStr}</p>
                <div class="progress-bar" hidden>
                    <div class="progress-bar-fill" style="width:0%"></div>
                </div>
                <p class="upload-file-status"></p>
            </div>
            <button class="upload-file-remove" data-remove="${id}" aria-label="Remover">×</button>
        `;

        item.querySelector('[data-remove]').addEventListener('click', () => removeFile(id));
        fileList.appendChild(item);
    }

    function removeFile(id) {
        pendingFiles = pendingFiles.filter(f => f.id !== id);
        const el = fileList.querySelector(`[data-id="${id}"]`);
        el?.remove();
        if (pendingFiles.length === 0) {
            fileList.hidden = true;
            startBtn.disabled = true;
        }
    }

    function isReadyToUpload() {
        const brandId    = document.getElementById('uploadBrand')?.value;
        const locationId = document.getElementById('uploadLocation')?.value;
        return pendingFiles.length > 0 && !!brandId && !!locationId;
    }

    // Re-check readiness when selects change
    document.getElementById('uploadBrand')?.addEventListener('change', () => {
        startBtn.disabled = !isReadyToUpload();
    });
    document.getElementById('uploadLocation')?.addEventListener('change', () => {
        startBtn.disabled = !isReadyToUpload();
    });

    // ─── Upload ───────────────────────────────────────────────────────────────

    startBtn?.addEventListener('click', async () => {
        const brandId    = document.getElementById('uploadBrand')?.value;
        const locationId = document.getElementById('uploadLocation')?.value;

        if (!brandId || !locationId) {
            window.toast?.error('Seleccione a marca e a localização antes de carregar.');
            return;
        }

        startBtn.disabled = true;
        resultsBox.hidden = true;
        resultsList.innerHTML = '';

        const queue = [...pendingFiles];
        pendingFiles = [];

        for (const { file, id } of queue) {
            await uploadFile(file, id, brandId, locationId);
        }

        fileList.hidden = true;
        resultsBox.hidden = false;
        startBtn.disabled = false;

        // Refresh gallery if available
        setTimeout(() => {
            window.toast?.success('Upload concluído. A actualizar galeria…');
            // Trigger gallery refresh
            if (typeof fetchGallery === 'function') {
                fetchGallery();
            } else {
                // Fallback: reload after short delay
                setTimeout(() => location.reload(), 1500);
            }
        }, 500);
    });

    function uploadFile(file, id, brandId, locationId) {
        return new Promise((resolve) => {
            const item      = fileList.querySelector(`[data-id="${id}"]`);
            const progressWrap = item?.querySelector('.progress-bar');
            const progressFill = item?.querySelector('.progress-bar-fill');
            const statusEl  = item?.querySelector('.upload-file-status');

            if (progressWrap) progressWrap.hidden = false;

            const fd = new FormData();
            fd.append('csrf_token', window.getCsrfToken());
            fd.append('brand_id', brandId);
            fd.append('location_id', locationId);
            fd.append('image', file);

            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const pct = Math.round((e.loaded / e.total) * 100);
                    if (progressFill) progressFill.style.width = pct + '%';
                }
            });

            xhr.addEventListener('load', () => {
                let data;
                try { data = JSON.parse(xhr.responseText); } catch { data = {}; }

                if (xhr.status === 200 && data.success) {
                    if (progressFill) progressFill.style.width = '100%';
                    if (progressFill) progressFill.classList.add('progress-bar-fill--success');
                    if (statusEl) statusEl.textContent = '✓ Carregado';

                    appendResult(file.name, data, true);
                } else {
                    if (progressFill) progressFill.classList.add('progress-bar-fill--error');
                    if (statusEl) statusEl.textContent = '✗ Erro: ' + (data.error || 'Falhou');
                    appendResult(file.name, data, false);
                }

                resolve();
            });

            xhr.addEventListener('error', () => {
                if (statusEl) statusEl.textContent = '✗ Erro de rede';
                appendResult(file.name, { error: 'Erro de rede' }, false);
                resolve();
            });

            xhr.open('POST', '/upload');
            xhr.send(fd);
        });
    }

    function appendResult(filename, data, success) {
        const row = document.createElement('div');
        row.className = 'upload-result-row ' + (success ? 'result--success' : 'result--error');

        if (success) {
            const ratio = data.ratio ? ` · -${parseFloat(data.ratio).toFixed(1)}%` : '';
            row.innerHTML = `
                <span class="result-icon">✓</span>
                <div class="result-info">
                    <strong>${escHtml(data.original_filename || filename)}</strong>
                    <span class="result-meta">
                        ${escHtml(data.original_size_human)} → ${escHtml(data.optimized_size_human)}${ratio}
                        · ${escHtml(data.width)}×${escHtml(data.height)}px
                    </span>
                </div>`;
        } else {
            row.innerHTML = `
                <span class="result-icon">✗</span>
                <div class="result-info">
                    <strong>${escHtml(filename)}</strong>
                    <span class="result-error">${escHtml(data.error || 'Erro desconhecido')}</span>
                </div>`;
        }

        resultsList.appendChild(row);
    }

    // ─── Utilities ────────────────────────────────────────────────────────────

    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(2) + ' MB';
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
})();
