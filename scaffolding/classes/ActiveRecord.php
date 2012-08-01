	/**
	 * The <%= $class %> is an active record and model representing a single
	 * <%= fGrammar::humanize($class) %> record.
	 *
	 * @author Matthew J. Sahagian [mjs] <gent@dotink.org>
	 * @copyright Copyright (c) 2011, Matthew J. Sahagian
	 */
	class <%= self::validateVariable($class) %> extends <%= self::validateVariable($parent_class) %>

	{
		/**
		 * Cached information about the class built during __init()
		 *
		 * @static
		 * @access private
		 * @var array
		 */
		static private $info = array();

		/**
		 * Initializes all static class information for the <%= $class %> model
		 *
		 * @static
		 * @access public
		 * @param array $config The configuration array
		 * @param array $element The element name of the configuration array
		 * @return void
		 */
		static public function __init(array $config = array(), $element = NULL)
		{
			$parent = get_parent_class(__CLASS__);
			$schema = fORMSchema::retrieve(__CLASS__);
			$table  = parent::classToRecordTable(__CLASS__);
			$ukeys  = $schema->getKeys($table, 'unique');

			//
			// Set Configuration Defaults
			//

			self::$info = array(
				'columns'        => array(),
				'pkey_columns'   => array(),
				'pkey_methods'   => array(),
				'fkey_columns'   => array(),
				'serial_columns' => array(),
				'fixed_columns'  => array(),
				'ordering'       => array(),
				'id_column'      => NULL,
				'slug_column'    => NULL,
			);

			//
			// Set an explicit ID column or attempt to find a natural one
			//

			if (isset($config['id_column']) && !empty($config['id_column'])) {
				self::$info['id_column'] = $config['id_column'];
			} else {
				if (sizeof($ukeys) == 1 && sizeof($ukeys[0]) == 1) {
					self::$info['id_column'] = $ukeys[0][0];
				}
			}

			//
			// If we have a slug column make sure it's unique across all records
			//

			if (isset($config['slug_column']) && !empty($config['slug_column'])) {
				$valid_column = FALSE;
				$slug_column  = $config['slug_column'];

				foreach ($ukeys as $ukey) {
					if (count($ukey) == 1 && $ukey[0] == $slug_column) {
						$valid_column = TRUE;
					}
				}

				if ($valid_column) {
					fORM::registerHookCallback(
						__CLASS__,
						'pre::validate()',
						iw::makeTarget($parent, 'resolveSlugColumn')
					);
				}

				self::$info['slug_column'] = $slug_column;
			}

			//
			// Set any explicitly configured ordering
			//

			if (isset($config['ordering']) && is_array($config['ordering'])) {
				self::$info['ordering'] = $config['ordering'];
			}

			//
			// Enabled fixed columns (columns which cannot be populated)
			//

			if (isset($config['fixed_columns']) && is_array($config['fixed_columns'])) {
				self::$info['fixed_columns'] = $config['fixed_columns'];
			}

			//
			// Set all non-configurable / schema-provided information
			//

			foreach ($schema->getColumnInfo($table) as $column => $info) {

				$fixed_dates = array(
					'date'      => 'CURRENT_DATE',
					'time'      => 'CURRENT_TIME',
					'timestamp' => 'CURRENT_TIMESTAMP'
				);

				fORM::registerInspectCallback(
					__CLASS__,
					$column,
					iw::makeTarget($parent, 'inspectColumn')
				);

				if ($info['auto_increment']) {
					self::$info['serial_columns'][] = $column;
					self::$info['fixed_columns']    = array_merge(
						self::$info['fixed_columns'],
						array($column)
					);
				} elseif (in_array($info['type'], array_keys($fixed_dates))) {
					if ($info['default'] == $fixed_dates[$info['type']]) {
						self::$info['fixed_columns'] = array_merge(
							self::$info['fixed_columns'],
							array($column)
						);
					}
				}

				self::$info['columns'][] = $column;
			}

			foreach ($schema->getKeys($table, 'primary') as $column) {
				self::$info['pkey_columns'][] = $column;
				self::$info['pkey_methods'][] = 'get' . fGrammar::camelize($column, TRUE);
			}

			foreach ($schema->getKeys($table, 'foreign') as $fkey_info) {
				self::$info['fkey_columns'][] = $fkey_info['column'];
			}

			return TRUE;
		}

		/**
		 * Determines if an Active Record class has been defined by ensuring the class exists
		 * and it is a subclass of this class.  This is, in part, a workaround for a PHP bug
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
		 * Fetches all or specific information about the record type
		 *
		 * @static
		 * @access protected
		 * @param string $key The key of the information to fetch, NULL (default) fetches all keys
		 * @return mixed The information
		 */
		static protected function fetchInfo($key = NULL)
		{
			if (!$key) {
				return self::$info;
			}

			return array_key_exists($key, self::$info)
				? self::$info[$key]
				: NULL;
		}

		/**
		 * Creates a new <%= $class %> from provided slug.
		 *
		 * @static
		 * @access public
		 * @param string $slug A slug or URL-friendly primary key representation of the record
		 * @return fActiveRecord The active record matching the slug
		 */
		static public function createFromSlug($slug)
		{
			return parent::createFromSlug(__CLASS__, $slug);
		}

		/**
		 * Creates a new <%= $class %> from the provided resource key.
		 *
		 * @static
		 * @access public
		 * @param string $resource_key A JSON encoded primary key
		 * @return fActiveRecord The active record matching the resource key
		 *
		 */
		static public function createFromResourceKey($resource_key)
		{
			return parent::createFromResourceKey(__CLASS__, $resource_key);
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
			if ($id_column = self::fetchInfo('id_column')) {
				$method = 'get' . fGrammar::camelize($id_column, TRUE);
				return $this->$method();
			}

			return fGrammar::humanize(__CLASS__);
		}
	}
