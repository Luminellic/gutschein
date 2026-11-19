# PRD: Gutschein-Webapp

Stand: 19.11.2026 · Version 1.0 · Owner: Studio Luminelli

## 1. Zweck

Eine schlanke Webapp, mit der ein Schweizer Betrieb Gutscheine online verkauft, an der Kasse einlöst und den Restwert verwaltet. Der Prototyp ist gleichzeitig **Showcase** und **Basis für Kundenprojekte**: Pro Kunde wird eine eigene Instanz aufgesetzt und innerhalb von Stunden angepasst (Name, Farben, Produkte, Texte, Payment-Konto).

## 2. Zielgruppe

| Rolle | Wer | Ziel |
|---|---|---|
| Käufer:in | Endkundschaft des Betriebs | Gutschein in 1 bis 2 Minuten kaufen, mit TWINT oder Karte bezahlen, sofort erhalten |
| Beschenkte:r | Empfänger:in | Gutschein öffnen, ausdrucken oder auf dem Handy vorzeigen, Restwert sehen |
| Kasse (Rolle `staff`) | Personal im Laden, Restaurant, Praxis | Code scannen oder eintippen, Betrag abziehen, fertig |
| Admin (Rolle `admin`) | Inhaber:in | Produkte pflegen, Übersicht über verkaufte und offene Gutscheine, Export für Buchhaltung |

Typische Kunden: Restaurants, Coiffeure, Kosmetik- und Massagepraxen, Läden, Kulturbetriebe, Vereine.

## 3. Grundsatzentscheide

1. **Eine Instanz pro Kunde (Single-Tenant).** Jeder Betrieb hat seine Installation, seine Datenbank und sein eigenes Stripe-Konto. Das Geld fliesst direkt an den Betrieb. Luminelli ist nie Zahlungsvermittler (kein Geldwäschereithema, kein Stripe Connect nötig).
2. **PHP 8.2+ und MySQL/MariaDB, ohne Framework.** Läuft auf jedem Schweizer Shared Hosting (cyon, Metanet, Hostpoint) und lokal mit XAMPP. Keine Build-Pipeline, wenige Abhängigkeiten (Composer: PHPMailer, php-qrcode).
3. **Serverseitig gerenderte Seiten**, minimales Vanilla-JS. Schnell, robust, einfach anpassbar.
4. **Payment über austauschbare Provider.** Stripe Checkout (TWINT + Karte, Apple/Google Pay) ist implementiert, ein Demo-Provider erlaubt Vorführungen ohne Zahlungskonto. Payrexx ist als nächster Provider vorgesehen.
5. **Restwert als Buchungsjournal.** Jede Veränderung ist eine Buchung (`voucher_transactions`). Der Saldo auf dem Gutschein ist ein Cache, der in derselben DB-Transaktion nachgeführt wird.
6. **Beträge in Rappen (Integer).** Nie Fliesskommazahlen.

## 4. Funktionsumfang (MVP)

### 4.1 Shop (öffentlich)
- F1 Startseite mit Name, Einleitungstext und allen aktiven Gutschein-Produkten.
- F2 Produkttypen:
  - **Wertgutschein, fixer Betrag** (z.B. CHF 50)
  - **Wertgutschein, frei wählbar** innerhalb Min/Max (z.B. CHF 20 bis 500)
  - **Leistungsgutschein** (z.B. «Massage 60 Minuten», Preis CHF 120). Wird als Ganzes eingelöst.
- F3 Kaufformular: Betrag (falls frei), Name und E-Mail Käufer:in, optional Name Empfänger:in, persönliche Nachricht (max. 300 Zeichen), Zustellung an Käufer:in oder direkt an Empfänger:in, Zustimmung zu den Bedingungen.
- F4 **Live-Vorschau** des Gutscheins neben dem Formular.
- F5 Weiterleitung zum Zahlungsanbieter, danach Bestellstatus-Seite. Bei bestätigter Zahlung Link zum Gutschein und E-Mail-Versand.
- F6 Gutscheinseite `/g/{token}`: Gutschein mit Code, QR-Code, Wert, Restwert, Gültigkeit, Nachricht, Druckansicht (A4/A5 tauglich).

### 4.2 Payment
- P1 Stripe Checkout (gehostet). Zahlarten werden im Stripe-Dashboard aktiviert (TWINT, Karte, Apple Pay, Google Pay). Währung immer CHF (TWINT ist nur in CHF verfügbar).
- P2 Gutschein wird **nur nach bestätigter Zahlung** erzeugt: über Webhook `checkout.session.completed` / `checkout.session.async_payment_succeeded` oder beim Zurückkehren auf die Bestellseite (Abfrage der Session bei Stripe). Beides idempotent: pro Bestellung entsteht genau ein Gutschein.
- P3 Webhook-Signatur wird geprüft (HMAC SHA-256, Toleranz 5 Min.). Jedes Event wird nur einmal verarbeitet.
- P4 Demo-Provider: simulierte Zahlungsseite («Zahlung simulieren» / «Abbrechen»), deutlich als Demo gekennzeichnet.
- P5 Abgebrochene Zahlungen lassen die Bestellung `pending`. Es entsteht kein Gutschein.

### 4.3 Einlösen (Rolle `staff` und `admin`)
- E1 Gutschein finden über Code-Eingabe (Gross/Klein und Bindestriche egal) oder QR-Scan. Der QR enthält die URL der Gutscheinseite; mit jeder Handykamera scanbar. Angemeldetes Personal sieht dort direkt die Einlöse-Maske.
- E2 Anzeige: Status, Restwert, Gültigkeit, Typ, letzte Buchungen.
- E3 Wertgutschein: Teilbetrag oder Restwert einlösen, optionale Notiz (z.B. Belegnummer).
- E4 Leistungsgutschein: «Leistung einlösen» (ganzer Wert).
- E5 Schutz gegen Doppeleinlösung: Zeilensperre (`SELECT … FOR UPDATE`) in einer Transaktion, Betrag darf Restwert nicht übersteigen.
- E6 Abgelaufene oder stornierte Gutscheine können nicht eingelöst werden (klare Meldung, Admin kann verlängern).

### 4.4 Verwaltung (Rolle `admin`)
- V1 Dashboard: verkauft (Anzahl, Summe), eingelöst, offener Restwert (Verbindlichkeit), letzte Buchungen.
- V2 Gutscheinliste mit Suche (Code, Name, E-Mail) und Filter (aktiv, eingelöst, abgelaufen, storniert).
- V3 Detail mit Buchungsjournal. Aktionen: Gültigkeit ändern, Korrekturbuchung (+/−) mit Pflichtnotiz, stornieren, E-Mail erneut senden.
- V4 Gutschein manuell ausstellen (z.B. an der Theke bar bezahlt, Kulanz, Wettbewerb).
- V5 Produkte anlegen, bearbeiten, sortieren, deaktivieren.
- V6 Bestellungen ansehen (inkl. offene/abgebrochene).
- V7 Einstellungen: Betriebsname, Adresse, Kontakt-E-Mail, Einleitungstext, Bedingungen, Akzentfarbe, Standard-Gültigkeit in Monaten, Absendername.
- V8 Benutzer verwalten (Admin, Kasse), Passwort setzen, deaktivieren.
- V9 CSV-Export Gutscheine und Buchungen (Semikolon, UTF-8 mit BOM, Excel-tauglich).
- V10 Mail-Protokoll (zeigt versendete Mails, im Demo-Modus die einzige «Zustellung»).

## 5. Nicht-funktionale Anforderungen

- **Sicherheit:** Passwort-Hash (bcrypt/argon), CSRF-Token auf allen POST, Session-Cookie `HttpOnly`, `SameSite=Lax`, `Secure` unter HTTPS, Prepared Statements, konsequentes Escaping, Login-Drosselung (5 Fehlversuche / 15 Min.), nicht erratbare Codes (12 Zeichen aus 32-Zeichen-Alphabet ohne 0/O/1/I, also 60 Bit) und Tokens (128 Bit).
- **Datenschutz (nDSG):** keine externen Fonts/CDNs/Tracker, Schrift lokal gehostet. Personendaten nur so weit nötig.
- **Barrierefreiheit:** semantisches HTML, sichtbarer Fokus, Kontrast AA, Formular-Labels, funktioniert ohne JS (bis auf Vorschau/Kamera).
- **Performance:** keine JS-Frameworks, eine CSS-Datei, Seiten < 100 KB.
- **Betrieb:** Apache mit `.htaccess` oder PHP-Built-in-Server lokal, Webroot `public/`.
- **Responsiv:** Kasse ist Mobile-first (Handy/Tablet an der Theke).

## 6. Design

Schlicht, ruhig, hochwertig. Weiss, Schwarz, Grau, eine Akzentfarbe pro Kunde (Einstellung). Eine Schrift (Hanken Grotesk, lokal), tabellarische Ziffern für Beträge. Das einzige ausdrucksstarke Element ist der **Gutschein selbst**: als Billett mit Abriss-Linie und Talon (Code + QR). Keine Verläufe, keine Emojis, keine Schatten-Karten, keine Animationen ausser Rückmeldungen auf Aktionen.

## 7. Rechtliches / Buchhaltung (Hinweise, keine Rechtsberatung)

- Wertgutscheine: MWST fällt in der Schweiz grundsätzlich erst bei der Einlösung an. Der Gutscheintyp wird gespeichert, Einlösungen sind mit Datum exportierbar.
- Leistungsgutscheine können anders behandelt werden. Der Betrieb klärt das mit seiner Treuhand.
- Gültigkeit ist pro Produkt einstellbar und wird auf dem Gutschein angezeigt.

## 8. Ausserhalb des MVP (Backlog)

Payrexx-Provider · PDF-Anhang in der Mail · Mehrsprachigkeit (DE/FR/EN) · Rabattcodes · Gutschein-Designs pro Produkt mit Bild · Versand per Post · Teilrückerstattung via Stripe · Kassensystem-Anbindung · Multi-Tenant-Betrieb · Ablauf-Erinnerung per Mail · 2-Faktor-Login.

## 9. Erfolgskriterien Showcase

- Komplett-Durchlauf (kaufen, Mail, einlösen in 2 Schritten, Restwert 0) in unter 3 Minuten vorführbar, auch ohne Stripe-Konto (Demo-Provider).
- Neue Kundeninstanz in unter 2 Stunden aufgesetzt (Hosting, DB, `.env`, Einstellungen, Produkte, Stripe-Schlüssel).
