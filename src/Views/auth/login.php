<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sessão — <?= e(env('APP_NAME', 'Repositório de Imagens')) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
</head>
<body class="login-page">

<div class="login-layout">
    <div class="login-brand">
        <svg width="48" height="48" viewBox="0 0 48 48" fill="none" aria-hidden="true">
            <rect width="48" height="48" rx="10" fill="#e94560"/>
            <path d="M8 34L17 21l7 9 5.5-7L38 34H8z" fill="white" opacity=".9"/>
            <circle cx="33" cy="15" r="5" fill="white" opacity=".9"/>
        </svg>
        <h1><?= e(env('APP_NAME', 'Repositório de Imagens')) ?></h1>
        <p>Caetano Automotive Portugal</p>
    </div>

    <div class="login-card">
        <h2 class="login-title">Iniciar sessão</h2>

        <?php if (!empty($error)): ?>
        <div class="alert alert-error" role="alert">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <?= e($error) ?>
        </div>
        <?php endif; ?>

        <form action="<?= url('/login') ?>" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-input"
                    value="<?= e(old('email')) ?>"
                    required
                    autocomplete="email"
                    autofocus
                    placeholder="utilizador@caetano.pt"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="password">
                    Palavra-passe
                </label>
                <div class="input-password-wrap">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-input"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                    >
                    <button type="button" class="toggle-password" aria-label="Mostrar/ocultar palavra-passe" id="togglePassword">
                        <svg class="eye-show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <svg class="eye-hide" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" hidden>
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                            <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                            <line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-group form-group--inline">
                <label class="checkbox-label">
                    <input type="checkbox" name="remember_me" value="1" class="checkbox">
                    <span class="checkbox-custom"></span>
                    Manter sessão activa
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">
                Entrar
            </button>
        </form>
    </div>

    <p class="login-footer">
        &copy; <?= date('Y') ?> Caetano Automotive Portugal
    </p>
</div>

<script>
document.getElementById('togglePassword')?.addEventListener('click', function () {
    const pwd  = document.getElementById('password');
    const show = this.querySelector('.eye-show');
    const hide = this.querySelector('.eye-hide');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        show.hidden = true;
        hide.hidden = false;
    } else {
        pwd.type = 'password';
        show.hidden = false;
        hide.hidden = true;
    }
});
</script>
</body>
</html>
