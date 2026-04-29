<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Router minimo con soporte de parametros, grupos y middlewares.
 *
 *   $router->get('/users/{id}', [UserController::class, 'show'])->middleware('auth');
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:mixed, middleware:array}> */
    private array $routes = [];

    private array $groupStack = [];
    private ?int $lastIndex = null;

    public function get(string $path, mixed $handler): self    { return $this->add('GET',    $path, $handler); }
    public function post(string $path, mixed $handler): self   { return $this->add('POST',   $path, $handler); }
    public function put(string $path, mixed $handler): self    { return $this->add('PUT',    $path, $handler); }
    public function patch(string $path, mixed $handler): self  { return $this->add('PATCH',  $path, $handler); }
    public function delete(string $path, mixed $handler): self { return $this->add('DELETE', $path, $handler); }
    public function any(string $path, mixed $handler): self    { return $this->add('ANY',    $path, $handler); }

    public function group(array $attrs, callable $cb): void
    {
        $this->groupStack[] = $attrs;
        $cb($this);
        array_pop($this->groupStack);
    }

    public function middleware(string|array $mw): self
    {
        if ($this->lastIndex === null) {
            return $this;
        }
        $mws = is_array($mw) ? $mw : [$mw];
        $this->routes[$this->lastIndex]['middleware'] = array_merge(
            $this->routes[$this->lastIndex]['middleware'],
            $mws
        );
        return $this;
    }

    private function add(string $method, string $path, mixed $handler): self
    {
        $prefix = '';
        $groupMw = [];
        foreach ($this->groupStack as $g) {
            if (!empty($g['prefix'])) {
                $prefix .= '/' . trim($g['prefix'], '/');
            }
            if (!empty($g['middleware'])) {
                $groupMw = array_merge($groupMw, (array) $g['middleware']);
            }
        }

        $fullPath = '/' . trim($prefix . '/' . trim($path, '/'), '/');
        $fullPath = $fullPath === '' ? '/' : $fullPath;

        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $fullPath,
            'handler'    => $handler,
            'middleware' => $groupMw,
        ];
        $this->lastIndex = array_key_last($this->routes);
        return $this;
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path   = $request->path();

        foreach ($this->routes as $route) {
            if ($route['method'] !== 'ANY' && $route['method'] !== $method) {
                continue;
            }

            $params = [];
            if ($this->matches($route['pattern'], $path, $params)) {
                $this->run($route, $request, $params);
                return;
            }
        }

        $this->notFound($request);
    }

    private function matches(string $pattern, string $path, array &$params): bool
    {
        // Convertir /users/{id}/posts/{slug} a regex
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}#',
            function ($m) {
                $name = $m[1];
                $rule = $m[2] ?? '[^/]+';
                return '(?P<' . $name . '>' . $rule . ')';
            },
            $pattern
        );
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
            foreach ($matches as $k => $v) {
                if (!is_int($k)) {
                    $params[$k] = $v;
                }
            }
            return true;
        }
        return false;
    }

    private function run(array $route, Request $request, array $params): void
    {
        // Ejecutar middlewares en cadena
        $middlewares = $route['middleware'];
        $core = function () use ($route, $request, $params) {
            $this->callHandler($route['handler'], $request, $params);
        };

        $pipeline = array_reduce(
            array_reverse($middlewares),
            function ($next, $mw) use ($request) {
                return function () use ($mw, $next, $request) {
                    $instance = $this->resolveMiddleware($mw);
                    $instance->handle($request, $next);
                };
            },
            $core
        );

        $pipeline();
    }

    private function resolveMiddleware(string $alias): object
    {
        $map = [
            'auth'      => \App\Middlewares\AuthMiddleware::class,
            'guest'     => \App\Middlewares\GuestMiddleware::class,
            'tenant'    => \App\Middlewares\TenantMiddleware::class,
            'csrf'      => \App\Middlewares\CsrfMiddleware::class,
            'role'      => \App\Middlewares\RoleMiddleware::class,
            'rate'      => \App\Middlewares\RateLimitMiddleware::class,
            'super'     => \App\Middlewares\SuperAdminMiddleware::class,
            'verified'  => \App\Middlewares\VerifiedMiddleware::class,
        ];

        if (str_contains($alias, ':')) {
            [$key, $arg] = explode(':', $alias, 2);
            $cls = $map[$key] ?? $alias;
            return new $cls($arg);
        }

        $cls = $map[$alias] ?? $alias;
        return new $cls();
    }

    private function callHandler(mixed $handler, Request $request, array $params): void
    {
        if (is_callable($handler)) {
            $handler($request, $params);
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $controller = new $class();
            $controller->$method($request, $params);
            return;
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler);
            $controller = new $class();
            $controller->$method($request, $params);
            return;
        }

        throw new \RuntimeException('Handler de ruta invalido.');
    }

    private function notFound(Request $request): void
    {
        if ($request->expectsJson()) {
            Response::json(['error' => 'Not Found'], 404);
            return;
        }
        http_response_code(404);
        try {
            echo View::render('errors.404', [], 'layouts.error');
        } catch (\Throwable) {
            echo '<h1>404 - Pagina no encontrada</h1>';
        }
    }
}
