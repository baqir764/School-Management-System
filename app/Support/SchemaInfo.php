<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Request-scoped memo for Schema::hasTable()/hasColumn().
 *
 * Each raw Schema::hasTable()/hasColumn() call is a round-trip to
 * information_schema (SHOW FULL TABLES / DESCRIBE) - on MySQL that is a
 * real query, not a cheap lookup. The layout (topbar) and the dashboard
 * service used to fire a dozen of these on EVERY page load, several of
 * them for the exact same table. This helper answers the first call from
 * the database and every later call in the same request from memory, so
 * a page costs at most one information_schema query per table instead of
 * one per check.
 *
 * Deliberately NOT cached across requests (Cache::rememberForever): a
 * fresh `php artisan migrate` must be visible immediately, without anyone
 * having to flush the cache. Per-request memoization carries no such risk.
 */
final class SchemaInfo
{
    /** @var array<string, bool> */
    private static array $tables = [];

    /** @var array<string, array<string, bool>> */
    private static array $columns = [];

    public static function hasTable(string $table): bool
    {
        return self::$tables[$table] ??= Schema::hasTable($table);
    }

    public static function hasColumn(string $table, string $column): bool
    {
        if (! self::hasTable($table)) {
            return false;
        }

        return self::$columns[$table][$column] ??= Schema::hasColumn($table, $column);
    }
}
