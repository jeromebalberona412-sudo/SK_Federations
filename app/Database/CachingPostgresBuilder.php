<?php

namespace App\Database;

use Closure;
use Illuminate\Database\Schema\PostgresBuilder;

/**
 * Reuses table and column lookups during one request.
 *
 * Each request starts empty, and schema changes clear the cache before and
 * after they run, so a later lookup in the same process sees the new schema.
 */
class CachingPostgresBuilder extends PostgresBuilder
{
    /** @var array<string, bool> */
    private static array $tables = [];

    /** @var array<string, list<array<string, mixed>>> */
    private static array $columns = [];

    public static function flush(): void
    {
        self::$tables = [];
        self::$columns = [];
    }

    public function hasTable($table)
    {
        $key = $this->cacheKey((string) $table);

        if (! array_key_exists($key, self::$tables)) {
            self::$tables[$key] = $this->resolveTableExists((string) $table);
        }

        return self::$tables[$key];
    }

    public function getColumns($table)
    {
        $key = $this->cacheKey((string) $table);

        if (! array_key_exists($key, self::$columns)) {
            self::$columns[$key] = $this->resolveColumns((string) $table);
        }

        return self::$columns[$key];
    }

    public function table($table, Closure $callback)
    {
        return $this->withFreshSchema(fn () => parent::table($table, $callback));
    }

    public function create($table, Closure $callback)
    {
        return $this->withFreshSchema(fn () => parent::create($table, $callback));
    }

    public function drop($table)
    {
        return $this->withFreshSchema(fn () => parent::drop($table));
    }

    public function dropIfExists($table)
    {
        return $this->withFreshSchema(fn () => parent::dropIfExists($table));
    }

    public function dropColumns($table, $columns)
    {
        return $this->withFreshSchema(fn () => parent::dropColumns($table, $columns));
    }

    public function dropAllTables()
    {
        $this->withFreshSchema(fn () => parent::dropAllTables());
    }

    public function dropAllViews()
    {
        $this->withFreshSchema(fn () => parent::dropAllViews());
    }

    public function dropAllTypes()
    {
        $this->withFreshSchema(fn () => parent::dropAllTypes());
    }

    public function rename($from, $to)
    {
        return $this->withFreshSchema(fn () => parent::rename($from, $to));
    }

    protected function resolveTableExists(string $table): bool
    {
        return parent::hasTable($table);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function resolveColumns(string $table): array
    {
        return parent::getColumns($table);
    }

    protected function cacheKey(string $suffix): string
    {
        return $this->connection->getName()
            .'|'.$this->connection->getDatabaseName()
            .'|'.strtolower($suffix);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function withFreshSchema(callable $callback): mixed
    {
        self::flush();

        try {
            return $callback();
        } finally {
            self::flush();
        }
    }
}
