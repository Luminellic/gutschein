<?php

declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Payments\PaymentException;
use App\Services\Products;
use App\Services\VoucherException;

final class ShopController
{
    public function index(Request $request): void
    {
        echo View::render('shop/index', [
            'title' => 'Gutscheine',
            'products' => (new Products(App::db()))->active(),
        ]);
    }

    public function product(Request $request, string $id): void
    {
        $product = $this->activeProduct($id);
        echo View::render('shop/product', [
            'title' => $product['title'],
            'product' => $product,
            'old' => Session::takeOld(),
            'errors' => Session::get('_errors', []),
            'validUntil' => App::vouchers()->validUntil($product['validity_months'] !== null ? (int) $product['validity_months'] : null),
        ]);
        Session::forget('_errors');
    }

    public function buy(Request $request, string $id): void
    {
        Csrf::verify($request);
        $product = $this->activeProduct($id);
        $payment = App::payment();
        $orders = App::orders();

        [$order, $errors] = $orders->create($product, array_map('strval', array_filter($request->post, 'is_string')), $payment->name());
        if ($order === null) {
            Session::keepOld($request->post);
            Session::set('_errors', $errors);
            Response::redirect('/gutschein/' . $product['id'] . '#formular');
        }

        try {
            $checkout = $payment->startCheckout($order, (string) $product['title']);
        } catch (PaymentException $e) {
            error_log('Checkout fehlgeschlagen: ' . $e->getMessage());
            Session::keepOld($request->post);
            Session::flash('error', 'Die Zahlung konnte nicht gestartet werden. Bitte versuchen Sie es in einem Moment nochmals.');
            Response::redirect('/gutschein/' . $product['id'] . '#formular');
        }
        $orders->attachReference((int) $order['id'], $checkout->reference);
        Response::redirect($checkout->redirectUrl);
    }

    /** Rücksprung nach der Zahlung: zeigt Erfolg, Wartezustand oder Abbruch. */
    public function order(Request $request, string $publicId): void
    {
        $orders = App::orders();
        $order = $orders->findByPublicId($publicId) ?? Response::error(404, 'Diese Bestellung gibt es nicht.');
        $voucher = $orders->voucherFor((int) $order['id']);
        $cancelled = $request->has('abgebrochen');

        // Fallback zum Webhook: direkt beim Anbieter nachfragen (z.B. lokal ohne Webhook).
        if ($voucher === null && !$cancelled && $order['status'] === 'pending' && $order['provider'] !== 'demo') {
            try {
                $result = App::payment()->confirmPayment($order);
                if ($result->paid) {
                    $voucher = $orders->markPaid((int) $order['id'], $result->amountCents, $result->currency);
                }
            } catch (PaymentException | VoucherException $e) {
                error_log('Zahlungsbestätigung fehlgeschlagen: ' . $e->getMessage());
            }
        }

        $product = App::db()->one('SELECT * FROM products WHERE id = ?', [$order['product_id']]);
        echo View::render('shop/order', [
            'title' => $voucher ? 'Vielen Dank' : 'Bestellung',
            'order' => $order,
            'voucher' => $voucher,
            'product' => $product,
            'cancelled' => $cancelled && $voucher === null,
            'refresh' => $voucher === null && !$cancelled && $order['status'] === 'pending' ? 4 : null,
        ]);
    }

    public function demoPayment(Request $request, string $publicId): void
    {
        $order = $this->demoOrder($publicId);
        if ($order['status'] !== 'pending') {
            Response::redirect('/bestellung/' . $order['public_id']);
        }
        $product = App::db()->one('SELECT * FROM products WHERE id = ?', [$order['product_id']]);
        echo View::render('shop/demo-payment', [
            'title' => 'Demo-Zahlung',
            'order' => $order,
            'product' => $product,
        ]);
    }

    public function demoPaymentSubmit(Request $request, string $publicId): void
    {
        Csrf::verify($request);
        $order = $this->demoOrder($publicId);
        if ($request->input('action') === 'cancel') {
            Response::redirect('/bestellung/' . $order['public_id'] . '?abgebrochen=1');
        }
        App::orders()->markPaid((int) $order['id']);
        Response::redirect('/bestellung/' . $order['public_id']);
    }

    /** @return array<string, mixed> */
    private function demoOrder(string $publicId): array
    {
        $order = App::orders()->findByPublicId($publicId);
        if ($order === null || $order['provider'] !== 'demo' || App::payment()->name() !== 'demo') {
            Response::error(404, 'Diese Seite gibt es nicht.');
        }
        return $order;
    }

    /** @return array<string, mixed> */
    private function activeProduct(string $id): array
    {
        $product = ctype_digit($id) ? (new Products(App::db()))->find((int) $id) : null;
        if ($product === null || !(int) $product['active']) {
            Response::error(404, 'Diesen Gutschein gibt es nicht oder nicht mehr.');
        }
        return $product;
    }
}
