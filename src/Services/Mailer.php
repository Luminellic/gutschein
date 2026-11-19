<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\View;
use App\Support\Code;
use App\Support\Money;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Mailversand mit Protokoll.
 * MAIL_DRIVER=log  → nichts verschicken, nur im Mail-Protokoll ablegen (Demo, lokal)
 * MAIL_DRIVER=smtp → via SMTP (MAIL_HOST, MAIL_PORT, MAIL_USER, MAIL_PASS, MAIL_ENCRYPTION)
 * MAIL_DRIVER=mail → PHP mail() des Hostings
 */
class Mailer
{
    public function __construct(
        private readonly Database $db,
        private readonly Settings $settings,
        private readonly string $driver = 'log',
        /** @var array<string, string> */
        private readonly array $smtp = [],
        private readonly string $fromAddress = 'noreply@example.ch',
    ) {
    }

    public static function fromEnv(Database $db, Settings $settings): self
    {
        return new self($db, $settings, (string) Env::get('MAIL_DRIVER', 'log'), [
            'host' => (string) Env::get('MAIL_HOST', ''),
            'port' => (string) Env::get('MAIL_PORT', '587'),
            'user' => (string) Env::get('MAIL_USER', ''),
            'pass' => (string) Env::get('MAIL_PASS', ''),
            'encryption' => (string) Env::get('MAIL_ENCRYPTION', 'tls'),
        ], (string) Env::get('MAIL_FROM', 'noreply@example.ch'));
    }

    /** @param array<string, mixed> $voucher @param array<string, mixed>|null $order */
    public function sendVoucher(string $to, array $voucher, ?array $order): bool
    {
        $business = $this->settings->get('business_name');
        $forRecipient = $order && $order['deliver_to'] === 'recipient' && $to === $order['recipient_email'];
        $subject = $forRecipient
            ? 'Ein Gutschein von ' . ($order['buyer_name'] ?? $business) . ' für Sie'
            : 'Ihr Gutschein von ' . $business;

        $html = View::partial('mail/voucher', [
            'voucher' => $voucher,
            'order' => $order,
            'forRecipient' => $forRecipient,
            'settings' => $this->settings,
        ]);
        $text = $this->voucherText($voucher, $forRecipient, $order);
        return $this->send($to, $subject, $html, $text, (int) $voucher['id']);
    }

    /** @param array<string, mixed> $voucher @param array<string, mixed> $order */
    public function sendPurchaseConfirmation(string $to, array $voucher, array $order): bool
    {
        $html = View::partial('mail/confirmation', [
            'voucher' => $voucher,
            'order' => $order,
            'settings' => $this->settings,
        ]);
        $text = "Vielen Dank für Ihren Kauf.\n\nDer Gutschein über " . Money::format((int) $voucher['initial_cents'])
            . ' wurde an ' . $order['recipient_email'] . " gesendet.\n\nGutschein ansehen: " . absolute_url('/g/' . $voucher['token']);
        return $this->send($to, 'Kaufbestätigung: Gutschein von ' . $this->settings->get('business_name'), $html, $text, (int) $voucher['id']);
    }

    public function send(string $to, string $subject, string $html, string $text, ?int $voucherId = null): bool
    {
        $status = 'logged';
        $error = null;

        if ($this->driver !== 'log') {
            try {
                $mail = new PHPMailer(true);
                $mail->CharSet = PHPMailer::CHARSET_UTF8;
                if ($this->driver === 'smtp') {
                    $mail->isSMTP();
                    $mail->Host = $this->smtp['host'];
                    $mail->Port = (int) $this->smtp['port'];
                    $mail->SMTPAuth = $this->smtp['user'] !== '';
                    $mail->Username = $this->smtp['user'];
                    $mail->Password = $this->smtp['pass'];
                    $mail->SMTPSecure = $this->smtp['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                }
                $fromName = $this->settings->get('mail_from_name') ?: $this->settings->get('business_name');
                $mail->setFrom($this->fromAddress, $fromName);
                $mail->addReplyTo($this->settings->get('business_email'), $this->settings->get('business_name'));
                $mail->addAddress($to);
                $mail->Subject = $subject;
                $mail->isHTML(true);
                $mail->Body = $html;
                $mail->AltBody = $text;
                $mail->send();
                $status = 'sent';
            } catch (MailException $e) {
                $status = 'failed';
                $error = mb_substr($e->getMessage(), 0, 500);
            }
        }

        $this->db->insert('mail_log', [
            'recipient' => $to,
            'subject' => $subject,
            'body_html' => $html,
            'status' => $status,
            'error' => $error,
            'voucher_id' => $voucherId,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ]);
        return $status !== 'failed';
    }

    /** @param array<string, mixed> $voucher @param array<string, mixed>|null $order */
    private function voucherText(array $voucher, bool $forRecipient, ?array $order): string
    {
        $lines = [];
        $lines[] = $forRecipient
            ? ($order['buyer_name'] ?? '') . ' schenkt Ihnen einen Gutschein von ' . $this->settings->get('business_name') . '.'
            : 'Vielen Dank für Ihren Kauf. Hier ist Ihr Gutschein von ' . $this->settings->get('business_name') . '.';
        $lines[] = '';
        $lines[] = $voucher['title'] . ': ' . Money::format((int) $voucher['initial_cents']);
        $lines[] = 'Code: ' . Code::format((string) $voucher['code']);
        $lines[] = 'Gültig bis: ' . date_ch((string) $voucher['valid_until']);
        if (!empty($voucher['message'])) {
            $lines[] = '';
            $lines[] = (string) $voucher['message'];
        }
        $lines[] = '';
        $lines[] = 'Gutschein ansehen und ausdrucken: ' . absolute_url('/g/' . $voucher['token']);
        return implode("\n", $lines);
    }
}
