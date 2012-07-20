<?php

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

	define('APPLICATION_ROOT', dirname($include_directory));

	require(implode(DIRECTORY_SEPARATOR, array(
		APPLICATION_ROOT,
		'includes',
		'core.php'
	)));

	define  ('TEST_ROOT', dirname(__FILE__));
	require dirname(__FILE__) . DIRECTORY_SEPARATOR . 'EnhanceTestFramework.php';

	\Enhance\Core::discoverTests(TEST_ROOT . DIRECTORY_SEPARATOR . 'tests', TRUE);
	\Enhance\Core::runTests();
