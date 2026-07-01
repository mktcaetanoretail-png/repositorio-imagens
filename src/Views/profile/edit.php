<?php
$pageTitle = 'Área pessoal';
require_once __DIR__ . '/../layout/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Área pessoal</h1>
        <span class="total-count"><?= e($user['email']) ?></span>
    </div>
    <div class="page-header-right">
        <a href="<?= url('/') ?>" class="btn btn-secondary">Voltar ao repositório</a>
    </div>
</div>

<?php if (!empty($flash_ok)): ?>
<div class="alert alert-success" role="alert"><?= e($flash_ok) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
<div class="alert alert-error" role="alert"><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="card card--form">
    <form action="<?= e(url('/perfil')) ?>" method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

        <div class="form-grid">
            <div class="form-group">
                <label class="form-label" for="photo">Foto de perfil</label>
                <?php if (!empty($user['photo_path'])): ?>
                <div style="display:flex; align-items:center; gap:.75rem; margin-bottom:.5rem;">
                    <img src="<?= e($user['photo_path']) ?>" alt=""
                         style="width:48px;height:48px;border-radius:50%;object-fit:cover;">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remove_photo" value="1" class="checkbox">
                        <span class="checkbox-custom"></span>
                        Remover foto actual
                    </label>
                </div>
                <?php endif; ?>
                <input type="file" id="photo" name="photo" class="form-input" accept="image/jpeg,image/png,image/gif,image/webp">
                <p class="form-hint-text">JPG, PNG, GIF ou WEBP. Máximo 4 MB.</p>
            </div>

            <div class="form-group">
                <label class="form-label" for="name">Nome <span class="required">*</span></label>
                <input type="text" id="name" name="name" class="form-input"
                       value="<?= e(old('name', $user['name'])) ?>"
                       required autocomplete="name" maxlength="100">
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-input"
                       value="<?= e(old('email', $user['email'])) ?>"
                       required autocomplete="email" maxlength="150">
            </div>

            <div class="form-group">
                <label class="form-label">Função</label>
                <div>
                    <span class="badge badge-role badge-<?= e($user['role']) ?>">
                        <?= e(match ($user['role']) {
                            'admin'  => 'Administrador',
                            'editor' => 'Editor',
                            default  => 'Visualizador',
                        }) ?>
                    </span>
                </div>
                <p class="form-hint-text">A função só pode ser alterada por um administrador.</p>
            </div>
        </div>

        <div class="user-menu-divider" style="margin: 1.5rem 0;"></div>

        <h3 style="font-size: 1rem; margin-bottom: 1rem;">Alterar palavra-passe</h3>
        <p class="form-hint-text" style="margin-top: -.5rem; margin-bottom: 1rem;">Deixa em branco se não quiseres alterar a palavra-passe.</p>

        <div class="form-grid">
            <div class="form-group">
                <label class="form-label" for="current_password">Palavra-passe actual</label>
                <input type="password" id="current_password" name="current_password" class="form-input"
                       autocomplete="current-password" placeholder="••••••••">
            </div>

            <div class="form-group">
                <label class="form-label" for="new_password">Nova palavra-passe</label>
                <input type="password" id="new_password" name="new_password" class="form-input"
                       autocomplete="new-password" minlength="8" placeholder="Mínimo 8 caracteres">
            </div>

            <div class="form-group">
                <label class="form-label" for="new_password_confirm">Confirmar nova palavra-passe</label>
                <input type="password" id="new_password_confirm" name="new_password_confirm" class="form-input"
                       autocomplete="new-password" minlength="8" placeholder="Repetir a nova palavra-passe">
            </div>
        </div>

        <div class="form-actions">
            <a href="<?= url('/') ?>" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar alterações</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
