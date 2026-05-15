--TEST--
AfterParseAction: interface method attributes fire for each implementation
--EXTENSIONS--
apa
--FILE--
<?php
$tmp = tempnam(sys_get_temp_dir(), 'apa');
file_put_contents($tmp, '<?php
class Collector {
    public static array $items = [];
    public static function add(string $c, ?string $m, ...$a): void {
        self::$items[] = "$c::$m";
    }
}

interface Actionable {
    #[\AfterParseAction([Collector::class, "add"])]
    public function execute(): void;
}

class TaskA implements Actionable {
    public function execute(): void {}
}

class TaskB implements Actionable {
    public function execute(): void {}
}
');
require $tmp;
unlink($tmp);

echo count(Collector::$items) . " actions\n";
sort(Collector::$items);
foreach (Collector::$items as $i) echo "$i\n";
?>
--EXPECT--
2 actions
TaskA::execute
TaskB::execute
