<?php
$pageTitle = 'Importar localizações';
require_once __DIR__ . '/../../layout/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Importar localizações em massa</h1>
        <span class="total-count">Uma localização por linha</span>
    </div>
    <div class="page-header-right">
        <a href="<?= url('/admin/marcas') ?>" class="btn btn-secondary">Voltar às marcas</a>
    </div>
</div>

<?php if (!empty($flash_error)): ?>
<div class="alert alert-error" role="alert"><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="card card--form">
    <form method="post" action="<?= e(url('/admin/localizacoes/importar')) ?>">
        <?= csrf_field() ?>

        <div class="form-group">
            <label class="form-label" for="data">
                Lista de marcas e localizações <span class="required">*</span>
            </label>
            <textarea id="data" name="data" class="form-input" rows="18"
                      style="font-family: monospace; white-space: pre;"
                      placeholder="Marca - Localização&#10;Caetano Audi - Aveiro&#10;Caetano BMW - Cascais"
                      required autofocus><?= e(old('data')) ?></textarea>
            <p class="form-hint-text">
                Uma localização por linha, no formato <code>Marca - Localização</code>.
                A marca tem de já existir no sistema (o prefixo "Caetano" é ignorado automaticamente).
                Na próxima página poderás confirmar antes de criar qualquer registo.
            </p>
        </div>

        <div class="form-actions">
            <a href="<?= url('/admin/marcas') ?>" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Pré-visualizar importação</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>
