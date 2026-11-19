# Gutschein-Webapp

Gutscheine online verkaufen, an der Kasse einlösen und den Restwert verwalten. Für Schweizer Betriebe, Zahlung mit TWINT und Karte über Stripe. Ein Prototyp von Studio Luminelli und Basis für Kundenprojekte.

- **Shop:** Wertgutscheine (fix oder frei wählbar) und Leistungsgutscheine, Live-Vorschau, persönliche Nachricht, Zustellung an sich oder direkt an die beschenkte Person
- **Zahlung:** Stripe Checkout mit TWINT, Kredit- und Debitkarte; Demo-Modus ohne Zahlungskonto
- **Gutschein:** eigene Seite mit QR-Code, Restwert und Druckansicht, Versand per E-Mail
- **Kasse:** Code eintippen oder QR scannen, Teilbetrag oder alles einlösen
- **Verwaltung:** Übersicht, Gutscheine mit Buchungsjournal, Korrektur, Storno, Verlängerung, manuell ausstellen, Produkte, Benutzer (Admin/Kasse), CSV-Export

Dokumentation: [PRD](docs/PRD.md) · [Implementierungsplan](docs/IMPLEMENTATION_PLAN.md) · [Hinweise für Claude Code](CLAUDE.md)

## Voraussetzungen

- PHP 8.2+ mit `pdo_mysql`, `curl`, `mbstring`
- MySQL 8 oder MariaDB 10.6+
- Composer

## Lokal starten

```bash
composer install
cp .env.example .env
# DB_* in .env anpassen, Datenbank "gutschein" anlegen
php bin/install.php --admin-email=du@example.ch --admin-name="Dein Name" --admin-password="mindestens-10-zeichen" --demo
composer serve
```

Shop: http://localhost:8000 · Verwaltung: http://localhost:8000/admin

Mit dem Handy testen (z.B. QR-Code scannen): `composer serve-lan` starten und in `.env` `APP_URL=http://<IP-des-Computers>:8000` setzen. Handy und Computer müssen im selben WLAN sein.

Schnelle Vorführung ohne MySQL: in `.env` `DB_DRIVER=sqlite` und `DB_DATABASE=storage/demo.sqlite` setzen, dann `php bin/install.php …` wie oben.

**XAMPP:** Projekt nach `htdocs/gutschein` legen, `.env` mit `APP_URL=http://localhost/gutschein` und `APP_BASE_PATH=/gutschein`. Die `.htaccess` im Projektordner leitet auf `public/` weiter.

## Stripe einrichten (TWINT und Karte)

1. Im Stripe-Dashboard unter *Einstellungen → Zahlungsmethoden* TWINT und Karten aktivieren. TWINT funktioniert nur mit CHF; die App rechnet ausschliesslich in CHF.
2. Unter *Entwickler → Webhooks* einen Endpunkt `https://deine-domain.ch/webhook/stripe` mit den Ereignissen `checkout.session.completed`, `checkout.session.async_payment_succeeded` und `checkout.session.async_payment_failed` anlegen.
3. In `.env`:
   ```
   PAYMENT_PROVIDER=stripe
   STRIPE_SECRET_KEY=sk_live_…
   STRIPE_WEBHOOK_SECRET=whsec_…
   ```
4. Lokal testen: `stripe listen --forward-to localhost:8000/webhook/stripe`. Ohne Webhook bestätigt die Bestellseite die Zahlung beim Rücksprung direkt bei Stripe.

## E-Mail

`MAIL_DRIVER=log` speichert Mails nur im Mail-Protokoll (Verwaltung → Einstellungen → Mail-Protokoll). Für echten Versand `MAIL_DRIVER=smtp` mit `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS` oder `MAIL_DRIVER=mail` (PHP-Mail des Hostings).

## Live schalten (Shared Hosting)

1. Dateien hochladen, `composer install --no-dev` (oder `vendor/` mit hochladen).
2. Webroot der Domain auf `public/` setzen. Falls nicht möglich, leitet die `.htaccess` im Hauptordner weiter.
3. `.env` mit Live-Werten, `APP_DEBUG=false`, HTTPS-Weiterleitung in `public/.htaccess` einkommentieren.
4. `php bin/install.php --admin-email=…` per SSH ausführen oder `database/schema.sql` per phpMyAdmin importieren und Admin per Skript anlegen.

## Tests

```bash
composer test
```

Die Tests laufen mit SQLite im Speicher und brauchen keine MySQL-Datenbank.

## Hinweis

Keine Rechts- oder Steuerberatung. Wertgutscheine sind in der Schweiz MWST-rechtlich in der Regel erst bei Einlösung relevant, Leistungsgutscheine können anders behandelt werden. Die Exporte trennen beide Typen; Details mit der Treuhand klären.
