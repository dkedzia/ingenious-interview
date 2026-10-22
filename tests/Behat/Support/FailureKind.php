<?php

declare(strict_types=1);

namespace Tests\Behat\Support;

enum FailureKind
{
    case InvalidRequest;
    case InvoiceNotFound;
    case InvoiceCannotBeSent;
    case CustomerNotNotified;
}
