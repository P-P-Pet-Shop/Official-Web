<?php
declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];
    private array $groupMiddlewares = [];
    private string $groupPrefix = '';

    public function group(string $prefix, array $middlewares, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddlewares = $this->groupMiddlewares;

        $this->groupPrefix = rtrim($previousPrefix . '/' . trim($prefix, '/'), '/');
        $this->groupMiddlewares = array_merge($previousMiddlewares, $middlewares);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddlewares = $previousMiddlewares;
    }

    public function add(string $method, string $path, mixed $handler, array $middlewares = []): void
    {
        $fullPath = rtrim($this->groupPrefix . '/' . trim($path, '/'), '/') ?: '/';
        $allMiddlewares = array_merge($this->groupMiddlewares, $middlewares);

        // Convert {param} placeholders into named regex groups
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $fullPath);
        $regex = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $fullPath,
            'regex' => $regex,
            'handler' => $handler,
            'middlewares' => $allMiddlewares,
        ];
    }

    public function get(string $path, mixed $handler, array $middlewares = []): void
    {
        $this->add('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, mixed $handler, array $middlewares = []): void
    {
        $this->add('POST', $path, $handler, $middlewares);
    }

    public function patch(string $path, mixed $handler, array $middlewares = []): void
    {
        $this->add('PATCH', $path, $handler, $middlewares);
    }

    public function put(string $path, mixed $handler, array $middlewares = []): void
    {
        $this->add('PUT', $path, $handler, $middlewares);
    }

    public function delete(string $path, mixed $handler, array $middlewares = []): void
    {
        $this->add('DELETE', $path, $handler, $middlewares);
    }

    public function dispatch(Request $request): Response
    {
        $reqMethod = $request->getMethod();
        $reqPath = $request->getPath();

        // Handle CORS OPTIONS pre-flight
        if ($reqMethod === 'OPTIONS') {
            return new Response('', 204);
        }

        $matchedRoute = null;
        $params = [];
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $reqPath, $matches)) {
                $allowedMethods[] = $route['method'];
                if ($route['method'] === $reqMethod) {
                    $matchedRoute = $route;
                    foreach ($matches as $k => $v) {
                        if (!is_int($k)) {
                            $params[$k] = urldecode($v);
                        }
                    }
                    break;
                }
            }
        }

        if ($matchedRoute === null) {
            if (!empty($allowedMethods)) {
                return Response::error('Method not allowed', 405, ['allowed' => array_unique($allowedMethods)]);
            }
            return Response::error("Endpoint not found: {$reqPath}", 404);
        }

        $request->setRouteParams($params);

        // Execute Middlewares
        foreach ($matchedRoute['middlewares'] as $middlewareClass) {
            $middleware = new $middlewareClass();
            $res = $middleware->handle($request);
            if ($res instanceof Response) {
                return $res;
            }
        }

        // Execute Handler
        $handler = $matchedRoute['handler'];
        if (is_callable($handler)) {
            $response = $handler($request, $params);
        } elseif (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $instance = new $class();
            $response = $instance->$method($request, $params);
        } else {
            return Response::error('Invalid route handler configuration', 500);
        }

        if ($response instanceof Response) {
            return $response;
        }

        return Response::json($response);
    }
}
