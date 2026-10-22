<?php

declare(strict_types=1);

namespace Tests\Behat\Support;

use RuntimeException;

/**
 * An expected failure, translated from whatever the layer under test reports
 * (a use case exception or an HTTP error response), so steps can check it the
 * same way in every suite. Anything unexpected is left to fail the step.
 */
final class LayerFailure extends RuntimeException
{
    /**
     * @param  list<array{string, ?int}>  $invalidFields  field label as written in the features
     *                                                    (e.g. "unit price") and zero-based product line index
     */
    public function __construct(
        public readonly FailureKind $kind,
        string $message,
        public readonly array $invalidFields = [],
    ) {
        parent::__construct($message);
    }
}
