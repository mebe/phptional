<?php
error_reporting(E_STRICT);
flush();
// example usage of PHPtional
include_once "phptional.php";

$_exceptional = new PHPtional_Client("7f9203fe7928833d39688344fc44e36419ee2d07");
//$_exceptional->authenticate();

class Foo
{
    function bar()
    {
        throw new Exception("This is pretty neat!");
    }
}

$f = new Foo;
$f->bar();
?>