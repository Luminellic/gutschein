<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function redirect(string $path, int $status = 303): never
    {
        $location = preg_match('#^https?://#', $path) ? $path : url($path);
        header('Location: ' . $location, true, $status);
        exit;
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function text(string $body, int $status = 200, string $type = 'text/plain'): never
    {
        http_response_code($status);
        header("Content-Type: $type; charset=utf-8");
        echo $body;
        exit;
    }

    public static function error(int $status, string $message): never
    {
        http_response_code($status);
        echo View::render('error', ['title' => 'Fehler ' . $status, 'status' => $status, 'message' => $message]);
        exit;
    }

    /** @param list<list<string|int|null>> $rows */
    public static function csv(string $filename, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM, damit Excel UTF-8 erkennt
        foreach ($rows as $row) {
            fputcsv($out, $row, ';', '"', '');
        }
        fclose($out);
        exit;
    }
}
