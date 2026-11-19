<?php

declare(strict_types=1);

namespace App\Payments;

final class PaymentResult
{
    public function __construct(
        public readonly bool $paid,
        public readonly ?int $amountCents = null,
        public readonly ?string $currency = null,
    ) {
    }

    public static function unpaid(): self
    {
        return new self(false);
    }
}
