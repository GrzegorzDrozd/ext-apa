--TEST--
AfterParseAction fires for class-level attribute
--EXTENSIONS--
apa
--FILE--
<?php
$tmp = tempnam(sys_get_temp_dir(), 'apa');
file_put_contents($tmp, '<?php
#[\AfterParseAction("var_dump", "class-level")]
class Tagged {}
');
require $tmp;
unlink($tmp);
echo "done\n";
?>
--EXPECT--
string(6) "Tagged"
NULL
string(11) "class-level"
done
