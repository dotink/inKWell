	return iw::createConfig('Controller', array(

		//
		// Definine a base URL will make all routes relative to it as well as isolates error
		// handling.  Example:
		//
		// 'base_url' => '/forums'
		//

		'base_url' => NULL,

		//
		// Error handlers allow you to provide custom callbacks which should return a response
		// when controller::triggerError() is called.  For example, if you did the following
		// calling self::triggerError('not_found') inside a controller handling requests to your
		// base URL would return the response provided by the callback.
		//
		// 'error_handlers' => array (
		//     'not_found'  => 'MyController::notFound',
		//     ...
		// )
		//

		'error_handlers' => array(
		),

		//
		// You can define per controller routes here.  These routes are sorted by a specificity
		// rating to avoid conflicting with other controllers which may define similar routes.
		// These are always relative to your configured base URL.
		//

		'routes' => array(
		)
	));
