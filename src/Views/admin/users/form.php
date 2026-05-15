<?php
$isEdit    = !empty($user);
$pageTitle = $isEdit ? 'Editar utilizador' : 'Novo utilizador';
require_once __DIR__ . '/../../layout/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <a href="<?= url('/admin/users') ?>" class="back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="m15 18-6-6 6-6"/>
            </svg>
            Utilizadores
        </a>
        <h1 class="page-title"><?= $isEdit ? 'Editar utilizador' : 'Novo utilizador' ?></h1>
    </div>
</div>

<?php if (!empty($flash_error)): ?>
<div class="alert alert-error" role="alert"><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="card card--form">
    <form action="<?= e($action) ?>" method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

        <div class="form-grid">
            <div class="form-group">
                <label class="form-label" for="name">Nome <span class="required">*</span></label>
                <input type="text" id="name" name="name" class="form-input"
                       value="<?= e($isEdit ? $user['name'] : old('name')) ?>"
                       required autocomplete="name" maxlength="100">
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-input"
                       value="<?= e($isEdit ? $user['email'] : old('email')) ?>"
                       required autocomplete="email" maxlength="150">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">
                    Palavra-passe <?= $isEdit ? '<span class="form-hint">(deixar em branco para manter)</span>' : '<span class="required">*</span>' ?>
                </label>
                <div class="input-password-wrap">
                    <input type="password" id="password" name="password" class="form-input"
                           <?= $isEdit ? '' : 'required' ?>
                           autocomplete="new-password" minlength="8"
                           placeholder="<?= $isEdit ? '••••••••' : 'Mínimo 8 caracteres' ?>">
                    <button type="button" class="toggle-password" aria-label="Mostrar/ocultar" id="togglePassword">
                        <svg class="eye-show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                        <svg class="eye-hide" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" hidden>
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                            <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                            <line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="role">Função <span class="required">*</span></label>
                <select id="role" name="role" class="form-select" required>
                    <?php
                    $currentRole = $isEdit ? $user['role'] : old('role', 'viewer');
                    $roles = ['admin' => 'Administrador', 'editor' => 'Editor', 'viewer' => 'Visualizador'];
                    foreach ($roles as $value => $label):
                    ?>
                    <option value="<?= e($value) ?>" <?= $currentRole === $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint-block">
                    <p><strong>Administrador</strong> — Acesso total, gestão de utilizadores e marcas</p>
                    <p><strong>Editor</strong> — Carregar, converter, transferir originais</p>
                    <p><strong>Visualizador</strong> — Apenas visualizar e transferir imagens optimizadas</p>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="<?= url('/admin/users') ?>" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">
                <?= $isEdit ? 'Guardar alterações' : 'Criar utilizador' ?>
            </button>
        </div>
    </form>
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

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>
