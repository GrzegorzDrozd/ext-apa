--TEST--
AfterParseAction: traits fire per-class, abstract skipped, inherited methods fire with child name
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

trait HasAction {
    #[\AfterParseAction([Collector::class, "add"])]
    public function traitMethod() {}
}

abstract class AbstractParent {
    #[\AfterParseAction([Collector::class, "add"])]
    public function parentMethod() {}
}

class ChildA extends AbstractParent {
    use HasAction;
}

class ChildB extends AbstractParent {
    use HasAction;
}

class ChildOverride extends AbstractParent {
    public function parentMethod() {} // override removes attr — no action
}
');
require $tmp;
unlink($tmp);

echo count(Collector::$items) . " actions\n";
sort(Collector::$items);
foreach (Collector::$items as $i) echo "$i\n";
?>
--EXPECT--
4 actions
ChildA::parentMethod
ChildA::traitMethod
ChildB::parentMethod
ChildB::traitMethod
