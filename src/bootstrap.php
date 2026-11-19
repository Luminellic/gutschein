<?php

declare(strict_types=1);

use App\Core\Env;

define('APP_ROOT', dirname(__DIR__));

$autoload = APP_ROOT . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit('Abhängigkeiten fehlen. Bitte "composer install" ausführen.');
}
require $autoload;
require __DIR__ . '/helpers.php';

Env::load(APP_ROOT . '/.env');
date_default_timezone_set(Env::get('APP_TIMEZONE', 'Europe/Zurich') ?? 'Europe/Zurich');
mb_internal_encoding('UTF-8');

$debug = Env::bool('APP_DEBUG');
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');

if (PHP_SAPI !== 'cli') {
    set_exception_handler(static function (Throwable $e) use ($debug): void {
        error_log((string) $e);
        http_response_code(500);
        if ($debug) {
            echo '<pre>' . e((string) $e) . '</pre>';
            return;
        }
        try {
            echo App\Core\View::render('error', [
                'title' => 'Fehler',
                'status' => 500,
                'message' => 'Es ist ein technischer Fehler aufgetreten. Bitte versuchen Sie es später nochmals.',
            ]);
        } catch (Throwable) {
            echo 'Es ist ein technischer Fehler aufgetreten.';
        }
    });
}
