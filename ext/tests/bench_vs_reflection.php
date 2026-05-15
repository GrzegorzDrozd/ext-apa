<?php
// Benchmark: APA static array lookup vs Reflection attribute reading
// Simulates middleware checking permissions on a controller method

#[Attribute]
class RequireRole {
    public function __construct(public string $role) {}
}

class AccessControl {
    public static array $requirements = [];
    public static function require(string $class, string $method, string $role): void {
        self::$requirements["$class::$method"] = $role;
    }
}

// Controller with both attributes
class BenchController {
    #[RequireRole('admin')]
    public function action1() {}
    #[RequireRole('editor')]
    public function action2() {}
    #[RequireRole('admin')]
    public function action3() {}
    #[RequireRole('viewer')]
    public function action4() {}
    #[RequireRole('admin')]
    public function action5() {}
}

// If APA is loaded, also has AfterParseAction — but we add manually for fairness
if (extension_loaded('apa')) {
    // Already fired via AfterParseAction if the class had them.
    // For this bench, simulate what APA would have collected:
}
// Manually populate for the APA path (simulates what APA would do):
AccessControl::$requirements = [
    'BenchController::action1' => 'admin',
    'BenchController::action2' => 'editor',
    'BenchController::action3' => 'admin',
    'BenchController::action4' => 'viewer',
    'BenchController::action5' => 'admin',
];

$methods = ['action1', 'action2', 'action3', 'action4', 'action5'];
$iterations = 100_000;

// --- Reflection path ---
$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $method = $methods[$i % 5];
    $ref = new ReflectionMethod('BenchController', $method);
    $attrs = $ref->getAttributes(RequireRole::class);
    if ($attrs) {
        $role = $attrs[0]->newInstance()->role;
        // simulate check
        $allowed = ($role === 'admin');
    }
}
$reflection_ms = (hrtime(true) - $start) / 1_000_000;

// --- Static array path (what APA gives you) ---
$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $method = $methods[$i % 5];
    $role = AccessControl::$requirements["BenchController::$method"] ?? null;
    if ($role) {
        $allowed = ($role === 'admin');
    }
}
$array_ms = (hrtime(true) - $start) / 1_000_000;

printf("100K lookups — Reflection: %.2f ms, Static array: %.2f ms, Speedup: %.0fx\n",
    $reflection_ms, $array_ms, $reflection_ms / $array_ms);
