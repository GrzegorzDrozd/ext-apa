<?php
// Benchmark: overhead of ancestor traversal.
// Creates classes with deep hierarchy + multiple interfaces.

$dir = sys_get_temp_dir() . '/apa_bench_h_' . getmypid();
mkdir($dir);
$mode = $argv[1] ?? 'no_attrs';

// Create 5 interfaces with 2 methods each
for ($i = 0; $i < 5; $i++) {
    $code = "<?php\ninterface Iface$i {\n";
    for ($j = 1; $j <= 2; $j++) {
        if ($mode === 'with_attrs') {
            $code .= "    #[\\AfterParseAction([BC::class, 'c'], idx: $j)]\n";
        }
        $code .= "    public function iface{$i}m$j(): void;\n";
    }
    $code .= "}\n";
    file_put_contents("$dir/Iface$i.php", $code);
}

// Create abstract base with 3 methods (2 abstract, 1 concrete)
$base = "<?php\nabstract class Base {\n";
if ($mode === 'with_attrs') {
    $base .= "    #[\\AfterParseAction([BC::class, 'c'])]\n";
}
$base .= "    abstract public function baseAbstract(): void;\n";
if ($mode === 'with_attrs') {
    $base .= "    #[\\AfterParseAction([BC::class, 'c'])]\n";
}
$base .= "    public function baseConcrete(): void {}\n";
$base .= "}\n";
file_put_contents("$dir/Base.php", $base);

// Create 200 concrete classes extending Base, implementing 2-3 interfaces each
for ($i = 0; $i < 200; $i++) {
    $ifaces = [];
    for ($k = 0; $k < 3; $k++) {
        $ifaces[] = "Iface" . (($i + $k) % 5);
    }
    $impl = "<?php\nclass Concrete$i extends Base implements " . implode(', ', array_unique($ifaces)) . " {\n";
    $impl .= "    public function baseAbstract(): void {}\n";
    // Implement all interface methods
    $seen = [];
    foreach (array_unique($ifaces) as $iname) {
        $idx = (int)substr($iname, 5);
        for ($j = 1; $j <= 2; $j++) {
            $mname = "iface{$idx}m$j";
            if (!isset($seen[$mname])) {
                $impl .= "    public function $mname(): void {}\n";
                $seen[$mname] = true;
            }
        }
    }
    if ($mode === 'with_attrs') {
        $impl .= "    #[\\AfterParseAction([BC::class, 'c'])]\n";
    }
    $impl .= "    public function own(): void {}\n";
    $impl .= "}\n";
    file_put_contents("$dir/Concrete$i.php", $impl);
}

// Collector
if ($mode === 'with_attrs') {
    file_put_contents("$dir/BC.php", "<?php\nclass BC { public static int \$n=0; public static function c(...\$a):void{ self::\$n++; } }\n");
    require "$dir/BC.php";
}

// Load everything
$start = hrtime(true);
for ($i = 0; $i < 5; $i++) require "$dir/Iface$i.php";
require "$dir/Base.php";
for ($i = 0; $i < 200; $i++) require "$dir/Concrete$i.php";
$elapsed = (hrtime(true) - $start) / 1_000_000;

printf("200 classes + 5 ifaces + abstract base (%s): %.2f ms", $mode, $elapsed);
if ($mode === 'with_attrs') printf(" (%d actions)", BC::$n);
echo "\n";

array_map('unlink', glob("$dir/*.php"));
rmdir($dir);
