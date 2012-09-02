<?php

	return iw::createConfig('Library', array(

		// Whether or not we should attempt to auto scaffold records using this class.

		'auto_scaffold' => TRUE,

		// The directory relative to application root in which user defined active record models
		// are stored.

		'root_directory' => 'user/models',

		// The wildcard autoloader means to use this classes __match() method and attemp to load
		// from it's root directory.  Removing it will remove model autoloading.

		'autoloaders' => array('*')

	));
