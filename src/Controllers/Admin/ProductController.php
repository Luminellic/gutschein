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

final class ProductController
{
    public function index(Request $request): void
    {
        Auth::requireAdmin();
        echo View::render('admin/products', [
            'title' => 'Produkte',
            'products' => (new Products(App::db()))->all(),
        ], 'layout-admin');
    }

    public function form(Request $request, ?string $id = null): void
    {
        Auth::requireAdmin();
        $product = null;
        if ($id !== null) {
            $product = (new Products(App::db()))->find((int) $id) ?? Response::error(404, 'Produkt nicht gefunden.');
        }
        echo View::render('admin/product-form', [
            'title' => $product ? 'Produkt bearbeiten' : 'Neues Produkt',
            'product' => $product,
            'old' => Session::takeOld(),
            'errors' => Session::get('_errors', []),
        ], 'layout-admin');
        Session::forget('_errors');
    }

    public function save(Request $request, ?string $id = null): void
    {
        Auth::requireAdmin();
        Csrf::verify($request);
        $products = new Products(App::db());
        if ($id !== null && $products->find((int) $id) === null) {
            Response::error(404, 'Produkt nicht gefunden.');
        }
        $input = array_map('strval', array_filter($request->post, 'is_string'));
        [$savedId, $errors] = $products->save($id !== null ? (int) $id : null, $input);
        if ($errors !== []) {
            Session::keepOld($request->post);
            Session::set('_errors', $errors);
            Response::redirect($id !== null ? '/admin/produkte/' . $id : '/admin/produkte/neu');
        }
        Session::flash('success', 'Produkt gespeichert.');
        Response::redirect('/admin/produkte');
    }
}
