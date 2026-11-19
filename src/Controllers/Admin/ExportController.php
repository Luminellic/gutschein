<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\VoucherService;
use App\Support\Code;
use App\Support\Money;

/** CSV-Exporte für die Buchhaltung (Semikolon, UTF-8 mit BOM, Beträge in Franken mit Punkt). */
final class ExportController
{
    public function vouchers(Request $request): void
    {
        Auth::requireAdmin();
        $rows = [['Code', 'Titel', 'Typ', 'Kanal', 'Wert', 'Restwert', 'Status', 'Gültig bis', 'Empfänger:in', 'Käufer:in', 'E-Mail', 'Ausgestellt']];
        foreach (App::db()->all('SELECT * FROM vouchers ORDER BY id') as $v) {
            $rows[] = [
                Code::format((string) $v['code']),
                $v['title'],
                $v['type'] === 'service' ? 'Leistung' : 'Wert',
                $v['channel'] === 'manual' ? 'Manuell' : 'Online',
                Money::toInput((int) $v['initial_cents']),
                Money::toInput((int) $v['balance_cents']),
                VoucherService::STATUS_LABELS[VoucherService::status($v)] ?? $v['status'],
                date_ch((string) $v['valid_until']),
                $v['recipient_name'],
                $v['buyer_name'],
                $v['buyer_email'],
                date_ch((string) $v['created_at'], true),
            ];
        }
        Response::csv('gutscheine-' . now()->format('Y-m-d') . '.csv', $rows);
    }

    public function transactions(Request $request): void
    {
        Auth::requireAdmin();
        $from = $request->input('von');
        $to = $request->input('bis');
        $where = [];
        $params = [];
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where[] = 't.created_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where[] = 't.created_at <= ?';
            $params[] = $to . ' 23:59:59';
        }
        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $rows = [['Datum', 'Code', 'Titel', 'Typ', 'Buchung', 'Betrag', 'Restwert danach', 'Notiz', 'Benutzer']];
        foreach (App::db()->all(
            "SELECT t.*, v.code, v.title, v.type AS voucher_type, u.name AS user_name
             FROM voucher_transactions t
             JOIN vouchers v ON v.id = t.voucher_id
             LEFT JOIN users u ON u.id = t.user_id
             $sqlWhere ORDER BY t.id",
            $params
        ) as $t) {
            $rows[] = [
                date_ch((string) $t['created_at'], true),
                Code::format((string) $t['code']),
                $t['title'],
                $t['voucher_type'] === 'service' ? 'Leistung' : 'Wert',
                VoucherService::TX_LABELS[$t['type']] ?? $t['type'],
                number_format((int) $t['amount_cents'] / 100, 2, '.', ''),
                Money::toInput((int) $t['balance_after_cents']),
                $t['note'],
                $t['user_name'],
            ];
        }
        Response::csv('buchungen-' . now()->format('Y-m-d') . '.csv', $rows);
    }
}
