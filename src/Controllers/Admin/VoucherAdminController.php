<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\Products;
use App\Services\VoucherException;
use App\Support\Code;
use App\Support\Money;
use DateTimeImmutable;

final class VoucherAdminController
{
    private const PER_PAGE = 50;

    public function index(Request $request): void
    {
        Auth::requireAdmin();
        $db = App::db();
        $q = $request->input('q');
        $status = $request->input('status');
        $page = max(1, (int) $request->input('seite', '1'));
        $today = now()->format('Y-m-d');

        $where = [];
        $params = [];
        if ($q !== '') {
            $code = Code::normalize($q);
            $where[] = '(code LIKE ? OR recipient_name LIKE ? OR buyer_name LIKE ? OR buyer_email LIKE ? OR title LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, '%' . ($code !== '' ? $code : $q) . '%', $like, $like, $like, $like);
        }
        switch ($status) {
            case 'active':
                $where[] = "status = 'active' AND valid_until >= ?";
                $params[] = $today;
                break;
            case 'expired':
                $where[] = "status = 'active' AND valid_until < ?";
                $params[] = $today;
                break;
            case 'redeemed':
            case 'void':
                $where[] = 'status = ?';
                $params[] = $status;
                break;
        }
        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) $db->value("SELECT COUNT(*) FROM vouchers $sqlWhere", $params);
        $offset = ($page - 1) * self::PER_PAGE;
        $vouchers = $db->all(
            "SELECT * FROM vouchers $sqlWhere ORDER BY id DESC LIMIT " . self::PER_PAGE . " OFFSET $offset",
            $params
        );

        echo View::render('admin/vouchers', [
            'title' => 'Gutscheine',
            'vouchers' => $vouchers,
            'q' => $q,
            'status' => $status,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            'total' => $total,
        ], 'layout-admin');
    }

    public function show(Request $request, string $id): void
    {
        Auth::requireAdmin();
        $vouchers = App::vouchers();
        $voucher = $vouchers->find((int) $id) ?? Response::error(404, 'Gutschein nicht gefunden.');
        $order = $voucher['order_id'] ? App::db()->one('SELECT * FROM orders WHERE id = ?', [$voucher['order_id']]) : null;
        echo View::render('admin/voucher', [
            'title' => 'Gutschein ' . Code::format((string) $voucher['code']),
            'voucher' => $voucher,
            'order' => $order,
            'transactions' => $vouchers->transactions((int) $voucher['id']),
            'journalBalance' => $vouchers->journalBalance((int) $voucher['id']),
        ], 'layout-admin');
    }

    public function create(Request $request): void
    {
        Auth::requireAdmin();
        echo View::render('admin/voucher-new', [
            'title' => 'Gutschein ausstellen',
            'products' => (new Products(App::db()))->all(),
            'old' => Session::takeOld(),
            'errors' => Session::get('_errors', []),
            'defaultValidUntil' => App::vouchers()->validUntil(null)->format('Y-m-d'),
        ], 'layout-admin');
        Session::forget('_errors');
    }

    public function store(Request $request): void
    {
        Auth::requireAdmin();
        Csrf::verify($request);
        $errors = [];
        $productId = (int) $request->input('product_id');
        $product = $productId > 0 ? (new Products(App::db()))->find($productId) : null;
        $type = $product['type'] ?? ($request->input('type') === 'service' ? 'service' : 'value');
        $title = $request->input('title') ?: (string) ($product['title'] ?? '');
        $amount = Money::parse($request->input('amount'));
        $validUntil = DateTimeImmutable::createFromFormat('!Y-m-d', $request->input('valid_until')) ?: null;
        $email = mb_strtolower($request->input('buyer_email'));

        if ($title === '') {
            $errors['title'] = 'Bitte einen Titel eingeben oder ein Produkt wählen.';
        }
        if ($amount === null || $amount <= 0) {
            $errors['amount'] = 'Bitte einen Betrag grösser als 0 eingeben.';
        }
        if ($validUntil === null || $validUntil < new DateTimeImmutable('today')) {
            $errors['valid_until'] = 'Bitte ein Datum in der Zukunft wählen.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['buyer_email'] = 'Diese E-Mail-Adresse ist ungültig.';
        }
        if ($errors !== []) {
            Session::keepOld($request->post);
            Session::set('_errors', $errors);
            Response::redirect('/admin/gutscheine/neu');
        }

        $voucher = App::vouchers()->issue([
            'type' => $type,
            'title' => $title,
            'service_description' => $type === 'service' ? ($product['description'] ?? null) : null,
            'amount_cents' => $amount,
            'product_id' => $product['id'] ?? null,
            'recipient_name' => $request->input('recipient_name'),
            'message' => mb_substr($request->input('message'), 0, 300),
            'buyer_name' => $request->input('buyer_name'),
            'buyer_email' => $email,
            'channel' => 'manual',
            'valid_until' => $validUntil,
            'user_id' => Auth::id(),
            'note' => $request->input('note') ?: 'Manuell ausgestellt',
        ]);
        if ($email !== '' && $request->input('send_mail') === '1') {
            App::orders()->sendVoucher($voucher, null, $email);
        }
        Session::flash('success', 'Gutschein ' . Code::format((string) $voucher['code']) . ' ausgestellt.');
        Response::redirect('/admin/gutscheine/' . $voucher['id']);
    }

    public function validity(Request $request, string $id): void
    {
        $this->action($request, $id, function (array $voucher) use ($request): string {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $request->input('valid_until'));
            if ($date === false) {
                throw new VoucherException('Bitte ein gültiges Datum wählen.');
            }
            App::vouchers()->changeValidity((int) $voucher['id'], $date, (int) Auth::id());
            return 'Gültigkeit auf ' . $date->format('d.m.Y') . ' geändert.';
        });
    }

    public function adjust(Request $request, string $id): void
    {
        $this->action($request, $id, function (array $voucher) use ($request): string {
            $amount = Money::parse($request->input('amount'));
            if ($amount === null) {
                throw new VoucherException('Bitte einen Betrag eingeben, z.B. 20 oder 12.50.');
            }
            $amount = $request->input('direction') === 'minus' ? -abs($amount) : abs($amount);
            App::vouchers()->adjust((int) $voucher['id'], $amount, (int) Auth::id(), $request->input('note'));
            return 'Korrektur von ' . Money::format($amount) . ' gebucht.';
        });
    }

    public function void(Request $request, string $id): void
    {
        $this->action($request, $id, function (array $voucher) use ($request): string {
            App::vouchers()->void((int) $voucher['id'], (int) Auth::id(), $request->input('note'));
            return 'Gutschein storniert.';
        });
    }

    public function resend(Request $request, string $id): void
    {
        $this->action($request, $id, function (array $voucher) use ($request): string {
            $to = mb_strtolower($request->input('email'));
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new VoucherException('Bitte eine gültige E-Mail-Adresse eingeben.');
            }
            $order = $voucher['order_id'] ? App::db()->one('SELECT * FROM orders WHERE id = ?', [$voucher['order_id']]) : null;
            if (!App::orders()->sendVoucher($voucher, $order, $to)) {
                throw new VoucherException('Die E-Mail konnte nicht gesendet werden. Details im Mail-Protokoll.');
            }
            return 'Gutschein an ' . $to . ' gesendet.';
        });
    }

    /** @param callable(array<string, mixed>): string $callback */
    private function action(Request $request, string $id, callable $callback): void
    {
        Auth::requireAdmin();
        Csrf::verify($request);
        $voucher = App::vouchers()->find((int) $id) ?? Response::error(404, 'Gutschein nicht gefunden.');
        try {
            Session::flash('success', $callback($voucher));
        } catch (VoucherException $e) {
            Session::flash('error', $e->getMessage());
        }
        Response::redirect('/admin/gutscheine/' . $voucher['id']);
    }
}
