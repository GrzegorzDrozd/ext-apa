--TEST--
AfterParseAction fires for method attribute with positional args
--EXTENSIONS--
apa
--FILE--
<?php
$tmp = tempnam(sys_get_temp_dir(), 'apa');
file_put_contents($tmp, '<?php
class Foo {
    #[\AfterParseAction("var_dump", 1, 2)]
    public function bar() {}
}
');
require $tmp;
unlink($tmp);
echo "done\n";
?>
--EXPECT--
string(3) "Foo"
string(3) "bar"
int(1)
int(2)
done
