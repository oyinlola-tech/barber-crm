<?php

it('defaults database-backed infrastructure to PostgreSQL when no connection is configured', function (): void {
    $originalEnvironment = getenv('DB_CONNECTION');
    $originalServer = $_SERVER['DB_CONNECTION'] ?? null;
    $originalEnv = $_ENV['DB_CONNECTION'] ?? null;

    putenv('DB_CONNECTION');
    unset($_SERVER['DB_CONNECTION'], $_ENV['DB_CONNECTION']);

    try {
        $databaseConfiguration = require base_path('config/database.php');
        $queueConfiguration = require base_path('config/queue.php');

        expect($databaseConfiguration['default'])->toBe('pgsql')
            ->and($queueConfiguration['batching']['database'])->toBe('pgsql')
            ->and($queueConfiguration['failed']['database'])->toBe('pgsql');
    } finally {
        if ($originalEnvironment === false) {
            putenv('DB_CONNECTION');
        } else {
            putenv('DB_CONNECTION='.$originalEnvironment);
        }

        if ($originalServer === null) {
            unset($_SERVER['DB_CONNECTION']);
        } else {
            $_SERVER['DB_CONNECTION'] = $originalServer;
        }

        if ($originalEnv === null) {
            unset($_ENV['DB_CONNECTION']);
        } else {
            $_ENV['DB_CONNECTION'] = $originalEnv;
        }
    }
});
