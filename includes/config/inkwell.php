<?php

	return array(

		// General Settings

		'write_directory'       => 'writable',

		// Error Reporting Information

		'display_errors'        => TRUE,
		'error_level'           => E_ALL,
		'error_email_to'        => 'webmaster@dotink.org',

		// Session information

		'persistent_sessions'   => FALSE,
		'session_length'        => '1 day',

		// Time and Date Information

		'default_timezone'      => 'America/Los_Angeles',

		'date_formats'          => array(

			'console_date'      => 'M jS, Y',
			'console_time'      => 'g:ia',
			'console_timestamp' => 'M jS, Y @ g:ia'
		)
	);
