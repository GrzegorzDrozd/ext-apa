--TEST--
AfterParseAction fires on second request with opcache file cache
--EXTENSIONS--
apa
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.file_cache={TMP}
opcache.file_cache_only=1
opcache.validate_timestamps=0
--FILE--
<?php
$dir = sys_get_temp_dir() . '/apa_opcache_' . getmypid();
mkdir($dir);

file_put_contents($dir . '/setup.php', '<?php
class OpcacheCollector {
    public static array $items = [];
    public static function add(string $cn, string $mn, ...$a): void {
        self::$items[] = "$cn::$mn";
    }
}
class OpcacheController {
    #[\AfterParseAction([OpcacheCollector::class, "add"], route: "/test")]
    public function test() {}
}
');

// First load — compiles and caches
require $dir . '/setup.php';
echo "Load 1: " . count(OpcacheCollector::$items) . " items\n";

// Cleanup
array_map('unlink', glob($dir . '/*.php'));
rmdir($dir);
?>
--EXPECT--
Load 1: 1 items
