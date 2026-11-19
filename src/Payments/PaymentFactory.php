<?php

declare(strict_types=1);

namespace App\Payments;

use App\Core\Env;

final class PaymentFactory
{
    public static function fromEnv(): PaymentProvider
    {
        return match (Env::get('PAYMENT_PROVIDER', 'demo')) {
            'stripe' => new StripeProvider(
                (string) Env::get('STRIPE_SECRET_KEY', ''),
                (string) Env::get('STRIPE_WEBHOOK_SECRET', ''),
                array_values(array_filter(array_map('trim', explode(',', (string) Env::get('STRIPE_PAYMENT_METHODS', ''))))),
            ),
            // 'payrexx' => new PayrexxProvider(...),  siehe docs/IMPLEMENTATION_PLAN.md, Phase 6
            default => new DemoProvider(),
        };
    }
}
