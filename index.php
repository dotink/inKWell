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

	define('APPLICATION_ROOT', realpath(dirname($include_directory)));
	define('MAINTENANCE_FILE', APPLICATION_ROOT . DIRECTORY_SEPARATOR . 'MAINTENANCE');

	try {

		//
		// Boostrap!
		//

		if (!is_readable($include_directory . DIRECTORY_SEPARATOR . 'init.php')) {
			throw new Exception('Unable to include inititialization file.');
		}

		include $include_directory . DIRECTORY_SEPARATOR . 'init.php';

		//
		// Check for and include maintenance file if it exists and exit right away
		//

		if (file_exists(MAINTENANCE_FILE)) {
			include MAINTENANCE_FILE;
			exit(-1);
		}

		//
		// Include our routing logic and run the router.
		//

		if (!is_readable($include_directory . DIRECTORY_SEPARATOR . 'routing.php')) {
			throw new Exception('Unable to include routing file.');
		}

		include $include_directory . DIRECTORY_SEPARATOR . 'routing.php';

		//
		// Resolve and send our response
		//

		Response::resolve(Moor::run())->send();
		exit(1);

	} catch (Exception $e) {

		//
		// Panic here, attempt to determine what state we're in, see if some
		// errors handlers are callable or if we're totally fucked.  In the
		// end, throw the exception and let Flourish handle it appropriately.
		//

		throw $e;
	}
