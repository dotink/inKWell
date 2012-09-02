<?php

	/**
	 * ActiveRecord is an abstract base class for all flourish active records.
	 * It provides a series of common methods used by common Controller objects.
	 *
	 * @author Matthew J. Sahagian [mjs] <gent@dotink.org>
	 * @copyright Copyright (c) 2012, Matthew J. Sahagian
	 * @license Please reference the LICENSE.txt file at the root of this distribution
	 *
	 * @package inKWell
	 */
	abstract class ActiveRecord extends fActiveRecord implements inkwell, JSONSerializable
	{
		const DEFAULT_FIELD_SEPARATOR = '-';
		const DEFAULT_WORD_SEPARATOR  = '_';

		/**
		 * Cached inspection information about table columns
		 *
		 * @static
		 * @access private
		 * @var array
		 */
		static private $inspectionInfo = array();

		/**
		 * Cached name translations for record classes
		 *
		 * @static
		 * @access private
		 * @var array
		 */
		static private $nameTranslations = array();

		/**
		 * Cached table translations for record classes
		 *
		 * @static
		 * @access private
		 * @var array
		 */
		static private $tableTranslations = array();

		/**
		 * Cached record set translations for record classes
		 *
		 * @static
		 * @access private
		 * @var array
		 */
		static private $setTranslations = array();

		/**
		 * The slug field separator, default is a dash
		 *
		 * @static
		 * @access private
		 * @var string
		 */
		static private $fieldSeparator = NULL;

		/**
		 * The slug word separator, default is an underscore
		 *
		 * @static
		 * @access private
		 * @var string
		 */
		static private $wordSeparator = NULL;

		/**
		 * The base image upload directory
		 *
		 * @static
		 * @access private
		 * @var string|fDirectory
		 */
		static private $imageUploadDirectory = NULL;

		/**
		 * The base file upload directory
		 *
		 * @static
		 * @access private
		 * @var string|fDirectory
		 */
		static private $fileUploadDirectory = NULL;

		/**
		 * The cached slug
		 *
		 * @access private
		 * @var string
		 */
		private $slug = NULL;

		/**
		 * The cached resource key
		 *
		 * @access private
		 * @var string
		 */
		private $resourceKey = NULL;

		/**
		 * Matches whether or not a given class name is a potential ActiveRecord by looking for an
		 * available matching ActiveRecord configuration or the tablized form in the list of the
		 * default database tables.
		 *
		 * @static
		 * @access public
		 * @param string $class The name of the class to check
		 * @return boolean TRUE if it matches, FALSE otherwise
		 */
		static public function __match($class)
		{
			if (in_array($class, iw::getConfigsByType('ActiveRecord', 'class'))) {
				return TRUE;
			}

			try {
				$schema = fORMSchema::retrieve();
				return in_array(fORM::tablize($class), $schema->getTables());
			} catch (fException $e) {}

			return FALSE;
		}

		/**
		 * Initializses the ActiveRecord class or a child class to be used as an active record.
		 *
		 * @static
		 * @access public
		 * @param array $config The configuration array
		 * @param string $element The element name of the configuration array
		 * @return boolen TRUE if the configuration succeeds, FALSE otherwise
		 */
		static public function __init(array $config = array(), $element = NULL)
		{
			self::$imageUploadDirectory = iw::getWriteDirectory('images');
			self::$fileUploadDirectory  = iw::getWriteDirectory('files');

			self::$fieldSeparator = (isset($config['field_separator']))
				? $config['field_separator']
				: self::DEFAULT_FIELD_SEPARATOR;

			self::$wordSeparator = (isset($config['word_separator']))
				? $config['word_separator']
				: self::DEFAULT_WORD_SEPARATOR;

			//
			// Configure active records
			//

			foreach (iw::getConfigsByType(__CLASS__) as $config_element => $config) {

				$record_class = iw::classize($config_element);
				$database     = NULL;
				$table        = NULL;
				$name         = NULL;

				extract($config);

				if (isset($database)) {
					if ($database !== 'default') {
						fORM::mapClassToDatabase($record_class, $database);
					}
				}

				if (isset($table)) {
					self::$tableTranslations[$record_class] = $table;
					fORM::mapClassToTable($record_class, $table);
				}

				if (isset($name)) {
					self::$nameTranslations[$record_class]  = $name;
				}
			}

			fORM::registerHookCallback(
				'*',
				'post::store()',
				iw::makeTarget(__CLASS__, 'resetCache')
			);

			return TRUE;
		}

		/**
		 * Dynamically scaffolds an Active Record class.
		 *
		 * @static
		 * @access public
		 * @param string $record_class The class name to scaffold
		 * @param array $template_vars Requested template vars
		 * @return void
		 */
		static public function __make($record_class, $template_vars = array())
		{
			Scaffolder::make('classes' . iw::DS . __CLASS__ . '.php', array_merge(
				array(
					'parent_class' => __CLASS__,
					'class'        => $record_class
				),
				$template_vars
			));
		}

		/**
		 * Determines if an Active Record class has been defined by ensuring the class exists
		 * and it is a subclass of ActiveRecord.  This is, in part, a workaround for a PHP bug
		 * #46753 where is_subclass_of() will not properly autoload certain classes in edge cases.
		 * This behavior is fixed in 5.3+, but the method will probably remain as a nice shorthand.
		 *
		 * @static
		 * @access public
		 * @param string $record_class The Active Record class
		 * @return boolean Whether or not the class is defined
		 */
		static public function classExists($record_class)
		{
			return (class_exists($record_class) && is_subclass_of($record_class, __CLASS__));
		}

		/**
		 * Converts a record name into a class name, for example: user to User or user_photograph
		 * to UserPhotograph
		 *
		 * @static
		 * @access public
		 * @param string $record The name of the record
		 * @return string|NULL The class name of the record or NULL if it does not exist
		 */
		static public function classFromRecordName($record_name)
		{
			if (!in_array($record_name, self::$nameTranslations)) {
				try {
					$record_class = iw::classize($record_name);

					if (self::classExists($record_class)){
						self::$nameTranslations[$record_class] = $record_name;
					}
				} catch (fProgrammerException $e) {}
			}

			return array_search($record_name, self::$nameTranslations);
		}

		/**
		 * Converts a record set class name into an active record class name, for example: Users to
		 * User
		 *
		 * @static
		 * @access public
		 * @param string $recordset The name of the recordset
		 * @return string|NULL The class name of the active record or NULL if it does not exist
		 */
		static public function classFromRecordSet($record_set)
		{
			if (!in_array($record_set, self::$setTranslations)) {
				try {
					$record_class = fGrammar::singularize($record_set);
					if (self::classExists($record_class)){
						self::$setTranslations[$record_class] = $record_set;
					}
				} catch (fProgrammerException $e) {}
			}

			return array_search($record_set, self::$setTranslations);
		}

		/**
		 * Converts a table name into an active record class name, for example: users to User
		 *
		 * @static
		 * @access public
		 * @param string $table The name of the table
		 * @return string|NULL The class name of the record or NULL if it does not exist
		 */
		static public function classFromRecordTable($record_table)
		{
			if (!in_array($record_table, self::$tableTranslations)) {
				try {
					$record_class = fORM::classize($record_table);

					if (self::classExists($record_class)){
						self::$tableTranslations[$record_class] = $record_table;
					}
				} catch (fProgrammerException $e) {}
			}

			return array_search($record_table, self::$tableTranslations);
		}

		/**
		 * Converts an Active Record class to its record name
		 *
		 * @static
		 * @access public
		 * @param string $record_class The Active Record class name
		 * @return string The custom or default record translation
		 */
		static public function classToRecordName($record_class)
		{
			return isset(self::$nameTranslations[$record_class])
				? self::$nameTranslations[$record_class]
				: iw::elementize($record_class);
		}

		/**
		 * Converts an Active Record class to its record set name
		 *
		 * @static
		 * @access public
		 * @param string $record_class The Active Record class name
		 * @return string The custom or default record set translation
		 */
		static public function classToRecordSet($record_class)
		{
			return isset(self::$setTranslations[$record_class])
				? self::$setTranslations[$record_class]
				: fGrammar::pluralize($record_class);
		}

		/**
		 * Converts an Active Record class to its table name
		 *
		 * @static
		 * @access public
		 * @param string $record_class The Active Record class name
		 * @return string The custom or default record table translation
		 */
		static public function classToRecordTable($record_class)
		{
			return isset(self::$tableTranslations[$record_class])
				? self::$tableTranslations[$record_class]
				: fORM::tablize($record_class);
		}

		/**
		 * Creates a record of a given type from a provided resource key.
		 *
		 * @static
		 * @access public
		 * @param string $record_class The Active Record Class
		 * @param string $resource_key A JSON encoded primary key string representation
		 * @return fActiveRecord The active record matching the resource key
		 *
		 */
		static public function createFromResourceKey($record_class, $resource_key)
		{
			return new $record_class(fJSON::decode($resource_key, TRUE));
		}

		/**
		 * Creates a record of a given type provided from a slug.
		 *
		 * @static
		 * @access public
		 * @param string $record_class The Active Record class
		 * @param string $slug A slug or URL-friendly primary key representation of the record
		 * @return fActiveRecord The active record matching the slug
		 */
		static public function createFromSlug($record_class, $slug)
		{
			if ($slug_column = self::fetchClassInfo($record_class, 'slug_column')) {
				return new $record_class(array($slug_column => $slug));
			}

			$columns = self::fetchClassInfo($record_class, 'pkey_columns');
			$lead    = self::classToRecordName($record_class) . self::$fieldSeparator;
			$slug    = str_replace($lead, '', $slug);
			$data    = explode(self::$fieldSeparator, $slug, count($columns));

			if (count($columns) == 1) {
				$pkey = $data[0];
			} else {
				foreach ($columns as $column) {
					$pkey[$column] = array_shift($data);
				}
			}

			return new $record_class($pkey);
		}

		/**
		 * Fetches information for a given ActiveRecord class
		 *
		 * @static
		 * @access public
		 * @param string $class The class to fetch information for
		 * @param string $key The key of the information to fetch, NULL (default) fetches all keys
		 * @return mixed The information
		 */
		static public function fetchClassInfo($class, $key = NULL)
		{
			return call_user_func(iw::makeTarget($class, 'fetchInfo'), $key);
		}

		/**
		 * Inspects a column on a particular record class.  If this is called using the
		 * inspectColumn() method on an active record it will add enhanced information.
		 *
		 * @static
		 * @access public
		 * @param string $record_class The Active Record class
		 * @param string $column The name of the column
		 * @param array $info The array of current inspection information
		 * @return array The enhanced inspection information
		 */
		static public function inspectColumn($record_class, $column, &$info = array())
		{

			// TODO: Determine if flourish will cache the $info array with
			// TODO: additional changes here... if not, implement local cache
			// TODO: using self::$inspectionInfo

			$schema = fORMSchema::retrieve($record_class);
			$table  = self::classToRecordTable($record_class);

			//
			// Populate basic information if it is not provided already
			//

			if (!count($info)) {
				$info = $schema->getColumnInfo($record_class, $column);
			}

			//
			// Populate advanced foreign key information
			//

			$fkey_info       = array();
			$info['is_fkey'] = FALSE;

			foreach ($schema->getKeys($table, 'foreign') as $fkey) {
				if ($fkey['column'] == $column) {

					$info['is_fkey'] = TRUE;
					$info            = array_merge($info, $fkey);
					$relationships   = $schema->getRelationships($table);

					foreach ($relationships as $type => $relationship) {
						foreach ($relationship as $relation_info) {
							if ($relation_info['column'] == $column) {
								switch ($type) {
									case 'one-to-many':
										$info['format'] = 'recordset_reference';
										break;
									case 'one-to-one':
									case 'many-to-one':
										$info['format'] = 'record_reference';
										break;
								}
							}
						}
					}

				}
			}

			//
			// Set a format
			//

			switch ($info['type']) {
				case 'varchar':
					$info['format'] = 'string';
					break;
				case 'boolean':
					$info['format'] = 'switch';
					break;
				default:
					$info['format'] = $info['type'];
					break;
			}

			//
			// Determine additional properties
			//

			$fixed_columns  = self::fetchClassInfo($record_class, 'fixed_columns');
			$serial_columns = self::fetchClassInfo($record_class, 'serial_columns');
			$info['fixed']  = in_array($column, $fixed_columns)  ? TRUE : FALSE;
			$info['serial'] = in_array($column, $serial_columns) ? TRUE : FALSE;

			return $info;
		}

		/**
		 * Resets some cached information such as the slug and resource keys in the event related
		 * information such as primary key values has changed.
		 *
		 * @static
		 * @access public
		 * @param fActiveRecord The active record object
		 * @param array $values The new column values being set
		 * @param array $history The columns and values as changed in the session
		 * @param array $relatives An array of related records
		 * @param array $cache The cache array for the record
		 * @return void
		 */
		static public function resetCache($object, &$values, &$history, &$relatives, &$cache)
		{
			$record_class    = get_class($object);
			$slug_column     = self::fetchClassInfo($record_class, 'slug_column');
			$pkey_columns    = self::fetchClassInfo($record_class, 'pkey_columns');
			$changed_columns = array_keys($history);

			if ($slug_column && in_array($slug_column, $changed_columns)) {
				$object->slug = NULL;
			}

			if ($pkey_columns && count(array_intersect($pkey_columns, $changed_columns))) {
				$object->resourceKey = NULL;
				if ($object->slug) {
					$object->slug = NULL;
				}
			}
		}

		/**
		 * A hook which generates or validates the slug column.
		 *
		 * Depending on whether or not it has a value, this method will either generate a slug if
		 * an ID Column exists, or it will validate it (assuming the user has provided input).
		 *
		 * @static
		 * @access public
		 * @param fActiveRecord The active record object
		 * @param array $values The new column values being set
		 * @param array $history The columns and values as changed in the session
		 * @param array $relatives An array of related records
		 * @param array $cache The cache array for the record
		 * @param array $messages An array of validation messages
		 * @return void
		 */
		static public function resolveSlugColumn($object, &$values, &$history, &$relatives, &$cache, &$messages)
		{
			$record_class = get_class($object);
			$slug_column  = self::fetchClassInfo($record_class, 'slug_column');
			$id_column    = self::fetchClassInfo($record_class, 'id_column');

			//
			// If no value is set for the slug column, try to generate it
			// based on the ID column.
			//

			if (!$values[$slug_column] && isset($id_column)) {

				$id_value = $values[$id_column];
				$rev      = NULL;
				$slug     = fURL::makeFriendly($values[$id_column], NULL,self::$wordSeparator);

				try {
					do {
						$try = $slug . (($revision) ? self::$wordSeparator . $revision : NULL);

						self::createFromSlug($record_class, $try);
						$revision++;
					} while (TRUE);

				} catch (fNotFoundException $e) {
					$values[$slug_column] = $try;
				}

				return;
			}

			//
			// If we've gotten to this point, i.e. we haven't generated a slug, we assume one
			// has been provided and we want to validate.
			//

			if (!trim($values[$slug_column])) {
				$validation_messages[] = fText::compose(
					'%s: Must have a value',
					fGrammar::humanize($slug_column)
				);

			} else {
				$url_friendly = fURL::makeFriendly(
					$values[$slug_column],
					NULL,
					self::$wordSeparator
				);

				if ($values[$slug_column] != $url_friendly) {
					$invalid_characters = array_diff(
						str_split(strtolower($values[$slug_column])),
						str_split($url_friendly)
					);

					if (($i = array_search(' ', $invalid_characters)) !== FALSE) {
						$invalid_characters   = array_diff(
							$invalid_characters,
							array(' ')
						);
						$invalid_characters[] = 'spaces';
					}

					if(count($invalid_characters)) {
						$validation_messages[] = fText::compose(
							'%s: Cannot contain %s',
							fGrammar::humanize($slug_column),
							fGrammar::joinArray($invalid_characters, 'or')
						);
					}
				}
			}

			return;
		}

		/**
		 * Get the value of the record's primary key as passed to the
		 * constructor or as a serialized string.
		 *
		 * @access public
		 * @return mixed The primary key, usable in the constructor
		 */
		public function fetchPrimaryKey()
		{
			$record_class = get_class($this);
			$columns      = self::fetchClassInfo($record_class, 'pkey_columns');
			$pkey         = array();

			foreach ($columns as $column) {
				$pkey[$column] = self::get($column);

				if (is_object($pkey[$column])) {
					$pkey[$column] = (string) $pkey[$column];
				}
			}

			return (count($pkey) == 1)
				? reset($pkey)
				: $pkey;
		}

		/**
		 * Default method for converting active record objects to JSON.  This will make all
		 * properties, normally private, publically available and return the object.
		 *
		 * @access public
		 * @return string The JSON encodable object with public properties
		 */
		public function jsonSerialize()
		{
			$record_class   = get_class($this);
			$schema         = fORMSchema::retrieve($record_class);
			$record_table   = fORM::tablize($record_class);
			$object         = new StdClass();
			$column_methods = array();

			foreach (array_keys($schema->getColumnInfo($record_table)) as $column) {
				$column_methods[$column] = 'get' . fGrammar::camelize($column, TRUE);
			}

			foreach ($column_methods as $column => $method) {
				$object->$column = $this->$method();
			}

			return $object;
		}

		/**
		 * Creates a resource key which (JSON Serialized Primary Key)
		 *
		 * @access public
		 * @return string The JSON serialized resource key
		 */
		public function makeResourceKey()
		{
			//
			// The cached resource key will be reset to NULL via the ::resetCache() callback in
			// the event any of the values comprising the primary key have changed.
			//

			if (!$this->resourceKey) {
				$this->resourceKey = fJSON::encode($this->fetchPrimaryKey());
			}

			return $this->resourceKey;
		}

		/**
		 * Creates a url friendly identifying slug.
		 *
		 * If a slug_column is configured on the record, it's value will be used.  Otherwise
		 * the slug will appear as the URL friendly record name + primary key info.
		 *
		 * @access public
		 * @return string The slug representation of the active record.
		 */
		public function makeSlug($friendly_id = TRUE)
		{
			//
			// The cached slug will be reset to NULL via the ::resetCache() callback in the
			// event any of the values comprising the slug or primary key have changed.
			//

			if (!$this->slug) {
				$record_class = get_class($this);
				$slug_column  = self::fetchClassInfo($record_class, 'slug_column');

				if ($slug_column) {
					$this->slug = self::get($slug_column);
				} else {
					if (!is_array($pkey = $this->fetchPrimaryKey())) {
						$pkey = array($pkey);
					}

					array_unshift($pkey, self::classToRecordName($record_class));

					$this->slug = implode(self::$fieldSeparator, $pkey);
				}
			}

			return $this->slug;
		}

		/**
		 * Populates a record from optionally namespaced parameters
		 *
		 * @access public
		 * @param string $namespace An optional namespace to look for values in
		 * @return void
		 */
		public function populate($namespace = NULL)
		{
			if ($namespace) {
				Request::filter($namespace . '::');
			}

			parent::populate();

			if ($namespace) {
				Request::unfilter();
			}
		}
	}