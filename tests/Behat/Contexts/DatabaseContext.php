<?php

declare(strict_types=1);

namespace Tests\Behat\Contexts;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Illuminate\Contracts\Console\Kernel;

final readonly class DatabaseContext implements Context
{
    public function __construct(
        private Kernel $artisan,
    ) {}

    #[BeforeScenario]
    public function cleanDatabase(): void
    {
        $this->artisan->call('migrate:fresh', ['--force' => true]);
    }
}
