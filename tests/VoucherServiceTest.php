<?php

declare(strict_types=1);

namespace Tests;

use App\App;
use App\Services\VoucherException;
use App\Services\VoucherService;
use DateTimeImmutable;

final class VoucherServiceTest extends TestCase
{
    private function issue(int $cents = 10000, string $type = 'value', ?DateTimeImmutable $validUntil = null): array
    {
        return App::vouchers()->issue([
            'type' => $type,
            'title' => 'Test',
            'amount_cents' => $cents,
            'valid_until' => $validUntil ?? new DateTimeImmutable('+1 year'),
        ]);
    }

    public function testIssueWritesJournal(): void
    {
        $voucher = $this->issue(5000);
        self::assertSame('active', $voucher['status']);
        self::assertSame(5000, (int) $voucher['balance_cents']);
        self::assertSame(5000, App::vouchers()->journalBalance((int) $voucher['id']));
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $voucher['token']);
    }

    public function testPartialAndFullRedemption(): void
    {
        $service = App::vouchers();
        $user = $this->user();
        $voucher = $this->issue(10000);

        $after = $service->redeem((int) $voucher['id'], 4050, $user, 'Beleg 1');
        self::assertSame(5950, (int) $after['balance_cents']);
        self::assertSame('active', $after['status']);

        $after = $service->redeem((int) $voucher['id'], 5950, $user);
        self::assertSame(0, (int) $after['balance_cents']);
        self::assertSame('redeemed', $after['status']);
        self::assertSame(0, $service->journalBalance((int) $voucher['id']));
        self::assertCount(3, $service->transactions((int) $voucher['id']));
    }

    public function testCannotRedeemMoreThanBalance(): void
    {
        $voucher = $this->issue(2000);
        $this->expectException(VoucherException::class);
        App::vouchers()->redeem((int) $voucher['id'], 2001, null);
    }

    public function testCannotRedeemZero(): void
    {
        $voucher = $this->issue(2000);
        $this->expectException(VoucherException::class);
        App::vouchers()->redeem((int) $voucher['id'], 0, null);
    }

    public function testServiceVoucherIsRedeemedAsWhole(): void
    {
        $voucher = $this->issue(12000, 'service');
        $after = App::vouchers()->redeem((int) $voucher['id'], 1, null);
        self::assertSame(0, (int) $after['balance_cents']);
        self::assertSame('redeemed', $after['status']);
    }

    public function testExpiredVoucherCannotBeRedeemed(): void
    {
        $voucher = $this->issue(5000, 'value', new DateTimeImmutable('-1 day'));
        self::assertSame('expired', VoucherService::status($voucher));
        try {
            App::vouchers()->redeem((int) $voucher['id'], 1000, null);
            self::fail('Abgelaufener Gutschein wurde eingelöst.');
        } catch (VoucherException $e) {
            self::assertStringContainsString('abgelaufen', $e->getMessage());
        }
        // Nach Verlängerung wieder einlösbar
        $user = $this->user('admin');
        App::vouchers()->changeValidity((int) $voucher['id'], new DateTimeImmutable('+1 month'), $user);
        $after = App::vouchers()->redeem((int) $voucher['id'], 1000, $user);
        self::assertSame(4000, (int) $after['balance_cents']);
    }

    public function testVoidAndAdjust(): void
    {
        $service = App::vouchers();
        $admin = $this->user('admin');
        $voucher = $this->issue(5000);
        $service->redeem((int) $voucher['id'], 5000, $admin);

        // Fehleinlösung korrigieren: wieder aktiv
        $after = $service->adjust((int) $voucher['id'], 2000, $admin, 'Fehleinlösung');
        self::assertSame('active', $after['status']);
        self::assertSame(2000, (int) $after['balance_cents']);

        $after = $service->void((int) $voucher['id'], $admin, 'Kundenwunsch');
        self::assertSame('void', $after['status']);
        self::assertSame(0, (int) $after['balance_cents']);
        self::assertSame(0, $service->journalBalance((int) $voucher['id']));

        $this->expectException(VoucherException::class);
        $service->redeem((int) $voucher['id'], 100, $admin);
    }

    public function testAdjustNeedsReasonAndCannotGoNegative(): void
    {
        $admin = $this->user('admin');
        $voucher = $this->issue(1000);
        try {
            App::vouchers()->adjust((int) $voucher['id'], -500, $admin, ' ');
            self::fail('Korrektur ohne Begründung akzeptiert.');
        } catch (VoucherException) {
        }
        $this->expectException(VoucherException::class);
        App::vouchers()->adjust((int) $voucher['id'], -1500, $admin, 'zu viel');
    }

    public function testFindByCodeIsForgiving(): void
    {
        $voucher = $this->issue();
        $code = (string) $voucher['code'];
        $typed = strtolower(substr($code, 0, 4) . ' ' . substr($code, 4, 4) . '-' . substr($code, 8));
        self::assertSame($voucher['id'], App::vouchers()->findByCode($typed)['id']);
        self::assertNull(App::vouchers()->findByCode('NICHT-DA'));
    }
}
