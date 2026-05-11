<?php
declare(strict_types=1);

namespace App\Services;

/**
 * TOTP (RFC 6238) puro PHP — sin dependencias.
 *
 * Compatible con Google Authenticator, Authy, 1Password, Microsoft Authenticator
 * y cualquier app TOTP estandar.
 *
 *   - Secret: base32, 20 bytes (160 bits), formato sin padding.
 *   - Algorithm: HMAC-SHA1 (universal).
 *   - Digits: 6.
 *   - Period: 30 segundos.
 *   - Verify window: codigo actual +/- 1 step (90s totales) para tolerar drift.
 */
final class TotpService
{
    public const DIGITS = 6;
    public const PERIOD = 30;
    public const WINDOW = 1; // ±1 step (30s) cada lado

    /**
     * Genera un nuevo secret base32 de 32 caracteres (160 bits).
     */
    public static function generateSecret(int $bytes = 20): string
    {
        $raw = random_bytes($bytes);
        return self::base32Encode($raw);
    }

    /**
     * Construye el URI otpauth:// para el QR.
     *
     *   otpauth://totp/Kyros%20Pulse:user@example.com?secret=XYZ&issuer=Kyros%20Pulse
     *
     * Se renderiza como QR; Google Auth/Authy/etc lo escanean directo.
     */
    public static function provisioningUri(string $secret, string $accountName, string $issuer = 'Kyros Pulse'): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($accountName);
        $params = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ]);
        return "otpauth://totp/$label?$params";
    }

    /**
     * URL publica para renderizar el QR sin dependencias JS.
     * Usa el endpoint /tools/qr o un servicio gratuito tipo QR Server.
     * Como fallback usamos qrserver.com (terms: gratis, no rate limit prohibitivo).
     */
    public static function qrImageUrl(string $provisioningUri, int $size = 220): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?'
             . http_build_query(['data' => $provisioningUri, 'size' => $size . 'x' . $size, 'margin' => 0]);
    }

    /**
     * Calcula el codigo TOTP para un secret + timestamp dado.
     * Usado para verificar y para tests.
     */
    public static function code(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $counter = intdiv($timestamp, self::PERIOD);
        return self::hotp($secret, $counter);
    }

    /**
     * Verifica un codigo (6 digitos) contra un secret. Tolerancia ±WINDOW steps.
     */
    public static function verify(string $secret, string $userCode, ?int $timestamp = null): bool
    {
        $userCode = preg_replace('/\s+/', '', $userCode) ?? '';
        if (!preg_match('/^\d{6}$/', $userCode)) return false;

        $timestamp = $timestamp ?? time();
        $baseCounter = intdiv($timestamp, self::PERIOD);

        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            $expected = self::hotp($secret, $baseCounter + $i);
            if (hash_equals($expected, $userCode)) return true;
        }
        return false;
    }

    // ---- HOTP (RFC 4226) ----
    private static function hotp(string $base32Secret, int $counter): string
    {
        $key = self::base32Decode($base32Secret);
        // Big-endian 64-bit counter
        $bin = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $bin, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $code = ((ord($hash[$offset])   & 0x7f) << 24)
              | ((ord($hash[$offset+1]) & 0xff) << 16)
              | ((ord($hash[$offset+2]) & 0xff) <<  8)
              |  (ord($hash[$offset+3]) & 0xff);
        $mod = $code % (10 ** self::DIGITS);
        return str_pad((string) $mod, self::DIGITS, '0', STR_PAD_LEFT);
    }

    // ---- Base32 (RFC 4648 sin padding) ----
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function base32Encode(string $bytes): string
    {
        if ($bytes === '') return '';
        $out = '';
        $buf = 0; $bits = 0;
        for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
            $buf = ($buf << 8) | ord($bytes[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $out .= self::ALPHABET[($buf >> $bits) & 0x1f];
            }
        }
        if ($bits > 0) {
            $out .= self::ALPHABET[($buf << (5 - $bits)) & 0x1f];
        }
        return $out;
    }

    public static function base32Decode(string $s): string
    {
        $s = strtoupper(preg_replace('/=+$/', '', preg_replace('/\s+/', '', $s) ?? '') ?? '');
        if ($s === '') return '';
        $map = array_flip(str_split(self::ALPHABET));
        $out = '';
        $buf = 0; $bits = 0;
        foreach (str_split($s) as $c) {
            if (!isset($map[$c])) continue;
            $buf = ($buf << 5) | $map[$c];
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $out .= chr(($buf >> $bits) & 0xff);
            }
        }
        return $out;
    }
}
