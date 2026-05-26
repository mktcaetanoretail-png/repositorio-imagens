<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Brand;
use App\Models\Image;
use App\Models\Location;

class GalleryController extends Controller
{
    private const PER_PAGE = 24;

    public function index(Request $request, array $params = []): void
    {
        $this->requirePermission('view_images');

        $filters = [
            'brand_id'    => $request->get('brand_id'),
            'location_id' => $request->get('location_id'),
            'search'      => trim($request->get('search', '')),
            'sort'        => $request->get('sort', 'newest'),
            'show_deleted'=> $this->auth->can('restore_images') && $request->get('show_deleted') === '1',
        ];

        // Clean empty filters
        foreach ($filters as $k => $v) {
            if ($v === '' || $v === null) {
                unset($filters[$k]);
            }
        }

        $page      = max(1, (int) $request->get('page', 1));
        $imageModel = new Image();
        $total     = $imageModel->countGallery($filters);
        $images    = $imageModel->searchGallery($filters, $page, self::PER_PAGE);
        $pagination = $this->paginate($total, self::PER_PAGE, $page);

        // Enrich images with public URLs
        $images = array_map([$this, 'enrichImage'], $images);

        // JSON response for AJAX
        if ($request->wantsJson()) {
            $this->json([
                'images'     => $images,
                'pagination' => $pagination,
                'total'      => $total,
            ]);
        }

        $brandModel    = new Brand();
        $locationModel = new Location();
        $brands        = $brandModel->findAll([], 'name ASC');
        $locations     = $locationModel->findAll([], 'name ASC');

        $this->render('gallery/index', [
            'images'        => $images,
            'brands'        => $brands,
            'locations'     => $locations,
            'pagination'    => $pagination,
            'filters'       => $filters,
            'total'         => $total,
            'csrf_token'    => $this->csrfToken(),
        ]);
    }

    public function show(Request $request, array $params = []): void
    {
        $this->requirePermission('view_images');

        $id    = (int) ($params['id'] ?? 0);
        $imageModel = new Image();
        $image = $imageModel->findWithRelations($id);

        if (!$image) {
            $this->json(['error' => 'Imagem não encontrada.'], 404);
        }

        $this->json($this->enrichImage($image));
    }

    public function delete(Request $request, array $params = []): void
    {
        $this->requireAuth();

        $id         = (int) ($params['id'] ?? 0);
        $imageModel = new Image();
        $image      = $imageModel->findWithRelations($id);

        if (!$image || $image['deleted_at'] !== null) {
            $this->json(['success' => false, 'error' => 'Imagem não encontrada.'], 404);
        }

        $user = $this->auth->user();

        // Check permissions: admin can delete any; editor can delete their own
        $canDelete = $this->auth->can('delete_any')
            || ($this->auth->can('delete_own') && (int) $image['uploaded_by'] === (int) $user['id']);

        if (!$canDelete) {
            $this->json(['success' => false, 'error' => 'Sem permissão para eliminar esta imagem.'], 403);
        }

        $imageModel->softDelete($id);

        $auditLog = new \App\Models\AuditLog();
        $auditLog->log($user['id'], 'image_delete', 'image', $id, [
            'filename' => $image['original_filename'],
        ]);

        $this->json(['success' => true]);
    }

    private function enrichImage(array $image): array
    {
        $storageBase = rtrim(env('APP_URL', ''), '/') . '/storage/images';
        $brandSlug   = $image['brand_slug'] ?? slugify($image['brand_name'] ?? 'unknown');

        $thumbPath     = $image['thumb_filepath'] ?? '';
        $optimizedPath = $image['filepath'] ?? '';
        $originalPath  = $image['original_filepath'] ?? '';

        $image['thumb_url']     = str_starts_with($thumbPath, 'http')
            ? $thumbPath
            : $storageBase . '/' . $brandSlug . '/' . basename($thumbPath);
        $image['optimized_url'] = str_starts_with($optimizedPath, 'http')
            ? $optimizedPath
            : $storageBase . '/' . $brandSlug . '/' . basename($optimizedPath);
        $image['original_url']  = str_starts_with($originalPath, 'http')
            ? $originalPath
            : $storageBase . '/' . $brandSlug . '/' . basename($originalPath);
        $image['download_url']  = '/download/' . $image['id'];

        // Human-readable sizes
        $image['filesize_human']          = formatBytes((int) ($image['filesize'] ?? 0));
        $image['original_filesize_human'] = formatBytes((int) ($image['original_filesize'] ?? 0));
        $image['optimized_filesize_human']= formatBytes((int) ($image['optimized_filesize'] ?? 0));

        return $image;
    }
}
