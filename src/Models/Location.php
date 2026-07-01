<?php
declare(strict_types=1);
namespace App\Models;

class Location extends Model
{
    protected string $table = 'locations';

    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    public function findByBrand(int $brandId): array
    {
        return $this->db()->query(
            'SELECT * FROM "locations" WHERE "brand_id" = ? ORDER BY "name" ASC',
            [$brandId]
        )->fetchAll();
    }

    /**
     * Full-text search across location and brand names.
     * Splits the query into words (e.g. "audi aveiro" or "audi - aveiro")
     * and requires every word to match the brand or the location name, so
     * a brand + location combination finds the right result regardless of
     * word order or separator.
     * Returns up to $limit results with brand info for autocomplete.
     */
    public function search(string $query, int $limit = 10): array
    {
        $tokens = preg_split('/[\s\-]+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($tokens)) {
            return [];
        }

        $where  = [];
        $params = [];
        foreach ($tokens as $token) {
            $escaped  = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $token);
            $like     = '%' . $escaped . '%';
            $where[]  = '(l.name ILIKE ? OR b.name ILIKE ?)';
            $params[] = $like;
            $params[] = $like;
        }

        return $this->db()->query(
            'SELECT l.slug AS loc_slug, l.name AS loc_name,
                    b.slug AS brand_slug, b.name AS brand_name
             FROM "locations" l
             JOIN "brands" b ON b.id = l.brand_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY b.name ASC, l.name ASC
             LIMIT ' . (int) $limit,
            $params
        )->fetchAll();
    }

    /**
     * Returns every location with its brand info and how many active
     * (non-deleted) photos it currently has. Used by the location audit
     * page to find locations with missing photos.
     */
    public function findAllWithPhotoCounts(): array
    {
        return $this->db()->query(
            'SELECT l.id, l.name, l.slug,
                    b.id AS brand_id, b.name AS brand_name, b.slug AS brand_slug,
                    COUNT(i.id) FILTER (WHERE i.deleted_at IS NULL) AS photo_count
             FROM "locations" l
             JOIN "brands" b ON b.id = l.brand_id
             LEFT JOIN "images" i ON i.location_id = l.id
             GROUP BY l.id, l.name, l.slug, b.id, b.name, b.slug
             ORDER BY b.name ASC, l.name ASC'
        )->fetchAll();
    }

    public function slugExistsForBrand(string $slug, int $brandId, ?int $excludeId = null): bool
    {
        $sql    = 'SELECT COUNT(*) AS cnt FROM "locations" WHERE "slug" = ? AND "brand_id" = ?';
        $params = [$slug, $brandId];
        if ($excludeId !== null) {
            $sql    .= ' AND "id" != ?';
            $params[] = $excludeId;
        }
        $row = $this->db()->query($sql, $params)->fetch();
        return (int) ($row['cnt'] ?? 0) > 0;
    }
}
