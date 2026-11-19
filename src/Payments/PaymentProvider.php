<?php

declare(strict_types=1);

namespace App\Payments;

use App\Core\Request;

/**
 * Schnittstelle für Zahlungsanbieter. Neue Anbieter (z.B. Payrexx) implementieren dieses Interface
 * und werden in PaymentFactory registriert. Der Rest der App bleibt unverändert.
 */
interface PaymentProvider
{
    /** Kurzname, wird in orders.provider gespeichert (z.B. "stripe"). */
    public function name(): string;

    /**
     * Startet die Zahlung und liefert die URL, auf die Käufer:innen weitergeleitet werden.
     *
     * @param array<string, mixed> $order
     */
    public function startCheckout(array $order, string $title): CheckoutStart;

    /**
     * Fragt beim Anbieter nach, ob die Bestellung bezahlt ist (Rücksprung auf die Bestellseite).
     *
     * @param array<string, mixed> $order
     */
    public function confirmPayment(array $order): PaymentResult;

    /** Prüft Signatur und liest ein Webhook-Ereignis. Null, wenn das Ereignis uns nicht interessiert. */
    public function parseWebhook(Request $request): ?WebhookEvent;
}
