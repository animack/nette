<?php

/**
 * Test: Nette\Configurator and services inheritance and overwriting.
 *
 * @author     David Grudl
 * @package    Nette\DI
 */

use Nette\Configurator,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$configurator = new Configurator;
$configurator->setDebugMode(FALSE);
$configurator->setTempDirectory(TEMP_DIR);
$container = $configurator->addConfig('files/configurator.inheritance4.neon')
	->createContainer();


Assert::type( 'Nette\Application\Application', $container->getService('application') );
