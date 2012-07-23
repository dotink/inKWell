<?php

	return iw::createConfig('Library', array(

		//
		// The default response
		//

		'default' => 'not_found',

		//
		// Renderers are custom callback logic which will have the response passed to them
		// prior to outputting the view.  They are based on mime-type and will only be called
		// if the response content type matches.
		//
		// The match, represented by the key of the array, is actually a RegEx delimited by #.
		//
		// Each callback is rendered in the order in which it is defined except for the '*'
		// match which will always be rendered last, and will match any mime type.
		//

		'renderers' => array(
			'application/json' => 'Response::renderJSON',
			'application/php'  => 'Response::renderPHP',
			'*'                => 'Response::renderAny'
		),

		//
		// Responses are short name aliases for various response codes.  They should not include
		// redirects, as redirects are never as an actual bodied response and are handled by
		// Controller::redirect().
		//

		'responses' => array(

			//
			// For additional information about when each one of these response codes should be
			// used, please see the following:
			//
			// http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
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
				'body' => 'The requested could not be understood'
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