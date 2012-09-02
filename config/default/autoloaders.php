<?php

	// The inKWell autoloader works via a series of match parameters and
	// target directories configured in a key => value array respectively.
	//
	// Match parameters can be any one of the following:
	//   * Strings with wildcards
	//   * Standard strings
	//   * Class names
	//
	// If the key contains a * they are used as wildcards, so for example the
	// following would make any class beginning with 'user' load from the
	// 'includes/lib/user' directory:
	//
	//      'user*'  =>   'includes/lib/user'
	//
	// Any class which does not begin with 'user' will have no attempt made to
	// load from the 'includes/lib/user' directory.
	//
	// Non-wildcard keys represent either static matches, i.e. no matter what
	// the class name an attempt will be made to load from that directory,
	// or class names.
	//
	// In the event that the key represents a class name, the requested class
	// name will be passed to the __match() method on the said class for custom
	// matching logic.  If the __match() method returns TRUE an attempt will be
	// made to load the class from that directory, if it returns FALSE, no
	// attempt will be made.
	//
	// Finally, if the key contains no wilcards or does not represent a class
	// with a __match() method, the entry will be treated as a static match,
	// and an attempt will be made to load the class from the target directory
	// regardless of the name.
	//
	// In ALL cases, if the class cannot be loaded from a target directory
	// the autoloader will move on to the next match => target pair.
	//
	// Since it is possible to recurse on autoloads, it is possible to make
	// any match a class which has not yet been loaded, however, would be
	// loaded if the array of autoloaders were to be called again.

	return iw::createConfig('Core', array(

		// Note: All paths are relative to document root
		//
		// Our base libraries, Flourish and Moor use the wildcard based keys for
		// class matching.  In short, this means that attempts to load classes
		// which do not match the provided prefixes will skip these directories.

		'f*'           => 'COMPAT: library/flourish',
		'Moor*'        => 'COMPAT: library/moor',

		// A non-wilcard string which does not represent a class means that an
		// attempt will be made to load any class from these directories.

		'library'      => 'IW: library',
		'vendor'       => 'PSR0: vendor'

		// Additional class matches will be triggered by class configurations
		// whose 'auto_load' key is set to true and for which a 'root_directory'
		// is defined.

	));
