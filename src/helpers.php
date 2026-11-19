<?php

declare(strict_types=1);

use App\App;
use App\Core\Csrf;
use App\Core\Env;
use App\Support\Money;

/** HTML-Escaping. In Templates immer für Ausgaben verwenden. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Relativer Pfad innerhalb der App (berücksichtigt APP_BASE_PATH für Unterordner-Installationen). */
function url(string $path = '/'): string
{
    $base = rtrim(Env::get('APP_BASE_PATH', '') ?? '', '/');
    return $base . '/' . ltrim($path, '/');
}

/** Absolute URL, z.B. für Mails, QR-Codes und Rücksprung-URLs des Zahlungsanbieters. */
function absolute_url(string $path = '/'): string
{
    $root = rtrim(Env::get('APP_URL', 'http://localhost:8000') ?? '', '/');
    return $root . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = dirname(__DIR__) . '/public/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return url('assets/' . ltrim($path, '/')) . '?v=' . $version;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function money(int|string|null $cents, bool $withCurrency = true): string
{
    return Money::format((int) $cents, $withCurrency);
}

function now(): DateTimeImmutable
{
    return new DateTimeImmutable('now');
}

function date_ch(?string $date, bool $withTime = false): string
{
    if ($date === null || $date === '') {
        return '';
    }
    $d = new DateTimeImmutable($date);
    return $d->format($withTime ? 'd.m.Y, H:i' : 'd.m.Y');
}

function setting(string $key, string $default = ''): string
{
    return App::settings()->get($key, $default);
}

/** Pfad aktiv? Für die Navigation. */
function nav_active(string $prefix): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $target = url($prefix);
    $active = $prefix === '/admin' ? rtrim($path, '/') === rtrim($target, '/') : str_starts_with($path, $target);
    return $active ? ' aria-current="page"' : '';
}
