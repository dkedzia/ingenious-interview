<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Tests\Behat\Contexts\NotificationDeliveryContext;
use Tests\Behat\LaravelServiceContainer;

return (new Config)
    ->withProfile(
        (new Profile('default'))
            ->withSuite(
                (new Suite('default', [
                    'services' => LaravelServiceContainer::class.'::create',
                    'autowire' => true,
                ]))
                    ->withPaths('%paths.base%/tests/Behat/Features')
                    ->withContexts(NotificationDeliveryContext::class),
            ),
    );
