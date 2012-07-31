<?php

	return iw::createConfig('Response', array(
		'renderers' => array(
			'application/php' => 'ResponsePHP::render'
		)
	));