<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($_SESSION['csrf_token'] ?? '') ?>">
    <title><?= e($pageTitle ?? 'Repositório de Imagens') ?> — <?= e(env('APP_NAME', 'Repositório de Imagens')) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
</head>
<body class="<?= isset($bodyClass) ? e($bodyClass) : '' ?>">

<?php if ($auth->check()): ?>
<nav class="navbar">
    <div class="navbar-brand">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
        <a href="<?= url('/') ?>" class="brand-logo">
            <svg width="28" height="28" viewBox="0 0 28 28" fill="none" aria-hidden="true">
                <rect width="28" height="28" rx="6" fill="#e94560"/>
                <path d="M5 20L10 13l4 5 3-4 6 6H5z" fill="white" opacity=".9"/>
                <circle cx="19" cy="9" r="3" fill="white" opacity=".9"/>
            </svg>
            <span><?= e(env('APP_NAME', 'Repositório de Imagens')) ?></span>
        </a>
    </div>

    <div class="navbar-search">
        <form action="<?= url('/') ?>" method="get" role="search">
            <div class="search-input-wrap">
                <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input type="search" name="search" placeholder="Pesquisar imagens..."
                       value="<?= e($_GET['search'] ?? '') ?>" class="search-input" autocomplete="off">
            </div>
        </form>
    </div>

    <div class="navbar-actions">
        <?php if ($auth->can('upload')): ?>
        <button class="btn btn-primary btn-sm" id="openUploadModal">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/>
                <line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            <span>Carregar</span>
        </button>
        <?php endif; ?>

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
                <a href="<?= url('/admin/users') ?>" class="user-menu-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    Utilizadores
                </a>
                <a href="<?= url('/admin/brands') ?>" class="user-menu-item">
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

<div class="app-layout">
    <aside class="sidebar" id="sidebar">
        <nav class="sidebar-nav">
            <a href="<?= url('/') ?>" class="sidebar-item <?= (($_SERVER['REQUEST_URI'] ?? '/') === '/' || strpos($_SERVER['REQUEST_URI'] ?? '', '/?') === 0) ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                </svg>
                <span>Galeria</span>
            </a>
            <?php if ($auth->can('convert')): ?>
            <a href="<?= url('/converter') ?>" class="sidebar-item <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/converter') === 0 ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2a10 10 0 0 1 7.38 16.74M12 22a10 10 0 0 1-7.38-16.74"/>
                    <polyline points="21 2 16 7 11 2"/><polyline points="3 22 8 17 13 22"/>
                </svg>
                <span>Conversor</span>
            </a>
            <?php endif; ?>
            <?php if ($auth->can('manage_users')): ?>
            <div class="sidebar-section-title">Administração</div>
            <a href="<?= url('/admin/users') ?>" class="sidebar-item <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/users') === 0 ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <span>Utilizadores</span>
            </a>
            <a href="<?= url('/admin/brands') ?>" class="sidebar-item <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/brands') === 0 ? 'active' : '' ?>">
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
