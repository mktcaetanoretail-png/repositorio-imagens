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
     * Returns up to $limit results with brand info for autocomplete.
     */
    public function search(string $query, int $limit = 10): array
    {
        $like = '%' . $query . '%';
        return $this->db()->query(
            'SELECT l.slug AS loc_slug, l.name AS loc_name,
                    b.slug AS brand_slug, b.name AS brand_name
             FROM "locations" l
             JOIN "brands" b ON b.id = l.brand_id
             WHERE l.name LIKE ? OR b.name LIKE ?
             ORDER BY b.name ASC, l.name ASC
             LIMIT ' . (int) $limit,
            [$like, $like]
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
