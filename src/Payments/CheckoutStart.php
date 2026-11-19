<?php

declare(strict_types=1);

namespace App\Payments;

final class CheckoutStart
{
    public function __construct(
        public readonly string $redirectUrl,
        public readonly string $reference,
    ) {
    }
}
