<?php

declare(strict_types=1);

namespace App\Models;

class Brand extends Model
{
    protected string $table = 'brands';

    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql    = 'SELECT COUNT(*) AS cnt FROM "brands" WHERE "slug" = ?';
        $params = [$slug];
        if ($excludeId !== null) {
            $sql    .= ' AND "id" != ?';
            $params[] = $excludeId;
        }
        $row = $this->db()->query($sql, $params)->fetch();
        return (int) ($row['cnt'] ?? 0) > 0;
    }

    /**
     * Returns all brands with location_count in a single query (avoids N+1).
     */
    public function findAllWithLocationCounts(): array
    {
        $stmt = $this->db()->query(
            'SELECT b.*, COUNT(l.id) AS location_count
             FROM brands b
             LEFT JOIN locations l ON l.brand_id = b.id
             GROUP BY b.id
             ORDER BY b.name ASC'
        );
        return $stmt->fetchAll();
    }
}
