<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Request;
use App\Payments\PaymentException;
use App\Payments\StripeProvider;

final class StripeProviderTest extends \PHPUnit\Framework\TestCase
{
    private const SECRET = 'whsec_test';

    private function signed(string $payload, ?int $time = null): string
    {
        $time ??= time();
        return 't=' . $time . ',v1=' . hash_hmac('sha256', $time . '.' . $payload, self::SECRET);
    }

    private function webhook(array $event, ?string $signature = null): Request
    {
        $body = (string) json_encode($event);
        return new Request('POST', '/webhook/stripe', [], [], ['stripe-signature' => $signature ?? $this->signed($body)], $body);
    }

    public function testStartCheckoutSendsChfAndReference(): void
    {
        $calls = [];
        $provider = new StripeProvider('sk_test', self::SECRET, ['card', 'twint'], function (string $method, string $path, array $params, array $headers) use (&$calls): array {
            $calls[] = compact('method', 'path', 'params', 'headers');
            return ['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_123'];
        });
        $start = $provider->startCheckout([
            'public_id' => str_repeat('a', 32), 'amount_cents' => 8000, 'buyer_email' => 'f@example.ch',
        ], 'Wertgutschein');

        self::assertSame('cs_test_123', $start->reference);
        self::assertStringStartsWith('https://checkout.stripe.com/', $start->redirectUrl);
        $params = $calls[0]['params'];
        self::assertSame('chf', $params['line_items'][0]['price_data']['currency']);
        self::assertSame(8000, $params['line_items'][0]['price_data']['unit_amount']);
        self::assertSame(['card', 'twint'], $params['payment_method_types']);
        self::assertSame(str_repeat('a', 32), $params['client_reference_id']);
        self::assertStringContainsString('{CHECKOUT_SESSION_ID}', $params['success_url']);
        self::assertSame('checkout-' . str_repeat('a', 32), $calls[0]['headers']['Idempotency-Key']);
    }

    public function testConfirmPaymentChecksReference(): void
    {
        $provider = new StripeProvider('sk_test', self::SECRET, [], fn (): array => [
            'client_reference_id' => 'other', 'payment_status' => 'paid', 'amount_total' => 8000, 'currency' => 'chf',
        ]);
        $result = $provider->confirmPayment(['public_id' => 'mine', 'provider_ref' => 'cs_test_1']);
        self::assertFalse($result->paid);

        $provider = new StripeProvider('sk_test', self::SECRET, [], fn (): array => [
            'client_reference_id' => 'mine', 'payment_status' => 'paid', 'amount_total' => 8000, 'currency' => 'chf',
        ]);
        $result = $provider->confirmPayment(['public_id' => 'mine', 'provider_ref' => 'cs_test_1']);
        self::assertTrue($result->paid);
        self::assertSame(8000, $result->amountCents);
        self::assertSame('CHF', $result->currency);
    }

    public function testValidWebhookIsParsed(): void
    {
        $provider = new StripeProvider('sk_test', self::SECRET);
        $event = $provider->parseWebhook($this->webhook([
            'id' => 'evt_1',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_1', 'client_reference_id' => 'abc', 'payment_status' => 'paid',
                'amount_total' => 5000, 'currency' => 'chf',
            ]],
        ]));
        self::assertNotNull($event);
        self::assertTrue($event->paid);
        self::assertSame('abc', $event->orderPublicId);
        self::assertSame(5000, $event->amountCents);
    }

    public function testUnpaidCompletedSessionIsNotPaid(): void
    {
        $provider = new StripeProvider('sk_test', self::SECRET);
        $event = $provider->parseWebhook($this->webhook([
            'id' => 'evt_2', 'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_2', 'client_reference_id' => 'abc', 'payment_status' => 'unpaid']],
        ]));
        self::assertFalse($event->paid);
    }

    public function testIrrelevantEventIsIgnored(): void
    {
        $provider = new StripeProvider('sk_test', self::SECRET);
        self::assertNull($provider->parseWebhook($this->webhook(['id' => 'evt_3', 'type' => 'customer.created', 'data' => ['object' => []]])));
    }

    public function testWrongSignatureIsRejected(): void
    {
        $provider = new StripeProvider('sk_test', self::SECRET);
        $this->expectException(PaymentException::class);
        $provider->parseWebhook($this->webhook(['id' => 'evt_4', 'type' => 'checkout.session.completed'], 't=' . time() . ',v1=deadbeef'));
    }

    public function testOldTimestampIsRejected(): void
    {
        $provider = new StripeProvider('sk_test', self::SECRET);
        $this->expectException(PaymentException::class);
        $provider->verifySignature('{}', $this->signed('{}', time() - 3600));
    }
}
