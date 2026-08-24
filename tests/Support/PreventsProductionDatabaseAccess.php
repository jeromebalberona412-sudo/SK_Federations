<?php

namespace Tests\Support;

trait PreventsProductionDatabaseAccess
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->guardAgainstProductionDatabase();
    }

    protected function guardAgainstProductionDatabase(): void
    {
        $connection = (string) config('database.default');
        $host = strtolower((string) config("database.connections.{$connection}.host", ''));
        $database = strtolower((string) config("database.connections.{$connection}.database", ''));

        $blockedHosts = [
            'aws-1-ap-southeast-1.pooler.supabase.com',
            'aws-0-ap-southeast-1.pooler.supabase.com',
        ];

        if (in_array($host, $blockedHosts, true) || str_contains($host, 'supabase.com')) {
            $this->fail(
                'Tests are blocked from running against the production Supabase database. '.
                'Install the PHP SQLite extension and run tests with DB_CONNECTION=sqlite only.'
            );
        }

        if ($connection === 'pgsql' && $database === 'postgres' && $host !== '' && $host !== '127.0.0.1' && $host !== 'localhost') {
            $this->fail(
                'Tests are blocked from running against a remote PostgreSQL database ('.$host.'). '.
                'Use sqlite :memory: for automated tests.'
            );
        }
    }
}
