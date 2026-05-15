<?php
/**
 * Integration test: a mini router built entirely with #[\AfterParseAction].
 * Run with PHP built-in server to verify it works across HTTP requests.
 */

class Router {
    public static array $routes = [];

    public static function add(string $class, string $method, string $path, string $httpMethod, ...$extra): void {
        self::$routes[] = [
            'class' => $class,
            'method' => $method,
            'path' => $path,
            'httpMethod' => $httpMethod,
            'extra' => $extra,
        ];
    }

    public static function dispatch(string $requestPath, string $requestMethod): ?array {
        foreach (self::$routes as $route) {
            if ($route['path'] === $requestPath && $route['httpMethod'] === $requestMethod) {
                return $route;
            }
        }
        return null;
    }
}
