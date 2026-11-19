<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class DashboardController
{
    public function index(Request $request): void
    {
        Auth::requireStaff();
        if (!Auth::isAdmin()) {
            Response::redirect('/admin/einloesen');
        }
        $db = App::db();
        $today = now()->format('Y-m-d');
        $monthStart = now()->format('Y-m-01 00:00:00');

        $stats = [
            'sold_count' => (int) $db->value("SELECT COUNT(*) FROM vouchers WHERE status <> 'void'"),
            'sold_sum' => (int) $db->value("SELECT COALESCE(SUM(initial_cents), 0) FROM vouchers WHERE status <> 'void'"),
            'open_sum' => (int) $db->value("SELECT COALESCE(SUM(balance_cents), 0) FROM vouchers WHERE status = 'active' AND valid_until >= ?", [$today]),
            'open_count' => (int) $db->value("SELECT COUNT(*) FROM vouchers WHERE status = 'active' AND valid_until >= ?", [$today]),
            'expired_sum' => (int) $db->value("SELECT COALESCE(SUM(balance_cents), 0) FROM vouchers WHERE status = 'active' AND valid_until < ?", [$today]),
            'month_sold' => (int) $db->value("SELECT COALESCE(SUM(initial_cents), 0) FROM vouchers WHERE status <> 'void' AND created_at >= ?", [$monthStart]),
            'month_redeemed' => -(int) $db->value("SELECT COALESCE(SUM(amount_cents), 0) FROM voucher_transactions WHERE type = 'redeem' AND created_at >= ?", [$monthStart]),
        ];

        $recent = $db->all(
            'SELECT t.*, v.code, v.title, v.recipient_name, u.name AS user_name
             FROM voucher_transactions t
             JOIN vouchers v ON v.id = t.voucher_id
             LEFT JOIN users u ON u.id = t.user_id
             ORDER BY t.id DESC LIMIT 12'
        );

        echo View::render('admin/dashboard', [
            'title' => 'Übersicht',
            'stats' => $stats,
            'recent' => $recent,
        ], 'layout-admin');
    }
}
