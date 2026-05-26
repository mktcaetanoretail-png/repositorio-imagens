<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Location;

class SearchController extends Controller
{
    private const MIN_CHARS = 4;
    private const MAX_RESULTS = 10;

    /**
     * GET /pesquisa/sugestoes?q=...
     * Returns JSON array of brand+location suggestions for autocomplete.
     */
    public function suggest(Request $request, array $params = []): void
    {
        $this->requirePermission('view_images');

        $q = trim($request->get('q', ''));

        if (mb_strlen($q) < self::MIN_CHARS) {
            $this->json([]);
        }

        $locationModel = new Location();
        $results = $locationModel->search($q, self::MAX_RESULTS);

        $this->json($results);
    }
}
