--TEST--
AfterParseAction passes named args correctly
--EXTENSIONS--
apa
--FILE--
<?php
$tmp = tempnam(sys_get_temp_dir(), 'apa');
file_put_contents($tmp, '<?php
class Collector {
    public static array $items = [];
    public static function add(string $cn, string $mn, ...$args): void {
        self::$items[] = ["class" => $cn, "method" => $mn, "args" => $args];
    }
}
class MyController {
    #[\AfterParseAction([Collector::class, "add"], path: "/users", method: "GET")]
    public function list() {}

    #[\AfterParseAction("Collector::add", path: "/users/{id}", method: "DELETE")]
    public function delete(int $id) {}
}
');
require $tmp;
unlink($tmp);

echo count(Collector::$items) . " routes\n";
foreach (Collector::$items as $item) {
    echo $item['class'] . '::' . $item['method'] . ' ' . json_encode($item['args']) . "\n";
}
?>
--EXPECT--
2 routes
MyController::list {"path":"\/users","method":"GET"}
MyController::delete {"path":"\/users\/{id}","method":"DELETE"}
