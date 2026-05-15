/**
 * gallery.js — Gallery filter, pagination, bulk select, AJAX rendering
 */

'use strict';

(function () {
    // ─── State ────────────────────────────────────────────────────────────────

    const state = {
        brand_ids   : [],
        location_ids: [],
        search      : '',
        sort        : 'newest',
        page        : 1,
        show_deleted: false,
        loading     : false,
    };

    let allImages   = [];  // flat array of all rendered image IDs in order
    let currentIndex = -1;  // index in allImages for lightbox nav

    // ─── Elements ─────────────────────────────────────────────────────────────

    const grid          = document.getElementById('imageGrid');
    const totalCountEl  = document.getElementById('totalCount');
    const paginationEl  = document.getElementById('pagination');
    const bulkToolbar   = document.getElementById('bulkToolbar');
    const selCountEl    = document.getElementById('selectionCount');
    const brandFilters  = document.getElementById('brandFilters');
    const locationFilters = document.getElementById('locationFilters');
    const sortSelect    = document.getElementById('sortSelect');
    const showDeleted   = document.getElementById('showDeletedToggle');

    // ─── Init ─────────────────────────────────────────────────────────────────

    function init() {
        if (!grid) return;

        // Read initial state from rendered page
        state.search      = new URLSearchParams(window.location.search).get('search') || '';
        state.sort        = new URLSearchParams(window.location.search).get('sort') || 'newest';

        // Bind brand checkboxes
        brandFilters?.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            if (cb.checked) state.brand_ids.push(cb.value);
            cb.addEventListener('change', () => {
                state.brand_ids = Array.from(
                    brandFilters.querySelectorAll('input:checked')
                ).map(i => i.value);
                state.page = 1;
                fetchGallery();
            });
        });

        // Clear brand filter button
        document.getElementById('clearBrands')?.addEventListener('click', () => {
            brandFilters?.querySelectorAll('input').forEach(cb => cb.checked = false);
            state.brand_ids = [];
            state.page = 1;
            fetchGallery();
        });

        // Location pills
        document.getElementById('locationFilters')?.querySelectorAll('.filter-pill').forEach(pill => {
            pill.addEventListener('click', () => {
                pill.classList.toggle('active');
                state.location_ids = Array.from(
                    document.querySelectorAll('.filter-pill.active')
                ).map(p => p.dataset.locationId);
                state.page = 1;
                fetchGallery();
            });
        });

        document.getElementById('clearLocations')?.addEventListener('click', () => {
            document.querySelectorAll('.filter-pill.active').forEach(p => p.classList.remove('active'));
            state.location_ids = [];
            state.page = 1;
            fetchGallery();
        });

        // Sort
        sortSelect?.addEventListener('change', () => {
            state.sort = sortSelect.value;
            state.page = 1;
            fetchGallery();
        });

        // Show deleted toggle
        showDeleted?.addEventListener('change', () => {
            state.show_deleted = showDeleted.checked;
            state.page = 1;
            fetchGallery();
        });

        // Reset all
        document.getElementById('resetFilters')?.addEventListener('click', () => {
            brandFilters?.querySelectorAll('input').forEach(cb => cb.checked = false);
            document.querySelectorAll('.filter-pill.active').forEach(p => p.classList.remove('active'));
            if (sortSelect) sortSelect.value = 'newest';
            if (showDeleted) showDeleted.checked = false;
            state.brand_ids    = [];
            state.location_ids = [];
            state.sort         = 'newest';
            state.show_deleted = false;
            state.page         = 1;
            fetchGallery();
        });

        // Search (debounced)
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            let timer;
            searchInput.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => {
                    state.search = searchInput.value.trim();
                    state.page   = 1;
                    fetchGallery();
                }, 350);
            });
        }

        // View toggle
        document.getElementById('viewGrid')?.addEventListener('click', () => setView('grid'));
        document.getElementById('viewList')?.addEventListener('click', () => setView('list'));

        // Bulk actions
        document.getElementById('selectAllBtn')?.addEventListener('click', selectAll);
        document.getElementById('deselectAllBtn')?.addEventListener('click', deselectAll);
        document.getElementById('bulkDownloadOptBtn')?.addEventListener('click', () => bulkDownload('optimized'));
        document.getElementById('bulkDownloadOrigBtn')?.addEventListener('click', () => bulkDownload('original'));

        // Pagination (event delegation)
        paginationEl?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-page]');
            if (!btn) return;
            state.page = parseInt(btn.dataset.page, 10);
            fetchGallery();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Initial image list for lightbox nav
        rebuildImageList();
    }

    // ─── Fetch Gallery ────────────────────────────────────────────────────────

    async function fetchGallery() {
        if (state.loading) return;
        state.loading = true;

        showSkeletons();

        const params = new URLSearchParams();
        params.set('format', 'json');
        params.set('sort', state.sort);
        params.set('page', state.page);
        if (state.search)         params.set('search', state.search);
        if (state.show_deleted)   params.set('show_deleted', '1');
        state.brand_ids.forEach(id => params.append('brand_id[]', id));
        state.location_ids.forEach(id => params.append('location_id[]', id));

        try {
            const res  = await fetch('/?' + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!res.ok) throw new Error('HTTP ' + res.status);

            const data = await res.json();

            renderImages(data.images || []);
            renderPagination(data.pagination || {});

            if (totalCountEl) {
                totalCountEl.textContent = (data.total || 0) + ' imagens';
            }

            rebuildImageList();
        } catch (err) {
            grid.innerHTML = '<div class="fetch-error"><p>Erro ao carregar imagens. Tente novamente.</p></div>';
            console.error('Gallery fetch failed:', err);
        } finally {
            state.loading = false;
        }
    }

    function showSkeletons() {
        const skeletons = Array.from({ length: 8 }, () =>
            `<div class="image-card skeleton-card">
                <div class="skeleton skeleton--thumb"></div>
                <div class="image-card-info">
                    <div class="skeleton skeleton--line skeleton--line-sm"></div>
                    <div class="skeleton skeleton--line skeleton--line-xs"></div>
                </div>
            </div>`
        ).join('');
        grid.innerHTML = skeletons;
    }

    function renderImages(images) {
        if (!images.length) {
            grid.innerHTML = `
                <div class="empty-state" id="emptyState">
                    <svg class="empty-state-svg" viewBox="0 0 200 160" fill="none">
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
                    <p class="empty-state-text">Nenhuma imagem corresponde aos filtros activos.</p>
                </div>`;
            return;
        }

        const view = grid.dataset.view || 'grid';
        grid.dataset.view = view;

        grid.innerHTML = images.map(img => renderImageCard(img)).join('');

        // Bind lightbox triggers on newly rendered cards
        grid.querySelectorAll('[data-lightbox]').forEach(btn => {
            btn.addEventListener('click', () => {
                window.openLightbox && window.openLightbox(parseInt(btn.dataset.lightbox, 10));
            });
        });

        // Bind checkboxes
        grid.querySelectorAll('.image-select').forEach(cb => {
            cb.addEventListener('change', updateBulkToolbar);
        });
    }

    function renderImageCard(img) {
        const ratio     = parseFloat(img.optimization_ratio) || 0;
        const deletedBadge = img.deleted_at
            ? '<div class="image-deleted-badge">Eliminada</div>' : '';
        const savingBadge  = ratio > 0
            ? `<span class="saving-badge">-${ratio.toFixed(1)}%</span>` : '';

        return `
            <div class="image-card" data-id="${img.id}">
                <div class="image-card-thumb">
                    <img src="${escHtml(img.thumb_url)}"
                         alt="${escHtml(img.original_filename)}"
                         loading="lazy" class="image-thumb">
                    <div class="image-card-overlay">
                        <button class="overlay-btn" data-lightbox="${img.id}" title="Visualizar">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                        <a href="${escHtml(img.download_url)}" class="overlay-btn" title="Transferir" download>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            </svg>
                        </a>
                    </div>
                    <label class="image-select-label">
                        <input type="checkbox" class="image-select" value="${img.id}">
                        <span class="image-select-custom"></span>
                    </label>
                    ${deletedBadge}
                </div>
                <div class="image-card-info">
                    <p class="image-filename" title="${escHtml(img.original_filename)}">${escHtml(img.original_filename)}</p>
                    <div class="image-badges">
                        <span class="badge badge-brand">${escHtml(img.brand_name)}</span>
                        <span class="badge badge-location">${escHtml(img.location_name)}</span>
                    </div>
                    <p class="image-meta-line">
                        ${img.width}×${img.height}
                        · ${escHtml(img.optimized_filesize_human || img.filesize_human)}
                        ${savingBadge}
                    </p>
                </div>
            </div>`;
    }

    function renderPagination(pagination) {
        if (!paginationEl) return;

        if (!pagination.total_pages || pagination.total_pages <= 1) {
            paginationEl.innerHTML = '';
            return;
        }

        const current = pagination.current_page;
        const total   = pagination.total_pages;
        const range   = 2;

        let html = '';

        if (pagination.has_prev) {
            html += `<button class="pagination-btn" data-page="${pagination.prev_page}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m15 18-6-6 6-6"/></svg>
                Anterior</button>`;
        }

        html += '<div class="pagination-pages">';
        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total || Math.abs(i - current) <= range) {
                html += `<button class="pagination-page${i === current ? ' active' : ''}" data-page="${i}">${i}</button>`;
            } else if (Math.abs(i - current) === range + 1) {
                html += '<span class="pagination-ellipsis">…</span>';
            }
        }
        html += '</div>';

        if (pagination.has_next) {
            html += `<button class="pagination-btn" data-page="${pagination.next_page}">
                Seguinte
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>
            </button>`;
        }

        paginationEl.innerHTML = html;
    }

    // ─── Bulk Selection ───────────────────────────────────────────────────────

    function getCheckedIds() {
        return Array.from(grid.querySelectorAll('.image-select:checked')).map(cb => cb.value);
    }

    function updateBulkToolbar() {
        const checked = getCheckedIds();
        const show    = checked.length > 0;

        if (bulkToolbar) {
            bulkToolbar.style.display = show ? '' : 'none';
        }
        if (selCountEl) {
            selCountEl.textContent = checked.length + ' seleccionada' + (checked.length !== 1 ? 's' : '');
        }
    }

    function selectAll() {
        grid.querySelectorAll('.image-select').forEach(cb => cb.checked = true);
        updateBulkToolbar();
    }

    function deselectAll() {
        grid.querySelectorAll('.image-select').forEach(cb => cb.checked = false);
        updateBulkToolbar();
        if (bulkToolbar) bulkToolbar.style.display = 'none';
    }

    async function bulkDownload(version) {
        const ids = getCheckedIds();
        if (!ids.length) {
            window.toast?.warning('Nenhuma imagem seleccionada.');
            return;
        }

        const confirmed = await window.confirm2(
            `Transferir ${ids.length} imagem(ns) (versão: ${version === 'original' ? 'original' : 'optimizada'})?`,
            'Transferência em massa'
        );
        if (!confirmed) return;

        const form = document.getElementById('bulkDownloadForm');
        if (!form) return;

        document.getElementById('bulkVersion').value = version;

        const container = document.getElementById('bulkIdsContainer');
        container.innerHTML = '';
        ids.forEach(id => {
            const inp   = document.createElement('input');
            inp.type    = 'hidden';
            inp.name    = 'ids[]';
            inp.value   = id;
            container.appendChild(inp);
        });

        form.submit();
    }

    // ─── View Toggle ─────────────────────────────────────────────────────────

    function setView(view) {
        grid.dataset.view = view;
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === view);
        });
    }

    // ─── Image list for lightbox nav ─────────────────────────────────────────

    function rebuildImageList() {
        allImages = Array.from(
            grid.querySelectorAll('.image-card[data-id]')
        ).map(card => parseInt(card.dataset.id, 10));
    }

    window.getGalleryImages = () => allImages;

    // ─── Utility ─────────────────────────────────────────────────────────────

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ─── Boot ─────────────────────────────────────────────────────────────────

    init();
})();
