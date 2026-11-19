-- SQLite-Variante von database/schema.sql. Nur für automatische Tests und lokale Vorführungen ohne MySQL.
-- Bei Schemaänderungen beide Dateien nachführen.

CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT NULL);

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'staff', active INTEGER NOT NULL DEFAULT 1,
    last_login_at TEXT NULL, created_at TEXT NOT NULL
);

CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL, ip TEXT NOT NULL, created_at TEXT NOT NULL
);

CREATE TABLE products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL, title TEXT NOT NULL, description TEXT NULL,
    amount_cents INTEGER NULL, min_cents INTEGER NULL, max_cents INTEGER NULL,
    validity_months INTEGER NULL, active INTEGER NOT NULL DEFAULT 1, sort INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL, updated_at TEXT NOT NULL
);

CREATE TABLE orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id TEXT NOT NULL UNIQUE, product_id INTEGER NOT NULL REFERENCES products(id),
    status TEXT NOT NULL DEFAULT 'pending', amount_cents INTEGER NOT NULL, currency TEXT NOT NULL DEFAULT 'CHF',
    buyer_name TEXT NOT NULL, buyer_email TEXT NOT NULL, recipient_name TEXT NULL, recipient_email TEXT NULL,
    message TEXT NULL, deliver_to TEXT NOT NULL DEFAULT 'buyer', provider TEXT NOT NULL, provider_ref TEXT NULL,
    paid_at TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL,
    UNIQUE (provider, provider_ref)
);

CREATE TABLE vouchers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE, token TEXT NOT NULL UNIQUE, order_id INTEGER NULL UNIQUE REFERENCES orders(id),
    product_id INTEGER NULL, type TEXT NOT NULL, title TEXT NOT NULL, service_description TEXT NULL,
    initial_cents INTEGER NOT NULL, balance_cents INTEGER NOT NULL, currency TEXT NOT NULL DEFAULT 'CHF',
    status TEXT NOT NULL DEFAULT 'active', recipient_name TEXT NULL, message TEXT NULL,
    buyer_name TEXT NULL, buyer_email TEXT NULL, channel TEXT NOT NULL DEFAULT 'online',
    valid_until TEXT NOT NULL, created_by INTEGER NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL
);

CREATE TABLE voucher_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    voucher_id INTEGER NOT NULL REFERENCES vouchers(id), type TEXT NOT NULL, amount_cents INTEGER NOT NULL,
    balance_after_cents INTEGER NOT NULL, note TEXT NULL, user_id INTEGER NULL, created_at TEXT NOT NULL
);

CREATE TABLE webhook_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider TEXT NOT NULL, event_id TEXT NOT NULL, type TEXT NOT NULL, received_at TEXT NOT NULL,
    UNIQUE (provider, event_id)
);

CREATE TABLE mail_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    recipient TEXT NOT NULL, subject TEXT NOT NULL, body_html TEXT NOT NULL, status TEXT NOT NULL,
    error TEXT NULL, voucher_id INTEGER NULL, created_at TEXT NOT NULL
);
