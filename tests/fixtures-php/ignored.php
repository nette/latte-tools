<?php

declare(strict_types=1);

function a() {
	return 1;
}

class A
{
	function x() {}
}

interface B
{
	function x();
}

trait C
{
	function x() {}
}

#[Attr]
function () {};

return 1;

goto a;
a:

throw new Exception;

static $var;

global $var;
