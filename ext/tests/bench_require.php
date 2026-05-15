<?php
$dir = sys_get_temp_dir() . '/apa_bench_' . getmypid();
mkdir($dir);
$mode = $argv[1] ?? 'no_attrs';
for ($i = 0; $i < 200; $i++) {
    $code = "<?php\nclass BenchClass$i {\n";
    for ($j = 1; $j <= 5; $j++) {
        if ($mode === 'with_attrs') {
            $code .= "    #[\\AfterParseAction([BenchCollector::class, 'collect'], idx: $j)]\n";
        }
        $code .= "    public function m$j(): int { return $j; }\n";
    }
    $code .= "}\n";
    file_put_contents("$dir/BenchClass$i.php", $code);
}
if ($mode === 'with_attrs') {
    file_put_contents("$dir/BenchCollector.php", "<?php\nclass BenchCollector {\n    public static int \$count = 0;\n    public static function collect(string \$c, ?string \$m, ...\$a): void { self::\$count++; }\n}\n");
    require "$dir/BenchCollector.php";
}
$start = hrtime(true);
for ($i = 0; $i < 200; $i++) { require "$dir/BenchClass$i.php"; }
$elapsed = (hrtime(true) - $start) / 1_000_000;
printf("200 requires (%s): %.2f ms", $mode, $elapsed);
if ($mode === 'with_attrs') { printf(" (%d actions)", BenchCollector::$count); }
echo "\n";
array_map('unlink', glob("$dir/*.php"));
rmdir($dir);
