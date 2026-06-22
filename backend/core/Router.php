<?php
declare(strict_types=1);

/**
 * Router — maps HTTP method + URI path to controller actions.
 */
class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    /** @var array<callable> */
    private array $middleware = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function put(string $path, callable $handler): void
    {
        $this->routes['PUT'][$path] = $handler;
    }

    public function patch(string $path, callable $handler): void
    {
        $this->routes['PATCH'][$path] = $handler;
    }

    public function delete(string $path, callable $handler): void
    {
        $this->routes['DELETE'][$path] = $handler;
    }

    /** Dispatches the current request against registered routes. */
    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri    = '/' . trim($uri, '/');

        // Strip a /api prefix if routed through a sub-directory
        $uri = preg_replace('#^/api#', '', $uri) ?: '/';
        $uri = '/' . ltrim($uri, '/');

        $methodRoutes = $this->routes[$method] ?? [];

        foreach ($methodRoutes as $pattern => $handler) {
            $regex  = $this->toRegex($pattern);
            $params = [];

            if (preg_match($regex, $uri, $matches)) {
                // Collect named captures
                foreach ($matches as $k => $v) {
                    if (is_string($k)) {
                        $params[$k] = $v;
                    }
                }
                $handler($params);
                return;
            }
        }

        Response::error('Route not found.', 404);
    }

    /** Converts :param placeholders to named regex groups. */
    private function toRegex(string $pattern): string
    {
        $regex = preg_replace('#:([a-zA-Z_]+)#', '(?P<$1>[^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }
}
