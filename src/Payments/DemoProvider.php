<?php

declare(strict_types=1);

namespace App\Payments;

use App\Core\Request;

/**
 * Simulierte Zahlung für Vorführungen und lokale Entwicklung.
 * Leitet auf /zahlung/demo/{id} weiter, wo die Zahlung bestätigt oder abgebrochen werden kann.
 * Nie produktiv einsetzen: PAYMENT_PROVIDER=stripe setzen.
 */
final class DemoProvider implements PaymentProvider
{
    public function name(): string
    {
        return 'demo';
    }

    public function startCheckout(array $order, string $title): CheckoutStart
    {
        return new CheckoutStart(
            '/zahlung/demo/' . $order['public_id'],
            'demo_' . $order['public_id'],
        );
    }

    public function confirmPayment(array $order): PaymentResult
    {
        // Die Demo-Zahlungsseite markiert die Bestellung direkt als bezahlt.
        return PaymentResult::unpaid();
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        return null;
    }
}
