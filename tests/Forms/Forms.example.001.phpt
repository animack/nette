<?php

/**
 * Test: Nette\Forms example.
 *
 * @author     David Grudl
 * @package    Nette\Forms
 * @subpackage UnitTests
 */

use Nette\Forms\Form;



require __DIR__ . '/../bootstrap.php';


ob_start();
require '../../examples/forms/basic-example.php';
Assert::match( file_get_contents(__DIR__ . '/Forms.example.001.expect'), ob_get_clean() );