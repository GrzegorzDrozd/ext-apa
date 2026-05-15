--TEST--
Multiple AfterParseAction attributes on same method fire in order
--EXTENSIONS--
apa
--FILE--
<?php
$tmp = tempnam(sys_get_temp_dir(), 'apa');
file_put_contents($tmp, '<?php
class Logger {
    public static array $log = [];
    public static function log(string $cn, string $mn, ...$args): void {
        self::$log[] = $args["tag"] ?? "no-tag";
    }
}
class Multi {
    #[\AfterParseAction([Logger::class, "log"], tag: "first")]
    #[\AfterParseAction([Logger::class, "log"], tag: "second")]
    public function action() {}
}
');
require $tmp;
unlink($tmp);

echo implode(", ", Logger::$log) . "\n";
?>
--EXPECT--
first, second
