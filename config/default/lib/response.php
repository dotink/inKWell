<?php

	return iw::createConfig('Library', array(

		//
		// The default response
		//

		'default' => 'not_found',

		//
		// Renderers are custom callback logic which will have the view passed to them for
		// matching response types during the the response construction.  The returned result
		// is what gets assigned to the body.  If you want to add to these, it is suggested that
		// you extend this class and add your callbacks and configuration there.
		//

		'renderers' => array(
			'application/php'  => 'Response::renderPHP',
			'application/json' => 'Response::renderJSON'
		),

		//
		// Responses are short name aliases for various response codes.  They should not include
		// redirects, as redirects are never as an actual bodied response and are, thus, inherently
		// handled by Controller::redirect()
		//

		'responses' => array(

			//
			// For additional information about when each one of these response codes should be
			// used, please see the following:
			//

			'ok' => array(
				'code' => 200,
				'body' => NULL
			),

			'created' => array(
				'code' => 201,
				'body' => NULL
			),

			'accepted' => array(
				'code' => 202,
				'body' => NULL
			),

			'no_content' => array(
				'code' => 204,
				'body' => NULL
			),

			'bad_request' => array(
				'code' => 400,
				'body' => 'The requested resource requires authorization'
			),

			'not_authorized' => array(
				'code' => 401,
				'body' => 'The requested resource requires authorization'
			),

			'forbidden' => array(
				'code' => 403,
				'body' => 'You do not have permission to view the requested resource'
			),

			'not_found' => array(
				'code' => 404,
				'body' => 'The requested resource could not be found'
			),

			'not_allowed' => array(
				'code' => 405,
				'body' => 'The requested resource does not support this method'
			),

			'not_acceptable' => array(
				'code' => 406,
				'body' => 'The requested resource is not available in the accepted parameters'
			),

			'service_unavailable' => array(
				'code' => 503,
				'body' => 'The requested resource is temporarily unavailable'
			)
		)
	));