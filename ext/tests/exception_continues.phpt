--TEST--
AfterParseAction: exception in one action does not prevent others from firing
--EXTENSIONS--
apa
--FILE--
<?php
$tmp = tempnam(sys_get_temp_dir(), 'apa');
file_put_contents($tmp, '<?php
class Logger {
    public static array $log = [];
    public static function fail(string $c, ?string $m, ...$a): void {
        self::$log[] = "fail";
        throw new RuntimeException("boom");
    }
    public static function succeed(string $c, ?string $m, ...$a): void {
        self::$log[] = "succeed";
    }
}
class Target {
    #[\AfterParseAction([Logger::class, "fail"])]
    #[\AfterParseAction([Logger::class, "succeed"])]
    public function test() {}
}
');

try {
    require $tmp;
} catch (RuntimeException $e) {
    echo "caught: " . $e->getMessage() . "\n";
}
unlink($tmp);

echo "actions: " . implode(", ", Logger::$log) . "\n";
?>
--EXPECT--
caught: boom
actions: fail, succeed
