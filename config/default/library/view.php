<?php

	return iw::createConfig('Library', array(

		// The directory relative to the inKWell root directory in which views
		// are stored.  Using the set() or add() method on a view will prepend
		// this directory if your view does not begin with a slash.
		//
		// Example:
		//
		// View::create('html.php');
		//
		// Resolves load the view:
		//
		// <inkwell_application_root>/<view_root>/html.php

		'root_directory' => 'user/views',

		// Disable minification completely

		'disable_minification' => FALSE,

		// As per Flourish's code minification, we can set minification modes
		// of either 'development' or 'production' -- The differences between
		// the two relate to caching and are outlined under the Minification
		// section at: http://flourishlib.com/docs/fTemplating
		//
		// Default is NULL which means the current core execution mode will be used

		'minification_mode' => NULL,

		// The cache directory is relative to the global write directory,
		// defined in the inkwell.php core config file and is used to store
		// cached versions of minified Javascript and CSS.

		'cache_directory' => 'cache'
	));
