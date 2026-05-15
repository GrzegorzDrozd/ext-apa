--TEST--
AfterParseAction fires for function-level attribute
--EXTENSIONS--
apa
--FILE--
<?php
$tmp = tempnam(sys_get_temp_dir(), 'apa');
file_put_contents($tmp, '<?php
#[\AfterParseAction("var_dump", "func-level")]
function myFunc() {}
');
require $tmp;
unlink($tmp);
echo "done\n";
?>
--EXPECT--
NULL
string(6) "myFunc"
string(10) "func-level"
done
