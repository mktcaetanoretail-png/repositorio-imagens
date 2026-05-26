<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Brand;
use App\Models\Image;
use App\Models\Location;

class BrandController extends Controller
{
    public function index(Request $request, array $params = []): void
    {
        $this->requirePermission('view_images');

        $brandModel = new Brand();
        $brands     = $brandModel->findAllWithLocationCounts();

        $logoBase = __DIR__ . '/../../public/assets/img/brands/';
        foreach ($brands as &$brand) {
            $brand['logo_url'] = null;
            foreach (['.png', '.svg'] as $ext) {
                if (file_exists($logoBase . $brand['slug'] . $ext)) {
                    $brand['logo_url'] = url('assets/img/brands/' . $brand['slug'] . $ext);
                    break;
                }
            }
        }
        unset($brand);

        $this->render('brands/index', [
            'brands'     => $brands,
            'pageTitle'  => 'Marcas',
            'bodyClass'  => 'page-home',
            'csrf_token' => $this->csrfToken(),
        ]);
    }

    public function locations(Request $request, array $params = []): void
    {
        $this->requirePermission('view_images');

        $brandId    = (int) ($params['id'] ?? 0);
        $brandModel = new Brand();
        $brand      = $brandModel->find($brandId);

        if (!$brand) {
            http_response_code(404);
            require __DIR__ . '/../Views/errors/404.php';
            exit;
        }

        $locationModel = new Location();
        $locations     = $locationModel->findByBrand($brandId);

        $imageModel    = new Image();
        $countMap      = $imageModel->countsByBrand($brandId);
        $previewMap    = $imageModel->previewsByBrand($brandId, 4);

        foreach ($locations as &$location) {
            $locId = (int) $location['id'];
            $location['image_count']    = $countMap[$locId] ?? 0;
            $location['preview_images'] = array_map(
                fn($img) => $this->enrichThumb($img, $brand['slug']),
                $previewMap[$locId] ?? []
            );
        }
        unset($location);

        $this->render('brands/locations', [
            'brand'      => $brand,
            'locations'  => $locations,
            'pageTitle'  => $brand['name'],
            'csrf_token' => $this->csrfToken(),
        ]);
    }

    private function enrichThumb(array $image, string $brandSlug): array
    {
        $path = $image['thumb_filepath'] ?? '';
        if (str_starts_with($path, 'http')) {
            $image['thumb_url'] = $path;
        } else {
            $base = rtrim(env('APP_URL', ''), '/') . '/storage/images';
            $image['thumb_url'] = $path !== ''
                ? $base . '/' . $brandSlug . '/' . basename($path)
                : '';
        }
        return $image;
    }
}
