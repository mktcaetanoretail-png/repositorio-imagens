<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Image extends Model
{
    protected string $table  = 'images';
    protected bool $softDeletes = true;

    public function findWithRelations(int $id): ?array
    {
        $stmt = $this->db()->query(
            "SELECT i.*,
                    b.name AS brand_name, b.slug AS brand_slug,
                    l.name AS location_name, l.slug AS location_slug,
                    u.name AS uploader_name, u.email AS uploader_email
             FROM images i
             INNER JOIN brands    b ON b.id = i.brand_id
             INNER JOIN locations l ON l.id = i.location_id
             LEFT  JOIN users     u ON u.id = i.uploaded_by
             WHERE i.id = ?",
            [$id]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function searchGallery(array $filters = [], int $page = 1, int $perPage = 24): array
    {
        [$where, $params] = $this->buildGalleryWhere($filters);

        $order = match ($filters['sort'] ?? 'newest') {
            'oldest'   => 'i.created_at ASC',
            'name_asc' => 'i.original_filename ASC',
            'name_desc'=> 'i.original_filename DESC',
            'size_asc' => 'i.optimized_filesize ASC',
            'size_desc'=> 'i.optimized_filesize DESC',
            default    => 'i.created_at DESC',
        };

        $offset = ($page - 1) * $perPage;

        $sql = "SELECT i.*,
                       b.name AS brand_name, b.slug AS brand_slug,
                       l.name AS location_name, l.slug AS location_slug,
                       u.name AS uploader_name
                FROM images i
                INNER JOIN brands    b ON b.id = i.brand_id
                INNER JOIN locations l ON l.id = i.location_id
                LEFT  JOIN users     u ON u.id = i.uploaded_by";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= " ORDER BY {$order} LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->db()->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function countGallery(array $filters = []): int
    {
        [$where, $params] = $this->buildGalleryWhere($filters);

        $sql = "SELECT COUNT(*) as cnt
                FROM images i
                INNER JOIN brands    b ON b.id = i.brand_id
                INNER JOIN locations l ON l.id = i.location_id
                LEFT  JOIN users     u ON u.id = i.uploaded_by";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->db()->query($sql, $params);
        $row  = $stmt->fetch();
        return (int) ($row['cnt'] ?? 0);
    }

    private function buildGalleryWhere(array $filters): array
    {
        $where  = [];
        $params = [];

        // Soft deletes
        $showDeleted = !empty($filters['show_deleted']);
        if (!$showDeleted) {
            $where[] = "i.deleted_at IS NULL";
        }

        // Brand filter — can be array or single value
        if (!empty($filters['brand_id'])) {
            $brandIds = (array) $filters['brand_id'];
            $brandIds = array_filter(array_map('intval', $brandIds));
            if (!empty($brandIds)) {
                $placeholders = implode(',', array_fill(0, count($brandIds), '?'));
                $where[]  = "i.brand_id IN ({$placeholders})";
                $params   = array_merge($params, $brandIds);
            }
        }

        // Location filter
        if (!empty($filters['location_id'])) {
            $locIds = (array) $filters['location_id'];
            $locIds = array_filter(array_map('intval', $locIds));
            if (!empty($locIds)) {
                $placeholders = implode(',', array_fill(0, count($locIds), '?'));
                $where[]  = "i.location_id IN ({$placeholders})";
                $params   = array_merge($params, $locIds);
            }
        }

        // Full-text search on filename
        if (!empty($filters['search'])) {
            $where[]  = "(i.original_filename ILIKE ? OR b.name ILIKE ? OR l.name ILIKE ?)";
            $escaped  = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['search']);
            $term     = '%' . $escaped . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        return [$where, $params];
    }

    public function findDeleted(): array
    {
        $stmt = $this->db()->query(
            "SELECT i.*,
                    b.name AS brand_name,
                    b.slug AS brand_slug,
                    l.name AS location_name,
                    l.slug AS location_slug,
                    u.name AS uploader_name
             FROM images i
             INNER JOIN brands    b ON b.id = i.brand_id
             INNER JOIN locations l ON l.id = i.location_id
             LEFT  JOIN users     u ON u.id = i.uploaded_by
             WHERE i.deleted_at IS NOT NULL
             ORDER BY i.deleted_at DESC"
        );
        return $stmt->fetchAll();
    }

    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $ids  = array_filter(array_map('intval', $ids));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db()->query(
            "SELECT i.*,
                    b.name AS brand_name, b.slug AS brand_slug,
                    l.name AS location_name
             FROM images i
             INNER JOIN brands    b ON b.id = i.brand_id
             INNER JOIN locations l ON l.id = i.location_id
             LEFT  JOIN users     u ON u.id = i.uploaded_by
             WHERE i.id IN ({$placeholders}) AND i.deleted_at IS NULL",
            $ids
        );
        return $stmt->fetchAll();
    }

    public function countByLocation(int $brandId, int $locationId): int
    {
        $row = $this->db()->query(
            'SELECT COUNT(*) AS cnt FROM "images" WHERE "brand_id" = ? AND "location_id" = ? AND "deleted_at" IS NULL',
            [$brandId, $locationId]
        )->fetch();
        return (int) ($row['cnt'] ?? 0);
    }

    public function countTrashedByLocation(int $brandId, int $locationId): int
    {
        $row = $this->db()->query(
            'SELECT COUNT(*) AS cnt FROM "images" WHERE "brand_id" = ? AND "location_id" = ? AND "deleted_at" IS NOT NULL',
            [$brandId, $locationId]
        )->fetch();
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Finds an active (non-deleted) image occupying the given slot for a
     * location, if any. Used to prevent restoring into an already-taken slot.
     */
    public function findActiveBySlot(int $brandId, int $locationId, int $slot): ?array
    {
        $row = $this->db()->query(
            'SELECT * FROM "images"
             WHERE "brand_id" = ? AND "location_id" = ? AND "slot" = ? AND "deleted_at" IS NULL',
            [$brandId, $locationId, $slot]
        )->fetch();
        return $row ?: null;
    }

    /**
     * Deleted images older than the given number of days, oldest first.
     */
    public function findDeletedOlderThan(int $days): array
    {
        $stmt = $this->db()->query(
            "SELECT i.*, b.name AS brand_name, l.name AS location_name
             FROM images i
             INNER JOIN brands    b ON b.id = i.brand_id
             INNER JOIN locations l ON l.id = i.location_id
             WHERE i.deleted_at IS NOT NULL
               AND i.deleted_at < NOW() - (? || ' days')::interval
             ORDER BY i.deleted_at ASC",
            [$days]
        );
        return $stmt->fetchAll();
    }

    public function findByLocation(int $brandId, int $locationId): array
    {
        return $this->db()->query(
            'SELECT i.*, u.name AS uploader_name
             FROM "images" i
             LEFT JOIN "users" u ON u.id = i.uploaded_by
             WHERE i.brand_id = ? AND i.location_id = ? AND i.deleted_at IS NULL
             ORDER BY i.created_at ASC',
            [$brandId, $locationId]
        )->fetchAll();
    }

    public function countByBrand(int $brandId): int
    {
        $row = $this->db()->query(
            'SELECT COUNT(*) AS cnt FROM "images" WHERE "brand_id" = ? AND "deleted_at" IS NULL',
            [$brandId]
        )->fetch();
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Returns image counts keyed by location_id for all locations of a brand.
     * Single query — avoids N+1.
     *
     * @return array<int, int>  [location_id => count]
     */
    public function countsByBrand(int $brandId): array
    {
        $rows = $this->db()->query(
            'SELECT location_id, COUNT(*) AS cnt
             FROM "images"
             WHERE brand_id = ? AND deleted_at IS NULL
             GROUP BY location_id',
            [$brandId]
        )->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['location_id']] = (int) $row['cnt'];
        }
        return $map;
    }

    /**
     * Returns up to $limit preview images per location for a given brand.
     * Single query using window function — avoids N+1.
     *
     * @return array<int, list<array>>  [location_id => [image, ...]]
     */
    public function previewsByBrand(int $brandId, int $limit = 4): array
    {
        $rows = $this->db()->query(
            "SELECT * FROM (
                SELECT i.*, u.name AS uploader_name,
                       ROW_NUMBER() OVER (PARTITION BY i.location_id ORDER BY i.created_at ASC) AS rn
                FROM images i
                LEFT JOIN users u ON u.id = i.uploaded_by
                WHERE i.brand_id = ? AND i.deleted_at IS NULL
             ) ranked
             WHERE rn <= {$limit}
             ORDER BY location_id ASC, rn ASC",
            [$brandId]
        )->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $locId = (int) $row['location_id'];
            $map[$locId][] = $row;
        }
        return $map;
    }
}
