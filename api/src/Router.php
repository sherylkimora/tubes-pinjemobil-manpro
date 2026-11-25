<?php
declare(strict_types=1);

namespace Pinjemobil;

final class Route
{
    public array $middleware = [];

    public function __construct(
        public readonly string $method,
        public readonly string $regex,
        public readonly mixed $handler
    ) {
    }

    /** Wajib login. */
    public function auth(): self
    {
        $this->middleware[] = ['auth'];
        return $this;
    }

    /** Wajib login + salah satu role. */
    public function roles(string ...$roles): self
    {
        $this->middleware[] = ['auth'];
        $this->middleware[] = ['roles', $roles];
        return $this;
    }

    /** Wajib login + punya permission (lihat config/permissions.php). */
    public function can(string $permission): self
    {
        $this->middleware[] = ['auth'];
        $this->middleware[] = ['can', $permission];
        return $this;
    }
}

final class Router
{
    /** @var Route[] */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): Route
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', rtrim($pattern, '/') ?: '/') . '/?$#';
        return $this->routes[] = new Route($method, $regex, $handler);
    }

    public function get(string $p, callable $h): Route { return $this->add('GET', $p, $h); }
    public function post(string $p, callable $h): Route { return $this->add('POST', $p, $h); }
    public function patch(string $p, callable $h): Route { return $this->add('PATCH', $p, $h); }
    public function delete(string $p, callable $h): Route { return $this->add('DELETE', $p, $h); }

    public function dispatch(Request $req, Middleware\Auth $auth): void
    {
        $pathMatched = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route->regex, $req->path, $m)) {
                continue;
            }
            $pathMatched = true;
            if ($route->method !== $req->method) {
                continue;
            }
            $req->params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);

            foreach ($route->middleware as $mw) {
                match ($mw[0]) {
                    'auth'  => $auth->authenticate($req),
                    'roles' => $auth->requireRoles($req, $mw[1]),
                    'can'   => $auth->requirePermission($req, $mw[1]),
                };
            }
            ($route->handler)($req);
            return;
        }
        throw $pathMatched
            ? new HttpException(405, 'Method tidak diizinkan.')
            : new HttpException(404, 'Endpoint tidak ditemukan.');
    }
}
