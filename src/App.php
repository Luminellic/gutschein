<?php

declare(strict_types=1);

namespace App;

use App\Core\Database;
use App\Payments\PaymentFactory;
use App\Payments\PaymentProvider;
use App\Services\Mailer;
use App\Services\OrderService;
use App\Services\Settings;
use App\Services\VoucherService;

/**
 * Einfacher Service-Container. Bewusst statisch gehalten, damit Controller und Templates
 * ohne Verdrahtung auskommen. In Tests werden die Dienste mit App::set…() ersetzt.
 */
final class App
{
    private static ?Database $db = null;
    private static ?Settings $settings = null;
    private static ?Mailer $mailer = null;
    private static ?PaymentProvider $payment = null;

    public static function db(): Database
    {
        return self::$db ??= Database::fromEnv();
    }

    public static function setDb(Database $db): void
    {
        self::$db = $db;
        self::$settings = null;
    }

    public static function settings(): Settings
    {
        return self::$settings ??= new Settings(self::db());
    }

    public static function mailer(): Mailer
    {
        return self::$mailer ??= Mailer::fromEnv(self::db(), self::settings());
    }

    public static function setMailer(Mailer $mailer): void
    {
        self::$mailer = $mailer;
    }

    public static function payment(): PaymentProvider
    {
        return self::$payment ??= PaymentFactory::fromEnv();
    }

    public static function setPayment(PaymentProvider $provider): void
    {
        self::$payment = $provider;
    }

    public static function vouchers(): VoucherService
    {
        return new VoucherService(self::db(), self::settings());
    }

    public static function orders(): OrderService
    {
        return new OrderService(self::db(), self::vouchers(), self::mailer());
    }
}
