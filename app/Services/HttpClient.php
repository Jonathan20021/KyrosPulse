<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

/**
 * Cliente HTTP minimo basado en cURL.
 */
final class HttpClient
{
    public static function request(
        string $method,
        string $url,
        array $headers = [],
        mixed $body = null,
        int $timeout = 30
    ): array {
        $ch = curl_init();

        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADER         => false,
        ];

        $hdrs = [];
        foreach ($headers as $k => $v) {
            $hdrs[] = "$k: $v";
        }
        $opts[CURLOPT_HTTPHEADER] = $hdrs;

        if ($body !== null) {
            if (is_array($body) || is_object($body)) {
                $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
            } else {
                $opts[CURLOPT_POSTFIELDS] = (string) $body;
            }
        }

        curl_setopt_array($ch, $opts);
        $start = microtime(true);
        $response = curl_exec($ch);
        $duration = (int) ((microtime(true) - $start) * 1000);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        $decoded = null;
        if (is_string($response) && $response !== '') {
            $decoded = json_decode($response, true);
        }

        return [
            'status'      => $status,
            'success'     => $status >= 200 && $status < 300,
            'body'        => is_array($decoded) ? $decoded : ['raw' => $response],
            'raw'         => is_string($response) ? $response : '',
            'error'       => $error,
            'duration_ms' => $duration,
        ];
    }

    public static function get(string $url, array $headers = [], int $timeout = 30): array
    {
        return self::request('GET', $url, $headers, null, $timeout);
    }

    public static function post(string $url, mixed $body, array $headers = [], int $timeout = 30): array
    {
        $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';
        return self::request('POST', $url, $headers, $body, $timeout);
    }
}
