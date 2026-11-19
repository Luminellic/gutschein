<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\View;

final class OrderAdminController
{
    public function index(Request $request): void
    {
        Auth::requireAdmin();
        $status = $request->input('status');
        $params = [];
        $where = '';
        if (in_array($status, ['pending', 'paid', 'cancelled'], true)) {
            $where = 'WHERE o.status = ?';
            $params[] = $status;
        }
        $orders = App::db()->all(
            "SELECT o.*, p.title AS product_title, v.id AS voucher_id, v.code
             FROM orders o
             LEFT JOIN products p ON p.id = o.product_id
             LEFT JOIN vouchers v ON v.order_id = o.id
             $where ORDER BY o.id DESC LIMIT 200",
            $params
        );
        echo View::render('admin/orders', [
            'title' => 'Bestellungen',
            'orders' => $orders,
            'status' => $status,
        ], 'layout-admin');
    }
}
