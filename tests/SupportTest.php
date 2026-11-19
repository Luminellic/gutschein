<?php

declare(strict_types=1);

namespace Tests;

use App\Support\Code;
use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;

final class SupportTest extends \PHPUnit\Framework\TestCase
{
    /** @return array<string, array{string, ?int}> */
    public static function amounts(): array
    {
        return [
            'ganze Franken' => ['50', 5000],
            'Punkt' => ['50.5', 5050],
            'Komma' => ['50,50', 5050],
            'Tausender' => ["1'250.00", 125000],
            'mit CHF' => ['CHF 20', 2000],
            'leer' => ['', null],
            'Text' => ['zwanzig', null],
            'drei Nachkommastellen' => ['1.234', null],
        ];
    }

    #[DataProvider('amounts')]
    public function testParse(string $input, ?int $expected): void
    {
        self::assertSame($expected, Money::parse($input));
    }

    public function testFormat(): void
    {
        self::assertSame('CHF 1’250.50', Money::format(125050));
        self::assertSame('0.05', Money::format(5, false));
        self::assertSame('−20.00', Money::format(-2000, false));
    }

    public function testCodesAreValidAndReadable(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $code = Code::generate();
            self::assertTrue(Code::isValid($code));
            self::assertDoesNotMatchRegularExpression('/[01IO]/', $code);
        }
        self::assertSame('ABCD-EFGH-JKLM', Code::format('ABCDEFGHJKLM'));
        self::assertSame('ABCDEFGHJKLM', Code::normalize(' abcd-efgh jklm '));
        self::assertFalse(Code::isValid('ABC'));
    }
}
