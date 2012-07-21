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
		 * Cached information about the class built during __init()
		 *
		 * @static
		 * @access private
		 * @var array
		 */
		static private $info = array();

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
			if (iw::classize($element) == __CLASS__) {

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
				$ar_configs = iw::getConfigsByType(__CLASS__);

				foreach ($ar_configs as $config_element => $config) {

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

			} else {
				$record_class = iw::classize($element);

				if (!is_subclass_of($record_class, __CLASS__)) {
					return FALSE;
				}
			}

			$schema = fORMSchema::retrieve($record_class);
			$table  = fORM::tablize($record_class);
			$ukeys  = $schema->getKeys($table, 'unique');

			//
			// Set Configuration Defaults
			//
			self::$info[$record_class]['id_column'] = NULL;
			self::$info[$record_class]['order']     = array();

			//
			// Set an explicit ID column or attempt to find a natural one
			//
			if (isset($config['id_column']) && !empty($config['id_column'])) {
				self::$info[$record_class]['id_column'] = $config['id_column'];
			} else {
				if (sizeof($ukeys) == 1 && sizeof($ukeys[0]) == 1) {
					self::$info[$record_class]['id_column'] = $ukeys[0][0];
				}
			}

			//
			// If we have a slug column make sure it's unique
			//
			if (isset($config['slug_column'])) {
				$valid_slug_column = FALSE;
				$slug_column       = $config['slug_column'];

				foreach ($ukeys as $ukey) {
					if (count($ukey) == 1 && $ukey[0] == $slug_column) {
						$valid_slug_column = TRUE;
					}
				}

				if (!$valid_slug_column) {
					throw new fProgrammerException (
						'Slug column requires the column to be unique.'
					);
				}

				fORM::registerHookCallback(
					$record_class,
					'pre::validate()',
					iw::makeTarget(__CLASS__, 'validateSlugColumn')
				);

				self::$info[$record_class]['slug_column'] = $slug_column;
			}

			//
			// Set any explicitly configure order
			//
			if (isset($config['order']) && is_array($config['order'])) {
				self::$info[$record_class]['order'] = $config['order'];
			}

			$column_configs = array(
				'image_columns',
				'file_columns',
				'password_columns',
				'order_columns',
				'money_columns',
				'url_columns',
				'email_columns',
				'fixed_columns'
			);

			foreach($column_configs as $column_config) {

				self::$info[$record_class][$column_config] = array();

				if (!isset($config[$column_config]) || !is_array($config[$column_config])) {
					continue;
				}

				//
				// Add each specially configured column's ORM mappings and callback
				//
				foreach ($config[$column_config] as $key => $column) {

					self::$info[$record_class][$column_config][$key] = $column;

					switch ($column_config) {

						//
						// Special handling of image columns
						//
						case 'image_columns':
							fORMFile::configureImageUploadColumn(
								$record_class,
								$column,
								iw::getWriteDirectory(
									self::$imageUploadDirectory .
									implode(DIRECTORY_SEPARATOR, array(
										$table,
										fGrammar::pluralize($column)
									))
								)
							);
							break;

						//
						// Special handling of file columns
						//
						case 'file_columns':
							fORMFile::configureFileUploadColumn(
								$record_class,
								$column,
								iw::getWriteDirectory(
									self::$fileUploadDirectory .
									implode(DIRECTORY_SEPARATOR, array(
										$table,
										fGrammar::pluralize($column)
									))
								)
							);
							break;

						//
						// Special handling for order columns
						//
						case 'order_columns':
							fORMOrdering::configureOrderingColumn(
								$record_class,
								$column
							);

							$order =& self::$info[$record_class]['order'];
							if (!isset($order[$column])) {
								$order[$column] = 'asc';
							}

							break;

						//
						// Special handling for URL columns
						//
						case 'url_columns':
							fORMColumn::configureLinkColumn(
								$record_class,
								$column
							);
							break;

						//
						// Special handling for e-mail columns
						//
						case 'email_columns':
							fORMColumn::configureEmailColumn(
								$record_class,
								$column
							);
							break;

						//
						// Special handling for money columns
						//
						case 'money_columns':
							fORMMoney::configureMoneyColumn(
								$record_class,
								$column,
								(!is_numeric($key)) ? $key : NULL
							);
							break;
					}
				}
			}

			//
			// If any password columns wer configured/set above, add our custom hook
			//
			if (count(self::$info[$record_class]['password_columns'])) {
				fORM::registerHookCallback(
					$record_class,
					'pre::validate()',
					iw::makeTarget(__CLASS__, 'validatePasswordColumns')
				);
			}

			//
			// Set all non-configurable / schema-provided information
			//
			self::$info[$record_class]['columns']        = array();
			self::$info[$record_class]['pkey_columns']   = array();
			self::$info[$record_class]['pkey_methods']   = array();
			self::$info[$record_class]['fkey_columns']   = array();
			self::$info[$record_class]['serial_columns'] = array();

			foreach ($schema->getColumnInfo($table) as $column => $info) {

				self::$info[$record_class]['columns'][] = $column;

				fORM::registerInspectCallback(
					$record_class,
					$column,
					iw::makeTarget(__CLASS__, 'inspectColumn')
				);

				if ($info['auto_increment']) {
					self::$info[$record_class]['serial_columns'][] = $column;
				}
			}

			foreach ($schema->getKeys($table, 'primary') as $column) {
				$method = 'get' . fGrammar::camelize($column, TRUE);

				self::$info[$record_class]['pkey_columns'][] = $column;
				self::$info[$record_class]['pkey_methods'][] = $method;
			}

			foreach ($schema->getKeys($table, 'foreign') as $fkey_info) {
				$column = $fkey_info['column'];

				self::$info[$record_class]['fkey_columns'][] = $column;
			}

			self::$info[$record_class]['is_relationship'] = !count(
				array_diff(
					self::$info[$record_class]['columns'],
					self::$info[$record_class]['pkey_columns']
				)
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
		 * Creates a record from a provided resource key.
		 *
		 * @static
		 * @access public
		 * @param string $record_class The Active Record Class
		 * @param string $resource_key A JSON encoded primary key string representation of the record
		 * @return fActiveRecord The active record matching the resource key
		 *
		 */
		static public function createFromResourceKey($record_class, $resource_key)
		{

			if (!self::classExists($record_class)) {
				throw new fProgrammerException(
					'Cannot create record of type %s, class does not exist',
					$record_class
				);
			}

			$resource_key = fJSON::decode($resource_key, TRUE);
			$pkey         = $resource_key['primary_key'];
			$record       = new $record_class($pkey);
			$friendly_id  = (isset($resource_key['friendly_id']))
				? $resource_key['friendly_id']
				: NULL;

			if ($friendly_id !== NULL) {
				$match_id = $record->__toString();

				if ($friendly_id != $match_id) {
					throw new fValidationException(
						'Provided friendly_id does not match.'
					);
				}
			}

			return $record;
		}

		/**
		 * Creates a record from a provided class, slug, and friendly_id.  The friendly_id is
		 * optional, but if is provided acts as an additional check against the validity of the
		 * record.  In short, a slug can either be a friendly slug 'such_as_this' using a slug
		 * column, or it can be a non-friendly numeric id.  If you are using numeric IDs for
		 * URLs like '/articles/1/the_time_i_ate_a_cheeseburger' without a slug column you can
		 * use a route such as the following: '/articles/:id/:friendly_id'.  If the 'friendly_id'
		 * is passed to this method, it will have to match the fURL::makeFriendly() version of
		 * the id_column, ensuring the non-canonical url '/articles/1/whatever' is not available.
		 *
		 * PLEASE NOTE: When a slug is created using the makeSlug() method, each of the primary
		 * key values is passed through fURL::makeFriendly().  In order for this method to work
		 * properly your primary key values must not change to be made friendly, i.e., they must
		 * be URL friendly/safe to begin with.
		 *
		 * @static
		 * @access public
		 * @param string $record_class The Active Record class
		 * @param string $slug A URL-friendly primary key string representation of the record
		 * @param string $friendly_id An optional URL friendly identifier to check the validity
		 * @return fActiveRecord The active record matching the slug
		 */
		static public function createFromSlug($record_class, $slug, $friendly_id = NULL)
		{
			if (!self::classExists($record_class)) {
				throw new fProgrammerException(
					'Cannot create record of type %s, class does not exist.',
					$record_class
				);
			} elseif ($column = self::getInfo($record_class, 'slug_column')) {
				return new $record_class(array($column => $slug));
			}

			$columns = self::getInfo($record_class, 'pkey_columns');
			$data    = explode(self::$fieldSeparator, $slug, count($columns));

			if (sizeof($data) < sizeof($columns)) {
				throw new fNotFoundException('Malformed slug for class %s.', $record_class);
			} elseif (count($columns) == 1) {
				$pkey = $data[0];
			} else {
				foreach ($columns as $column) {
					$pkey[$column] = array_shift($data);
				}
			}

			$record = new $record_class($pkey);

			if ($friendly_id !== NULL) {
				$match_id = fURL::makeFriendly($record->__toString(), NULL, self::$wordSeparator);

				if ($friendly_id != $match_id) {
					throw new fNotFoundException('Provided friendly_id does not match.');
				}
			}

			return $record;
		}

		/**
		 * Gets the the ordering of the Active Record class
		 *
		 * @static
		 * @access public
		 * @param string $record_class The Active Record class name
		 * @return array The ordering array for the Active Record class
		 */
		static public function getOrder($record_class)
		{
			return self::getInfo($record_class, 'order');
		}

		/**
		 * Gets the record name for an Active Record class
		 *
		 * @static
		 * @access public
		 * @param string $record_class The Active Record class name
		 * @return string The custom or default record translation
		 */
		static public function getRecordName($record_class)
		{
			return isset(self::$nameTranslations[$record_class])
				? self::$nameTranslations[$record_class]
				: fGrammar::underscorize($record_class);
		}

		/**
		 * Gets the record set name for an Active Record class
		 *
		 * @static
		 * @access public
		 * @param string $record_class The Active Record class name
		 * @return string The custom or default record set translation
		 */
		static public function getRecordSet($record_class)
		{
			return isset(self::$setTranslations[$record_class])
				? self::$setTranslations[$record_class]
				: fGrammar::pluralize($record_class);
		}

		/**
		 * Gets the record table name for an Active Record class
		 *
		 * @static
		 * @access public
		 * @param string $record_class The Active Record class name
		 * @return string The custom or default record table translation
		 */
		static public function getRecordTable($record_class)
		{
			return isset(self::$tableTranslations[$record_class])
				? self::$tableTranslations[$record_class]
				: fORM::tablize($record_class);
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
			$table  = self::getRecordTable($record_class);

			//
			// Populate basic information if it is not provided
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
				}
			}

			//
			// Determine any special formatting for the column
			//
			$image_columns = self::getInfo($record_class, 'image_columns');
			$file_columns  = self::getInfo($record_class, 'file_columns');
			$pass_columns  = self::getInfo($record_class, 'password_columns');
			$order_columns = self::getInfo($record_class, 'order_columns');
			$url_columns   = self::getInfo($record_class, 'url_columns');
			$email_columns = self::getInfo($record_class, 'email_columns');

			if ($column == self::getInfo($record_class, 'slug_column')) {
				$info['format'] = 'slug';
			} elseif (in_array($column, $order_columns)) {
				$info['format'] = 'ordering';
			} elseif (in_array($column, $image_columns)) {
				$info['format'] = 'image';
			} elseif (in_array($column, $file_columns)) {
				$info['format'] = 'file';
			} elseif (in_array($column, $pass_columns)) {
				$info['format'] = 'password';
			} elseif (in_array($column, $url_columns)) {
				$info['format'] = 'url';
			} elseif (in_array($column, $email_columns)) {
				$info['format'] = 'email';
			} elseif ($info['is_fkey']) {
				$relationships = $schema->getRelationships($table);

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

			//
			// Last ditch attempt to get a usable format
			//
			if (!isset($info['format'])) {
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
			}

			//
			// Determine additional properties
			//
			$fixed_columns  = self::getInfo($record_class, 'fixed_columns');
			$serial_columns = self::getInfo($record_class, 'serial_columns');
			$info['serial'] = FALSE;

			if (
				$info['format'] == 'ordering'
				|| in_array($column, $fixed_columns)
				|| in_array($column, $serial_columns)
			) {
				$info['fixed'] = TRUE;
			} else {
				$info['fixed'] = FALSE;
			}

			if (in_array($column, $serial_columns)) {
				$info['serial'] = TRUE;
			}

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
		 * @param array $old_values The original column values
		 * @param array $related The related records array for the record
		 * @param array $cache The cache array for the record
		 * @return void
		 */
		static public function resetCache($object, &$values, &$old_values, &$related_records, &$cache)
		{
			$record_class    = get_class($object);
			$slug_column     = self::getInfo($record_class, 'slug_column');
			$pkey_columns    = self::getInfo($record_class, 'pkey_columns');
			$changed_columns = array_keys($old_values);

			if (in_array($slug_column, $changed_columns)) {
				$object->slug = NULL;
			}

			if (count(array_intersect($pkey_columns, $changed_columns))) {
				$object->resourceKey = NULL;
				if ($object->slug) {
					$object->slug = NULL;
				}
			}
		}

		/**
		 * A validation hook for password columns. If any columns are set as password columns, this
		 * method will be registered to ensure that a password confirmation field matches the
		 * original field when storing the record or that if a password is already set, an empty
		 * value will result in no change.  In addition, this method ensures the password is
		 * hashed.
		 *
		 * @static
		 * @access public
		 * @param fActiveRecord The active record object
		 * @param array $values The new column values being set
		 * @param array $old_values The original column values
		 * @param array $related The related records array for the record
		 * @param array $cache The cache array for the record
		 * @param array $validation_messages An array of validation messages
		 * @return void
		 */
		static public function validatePasswordColumns($object, &$values, &$old_values, &$related_records, &$cache, &$validation_messages)
		{
			$record_class     = get_class($object);
			$password_columns = self::getInfo($record_class, 'password_columns');

			foreach ($password_columns as $password_column) {

				if (
					!empty($values[$password_column])     &&
					!empty($old_values[$password_column])
				) {

					$confirmation = Request::get(implode('-', array(
						'confirm',
						$password_column
					)));

					if (iw::checkSAPI('cli')) {
						$confirmation = $values[$password_column];
					}

					if ($confirmation == $values[$password_column]) {

						$values[$password_column] = fCryptography::hashPassword(
							$values[$password_column]
						);

					} else {
						$validation_messages[] = fText::compose(
							'%s: Does not match confirmation field',
							fGrammar::humanize($password_column)
						);
					}

				} elseif (!empty($old_values[$password_column])) {

					$values[$password_column] = end(
						$old_values[$password_column]
					);

				}
			}
		}

		/**
		 * A validation hook for the slug column.  This ensures that the slug contains only URL
		 * safe characters without requiring encoding.
		 *
		 * @static
		 * @access public
		 * @param fActiveRecord The active record object
		 * @param array $values The new column values being set
		 * @param array $old_values The original column values
		 * @param array $related The related records array for the record
		 * @param array $cache The cache array for the record
		 * @param array $validation_messages An array of validation messages
		 * @return void
		 */
		static public function validateSlugColumn($object, &$values, &$old_values, &$related_records, &$cache, &$validation_messages)
		{
			$record_class = get_class($object);
			$slug_column  = self::getInfo($record_class, 'slug_column');
			$id_column    = self::getInfo($record_class, 'id_column');

			//
			// If no value is set for the slug column, try to generate it
			// based on the ID column.
			//
			if (!$values[$slug_column] && isset($id_column)) {

				$id_value  = $values[$id_column];

				try {
					$revision    = NULL;
					$friendly_id = fURL::makeFriendly(
						$values[$id_column],
						NULL,
						self::$wordSeparator
					);

					do {

						$slug  = $friendly_id;
						$slug .= ($revision)
							? self::$wordSeparator . $revision
							: NULL;

						self::createFromSlug($record_class, $slug);
						$revision++;

					} while (TRUE);
				} catch (fNotFoundException $e) {
					$values[$slug_column] = $slug;
				}
			}

			//
			// If the value of the slug column is still empty, add a validation, otherwise ensure
			// that it is fURL::makeFriendly() compatible.
			//
			if (empty($values[$slug_column])) {
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
		 * Gets record information on a particular Active Record class.
		 *
		 * @static
		 * @access private
		 * @param string $record_class The Active Record class name
		 * @param string $key Specific information rquested, NULL for all class info
		 * @return mixed The class information requested
		 */
		static private function getInfo($record_class, $key = NULL)
		{
			if (isset(self::$info[$record_class])) {
				if ($key !== NULL) {
					if (isset(self::$info[$record_class][$key])) {
						return self::$info[$record_class][$key];
					}

				} else {
					return self::$info[$record_class];
				}
			}

			return NULL;
		}

		/**
		 * Represents the object as a string using the value of a configured or natural id_column.
		 * If no such column exists, it uses the human version of the record class.
		 *
		 * @access public
		 * @return string The string representation of the object
		 */
		public function __toString()
		{
			$record_class = get_class($this);

			if ($id_column = self::getInfo($record_class, 'id_column')) {
				$method = 'get' . fGrammar::camelize($id_column, TRUE);
				return $this->$method();
			}

			return fGrammar::humanize($record_class);
		}

		/**
		 * Get the value of the record's primary key as passed to the
		 * constructor or as a serialized string.
		 *
		 * @access public
		 * @param boolean $serialize Whether or not to serialize the pkey
		 * @return mixed The primary key, usable in the constructor
		 */
		public function getPrimaryKey($serialize = FALSE)
		{
			$record_class = get_class($this);
			$columns      = self::getInfo($record_class, 'pkey_columns');
			$pkey         = array();

			foreach ($columns as $column) {
				$get_method    = 'get' . fGrammar::camelize($column, TRUE);
				$pkey[$column] = $this->$get_method();

				// Gets columns returned as objects in string form

				if (is_object($pkey[$column])) {
					$pkey[$column] = (string) $pkey[$column];
				}
			}

			if (count($pkey) == 1) {
				$pkey = reset($pkey);
			}

			return ($serialize) ? fJSON::encode($pkey) : $pkey;
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
		 * Creates a resource key which can be comprised ultimately of the JSON serialized primary
		 * key and optionally a friendly identifier.  The returned value is not necessarily HTML
		 * safe and should be encoded if embedded in HTML.
		 *
		 * @access public
		 * @param boolean $friendly_id Whether or not to append a human friendly identifier
		 * @return string The JSON serialized resource key
		 */
		public function makeResourceKey($friendly_id = TRUE)
		{
			//
			// The cached resource key will be reset to NULL via the ::resetCache() callback in the
			// event any of the values comprising the primary key have changed.
			//
			if (!$this->resourceKey) {
				$record_class      = get_class($this);
				$resource_key      = array('primary_key' => $this->getPrimaryKey());
				$this->resourceKey = $resource_key;
			}

			if ($friendly_id === TRUE) {
				return fJSON::encode(array_merge(
					$this->resourceKey,
					array('friendly_id' => (string) $this)
				));
			}

			return fJSON::encode($this->resourceKey);
		}

		/**
		 * Creates a url friendly identifying slug.  If a slug_column is configured on the record
		 * it will use this value.  Otherwise it will be the primary key values, made friendly
		 * and separated by the field separator.  If the optional and default friendly_id is set
		 * to true, it will additionally add a friendly version of the id_column as an appended
		 * URL segment.
		 *
		 * @access public
		 * @param string $friendly_id Whether or not to append a human friendly identifier
		 * @return string The slug representation of the active record.
		 */
		public function makeSlug($friendly_id = TRUE)
		{
			//
			// The cached slug will be reset to NULL via the ::resetCache() callback in the event
			// any of the values comprising the slug or primary key have changed.
			//
			if (!$this->slug) {
				$record_class = get_class($this);
				$slug_column  = self::getInfo($record_class, 'slug_column');

				if ($slug_column) {
					$method = 'get' . fGrammar::camelize($slug_column, TRUE);
					$slug   = $this->$method();
				} else {
					if (!is_array($pkey = $this->getPrimaryKey())) {
						$pkey = array($pkey);
					}

					foreach ($pkey as $i => $value) {
						$pkey[$i] = fURL::makeFriendly(
							$value,
							NULL,
							self::$wordSeparator
						);
					}

					$slug = implode(self::$fieldSeparator, $pkey);
				}

				$this->slug = $slug;
			}

			if ($friendly_id === TRUE) {
				return implode('/', array($this->slug, fURL::makeFriendly(
					$this->__toString(),
					NULL,
					self::$wordSeparator
				)));
			}

			return $this->slug;
		}

		/**
		 * Populates a record from (non) namespaced parameters
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