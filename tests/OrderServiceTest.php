<?php

declare(strict_types=1);

namespace Tests;

use App\App;
use App\Services\VoucherException;

final class OrderServiceTest extends TestCase
{
    /** @return array<string, string> */
    private function input(array $overrides = []): array
    {
        return $overrides + [
            'amount' => '80',
            'buyer_name' => 'Felix Muster',
            'buyer_email' => 'Felix@Example.ch',
            'recipient_name' => 'Anna',
            'message' => 'Alles Gute',
            'deliver_to' => 'buyer',
            'terms' => '1',
        ];
    }

    public function testValidation(): void
    {
        [$order, $errors] = App::orders()->create($this->product(), [
            'amount' => '5', 'buyer_name' => '', 'buyer_email' => 'kaputt', 'deliver_to' => 'recipient',
        ], 'demo');
        self::assertNull($order);
        self::assertArrayHasKey('amount', $errors);
        self::assertArrayHasKey('buyer_name', $errors);
        self::assertArrayHasKey('buyer_email', $errors);
        self::assertArrayHasKey('recipient_email', $errors);
        self::assertArrayHasKey('terms', $errors);
    }

    public function testFixedAmountIgnoresPostedAmount(): void
    {
        $product = $this->product(['amount_cents' => 5000, 'min_cents' => null, 'max_cents' => null]);
        [$order] = App::orders()->create($product, $this->input(['amount' => '1']), 'demo');
        self::assertSame(5000, (int) $order['amount_cents']);
        self::assertSame('felix@example.ch', $order['buyer_email']);
    }

    public function testMarkPaidIsIdempotent(): void
    {
        $orders = App::orders();
        [$order] = $orders->create($this->product(), $this->input(), 'demo');

        $first = $orders->markPaid((int) $order['id'], 8000, 'CHF');
        $second = $orders->markPaid((int) $order['id'], 8000, 'CHF');

        self::assertSame($first['id'], $second['id']);
        self::assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM vouchers'));
        self::assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM mail_log'));
        self::assertSame('paid', $this->db->value('SELECT status FROM orders WHERE id = ?', [$order['id']]));
        self::assertSame(8000, (int) $first['balance_cents']);
        self::assertSame('Anna', $first['recipient_name']);
    }

    public function testAmountMismatchIsRejected(): void
    {
        $orders = App::orders();
        [$order] = $orders->create($this->product(), $this->input(), 'stripe');
        try {
            $orders->markPaid((int) $order['id'], 100, 'CHF');
            self::fail('Falscher Betrag akzeptiert.');
        } catch (VoucherException) {
        }
        self::assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM vouchers'));
        self::assertSame('pending', $this->db->value('SELECT status FROM orders WHERE id = ?', [$order['id']]));
    }

    public function testDirectDeliverySendsVoucherAndConfirmation(): void
    {
        $orders = App::orders();
        [$order] = $orders->create($this->product(), $this->input([
            'deliver_to' => 'recipient',
            'recipient_email' => 'anna@example.ch',
        ]), 'demo');
        $orders->markPaid((int) $order['id']);

        $recipients = array_column($this->db->all('SELECT recipient FROM mail_log ORDER BY id'), 'recipient');
        self::assertSame(['anna@example.ch', 'felix@example.ch'], $recipients);
        $subject = (string) $this->db->value('SELECT subject FROM mail_log WHERE recipient = ?', ['anna@example.ch']);
        self::assertStringContainsString('für Sie', $subject);
    }

    public function testValidityFromProduct(): void
    {
        $orders = App::orders();
        [$order] = $orders->create($this->product(['validity_months' => 6]), $this->input(), 'demo');
        $voucher = $orders->markPaid((int) $order['id']);
        $expected = (new \DateTimeImmutable('today'))->modify('+6 months')->format('Y-m-d');
        self::assertSame($expected, $voucher['valid_until']);
    }
}
