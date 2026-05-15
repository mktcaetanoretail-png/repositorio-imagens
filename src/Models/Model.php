<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected bool $softDeletes  = false;

    protected function db(): Database
    {
        return Database::getInstance();
    }

    protected function isPgsql(): bool
    {
        return $this->db()->getDriver() === 'pgsql';
    }

    // Quote an identifier for the active driver
    protected function q(string $identifier): string
    {
        return '"' . $identifier . '"';
    }

    public function find(int $id): ?array
    {
        $t  = $this->q($this->table);
        $pk = $this->q($this->primaryKey);
        $sql = "SELECT * FROM {$t} WHERE {$pk} = ?";
        if ($this->softDeletes) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $row = $this->db()->query($sql, [$id])->fetch();
        return $row ?: null;
    }

    public function findAll(
        array $conditions = [],
        string $order     = '',
        ?int $limit       = null,
        int $offset       = 0
    ): array {
        [$where, $params] = $this->buildWhere($conditions);
        $t = $this->q($this->table);

        if ($this->softDeletes && !isset($conditions['deleted_at'])) {
            $where[] = "{$t}.deleted_at IS NULL";
        }

        $sql = "SELECT * FROM {$t}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        if (!empty($order)) {
            $sql .= " ORDER BY {$order}";
        }
        if ($limit !== null) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }

        return $this->db()->query($sql, $params)->fetchAll();
    }

    public function findBy(string $field, mixed $value): ?array
    {
        $t  = $this->q($this->table);
        $f  = $this->q($field);
        $sql = "SELECT * FROM {$t} WHERE {$f} = ?";
        if ($this->softDeletes) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' LIMIT 1';
        $row = $this->db()->query($sql, [$value])->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $cols = implode(', ', array_map(fn($k) => $this->q($k), array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $t = $this->q($this->table);

        if ($this->isPgsql()) {
            $sql = "INSERT INTO {$t} ({$cols}) VALUES ({$placeholders}) RETURNING id";
            $row = $this->db()->query($sql, array_values($data))->fetch();
            return (int) ($row['id'] ?? 0);
        }

        $sql = "INSERT INTO {$t} ({$cols}) VALUES ({$placeholders})";
        $this->db()->query($sql, array_values($data));
        return $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        if (empty($data)) {
            return false;
        }
        $sets = implode(', ', array_map(fn($k) => $this->q($k) . ' = ?', array_keys($data)));
        $pk   = $this->q($this->primaryKey);
        $t    = $this->q($this->table);
        $sql  = "UPDATE {$t} SET {$sets} WHERE {$pk} = ?";
        $stmt = $this->db()->query($sql, [...array_values($data), $id]);
        return $stmt->rowCount() >= 0;
    }

    public function softDelete(int $id): bool
    {
        if (!$this->softDeletes) {
            return $this->hardDelete($id);
        }
        $t  = $this->q($this->table);
        $pk = $this->q($this->primaryKey);
        $stmt = $this->db()->query(
            "UPDATE {$t} SET deleted_at = NOW() WHERE {$pk} = ?",
            [$id]
        );
        return $stmt->rowCount() > 0;
    }

    public function hardDelete(int $id): bool
    {
        $t  = $this->q($this->table);
        $pk = $this->q($this->primaryKey);
        $stmt = $this->db()->query("DELETE FROM {$t} WHERE {$pk} = ?", [$id]);
        return $stmt->rowCount() > 0;
    }

    public function restore(int $id): bool
    {
        if (!$this->softDeletes) {
            return false;
        }
        $t  = $this->q($this->table);
        $pk = $this->q($this->primaryKey);
        $stmt = $this->db()->query(
            "UPDATE {$t} SET deleted_at = NULL WHERE {$pk} = ?",
            [$id]
        );
        return $stmt->rowCount() > 0;
    }

    public function count(array $conditions = []): int
    {
        [$where, $params] = $this->buildWhere($conditions);
        $t = $this->q($this->table);

        if ($this->softDeletes && !isset($conditions['deleted_at'])) {
            $where[] = "{$t}.deleted_at IS NULL";
        }

        $sql = "SELECT COUNT(*) AS cnt FROM {$t}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $row = $this->db()->query($sql, $params)->fetch();
        return (int) ($row['cnt'] ?? 0);
    }

    private function buildWhere(array $conditions): array
    {
        $where  = [];
        $params = [];
        foreach ($conditions as $field => $value) {
            $f = $this->q($field);
            if ($value === null) {
                $where[] = "{$f} IS NULL";
            } else {
                $where[] = "{$f} = ?";
                $params[] = $value;
            }
        }
        return [$where, $params];
    }
}
