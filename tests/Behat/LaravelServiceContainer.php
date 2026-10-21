<?php

declare(strict_types=1);

namespace Tests\Behat;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;

/**
 * Exposes a booted Laravel application to Behat as the suite's service container.
 *
 * Behat calls the factory once per scenario, so every scenario gets a fresh
 * application and container state never leaks between scenarios. Laravel's
 * container implements PSR-11, which is all Behat needs to inject services
 * into context constructors.
 */
final class LaravelServiceContainer
{
    /**
     * Mirrors the testing environment configured in phpunit.xml, plus an
     * in-memory SQLite database so scenarios never touch the development
     * database (see DatabaseContext, which migrates it before each scenario).
     */
    private const array ENVIRONMENT = [
        'APP_ENV' => 'testing',
        'APP_MAINTENANCE_DRIVER' => 'file',
        'BCRYPT_ROUNDS' => '4',
        'CACHE_STORE' => 'array',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => ':memory:',
        'DB_URL' => '',
        'MAIL_MAILER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
    ];

    public static function create(): Application
    {
        foreach (self::ENVIRONMENT as $name => $value) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        /** @var Application $app */
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        Facade::clearResolvedInstances();

        return $app;
    }
}
