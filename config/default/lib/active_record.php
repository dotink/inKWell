<?php

	return iw::createConfig('Library', array(

		// Whether or not we should attempt to autoload classes which match this class from the
		// root_directory

		'auto_load' => TRUE,

		// Whether or not we should attempt to auto scaffold records using this class.

		'auto_scaffold' => FALSE,

		// The directory relative to application root in which user defined active record models
		// are stored.  This will be used by the autoloader and the scaffolder.

		'root_directory' => 'user/models'
	));
