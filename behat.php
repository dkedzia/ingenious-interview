<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Filter\TagFilter;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Tests\Behat\Contexts\DatabaseContext;
use Tests\Behat\Contexts\Invoices\ApiInvoiceContext;
use Tests\Behat\Contexts\Invoices\CustomerNotificationContext;
use Tests\Behat\Contexts\Invoices\InvoiceStorageContext;
use Tests\Behat\Contexts\Invoices\UseCaseInvoiceContext;
use Tests\Behat\Contexts\NotificationDeliveryContext;
use Tests\Behat\Contexts\Setup\InvoiceSetupContext;
use Tests\Behat\Contexts\Setup\NotificationSetupContext;
use Tests\Behat\LaravelServiceContainer;

$container = [
    'services' => LaravelServiceContainer::class.'::create',
    'autowire' => true,
];

/*
 * The invoice features run twice, once per layer: the use_case suite calls the
 * use cases directly, and the api suite goes through the HTTP endpoints. Both
 * run every scenario, except those tagged for the other layer only. Setup
 * (Given) and storage/notification checks are shared; only the layer context
 * differs.
 */
return (new Config)
    ->withProfile(
        (new Profile('default'))
            ->withSuite(
                (new Suite('notifications', $container))
                    ->withPaths('%paths.base%/tests/Behat/Features/Notifications')
                    // The Invoices module reacts to delivery events, so it needs its tables too.
                    ->withContexts(
                        DatabaseContext::class,
                        NotificationDeliveryContext::class,
                    ),
            )
            ->withSuite(
                (new Suite('use_case', $container))
                    ->withPaths('%paths.base%/tests/Behat/Features/Invoices')
                    ->withFilter(new TagFilter('~@api_only'))
                    ->withContexts(
                        DatabaseContext::class,
                        InvoiceSetupContext::class,
                        NotificationSetupContext::class,
                        UseCaseInvoiceContext::class,
                        InvoiceStorageContext::class,
                        CustomerNotificationContext::class,
                    ),
            )
            ->withSuite(
                (new Suite('api', $container))
                    ->withPaths('%paths.base%/tests/Behat/Features/Invoices')
                    ->withFilter(new TagFilter('~@use_case_only'))
                    ->withContexts(
                        DatabaseContext::class,
                        InvoiceSetupContext::class,
                        NotificationSetupContext::class,
                        ApiInvoiceContext::class,
                        InvoiceStorageContext::class,
                        CustomerNotificationContext::class,
                    ),
            ),
    );
