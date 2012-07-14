<?php
	//
	// Redirect extraneous root URLs to '/'
	//
	if (preg_match('#^(/index\.php(/?)?)$#', fURL::get())) {
		header($_SERVER['SERVER_PROTOCOL'] . ' 301 Moved Permanently');
		fURL::redirect(($query = fURL::getQueryString())
			? '/?' . $query
			: '/'
		);
	}
	//
	// Not every server supports rewriting.  In particular we want to fake PATH_INFO
	// for routers that handle it.  And normalize PATH_TRANSLATED.  If we do support
	// it we shouldn't be using either.
	//
	if (!isset($_SERVER['IW_REWRITE_ENABLED']) || !$_SERVER['IW_REWRITE_ENABLED']) {
		if ($_SERVER['REQUEST_URI'] == '/') {
			$_SERVER['PATH_INFO']   = '/';
			$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
		}

		if (!isset($_SERVER['PATH_TRANSLATED']) && isset($_SERVER['PATH_INFO'])) {
			$_SERVER['PATH_TRANSLATED'] = $_SERVER['SCRIPT_FILENAME'];
		}
	} elseif (isset($_SERVER['PATH_INFO'])) {
		unset($_SERVER['PATH_INFO']);
		unset($_SERVER['PATH_TRANSLATED']);
	}
	//
	// Enable debugging depending on execution mode
	//
	if (iw::getExecutionMode() == 'development') {
		Moor::enableDebug();
	}
	//
	// Enable restless depending on execution mode
	//
	if (iw::getExecutionMode() == 'production') {
		Moor::enableRestlessURLs();
	}
	//
	// Set Moor's not found callback to NULL so it doesn't handle not founds
	//
	Moor::setNotFoundCallback(NULL);
	//
	//
	// Set the Request Param Pattern
	//
	Moor::setRequestParamPattern(iw::getConfig('inkwell', 'request_param_pattern')
		? iw::getConfig('inkwell', 'request_param_pattern')
		: '[A-Za-z0-9_-]+'
	);
	//
	// Register all of our global priority routes first
	//
	foreach (iw::getConfig('routes') as $route => $target) {
		Moor::route($route, $target);
	}
	//
	// Look for controller configurations which specify routes and sort them
	// by a specificity and then register them in the order of highest to
	// leaste specific.
	//
	$controller_configs = iw::getConfigsByType('Controller');
	$routes             = array();
	$ordered_routes     = array();

	foreach ($controller_configs as $controller_config) {

		if (isset($controller_config['routes'])) {

			if (!is_array($controller_config['routes'])) {
				throw new fProgrammerException(
					'Wrong data type for routes configuration.'
				);
			}

			foreach ($controller_config['routes'] as $route => $target) {

				$route_parts = explode('/', $route);
				$route_depth = count($route_parts);
				$specificity = $route_depth * 10;

				foreach ($route_parts as $route_part) {

					if (isset($route_part[0])) {

						switch ($route_part[0]) {
							case ':':
								$extra_points = 1;
								break;
							case '@':
								$extra_points = 2;
								break;
							default:
								$extra_points = strlen($route_part);
								break;
						}

						$specificity += $extra_points;
					}
				}

				$routes[$route] = array(
					'target'      => $target,
					'specificity' => $specificity
				);

			}
		}
	}

	foreach($routes as $route => $info) {
		$ordered_routes[$route] = $info['specificity'];
	}

	arsort($ordered_routes);

	foreach ($ordered_routes as $route => $specificity) {
		Moor::route($route, $routes[$route]['target']);
	}