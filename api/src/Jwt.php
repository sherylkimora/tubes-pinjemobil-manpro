<?php
declare(strict_types=1);

namespace Pinjemobil;

/** JWT HS256 sederhana (tanpa composer). */
final class Jwt
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttl,
        private readonly string $issuer
    ) {
        if (strlen($secret) < 32) {
            throw new \RuntimeException('JWT_SECRET minimal 32 karakter. Cek file .env.');
        }
    }

    public function encode(array $claims): string
    {
        $now = time();
        $payload = $claims + ['iss' => $this->issuer, 'iat' => $now, 'exp' => $now + $this->ttl];
        $head = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = self::b64(json_encode($payload));
        $sig  = self::b64(hash_hmac('sha256', "$head.$body", $this->secret, true));
        return "$head.$body.$sig";
    }

    /** @throws HttpException 401 bila token tidak valid / kedaluwarsa */
    public function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new HttpException(401, 'Token tidak valid.');
        }
        [$head, $body, $sig] = $parts;

        $header = json_decode(self::unb64($head), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256') {
            throw new HttpException(401, 'Token tidak valid.');
        }
        $expected = self::b64(hash_hmac('sha256', "$head.$body", $this->secret, true));
        if (!hash_equals($expected, $sig)) {
            throw new HttpException(401, 'Token tidak valid.');
        }
        $payload = json_decode(self::unb64($body), true);
        if (!is_array($payload) || ($payload['iss'] ?? '') !== $this->issuer) {
            throw new HttpException(401, 'Token tidak valid.');
        }
        if (($payload['exp'] ?? 0) < time()) {
            throw new HttpException(401, 'Token sudah kedaluwarsa, silakan login ulang.');
        }
        return $payload;
    }

    public function ttl(): int
    {
        return $this->ttl;
    }

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private static function unb64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'));
    }
}
