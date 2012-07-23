<?php

	return iw::createConfig('Library', array(

		//
		// Preload our scaffolder
		//

		'preload' => TRUE,

		// If the scaffolder is disabled, this will only prevent on the fly
		// scaffolding from taking place... you will still be able to scaffold
		// by manually calling Scaffolder::build()

		'disabled' => FALSE,

		// Where are we looking for our scaffolding templates?  This is relative
		// to the inkwell root

		'root_directory' => 'scaffolding',

	));
