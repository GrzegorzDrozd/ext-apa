--TEST--
AfterParseAction emits warning for invalid callable
--EXTENSIONS--
apa
--FILE--
<?php
$tmp = tempnam(sys_get_temp_dir(), 'apa');
file_put_contents($tmp, '<?php
class Bad {
    #[\AfterParseAction("nonexistent_function_xyz")]
    public function oops() {}
}
');
require $tmp;
unlink($tmp);
echo "survived\n";
?>
--EXPECTF--
Warning: %s: AfterParseAction: invalid callable: %s in %s on line %d
survived
