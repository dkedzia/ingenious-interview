<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\EmptyProductLinesException;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Ramsey\Uuid\UuidInterface;

final class Invoice
{
    /** @param ProductLine[] $productLines */
    private function __construct(
        private readonly UuidInterface $id,
        private readonly string $customerName,
        private readonly string $customerEmail,
        private StatusEnum $status,
        private readonly array $productLines,
    ) {}

    /** @param ProductLine[] $productLines */
    public static function create(
        UuidInterface $id,
        string $customerName,
        string $customerEmail,
        array $productLines = [],
    ): self {
        return new self($id, $customerName, $customerEmail, StatusEnum::Draft, $productLines);
    }

    /** @param ProductLine[] $productLines */
    public static function reconstitute(
        UuidInterface $id,
        string $customerName,
        string $customerEmail,
        StatusEnum $status,
        array $productLines,
    ): self {
        return new self($id, $customerName, $customerEmail, $status, $productLines);
    }

    public function markAsSending(): void
    {
        if ($this->status !== StatusEnum::Draft) {
            throw InvalidStatusTransitionException::cannotTransition($this->status, StatusEnum::Sending);
        }

        if (empty($this->productLines)) {
            throw EmptyProductLinesException::cannotSend();
        }

        $this->status = StatusEnum::Sending;
    }

    public function markAsSentToClient(): void
    {
        if ($this->status !== StatusEnum::Sending) {
            throw InvalidStatusTransitionException::cannotTransition($this->status, StatusEnum::SentToClient);
        }

        $this->status = StatusEnum::SentToClient;
    }

    public function totalPrice(): int
    {
        return array_reduce(
            $this->productLines,
            static fn (int $carry, ProductLine $line): int => $carry + $line->totalUnitPrice(),
            0,
        );
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function customerName(): string
    {
        return $this->customerName;
    }

    public function customerEmail(): string
    {
        return $this->customerEmail;
    }

    public function status(): StatusEnum
    {
        return $this->status;
    }

    /** @return ProductLine[] */
    public function productLines(): array
    {
        return $this->productLines;
    }
}
