<?php

/**
 * Test: Nette\Forms example.
 *
 * @author     David Grudl
 * @package    Nette\Forms
 */

use Nette\Forms\Form,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


ini_set('session.save_path', TEMP_DIR);


$_SERVER['REQUEST_METHOD'] = 'POST';


$form = new Form;

$input = $form->addProtection('Security token did not match. Possible CSRF attack.', 3);

$form->fireEvents();

Assert::same( array('Security token did not match. Possible CSRF attack.'), $form->getErrors() );
Assert::match('<input type="hidden" name="_token_" value="%S%">', (string) $input->getControl());
