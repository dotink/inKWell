<?php

	//
	// Fake a Server
	//

	$_SERVER['DOCUMENT_ROOT']   = '/';
	$_SERVER['REQUEST_URI']     = '/';
	$_SERVER['REQUEST_METHOD']  = 'GET';
	$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
	$_SERVER['SERVER_NAME']     = 'localhost';
	$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

	//
	// If our document root has been moved to a subdirectory of the actual application
	// directory, then we need to find it.
	//

	for (

		//
		// Initial assignment
		//

		$include_directory = 'includes';

		//
		// While Condition
		//

		!is_dir($include_directory);

		//
		// Modifier
		//

		$include_directory = realpath('..' . DIRECTORY_SEPARATOR . $include_directory)
	);

	//
	// Define our application root as the directory containing the includes folder
	//

	define('APPLICATION_ROOT', realpath(dirname($include_directory)));

	require(implode(DIRECTORY_SEPARATOR, array(
		APPLICATION_ROOT,
		'includes',
		'core.php'
	)));

	define  ('TEST_ROOT', dirname(__FILE__));
	require dirname(__FILE__) . DIRECTORY_SEPARATOR . 'EnhanceTestFramework.php';

	\Enhance\Core::discoverTests(TEST_ROOT . DIRECTORY_SEPARATOR . 'tests', TRUE);
	\Enhance\Core::runTests(\Enhance\TemplateType::Tap);
