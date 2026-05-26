<?php

declare(strict_types=1);

namespace App\Models;

class AuditLog extends Model
{
    protected string $table = 'audit_log';

    public function log(
        ?int $userId,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $detail = null
    ): int {
        // Skip audit log when auth is disabled (no real user in DB)
        if (!$userId) {
            return 0;
        }

        return $this->create([
            'user_id'     => $userId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'detail'      => $detail !== null ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    public function findRecent(int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));
        $stmt  = $this->db()->query(
            "SELECT al.*, u.name AS user_name, u.email AS user_email
             FROM audit_log al
             LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC
             LIMIT {$limit}"
        );
        return $stmt->fetchAll();
    }
}
