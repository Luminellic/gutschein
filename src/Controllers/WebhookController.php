<?php

declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Core\Request;
use App\Core\Response;
use App\Payments\PaymentException;
use App\Services\VoucherException;

final class WebhookController
{
    public function stripe(Request $request): void
    {
        $provider = App::payment();
        if ($provider->name() !== 'stripe') {
            Response::json(['error' => 'Stripe ist nicht aktiv.'], 404);
        }

        try {
            $event = $provider->parseWebhook($request);
        } catch (PaymentException $e) {
            error_log('Stripe-Webhook abgelehnt: ' . $e->getMessage());
            Response::json(['error' => 'Ungültige Signatur oder Daten.'], 400);
        }
        if ($event === null) {
            Response::json(['received' => true, 'ignored' => true]);
        }

        $db = App::db();
        if ($db->value('SELECT COUNT(*) FROM webhook_events WHERE provider = ? AND event_id = ?', ['stripe', $event->eventId]) > 0) {
            Response::json(['received' => true, 'duplicate' => true]);
        }

        $orders = App::orders();
        $order = ($event->orderPublicId ? $orders->findByPublicId($event->orderPublicId) : null)
            ?? ($event->providerReference ? $orders->findByReference('stripe', $event->providerReference) : null);

        if ($order === null) {
            error_log('Stripe-Webhook: Bestellung nicht gefunden für ' . $event->eventId);
        } elseif ($event->paid) {
            try {
                $orders->markPaid((int) $order['id'], $event->amountCents, $event->currency);
            } catch (VoucherException $e) {
                // Fachlicher Widerspruch (z.B. Betrag): nicht erneut zustellen lassen, aber protokollieren.
                error_log('Stripe-Webhook: ' . $e->getMessage() . ' (Bestellung ' . $order['public_id'] . ')');
            }
        } elseif ($event->type === 'checkout.session.async_payment_failed') {
            $orders->cancel((int) $order['id']);
        }

        // Erst nach erfolgreicher Verarbeitung merken; bei technischem Fehler (500) stellt Stripe erneut zu.
        try {
            $db->insert('webhook_events', [
                'provider' => 'stripe',
                'event_id' => $event->eventId,
                'type' => $event->type,
                'received_at' => now()->format('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException) {
            // Paralleler Duplikat-Eintrag: unkritisch, markPaid ist idempotent.
        }
        Response::json(['received' => true]);
    }
}
