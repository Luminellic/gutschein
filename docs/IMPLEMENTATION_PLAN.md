# Implementierungsplan für Claude Code

Dieser Plan beschreibt den Aufbau der Gutschein-Webapp in Phasen. Phasen 1 bis 5 sind im Prototyp umgesetzt und getestet. Phasen 6 bis 9 sind die nächsten Ausbauschritte. Jede Aufgabe nennt Dateien und Abnahmekriterien, damit Claude Code sie einzeln abarbeiten kann.

Arbeitsregeln für jede Phase:
1. Zuerst `CLAUDE.md` lesen (Konventionen, Architektur).
2. Nach jeder Änderung: `composer test` muss grün sein, `php -l` auf geänderte Dateien.
3. UI-Änderungen im Browser prüfen (Desktop 1280 px und Mobile 390 px), siehe «Lokal starten» in `README.md`.
4. Schemaänderungen immer in **beiden** Dateien: `database/schema.sql` (MySQL) und `database/schema.sqlite.sql` (Tests/Demo). Für bestehende Installationen zusätzlich eine Migration in `database/migrations/` (siehe Phase 9).

---

## Phase 1: Grundgerüst ✅

| Aufgabe | Dateien | Abnahme |
|---|---|---|
| Front-Controller, Router, Request/Response | `public/index.php`, `src/Core/Router.php`, `Request.php`, `Response.php` | Unbekannte Pfade liefern 404-Seite, falsche Methode 405 |
| `.env`-Konfiguration ohne Abhängigkeit | `src/Core/Env.php`, `.env.example` | Werte aus Server-Umgebung haben Vorrang vor `.env` |
| PDO-Datenbank mit Transaktionen und `forUpdate()` | `src/Core/Database.php` | MySQL produktiv, SQLite für Tests |
| Session, CSRF, Flash-Meldungen | `src/Core/Session.php`, `Csrf.php` | Jeder POST ohne gültiges Token → 419 |
| Login mit Rollen und Drosselung | `src/Core/Auth.php`, `Controllers/Admin/AuthController.php` | 5 Fehlversuche pro Konto / 20 pro IP in 15 Min. sperren |
| Schema und Einrichtung | `database/schema.sql`, `bin/install.php` | `php bin/install.php --admin-email=… --demo` legt alles an |
| Sicherheitsheader und CSP | `public/index.php` | Keine Inline-Skripte, `form-action` erlaubt Stripe Checkout |

## Phase 2: Gutschein-Kern ✅

| Aufgabe | Dateien | Abnahme |
|---|---|---|
| Codes (12 Zeichen, 60 Bit) und Tokens (128 Bit) | `src/Support/Code.php` | Keine 0/O/1/I, Eingabe tolerant (Bindestriche, Kleinbuchstaben) |
| Beträge in Rappen, CH-Format | `src/Support/Money.php` | `1'250.50`, `50,5`, `CHF 20` werden korrekt geparst |
| Ausstellen, Einlösen, Korrektur, Storno, Gültigkeit | `src/Services/VoucherService.php` | Journal-Summe = gespeicherter Saldo nach jeder Aktion |
| Doppeleinlösung verhindern | `VoucherService::lock()` | `SELECT … FOR UPDATE` in Transaktion |
| Status «abgelaufen» berechnet | `VoucherService::status()` | Kein Cronjob nötig |

## Phase 3: Shop und Payment ✅

| Aufgabe | Dateien | Abnahme |
|---|---|---|
| Produkte (fix, frei, Leistung) | `src/Services/Products.php` | Grenzen werden serverseitig geprüft |
| Shop-Startseite, Kaufformular mit Live-Vorschau | `templates/shop/*.php`, `public/assets/app.js` | Funktioniert ohne JS (ausser Vorschau) |
| Bestellung anlegen und validieren | `src/Services/OrderService.php` | Fixer Preis ignoriert manipulierten Betrag |
| Provider-Schnittstelle | `src/Payments/PaymentProvider.php` | Neuer Anbieter = neue Klasse + Eintrag in `PaymentFactory` |
| Stripe Checkout (TWINT, Karte) ohne SDK | `src/Payments/StripeProvider.php` | CHF, Idempotency-Key, `client_reference_id` |
| Webhook mit Signaturprüfung | `src/Controllers/WebhookController.php` | Ungültige Signatur → 400, Duplikat → ignoriert |
| Bestätigung auch über Rücksprung | `ShopController::order()` | Funktioniert lokal ohne Webhook |
| Genau ein Gutschein pro Bestellung | `OrderService::markPaid()` | Unique-Index `vouchers.order_id` + Zeilensperre |
| Demo-Provider | `src/Payments/DemoProvider.php` | Nur aktiv bei `PAYMENT_PROVIDER=demo` |
| Gutscheinseite mit QR und Druckansicht | `templates/voucher/show.php`, `Services/QrCode.php` | A4 quer, eine Seite |
| Mails mit Protokoll | `src/Services/Mailer.php`, `templates/mail/*.php` | Treiber `log`, `smtp`, `mail` |

## Phase 4: Kasse und Verwaltung ✅

| Aufgabe | Dateien | Abnahme |
|---|---|---|
| Einlösen per Code, URL oder Kamera | `Admin/RedeemController.php`, `templates/admin/redeem*.php` | Teilbetrag, ganzer Restwert, Leistung als Ganzes |
| Dashboard | `Admin/DashboardController.php` | Offener Restwert, Monat, Total, abgelaufen |
| Gutscheinliste, Detail, Aktionen | `Admin/VoucherAdminController.php` | Suche, Filter, Pagination, Journal, Warnung bei Saldo-Abweichung |
| Manuell ausstellen | `templates/admin/voucher-new.php` | Optional Mailversand |
| Produkte, Einstellungen, Benutzer | `Admin/ProductController.php`, `SettingsController.php`, `UserController.php` | Eigene Rolle/Konto nicht änderbar |
| CSV-Export | `Admin/ExportController.php` | Excel-tauglich (BOM, Semikolon), Zeitraum `?von=&bis=` |
| Mail-Protokoll | `Admin/MailLogController.php` | Anzeige in CSP-Sandbox |

## Phase 5: Qualität ✅

| Aufgabe | Dateien | Abnahme |
|---|---|---|
| Unit- und Integrationstests (SQLite) | `tests/*` | `composer test`: 32 Tests grün |
| E2E-Durchlauf im Browser | manuell / Playwright | Kaufen → Mail → zweimal einlösen → Restwert 0 |
| Responsives Layout, Druck, Fokus, Kontrast | `public/assets/app.css` | 390 px und 1280 px geprüft |

---

## Phase 6: Payrexx als zweiter Provider

Ziel: Schweizer Alternative zu Stripe, bei vielen KMU bereits im Einsatz.

1. `src/Payments/PayrexxProvider.php` implementiert `PaymentProvider`:
   - `startCheckout()`: Gateway über die Payrexx-API anlegen (Betrag in Rappen, `currency=CHF`, `referenceId` = `orders.public_id`, `successRedirectUrl`/`failedRedirectUrl`/`cancelRedirectUrl` auf `/bestellung/{id}`), `link` zurückgeben, Gateway-ID als Referenz.
   - `confirmPayment()`: Gateway abfragen, Status `confirmed` = bezahlt, Betrag und Währung zurückgeben.
   - `parseWebhook()`: Payrexx-Webhook (Transaktion) lesen, Signatur bzw. API-Abgleich prüfen, `referenceId` als Bestell-ID.
2. `PaymentFactory`: Fall `'payrexx'` mit `PAYREXX_INSTANCE`, `PAYREXX_API_SECRET`.
3. Route `POST /webhook/payrexx` in `src/routes.php`, Controller analog `WebhookController::stripe()` (gemeinsame Logik in eine private Methode auslagern).
4. `.env.example`, `README.md` und Einstellungen-Seite (Anzeige «Zahlung») ergänzen.
5. Tests mit gefälschtem HTTP-Client wie in `tests/StripeProviderTest.php`.

Abnahme: Kauf mit Payrexx-Testinstanz erzeugt genau einen Gutschein, auch wenn Webhook und Rücksprung gleichzeitig eintreffen. API-Details vorher in der offiziellen Payrexx-Dokumentation prüfen.

## Phase 7: Gutschein als PDF-Anhang

1. `composer require dompdf/dompdf` (oder `mpdf/mpdf`).
2. `src/Services/VoucherPdf.php`: rendert `templates/voucher/pdf.php` (A5 quer, QR als Inline-SVG oder PNG).
3. `Mailer::sendVoucher()`: PDF anhängen, Dateiname `Gutschein-XXXX-XXXX-XXXX.pdf`.
4. Route `GET /g/{token}/pdf` für Download.

Abnahme: PDF öffnet in Apple Mail, Outlook und Gmail; QR scannbar ab Ausdruck.

## Phase 8: Mehrsprachigkeit (DE/FR/EN)

1. `src/Support/Lang.php` mit `t('key')`, Sprachdateien `lang/de.php`, `lang/fr.php`, `lang/en.php`.
2. Sprache aus URL-Präfix (`/fr/…`) oder Einstellung, `orders.locale` speichern, Mail in Bestellsprache.
3. Stripe `locale` passend setzen.

## Phase 9: Betrieb und Ausbau

- **Migrationen:** `database/migrations/NNN_name.sql` und `bin/migrate.php` (Tabelle `migrations`), bevor das erste Kundenprojekt live geht.
- **Ablauf-Erinnerung:** `bin/remind.php` (Cronjob täglich), Mail 30 Tage vor Ablauf bei Restwert > 0.
- **Rückerstattung:** Storno mit optionaler Stripe-Refund-API (`POST /v1/refunds`).
- **2-Faktor-Login** (TOTP) für Admins.
- **Rabattcodes** im Kaufformular.
- **Design pro Produkt** (Bild/Farbe auf dem Gutschein).
- **Bestellungen aufräumen:** offene Bestellungen älter als 7 Tage auf `cancelled` setzen.

---

## Checkliste: neues Kundenprojekt (ca. 2 Stunden)

1. Repo kopieren bzw. forken, `composer install --no-dev`.
2. Hosting: PHP 8.2+, MySQL-Datenbank anlegen, Webroot auf `public/` setzen, HTTPS aktiv.
3. `.env` aus `.env.example` erstellen: `APP_URL`, DB-Zugang, `MAIL_*` (SMTP des Kunden oder Hosting).
4. `php bin/install.php --admin-email=kunde@… --admin-name="…"` (Passwort wird ausgegeben).
5. Im Admin: Einstellungen (Name, Adresse, Farbe, Bedingungen, Impressum/Datenschutz-Links), Produkte anlegen, Kasse-Benutzer erfassen.
6. Stripe: Konto des Kunden, TWINT im Dashboard aktivieren, Webhook auf `https://…/webhook/stripe` mit den drei `checkout.session.*`-Ereignissen, Schlüssel in `.env`, `PAYMENT_PROVIDER=stripe`.
7. Testkauf mit Stripe-Testmodus, dann Live-Schlüssel eintragen und einen echten Kleinbetrag kaufen und einlösen.
8. Optional: Kundenspezifische Anpassungen nur in `templates/`, `public/assets/app.css` (Variablen) und `Settings::DEFAULTS`.
