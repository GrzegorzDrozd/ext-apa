--TEST--
AfterParseAction triggers autoloading for callable class
--EXTENSIONS--
apa
--FILE--
<?php
$dir = sys_get_temp_dir() . '/apa_autoload_' . getmypid();
mkdir($dir);

file_put_contents($dir . '/Handler.php', '<?php
class Handler {
    public static function handle(string $cn, string $mn, ...$args): void {
        echo "handled: $cn::$mn " . json_encode($args) . "\n";
    }
}
');

file_put_contents($dir . '/Target.php', '<?php
class Target {
    #[\AfterParseAction([Handler::class, "handle"], key: "value")]
    public function doStuff() {}
}
');

spl_autoload_register(function($class) use ($dir) {
    $file = $dir . '/' . $class . '.php';
    if (file_exists($file)) require $file;
});

require $dir . '/Target.php';

// Cleanup
array_map('unlink', glob($dir . '/*.php'));
rmdir($dir);
?>
--EXPECT--
handled: Target::doStuff {"key":"value"}
