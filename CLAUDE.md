# CLAUDE.md

Gutschein-Webapp für Schweizer KMU: Gutscheine online verkaufen (Stripe: TWINT, Karte), an der Kasse einlösen, Restwert verwalten. Basis für Kundenprojekte von Studio Luminelli. Eine Installation pro Kunde.

- Anforderungen: `docs/PRD.md`
- Plan und offene Phasen: `docs/IMPLEMENTATION_PLAN.md`

## Stack

PHP 8.2+ ohne Framework, MySQL 8 / MariaDB 10.6+, serverseitige PHP-Templates, Vanilla-JS, eine CSS-Datei. Composer: `phpmailer/phpmailer`, `chillerlan/php-qrcode`, dev: `phpunit/phpunit`. Muss auf Shared Hosting (Apache, `.htaccess`) und lokal mit XAMPP laufen. Keine Build-Pipeline einführen.

## Befehle

```bash
composer install
cp .env.example .env                      # anpassen
php bin/install.php --admin-email=a@b.ch --admin-name="Name" --admin-password="mind. 10 Zeichen" --demo
composer serve                            # http://localhost:8000
composer test                             # PHPUnit, SQLite im Speicher
```

Ohne MySQL vorführen: in `.env` `DB_DRIVER=sqlite` und `DB_DATABASE=storage/demo.sqlite`.

## Architektur

```
public/index.php        Front-Controller, Sicherheitsheader, CSP
src/routes.php          Alle Routen
src/App.php             Statischer Service-Container (db, settings, mailer, payment, vouchers, orders)
src/Core/               Router, Request, Response, View, Database, Session, Csrf, Auth, Env
src/Services/           VoucherService (Kernlogik), OrderService, Products, Settings, Mailer, QrCode
src/Payments/           PaymentProvider-Interface, StripeProvider, DemoProvider, PaymentFactory
src/Controllers/        Shop, Gutscheinseite, Webhook; Admin/* für Verwaltung und Kasse
templates/              layout.php (Shop), layout-admin.php, partials/ticket.php (der Gutschein), mail/
database/               schema.sql (MySQL) und schema.sqlite.sql (Tests/Demo)
bin/install.php         Tabellen, erster Admin, Demodaten
```

## Regeln, die nicht gebrochen werden dürfen

- **Beträge immer in Rappen als int.** Parsen mit `Money::parse()`, Anzeigen mit `money()` / `Money::format()`.
- **Jede Saldoänderung über `VoucherService`.** Nie `vouchers.balance_cents` direkt ändern. Jede Änderung schreibt eine Zeile in `voucher_transactions`, in derselben Transaktion, mit Zeilensperre (`$db->forUpdate()`).
- **Gutschein nur nach bestätigter Zahlung** über `OrderService::markPaid()`. Diese Methode ist idempotent und prüft Betrag und Währung.
- **Alle POST-Formulare** enthalten `<?= csrf_field() ?>`, Controller rufen `Csrf::verify($request)` auf.
- **Zugriff:** Admin-Seiten `Auth::requireAdmin()`, Kasse `Auth::requireStaff()`.
- **Ausgaben in Templates immer mit `e()`** escapen. Keine Inline-Skripte oder `on…`-Attribute (CSP `script-src 'self'`); JS gehört in `public/assets/app.js`.
- **SQL nur mit Platzhaltern.** Keine Werte in SQL-Strings einsetzen.
- **Schemaänderungen** in `database/schema.sql` und `database/schema.sqlite.sql`.
- Zeiten setzt PHP (`now()`, Zeitzone Europe/Zurich), nicht die Datenbank.

## Gestaltung

Schlicht und ruhig: Weiss/Grau, Schwarz für Text, eine Akzentfarbe aus den Einstellungen (`--accent`). Schrift Hanken Grotesk, lokal in `public/assets/fonts/` (keine externen CDNs, nDSG). Das einzige markante Element ist der Gutschein als Billett mit Abrisslinie (`.ticket` in `app.css`, Markup in `templates/partials/ticket.php`).

Nicht verwenden: Farbverläufe, Emojis, Glow/Schatten-Karten, Deko-Animationen, Grossbuchstaben-Labels, Pfeile in Buttons. Buttons sagen, was passiert («Betrag einlösen», «Produkt speichern»). Fehlermeldungen erklären, was zu tun ist.

## Sprache

UI-Texte Deutsch (Schweiz): «ss» statt «ß», Beträge `CHF 1’250.00`, Datum `19.11.2026`. Anrede «Sie» im Shop.

## Kundenanpassung

Pro Kunde nur: `.env`, Einstellungen im Admin, Produkte, bei Bedarf `templates/` und CSS-Variablen. Neue Einstellung: Schlüssel in `Settings::DEFAULTS`, Feld in `templates/admin/settings.php`, Validierung in `SettingsController::save()`.

## Neuer Zahlungsanbieter

Klasse in `src/Payments/` implementiert `PaymentProvider`, Eintrag in `PaymentFactory`, Webhook-Route in `src/routes.php`, Tests mit injiziertem HTTP-Client (siehe `tests/StripeProviderTest.php`).
