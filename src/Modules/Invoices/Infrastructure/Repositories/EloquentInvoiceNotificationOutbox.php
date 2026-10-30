<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Repositories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Invoices\Application\Outbox\InvoiceNotificationOutboxInterface;
use Modules\Invoices\Application\Outbox\OutboxStatus;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Infrastructure\Eloquent\Invoice as InvoiceRecord;
use Modules\Invoices\Infrastructure\Eloquent\NotificationOutbox;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class EloquentInvoiceNotificationOutbox implements InvoiceNotificationOutboxInterface
{
    public function enqueuePending(UuidInterface $invoiceId): void
    {
        $existing = NotificationOutbox::query()->where('invoice_id', $invoiceId->toString())->first();

        if ($existing === null) {
            $record = new NotificationOutbox;
            $record->id = Uuid::uuid4()->toString();
            $record->invoice_id = $invoiceId->toString();
            $record->status = OutboxStatus::Pending->value;
            $record->attempts = 0;
            $record->claim_token = null;
            $record->claimed_at = null;
            $record->save();

            return;
        }

        $existing->status = OutboxStatus::Pending->value;
        $existing->attempts = 0;
        $existing->claim_token = null;
        $existing->claimed_at = null;
        $existing->save();
    }

    public function isNotified(UuidInterface $invoiceId): bool
    {
        return NotificationOutbox::query()
            ->where('invoice_id', $invoiceId->toString())
            ->where('status', OutboxStatus::Notified->value)
            ->exists();
    }

    public function claim(UuidInterface $invoiceId, DateTimeInterface $staleBefore): ?string
    {
        $token = Uuid::uuid4()->toString();

        $this->claimableQuery($staleBefore)
            ->where('invoice_id', $invoiceId->toString())
            ->update([
                'status' => OutboxStatus::Processing->value,
                'claimed_at' => now(),
                'claim_token' => $token,
                'attempts' => DB::raw('attempts + 1'),
            ]);

        if (! $this->ownsClaim($invoiceId, $token)) {
            return null;
        }

        return $token;
    }

    public function ownsClaim(UuidInterface $invoiceId, string $token): bool
    {
        return NotificationOutbox::query()
            ->where('invoice_id', $invoiceId->toString())
            ->where('status', OutboxStatus::Processing->value)
            ->where('claim_token', $token)
            ->exists();
    }

    public function markNotified(UuidInterface $invoiceId, string $token): bool
    {
        $this->ownedProcessingQuery($invoiceId, $token)->update([
            'status' => OutboxStatus::Notified->value,
            'updated_at' => now(),
        ]);

        return NotificationOutbox::query()
            ->where('invoice_id', $invoiceId->toString())
            ->where('status', OutboxStatus::Notified->value)
            ->where('claim_token', $token)
            ->exists();
    }

    public function markFailed(UuidInterface $invoiceId, string $token): bool
    {
        $this->ownedProcessingQuery($invoiceId, $token)->update([
            'status' => OutboxStatus::Failed->value,
            'updated_at' => now(),
        ]);

        return NotificationOutbox::query()
            ->where('invoice_id', $invoiceId->toString())
            ->where('status', OutboxStatus::Failed->value)
            ->where('claim_token', $token)
            ->exists();
    }

    public function revertSendingIfClaimHeld(UuidInterface $invoiceId, string $token): bool
    {
        return (bool) DB::transaction(function () use ($invoiceId, $token): bool {
            InvoiceRecord::query()
                ->where('id', $invoiceId->toString())
                ->where('status', StatusEnum::Sending->value)
                ->whereExists(function ($query) use ($invoiceId, $token): void {
                    $query->selectRaw('1')
                        ->from('invoice_notification_outbox')
                        ->where('invoice_id', $invoiceId->toString())
                        ->where('claim_token', $token)
                        ->where('status', OutboxStatus::Processing->value);
                })
                ->update([
                    'status' => StatusEnum::Draft->value,
                    'updated_at' => now(),
                ]);

            $invoice = InvoiceRecord::query()->find($invoiceId->toString());

            if ($invoice === null || $invoice->status !== StatusEnum::Draft->value) {
                return false;
            }

            return $this->markFailed($invoiceId, $token);
        });
    }

    /** @return UuidInterface[] */
    public function idsDueForDelivery(DateTimeInterface $staleBefore): array
    {
        return $this->dueQuery($staleBefore)
            ->orderBy('created_at')
            ->pluck('invoice_id')
            ->map(static fn (string $id): UuidInterface => Uuid::fromString($id))
            ->all();
    }

    private function claimableQuery(DateTimeInterface $staleBefore): Builder
    {
        return NotificationOutbox::query()->where(function (Builder $query) use ($staleBefore): void {
            $query->whereIn('status', [OutboxStatus::Pending->value, OutboxStatus::Failed->value])
                ->orWhere(function (Builder $processing) use ($staleBefore): void {
                    $this->staleProcessing($processing, $staleBefore);
                });
        });
    }

    private function dueQuery(DateTimeInterface $staleBefore): Builder
    {
        return NotificationOutbox::query()->where(function (Builder $query) use ($staleBefore): void {
            $query->where('status', OutboxStatus::Pending->value)
                ->orWhere(function (Builder $processing) use ($staleBefore): void {
                    $this->staleProcessing($processing, $staleBefore);
                });
        });
    }

    private function staleProcessing(Builder $query, DateTimeInterface $staleBefore): void
    {
        $stale = $staleBefore->format('Y-m-d H:i:s');

        $query->where('status', OutboxStatus::Processing->value)
            ->where(static function (Builder $claimed) use ($stale): void {
                $claimed->whereNull('claimed_at')
                    ->orWhere('claimed_at', '<', $stale);
            });
    }

    private function ownedProcessingQuery(UuidInterface $invoiceId, string $token): Builder
    {
        return NotificationOutbox::query()
            ->where('invoice_id', $invoiceId->toString())
            ->where('status', OutboxStatus::Processing->value)
            ->where('claim_token', $token);
    }
}
