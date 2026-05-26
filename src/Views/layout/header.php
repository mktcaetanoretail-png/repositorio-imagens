<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($_SESSION['csrf_token'] ?? '') ?>">
    <title><?= e($pageTitle ?? 'Repositório de Imagens') ?> — <?= e(env('APP_NAME', 'Repositório de Imagens')) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>?v=<?= ASSET_VER ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Load Google Fonts async — prevents render-blocking -->
    <link rel="preload" as="style"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Nunito:wght@700;800&display=swap"
          onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Nunito:wght@700;800&display=swap">
    </noscript>
</head>
<body class="<?= isset($bodyClass) ? e($bodyClass) : '' ?>">

<?php if ($auth->check()): ?>
<nav class="navbar">
    <div class="navbar-brand">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
        <a href="<?= url('/') ?>" class="brand-logo">
            <img src="<?= url('assets/img/caetano-logo.svg') ?>" alt="Caetano" class="brand-logo-img">
        </a>
    </div>

    <div class="navbar-search">
        <div class="search-input-wrap">
            <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
            <input type="search" id="headerSearch" placeholder="Pesquisar marca ou localização..."
                   class="search-input" autocomplete="off" spellcheck="false">
            <div class="search-autocomplete" id="searchAutocomplete" hidden></div>
        </div>
    </div>

    <div class="navbar-actions">
        <div class="user-menu" id="userMenu">
            <button class="user-menu-trigger" id="userMenuTrigger">
                <div class="user-avatar"><?= e(mb_substr($auth->user()['name'] ?? 'U', 0, 1)) ?></div>
                <span class="user-name"><?= e($auth->user()['name'] ?? '') ?></span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="m6 9 6 6 6-6"/>
                </svg>
            </button>
            <div class="user-menu-dropdown" id="userMenuDropdown">
                <div class="user-menu-header">
                    <div class="user-menu-name"><?= e($auth->user()['name'] ?? '') ?></div>
                    <div class="user-menu-email"><?= e($auth->user()['email'] ?? '') ?></div>
                    <span class="badge badge-role badge-<?= e($auth->user()['role'] ?? 'viewer') ?>">
                        <?= e(match($auth->user()['role'] ?? '') {
                            'admin'  => 'Administrador',
                            'editor' => 'Editor',
                            default  => 'Visualizador',
                        }) ?>
                    </span>
                </div>
                <div class="user-menu-divider"></div>
                <?php if ($auth->can('manage_users') || $auth->can('manage_brands')): ?>
                <a href="<?= url('/admin/utilizadores') ?>" class="user-menu-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    Utilizadores
                </a>
                <a href="<?= url('/admin/marcas') ?>" class="user-menu-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>
                    </svg>
                    Marcas
                </a>
                <div class="user-menu-divider"></div>
                <?php endif; ?>
                <a href="<?= url('/logout') ?>" class="user-menu-item user-menu-logout">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Terminar sessão
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
(function () {
    const input = document.getElementById('headerSearch');
    const box   = document.getElementById('searchAutocomplete');
    if (!input || !box) return;

    let timer, active = -1;

    function esc(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function close() { box.hidden = true; active = -1; }

    function renderItems(items) {
        if (!items.length) {
            box.innerHTML = '<div class="search-autocomplete-empty">Nenhum resultado encontrado.</div>';
            box.hidden = false;
            return;
        }
        box.innerHTML = items.map((item, i) =>
            `<a href="/marcas/${esc(item.brand_slug)}/${esc(item.loc_slug)}"
                class="search-autocomplete-item" data-idx="${i}">
                <span class="search-autocomplete-brand">${esc(item.brand_name)}</span>
                <span class="search-autocomplete-sep">›</span>
                <span class="search-autocomplete-loc">${esc(item.loc_name)}</span>
             </a>`
        ).join('');
        box.hidden = false;
        active = -1;
    }

    async function fetchSuggestions(q) {
        try {
            const res  = await fetch('/pesquisa/sugestoes?q=' + encodeURIComponent(q),
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            renderItems(data);
        } catch (_) { close(); }
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        const q = this.value.trim();
        if (q.length < 4) { close(); return; }
        timer = setTimeout(() => fetchSuggestions(q), 260);
    });

    input.addEventListener('keydown', function (e) {
        const items = box.querySelectorAll('.search-autocomplete-item');
        if (e.key === 'Escape') { close(); return; }
        if (!items.length) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            active = Math.min(active + 1, items.length - 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            active = Math.max(active - 1, 0);
        } else if (e.key === 'Enter' && active >= 0) {
            e.preventDefault();
            items[active].click();
            return;
        }
        items.forEach((el, i) => el.classList.toggle('is-active', i === active));
    });

    document.addEventListener('click', function (e) {
        if (!input.contains(e.target) && !box.contains(e.target)) close();
    });
})();
</script>

<div class="app-layout">
    <aside class="sidebar" id="sidebar">
        <nav class="sidebar-nav">
            <a href="<?= url('/') ?>" class="sidebar-item <?= (($_SERVER['REQUEST_URI'] ?? '/') === '/' || preg_match('#^/marcas(/|$)#', $_SERVER['REQUEST_URI'] ?? '')) ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                </svg>
                <span>Repositório</span>
            </a>
            <?php if ($auth->can('convert')): ?>
            <a href="<?= url('/conversor') ?>" class="sidebar-item <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/conversor') === 0 ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2a10 10 0 0 1 7.38 16.74M12 22a10 10 0 0 1-7.38-16.74"/>
                    <polyline points="21 2 16 7 11 2"/><polyline points="3 22 8 17 13 22"/>
                </svg>
                <span>Conversor</span>
            </a>
            <?php endif; ?>
            <?php if ($auth->can('manage_users')): ?>
            <div class="sidebar-section-title">Administração</div>
            <a href="<?= url('/admin/utilizadores') ?>" class="sidebar-item <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/utilizadores') === 0 ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <span>Utilizadores</span>
            </a>
            <a href="<?= url('/admin/marcas') ?>" class="sidebar-item <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/marcas') === 0 ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>
                </svg>
                <span>Marcas</span>
            </a>
            <?php endif; ?>
        </nav>
    </aside>

    <main class="main-content" id="mainContent">
<?php else: ?>
<main class="main-content main-content--full">
<?php endif; ?>
