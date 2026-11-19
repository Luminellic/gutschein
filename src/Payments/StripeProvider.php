<?php

declare(strict_types=1);

namespace App\Payments;

use App\Core\Request;

/**
 * Stripe Checkout (gehostete Zahlungsseite) über die REST-API, ohne SDK.
 *
 * Zahlarten (TWINT, Karte, Apple Pay, Google Pay) werden im Stripe-Dashboard aktiviert.
 * Optional kann STRIPE_PAYMENT_METHODS (z.B. "card,twint") die Auswahl fest vorgeben.
 * TWINT funktioniert nur mit CHF. Die App rechnet ausschliesslich in CHF.
 *
 * Webhook-Endpunkt: {APP_URL}/webhook/stripe
 * Ereignisse: checkout.session.completed, checkout.session.async_payment_succeeded,
 *             checkout.session.async_payment_failed
 */
final class StripeProvider implements PaymentProvider
{
    private const API = 'https://api.stripe.com/v1/';
    private const SIGNATURE_TOLERANCE = 300;

    /** @var \Closure(string, string, array<string, mixed>, array<string, string>): array<string, mixed> */
    private \Closure $http;

    /**
     * @param list<string> $paymentMethods leer = dynamische Zahlarten gemäss Dashboard
     * @param (callable(string, string, array<string, mixed>, array<string, string>): array<string, mixed>)|null $http
     */
    public function __construct(
        private readonly string $secretKey,
        private readonly string $webhookSecret,
        private readonly array $paymentMethods = [],
        ?callable $http = null,
    ) {
        $this->http = $http !== null ? \Closure::fromCallable($http) : \Closure::fromCallable([$this, 'curl']);
    }

    public function name(): string
    {
        return 'stripe';
    }

    public function startCheckout(array $order, string $title): CheckoutStart
    {
        $publicId = (string) $order['public_id'];
        $params = [
            'mode' => 'payment',
            'locale' => 'de',
            'client_reference_id' => $publicId,
            'customer_email' => (string) $order['buyer_email'],
            'success_url' => absolute_url('/bestellung/' . $publicId) . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => absolute_url('/bestellung/' . $publicId) . '?abgebrochen=1',
            'metadata' => ['order' => $publicId],
            'payment_intent_data' => ['metadata' => ['order' => $publicId]],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'chf',
                    'unit_amount' => (int) $order['amount_cents'],
                    'product_data' => ['name' => 'Gutschein: ' . $title],
                ],
            ]],
        ];
        if ($this->paymentMethods !== []) {
            $params['payment_method_types'] = $this->paymentMethods;
        }

        $session = ($this->http)('POST', 'checkout/sessions', $params, ['Idempotency-Key' => 'checkout-' . $publicId]);
        if (empty($session['url']) || empty($session['id'])) {
            throw new PaymentException('Stripe hat keine Zahlungsseite geliefert.');
        }
        return new CheckoutStart((string) $session['url'], (string) $session['id']);
    }

    public function confirmPayment(array $order): PaymentResult
    {
        $sessionId = (string) ($order['provider_ref'] ?? '');
        if ($sessionId === '' || !str_starts_with($sessionId, 'cs_')) {
            return PaymentResult::unpaid();
        }
        $session = ($this->http)('GET', 'checkout/sessions/' . rawurlencode($sessionId), [], []);
        if (($session['client_reference_id'] ?? null) !== $order['public_id']) {
            return PaymentResult::unpaid();
        }
        return new PaymentResult(
            ($session['payment_status'] ?? '') === 'paid',
            isset($session['amount_total']) ? (int) $session['amount_total'] : null,
            isset($session['currency']) ? strtoupper((string) $session['currency']) : null,
        );
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $this->verifySignature($request->body, $request->header('Stripe-Signature'));

        $event = json_decode($request->body, true);
        if (!is_array($event) || !isset($event['id'], $event['type'])) {
            throw new PaymentException('Ungültiges Webhook-Ereignis.');
        }
        $type = (string) $event['type'];
        $relevant = [
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
            'checkout.session.async_payment_failed',
        ];
        if (!in_array($type, $relevant, true)) {
            return null;
        }
        $session = $event['data']['object'] ?? [];
        $paid = $type !== 'checkout.session.async_payment_failed' && ($session['payment_status'] ?? '') === 'paid';

        return new WebhookEvent(
            (string) $event['id'],
            $type,
            isset($session['client_reference_id']) ? (string) $session['client_reference_id'] : null,
            isset($session['id']) ? (string) $session['id'] : null,
            $paid,
            isset($session['amount_total']) ? (int) $session['amount_total'] : null,
            isset($session['currency']) ? strtoupper((string) $session['currency']) : null,
        );
    }

    /** Prüft den Header "t=…,v1=…" gemäss Stripe-Dokumentation (HMAC SHA-256 über "t.payload"). */
    public function verifySignature(string $payload, string $header, ?int $now = null): void
    {
        if ($this->webhookSecret === '') {
            throw new PaymentException('STRIPE_WEBHOOK_SECRET ist nicht gesetzt.');
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') {
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }
        if ($timestamp === null || $signatures === []) {
            throw new PaymentException('Webhook-Signatur fehlt.');
        }
        if (abs(($now ?? time()) - $timestamp) > self::SIGNATURE_TOLERANCE) {
            throw new PaymentException('Webhook-Zeitstempel ausserhalb der Toleranz.');
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $this->webhookSecret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return;
            }
        }
        throw new PaymentException('Webhook-Signatur ungültig.');
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function curl(string $method, string $path, array $params, array $headers): array
    {
        if ($this->secretKey === '') {
            throw new PaymentException('STRIPE_SECRET_KEY ist nicht gesetzt.');
        }
        $url = self::API . $path;
        $ch = curl_init();
        $httpHeaders = ['Authorization: Bearer ' . $this->secretKey, 'Stripe-Version: 2024-06-20'];
        foreach ($headers as $name => $value) {
            $httpHeaders[] = $name . ': ' . $value;
        }
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } elseif ($params !== []) {
            $url .= '?' . http_build_query($params);
        }
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $httpHeaders,
            CURLOPT_TIMEOUT => 20,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new PaymentException('Stripe ist nicht erreichbar: ' . $error);
        }
        $data = json_decode((string) $body, true);
        if ($status >= 400 || !is_array($data)) {
            $message = is_array($data) ? ($data['error']['message'] ?? 'Unbekannter Fehler') : 'Ungültige Antwort';
            throw new PaymentException('Stripe-Fehler (' . $status . '): ' . $message);
        }
        return $data;
    }
}
