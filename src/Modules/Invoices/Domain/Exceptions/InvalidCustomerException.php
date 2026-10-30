<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use InvalidArgumentException;

final class InvalidCustomerException extends InvalidArgumentException
{
    public static function emptyName(): self
    {
        return new self('Customer name must not be empty.');
    }

    public static function nameTooLong(): self
    {
        return new self('Customer name must not exceed 255 characters.');
    }

    public static function invalidEmail(string $email): self
    {
        return new self("Customer email is invalid: {$email}.");
    }

    public static function emailTooLong(): self
    {
        return new self('Customer email must not exceed 255 characters.');
    }
}
