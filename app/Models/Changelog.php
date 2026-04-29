<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Changelog
{
    public const CATEGORIES = [
        'feature'      => ['Nuevo', '#7C3AED'],
        'improvement'  => ['Mejora', '#06B6D4'],
        'fix'          => ['Fix', '#10B981'],
        'security'     => ['Seguridad', '#F43F5E'],
        'breaking'     => ['Breaking', '#F59E0B'],
        'announcement' => ['Anuncio', '#A78BFA'],
    ];

    public static function listPublished(int $limit = 50): array
    {
        return Database::fetchAll(
            "SELECT * FROM changelog_entries
             WHERE is_published = 1 AND published_at IS NOT NULL AND published_at <= NOW()
             ORDER BY published_at DESC, id DESC
             LIMIT $limit"
        );
    }

    public static function latest(int $limit = 5): array
    {
        return self::listPublished($limit);
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::fetch(
            "SELECT * FROM changelog_entries WHERE slug = :s LIMIT 1",
            ['s' => $slug]
        );
    }

    public static function findById(int $id): ?array
    {
        return Database::fetch(
            "SELECT * FROM changelog_entries WHERE id = :id",
            ['id' => $id]
        );
    }

    public static function listAll(int $limit = 200): array
    {
        return Database::fetchAll(
            "SELECT * FROM changelog_entries ORDER BY COALESCE(published_at, created_at) DESC, id DESC LIMIT $limit"
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('changelog_entries', $data);
    }

    public static function update(int $id, array $data): int
    {
        return Database::update('changelog_entries', $data, ['id' => $id]);
    }

    public static function delete(int $id): int
    {
        return Database::delete('changelog_entries', ['id' => $id]);
    }

    public static function uniqueSlug(string $base, ?int $excludeId = null): string
    {
        $slug = slugify($base);
        $candidate = $slug;
        $i = 1;
        while (true) {
            $existing = Database::fetch(
                "SELECT id FROM changelog_entries WHERE slug = :s LIMIT 1",
                ['s' => $candidate]
            );
            if (!$existing) break;
            if ($excludeId !== null && (int) $existing['id'] === $excludeId) break;
            $candidate = $slug . '-' . (++$i);
        }
        return $candidate;
    }
}
