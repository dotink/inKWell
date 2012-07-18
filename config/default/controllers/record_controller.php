<?php

	return iw::createConfig('Controller', array(

		//
		// The master view to use for views returned by default actions.  This can be an array
		// of views to try.
		//

		'master_view' => 'html.php'

		//
		//
		//
		'routes' => array(
			'/@record_set/'      => '@record_set(uc)Controller::manage',
			'/@record_set/:slug' => '@record_set(uc)Controller::select'
		)
	));