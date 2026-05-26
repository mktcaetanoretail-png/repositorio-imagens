<?php

declare(strict_types=1);

namespace App\Models;

class User extends Model
{
    protected string $table = 'users';

    public function findByEmail(string $email): ?array
    {
        $row = $this->db()->query(
            'SELECT * FROM "users" WHERE "email" = ? LIMIT 1',
            [$email]
        )->fetch();
        return $row ?: null;
    }

    public function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public function incrementLoginAttempts(int $id): void
    {
        $this->db()->query(
            'UPDATE "users" SET "login_attempts" = "login_attempts" + 1 WHERE "id" = ?',
            [$id]
        );
    }

    public function resetLoginAttempts(int $id): void
    {
        $this->db()->query(
            'UPDATE "users" SET "login_attempts" = 0, "locked_until" = NULL WHERE "id" = ?',
            [$id]
        );
    }

    public function lockAccount(int $id, int $minutes = 15): void
    {
        $minutes = max(1, min(1440, $minutes));
        if ($this->isPgsql()) {
            $sql = sprintf(
                "UPDATE \"users\" SET \"locked_until\" = NOW() + INTERVAL '%d minutes' WHERE \"id\" = ?",
                $minutes
            );
            $this->db()->query($sql, [$id]);
        } else {
            $this->db()->query(
                'UPDATE "users" SET "locked_until" = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE "id" = ?',
                [$minutes, $id]
            );
        }
    }

    public function isLocked(array $user): bool
    {
        if (empty($user['locked_until'])) {
            return false;
        }
        return strtotime($user['locked_until']) > time();
    }

    public function setRememberToken(int $id, string $token): void
    {
        $this->db()->query(
            'UPDATE "users" SET "remember_token" = ? WHERE "id" = ?',
            [$token, $id]
        );
    }

    public function findByRememberToken(string $token): ?array
    {
        $row = $this->db()->query(
            'SELECT * FROM "users" WHERE "remember_token" = ? AND "active" = TRUE LIMIT 1',
            [$token]
        )->fetch();
        return $row ?: null;
    }

    public function clearRememberToken(int $id): void
    {
        $this->db()->query(
            'UPDATE "users" SET "remember_token" = NULL WHERE "id" = ?',
            [$id]
        );
    }

    public function toggle(int $id): void
    {
        if ($this->isPgsql()) {
            $this->db()->query(
                'UPDATE "users" SET "active" = CASE WHEN "active" = TRUE THEN FALSE ELSE TRUE END WHERE "id" = ?',
                [$id]
            );
        } else {
            $this->db()->query(
                'UPDATE "users" SET "active" = IF("active" = 1, 0, 1) WHERE "id" = ?',
                [$id]
            );
        }
    }
}
