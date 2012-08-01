<?php

	// Below is a list of routes which Moor (the router) will try to resolve when the
	// /includes/routing.php file is called.  In addition to the below routes, any controller
	// configurations containing a 'routes' key will have its routes run as well in the order of
	// general route specificity.

	return iw::createConfig('Core', array(
		'/' => function() {
			return new Response('ok', NULL, View::create('hello_world.php'));
		}
	));
