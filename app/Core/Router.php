<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Router - Fast regex-based HTTP router
 */
class Router
{
    private array $routes     = [];
    private array $middleware = [];
    private string $prefix    = '';
    private array $groupMiddleware = [];

    public function get(string $path, array|callable $action, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $action, $middleware);
    }

    public function post(string $path, array|callable $action, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $action, $middleware);
    }

    public function put(string $path, array|callable $action, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $action, $middleware);
    }

    public function patch(string $path, array|callable $action, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $action, $middleware);
    }

    public function delete(string $path, array|callable $action, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $action, $middleware);
    }

    public function group(array $options, callable $callback): void
    {
        $oldPrefix = $this->prefix;
        $oldMiddleware = $this->groupMiddleware;

        $this->prefix .= ($options['prefix'] ?? '');
        $this->groupMiddleware = array_merge($this->groupMiddleware, $options['middleware'] ?? []);

        $callback($this);

        $this->prefix = $oldPrefix;
        $this->groupMiddleware = $oldMiddleware;
    }

    private function addRoute(string $method, string $path, array|callable $action, array $middleware): void
    {
        $fullPath = $this->prefix . $path;
        // {path} یا {file} می‌تونه شامل / باشه (multi-segment)
        $pattern = preg_replace('/\{(path|file|slug_full)\}/', '(.+)', $fullPath);
        $pattern  = preg_replace('/\{([a-zA-Z_]+)\}/', '([^/]+)', $pattern);
        $pattern  = '#^' . $pattern . '$#';

        preg_match_all('/\{([a-zA-Z_]+)\}/', $fullPath, $matches);

        $this->routes[] = [
            'method'     => $method,
            'path'       => $fullPath,
            'pattern'    => $pattern,
            'params'     => $matches[1] ?? [],
            'action'     => $action,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $uri    = $request->path();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            if (preg_match($route['pattern'], $uri, $matches)) {
                array_shift($matches);
                $params = array_combine($route['params'], $matches) ?: [];
                $request->setParams($params);

                $this->runMiddleware($route['middleware'], $request, function() use ($route, $request, $params) {
                    $this->callAction($route['action'], $request, $params);
                });
                return;
            }
        }

        $this->notFound($request);
    }

    private function runMiddleware(array $middleware, Request $request, callable $next): void
    {
        if (empty($middleware)) {
            $next();
            return;
        }

        $mw = array_shift($middleware);
        $instance = new $mw();
        $instance->handle($request, function() use ($middleware, $request, $next) {
            $this->runMiddleware($middleware, $request, $next);
        });
    }

    private function callAction(array|callable $action, Request $request, array $params): void
    {
        if (is_callable($action)) {
            echo $action($request, $params);
            return;
        }

        [$controllerClass, $method] = $action;
        $controller = new $controllerClass();
        $controller->$method($request, $params);
    }

    private function notFound(Request $request): void
    {
        if (str_starts_with($request->path(), '/api/')) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
        } else {
            http_response_code(404);
            include VIEWS_PATH . '/errors/404.php';
        }
    }
}
