<?php

/**
 * Test: Nette\Diagnostics\Debugger Bar in non-HTML mode.
 *
 * @author     David Grudl
 * @package    Nette\Diagnostics
 */

use Nette\Diagnostics\Debugger;



require __DIR__ . '/../bootstrap.php';



Debugger::$productionMode = FALSE;
header('Content-Type: text/plain');

Debugger::enable();

function shutdown() {
	Assert::same('', ob_get_clean());
}
ob_start();
Debugger::$onFatalError[] = 'shutdown';
