<?php

	return iw::createConfig('Core', array(

		// By default database support is disabled.  You should not set this to FALSE unless you
		// configure at least one database below.

		'disabled'  => TRUE,

		'databases' => array(

			// Multiple databases can be configured.  If database support is enabled above the
			// default database is always 'default', but it is possible to add independent
			// databases and then configure ActiveRecords/models to use those databases using
			// the 'database' configuration element in their independent configurations.
			//
			// Database names are reflected by the keys and optionally can have a '::role' string
			// appended to them, example: 'default::both'. If the role is ommitted the default
			// role is both.
			//
			// For more information about roles, please see Flourish's fORM Documentation.

			'default::both' => array(

				// The database types used/allowed by inKWell reflect whatever is currently
				// supported by Flourish, examples at the time of creating this file include:
				// db2, mssql, mysql, oracle, postgresql, and sqlite.
				//
				// Both the type and name are required and should be a string value.

				'type' => NULL,
				'name' => NULL,

				// Authentication information if required

				'user'     => NULL,
				'password' => NULL,

				// If the host parameter is configured as an array then inKWell will select a
				// random host to pull data from.  This can be good for "round-robin" hunting.
				// The particular database server which a visitor connects to for the first
				// time will be stored in their session to ensure any effect they have on the data
				// will be reflected instantly to them.  Replication between databases must be
				// handled elsewhere, and is presumed to be for the most part on-the-fly.
				//
				// You can specify ports with each host in standard syntax:
				//
				// <address>:<port>

				'hosts' => array('127.0.0.1'),
			),
		),
	));
