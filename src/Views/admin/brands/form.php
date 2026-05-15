<?php
$isEdit    = !empty($brand);
$pageTitle = $isEdit ? 'Editar marca' : 'Nova marca';
require_once __DIR__ . '/../../layout/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <a href="<?= url('/admin/brands') ?>" class="back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="m15 18-6-6 6-6"/>
            </svg>
            Marcas
        </a>
        <h1 class="page-title"><?= $isEdit ? 'Editar marca' : 'Nova marca' ?></h1>
    </div>
</div>

<?php if (!empty($flash_error)): ?>
<div class="alert alert-error" role="alert"><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="card card--form">
    <form action="<?= e($action) ?>" method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

        <div class="form-grid form-grid--narrow">
            <div class="form-group">
                <label class="form-label" for="name">Nome da marca <span class="required">*</span></label>
                <input type="text" id="name" name="name" class="form-input"
                       value="<?= e($isEdit ? $brand['name'] : old('name')) ?>"
                       required maxlength="100" autocomplete="off"
                       placeholder="ex: Toyota">
            </div>

            <div class="form-group">
                <label class="form-label" for="slug">
                    Slug
                    <span class="form-hint">(gerado automaticamente)</span>
                </label>
                <input type="text" id="slug" class="form-input form-input--readonly"
                       value="<?= e($isEdit ? $brand['slug'] : '') ?>"
                       readonly aria-readonly="true">
                <p class="form-hint-text">
                    O slug é usado como nome da pasta de armazenamento: <code>/storage/images/<span id="slugPreview"><?= e($isEdit ? $brand['slug'] : '...') ?></span>/</code>
                </p>
            </div>
        </div>

        <div class="form-actions">
            <a href="<?= url('/admin/brands') ?>" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">
                <?= $isEdit ? 'Guardar alterações' : 'Criar marca' ?>
            </button>
        </div>
    </form>
</div>

<script>
// Auto-generate slug from name
function slugify(str) {
    const map = {
        'á':'a','à':'a','ã':'a','â':'a','ä':'a',
        'é':'e','è':'e','ê':'e','ë':'e',
        'í':'i','ì':'i','î':'i','ï':'i',
        'ó':'o','ò':'o','õ':'o','ô':'o','ö':'o',
        'ú':'u','ù':'u','û':'u','ü':'u',
        'ç':'c','ñ':'n',
    };
    return str.toLowerCase()
        .replace(/[áàãâäéèêëíìîïóòõôöúùûüçñ]/g, c => map[c] || c)
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/[\s_]+/g, '-')
        .replace(/-+/g, '-')
        .trim('-');
}

const nameInput    = document.getElementById('name');
const slugInput    = document.getElementById('slug');
const slugPreview  = document.getElementById('slugPreview');
const isEdit       = <?= json_encode($isEdit) ?>;

nameInput.addEventListener('input', function () {
    if (isEdit) return; // Don't auto-change slug on edit
    const s = slugify(this.value);
    slugInput.value      = s;
    slugPreview.textContent = s || '...';
});
</script>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>
