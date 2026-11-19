-- Gutschein-Webapp: MySQL 8 / MariaDB 10.6+
-- Beträge immer in Rappen (INT). Zeitstempel in Lokalzeit Europe/Zurich, von PHP gesetzt.
-- Bei Änderungen auch database/schema.sqlite.sql nachführen.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
    name        VARCHAR(64)  NOT NULL PRIMARY KEY,
    value       TEXT         NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            VARCHAR(20)  NOT NULL DEFAULT 'staff',   -- admin | staff
    active          TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at   DATETIME     NULL,
    created_at      DATETIME     NOT NULL,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(190) NOT NULL,
    ip          VARCHAR(45)  NOT NULL,
    created_at  DATETIME     NOT NULL,
    KEY idx_login_attempts_lookup (email, created_at),
    KEY idx_login_attempts_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type            VARCHAR(20)  NOT NULL,                  -- value | service
    title           VARCHAR(160) NOT NULL,
    description     TEXT         NULL,
    amount_cents    INT UNSIGNED NULL,                      -- fixer Betrag bzw. Preis, NULL = frei wählbar
    min_cents       INT UNSIGNED NULL,
    max_cents       INT UNSIGNED NULL,
    validity_months SMALLINT UNSIGNED NULL,                 -- NULL = Standard aus Einstellungen
    active          TINYINT(1)   NOT NULL DEFAULT 1,
    sort            INT          NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL,
    updated_at      DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id       CHAR(32)     NOT NULL,
    product_id      INT UNSIGNED NOT NULL,
    status          VARCHAR(20)  NOT NULL DEFAULT 'pending', -- pending | paid | cancelled
    amount_cents    INT UNSIGNED NOT NULL,
    currency        CHAR(3)      NOT NULL DEFAULT 'CHF',
    buyer_name      VARCHAR(160) NOT NULL,
    buyer_email     VARCHAR(190) NOT NULL,
    recipient_name  VARCHAR(160) NULL,
    recipient_email VARCHAR(190) NULL,
    message         VARCHAR(500) NULL,
    deliver_to      VARCHAR(20)  NOT NULL DEFAULT 'buyer',  -- buyer | recipient
    provider        VARCHAR(20)  NOT NULL,
    provider_ref    VARCHAR(190) NULL,
    paid_at         DATETIME     NULL,
    created_at      DATETIME     NOT NULL,
    updated_at      DATETIME     NOT NULL,
    UNIQUE KEY uq_orders_public_id (public_id),
    UNIQUE KEY uq_orders_provider_ref (provider, provider_ref),
    KEY idx_orders_status (status, created_at),
    CONSTRAINT fk_orders_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vouchers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            CHAR(12)     NOT NULL,                  -- normalisiert, ohne Bindestriche
    token           CHAR(32)     NOT NULL,                  -- für die öffentliche URL /g/{token}
    order_id        INT UNSIGNED NULL,
    product_id      INT UNSIGNED NULL,
    type            VARCHAR(20)  NOT NULL,                  -- value | service
    title           VARCHAR(160) NOT NULL,
    service_description TEXT     NULL,
    initial_cents   INT UNSIGNED NOT NULL,
    balance_cents   INT UNSIGNED NOT NULL,                  -- Cache, Quelle ist voucher_transactions
    currency        CHAR(3)      NOT NULL DEFAULT 'CHF',
    status          VARCHAR(20)  NOT NULL DEFAULT 'active', -- active | redeemed | void (abgelaufen wird berechnet)
    recipient_name  VARCHAR(160) NULL,
    message         VARCHAR(500) NULL,
    buyer_name      VARCHAR(160) NULL,
    buyer_email     VARCHAR(190) NULL,
    channel         VARCHAR(20)  NOT NULL DEFAULT 'online', -- online | manual
    valid_until     DATE         NOT NULL,
    created_by      INT UNSIGNED NULL,
    created_at      DATETIME     NOT NULL,
    updated_at      DATETIME     NOT NULL,
    UNIQUE KEY uq_vouchers_code (code),
    UNIQUE KEY uq_vouchers_token (token),
    UNIQUE KEY uq_vouchers_order (order_id),
    KEY idx_vouchers_status (status, valid_until),
    CONSTRAINT fk_vouchers_order FOREIGN KEY (order_id) REFERENCES orders (id),
    CONSTRAINT fk_vouchers_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
    CONSTRAINT fk_vouchers_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS voucher_transactions (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    voucher_id          INT UNSIGNED NOT NULL,
    type                VARCHAR(20)  NOT NULL,              -- issue | redeem | adjust | void
    amount_cents        INT          NOT NULL,              -- vorzeichenbehaftet: + Gutschrift, − Abbuchung
    balance_after_cents INT UNSIGNED NOT NULL,
    note                VARCHAR(255) NULL,
    user_id             INT UNSIGNED NULL,
    created_at          DATETIME     NOT NULL,
    KEY idx_tx_voucher (voucher_id, id),
    KEY idx_tx_created (created_at),
    CONSTRAINT fk_tx_voucher FOREIGN KEY (voucher_id) REFERENCES vouchers (id),
    CONSTRAINT fk_tx_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhook_events (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider    VARCHAR(20)  NOT NULL,
    event_id    VARCHAR(190) NOT NULL,
    type        VARCHAR(120) NOT NULL,
    received_at DATETIME     NOT NULL,
    UNIQUE KEY uq_webhook_event (provider, event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mail_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient   VARCHAR(190) NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    body_html   MEDIUMTEXT   NOT NULL,
    status      VARCHAR(20)  NOT NULL,                      -- sent | logged | failed
    error       VARCHAR(500) NULL,
    voucher_id  INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL,
    KEY idx_mail_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
