--TEST--
AfterParseAction: complex hierarchy with abstract, interface, trait, override, multi-callable
--EXTENSIONS--
apa
--FILE--
<?php


$tmp = tempnam(sys_get_temp_dir(), 'apa');
file_put_contents($tmp, '<?php
class A {
    public static array $log = [];
    public static function a(string $c, ?string $m, ...$args): void {
        self::$log[] = "A:$c::$m";
    }
}
class B {
    public static array $log = [];
    public static function b(string $c, ?string $m, ...$args): void {
        self::$log[] = "B:$c::$m";
    }
}

// Interface: calls A
interface Loggable {
    #[\AfterParseAction([A::class, "a"])]
    public function log(): void;
}

// Interface: same method has TWO attrs calling different things
interface Auditable {
    #[\AfterParseAction([A::class, "a"])]
    #[\AfterParseAction([B::class, "b"])]
    public function audit(): void;
}

// Trait: calls A
trait HasHealth {
    #[\AfterParseAction([A::class, "a"])]
    public function health(): string { return "ok"; }
}

// Abstract base: concrete ping calls A, abstract process calls B
abstract class AbstractService {
    #[\AfterParseAction([A::class, "a"])]
    public function ping(): string { return "pong"; }

    #[\AfterParseAction([B::class, "b"])]
    abstract public function process(): void;
}

// Full mix: abstract + 2 interfaces + trait
// log() has OWN attr calling B (interface calls A -> both fire, different callables)
// audit() has NO attr (interface has 2 attrs -> both propagate)
class FullService extends AbstractService implements Loggable, Auditable {
    use HasHealth;
    public function process(): void {}

    #[\AfterParseAction([B::class, "b"])]
    public function log(): void {}

    public function audit(): void {}

    #[\AfterParseAction([A::class, "a"])]
    public function create(): void {}
}

// Simple: abstract + 1 interface + trait
class SimpleService extends AbstractService implements Loggable {
    use HasHealth;
    public function process(): void {}
    public function log(): void {}
}

// Overrides concrete ping WITHOUT attr (intentional removal)
class OverrideService extends AbstractService {
    public function process(): void {}
    public function ping(): string { return "custom"; }
}

// No own attrs, inherits everything
class BareService extends AbstractService {
    public function process(): void {}
}
');
require $tmp;
unlink($tmp);

$all = array_merge(A::$log, B::$log);
sort($all);
echo count($all) . " actions\n";
foreach ($all as $entry) echo "$entry\n";
?>
--EXPECT--
15 actions
A:BareService::ping
A:FullService::audit
A:FullService::create
A:FullService::health
A:FullService::log
A:FullService::ping
A:SimpleService::health
A:SimpleService::log
A:SimpleService::ping
B:BareService::process
B:FullService::audit
B:FullService::log
B:FullService::process
B:OverrideService::process
B:SimpleService::process
