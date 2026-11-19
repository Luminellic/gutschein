<?php

declare(strict_types=1);

namespace App\Payments;

final class WebhookEvent
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $type,
        public readonly ?string $orderPublicId,
        public readonly ?string $providerReference,
        public readonly bool $paid,
        public readonly ?int $amountCents = null,
        public readonly ?string $currency = null,
    ) {
    }
}
