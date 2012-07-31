<?php

	return iw::createConfig('Response', array(
		'renderers' => array(
			'application/json' => 'ResponseJSON::render'
		)
	));