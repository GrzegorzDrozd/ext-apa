--TEST--
AfterParseAction: extra named args collected by variadic parameter
--EXTENSIONS--
apa
--FILE--
<?php
$tmp = tempnam(sys_get_temp_dir(), 'apa');
file_put_contents($tmp, '<?php
class Router {
    public static array $routes = [];
    public static function add(string $class, string $method, string $path, string $httpMethod, ...$args): void {
        self::$routes[] = ["target" => "$class::$method", "path" => $path, "httpMethod" => $httpMethod, "extra" => $args];
    }
}
class UserController {
    #[\AfterParseAction([Router::class, "add"], path: "/users", httpMethod: "GET", middleware: "auth", cache: 3600)]
    public function list() {}
}
');
require $tmp;
unlink($tmp);

$r = Router::$routes[0];
echo $r['target'] . "\n";
echo $r['path'] . " " . $r['httpMethod'] . "\n";
echo json_encode($r['extra']) . "\n";
?>
--EXPECT--
UserController::list
/users GET
{"middleware":"auth","cache":3600}
