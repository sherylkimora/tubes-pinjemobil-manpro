<?php
declare(strict_types=1);

namespace Pinjemobil;

final class Request
{
    public array $params = [];   // parameter path, mis. {id}
    public ?array $user = null;  // diisi oleh middleware auth

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $headers,
        private readonly array $body,
        public readonly array $query,
        public readonly string $ip,
        public readonly string $userAgent
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        // buang prefix folder sampai "/api" agar route selalu mulai dari /auth/..., /health
        $pos = strpos($path, '/api');
        $path = $pos !== false ? substr($path, $pos + 4) : $path;
        $path = '/' . trim($path, '/');
        if ($path === '/index.php') {
            $path = '/';
        }

        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
            }
        }

        $body = [];
        $raw = file_get_contents('php://input') ?: '';
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new HttpException(400, 'Body harus berupa JSON yang valid.');
            }
            $body = $decoded;
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $headers,
            $body,
            $_GET,
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function bearerToken(): ?string
    {
        $h = $this->header('authorization');
        if ($h && preg_match('/^Bearer\s+(.+)$/i', $h, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->body;
    }
}
