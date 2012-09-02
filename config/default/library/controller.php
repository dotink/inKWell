<?php

	return iw::createConfig('Library', array(

		// Whether or not we should attempt to auto scaffold records using this class.

		'auto_scaffold' => FALSE,

		// The directory relative to application root in which user defined record sets are stored.

		'root_directory' => 'user/controllers',

		// The wildcard autoloader means to use this classes __match() method and attemp to load
		// from it's root directory.  Removing it will remove controller autoloading.

		'autoloaders' => array('*'),

		// The default accept types in preferred order.

		'default_accept_types' => array(
			'text/plain',
			'text/html'
		)
	));
