<?php
/**
 * Entry point for PHP built-in server.
 * Loads router + controllers, dispatches request, returns JSON.
 */

require __DIR__ . '/router.php';
require __DIR__ . '/controllers.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

header('Content-Type: application/json');

if ($path === '/routes') {
    echo json_encode(['routes' => Router::$routes], JSON_PRETTY_PRINT);
    exit;
}

$route = Router::dispatch($path, $method);
if ($route) {
    $controller = new $route['class']();
    $result = $controller->{$route['method']}();
    echo json_encode($result);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Not found', 'path' => $path, 'method' => $method]);
}
