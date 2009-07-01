<?php
/**
 * An [http://en.wikipedia.org/wiki/Active_record_pattern active record pattern] base class
 * 
 * This class uses fORMSchema to inspect your database and provides an
 * OO interface to a single database table. The class dynamically handles
 * method calls for getting, setting and other operations on columns. It also
 * dynamically handles retrieving and storing related records.
 * 
 * @copyright  Copyright (c) 2007-2009 Will Bond, others
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @author     Will Bond, iMarc LLC [wb-imarc] <will@imarc.net>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fActiveRecord
 * 
 * @version    1.0.0b25
 * @changes    1.0.0b25  Updated ::validate() to use new fORMValidation API, including new message search/replace functionality [wb, 2009-07-01]
 * @changes    1.0.0b24  Changed ::validate() to remove duplicate validation messages [wb, 2009-06-30]
 * @changes    1.0.0b23  Updated code for new fORMValidation::validateRelated() API [wb, 2009-06-26]
 * @changes    1.0.0b22  Added support for the $formatting parameter to encode methods on char, text and varchar columns [wb, 2009-06-19]
 * @changes    1.0.0b21  Performance tweaks and updates for fORM and fORMRelated API changes [wb, 2009-06-15]
 * @changes    1.0.0b20  Changed replacement values in preg_replace() calls to be properly escaped [wb, 2009-06-11]
 * @changes    1.0.0b19  Added `list{RelatedRecords}()` methods, updated code for new fORMRelated API [wb, 2009-06-02]
 * @changes    1.0.0b18  Changed ::store() to use new fORMRelated::store() method [wb, 2009-06-02]
 * @changes    1.0.0b17  Added some missing parameter information to ::reflect() [wb, 2009-06-01]
 * @changes    1.0.0b16  Fixed bugs in ::__clone() and ::replicate() related to recursive relationships [wb-imarc, 2009-05-20]
 * @changes    1.0.0b15  Fixed an incorrect variable reference in ::store() [wb, 2009-05-06]
 * @changes    1.0.0b14  ::store() no longer tries to get an auto-incrementing ID from the database if a value was set [wb, 2009-05-02]
 * @changes    1.0.0b13  ::delete(), ::load(), ::populate() and ::store() now return the record to allow for method chaining [wb, 2009-03-23]
 * @changes    1.0.0b12  ::set() now removes commas from integers and floats to prevent validation issues [wb, 2009-03-22]
 * @changes    1.0.0b11  ::encode() no longer adds commas to floats [wb, 2009-03-22]
 * @changes    1.0.0b10  ::__wakeup() no longer registers the record as the definitive copy in the identity map [wb, 2009-03-22]
 * @changes    1.0.0b9   Changed ::__construct() to populate database default values when a non-existing record is instantiated [wb, 2009-01-12]
 * @changes    1.0.0b8   Fixed ::exists() to properly detect cases when an existing record has one or more NULL values in the primary key [wb, 2009-01-11]
 * @changes    1.0.0b7   Fixed ::__construct() to not trigger the post::__construct() hook when force-configured [wb, 2008-12-30]
 * @changes    1.0.0b6   ::__construct() now accepts an associative array matching any unique key or primary key, fixed the post::__construct() hook to be called once for each record [wb, 2008-12-26]
 * @changes    1.0.0b5   Fixed ::replicate() to use plural record names for related records [wb, 2008-12-12]
 * @changes    1.0.0b4   Added ::replicate() to allow cloning along with related records [wb, 2008-12-12]
 * @changes    1.0.0b3   Changed ::__clone() to clone objects contains in the values and cache arrays [wb, 2008-12-11]
 * @changes    1.0.0b2   Added the ::__clone() method to properly duplicate a record [wb, 2008-12-04]
 * @changes    1.0.0b    The initial implementation [wb, 2007-08-04]
 */
abstract class fActiveRecord
{
	// The following constants allow for nice looking callbacks to static methods
	const assign         = 'fActiveRecord::assign';
	const changed        = 'fActiveRecord::changed';
	const forceConfigure = 'fActiveRecord::forceConfigure';
	const hasOld         = 'fActiveRecord::hasOld';
	const retrieveOld    = 'fActiveRecord::retrieveOld';
	
	
	/**
	 * An array of flags indicating a class has been configured
	 * 
	 * @var array
	 */
	static protected $configured = array();
	
	
	/**
	 * Maps objects via their primary key
	 * 
	 * @var array
	 */
	static protected $identity_map = array();
	
	
	/**
	 * Keeps track of the recursive call level of replication so we can clear the map
	 * 
	 * @var integer
	 */
	static protected $replicate_level = 0;
	
	
	/**
	 * Keeps a list of records that have been replicated
	 * 
	 * @var array
	 */
	static protected $replicate_map = array();
	
	
	/**
	 * Sets a value to the `$values` array, preserving the old value in `$old_values`
	 *
	 * @internal
	 * 
	 * @param  array  &$values      The current values
	 * @param  array  &$old_values  The old values
	 * @param  string $column       The column to set
	 * @param  mixed  $value        The value to set
	 * @return void
	 */
	static public function assign(&$values, &$old_values, $column, $value)
	{
		if (!isset($old_values[$column])) {
			$old_values[$column] = array();
		}
		
		$old_values[$column][] = $values[$column];
		$values[$column]       = $value;	
	}
	
	
	/**
	 * Checks to see if a value has changed
	 *
	 * @internal
	 * 
	 * @param  array  &$values      The current values
	 * @param  array  &$old_values  The old values
	 * @param  string $column       The column to check
	 * @return boolean  If the value for the column specified has changed
	 */
	static public function changed(&$values, &$old_values, $column)
	{
		if (!isset($old_values[$column])) {
			return FALSE;
		}
		
		return $old_values[$column][0] != $values[$column];	
	}
	
	
	/**
	 * Composes text using fText if loaded
	 * 
	 * @param  string  $message    The message to compose
	 * @param  mixed   $component  A string or number to insert into the message
	 * @param  mixed   ...
	 * @return string  The composed and possible translated message
	 */
	static protected function compose($message)
	{
		$args = array_slice(func_get_args(), 1);
		
		if (class_exists('fText', FALSE)) {
			return call_user_func_array(
				array('fText', 'compose'),
				array($message, $args)
			);
		} else {
			return vsprintf($message, $args);
		}
	}
	
	
	/**
	 * Ensures that ::configure() has been called for the class
	 *
	 * @internal
	 * 
	 * @param  string $class  The class to configure
	 * @return void
	 */
	static public function forceConfigure($class)
	{
		if (isset(self::$configured[$class])) {
			return;	
		}
		new $class();
	}
	
	
	/**
	 * Checks to see if an old value exists for a column 
	 *
	 * @internal
	 * 
	 * @param  array  &$old_values  The old values
	 * @param  string $column       The column to set
	 * @return boolean  If an old value for that column exists
	 */
	static public function hasOld(&$old_values, $column)
	{
		return array_key_exists($column, $old_values);
	}
	
	
	/**
	 * Retrieves the oldest value for a column or all old values
	 *
	 * @internal
	 * 
	 * @param  array   &$old_values  The old values
	 * @param  string  $column       The column to get
	 * @param  mixed   $default      The default value to return if no value exists
	 * @param  boolean $return_all   Return the array of all old values for this column instead of just the oldest
	 * @return mixed  The old value for the column
	 */
	static public function retrieveOld(&$old_values, $column, $default=NULL, $return_all=FALSE)
	{
		if (!isset($old_values[$column])) {
			return $default;	
		}
		
		if ($return_all === TRUE) {
			return $old_values[$column];	
		}
		
		return $old_values[$column][0];
	}
	
	
	/**
	 * A data store for caching data related to a record, the structure of this is completely up to the developer using it
	 * 
	 * @var array
	 */
	protected $cache = array();
	
	/**
	 * The old values for this record
	 * 
	 * Column names are the keys, but a column key will only be present if a
	 * value has changed. The value associated with each key is an array of
	 * old values with the first entry being the oldest value. The static 
	 * methods ::assign(), ::changed(), ::hasOld() and ::retrieveOld() are the
	 * best way to interact with this array.
	 * 
	 * @var array
	 */
	protected $old_values = array();
	
	/**
	 * Records that are related to the current record via some relationship
	 * 
	 * This array is used to cache related records so that a database query
	 * is not required each time related records are accessed. The fORMRelated
	 * class handles most of the interaction with this array.
	 * 
	 * @var array
	 */
	protected $related_records = array();
	
	/**
	 * The values for this record
	 * 
	 * This array always contains every column in the database table as a key
	 * with the value being the current value. 
	 * 
	 * @var array
	 */
	protected $values = array();
	
	
	/**
	 * Handles all method calls for columns, related records and hook callbacks
	 * 
	 * Dynamically handles `get`, `set`, `prepare`, `encode` and `inspect`
	 * methods for each column in this record. Method names are in the form
	 * `verbColumName()`.
	 * 
	 * This method also handles `associate`, `build`, `count` and `link` verbs
	 * for records in many-to-many relationships; `build`, `count` and
	 * `populate` verbs for all related records in one-to-many relationships
	 * and the `create` verb for all related records in *-to-one relationships.
	 * 
	 * Method callbacks registered through fORM::registerActiveRecordMethod()
	 * will be delegated via this method.
	 * 
	 * @param  string $method_name  The name of the method called
	 * @param  array  $parameters   The parameters passed
	 * @return void
	 */
	public function __call($method_name, $parameters)
	{
		$class = get_class($this);
		
		if ($callback = fORM::getActiveRecordMethod($class, $method_name)) {
			return call_user_func_array(
				$callback,
				array(
					$this,
					&$this->values,
					&$this->old_values,
					&$this->related_records,
					&$this->cache,
					$method_name,
					$parameters
				)
			);
		}
		
		list ($action, $subject) = fORM::parseMethod($method_name);
		
		// This will prevent quiet failure
		if (in_array($action, array('set', 'associate', 'inject', 'tally')) && sizeof($parameters) < 1) {
			throw new fProgrammerException(
				'The method, %s(), requires at least one parameter',
				$method_name
			);
		}
		
		switch ($action) {
			
			// Value methods
			case 'encode':
				if (isset($parameters[0])) {
					return $this->encode($subject, $parameters[0]);
				}
				return $this->encode($subject);
			
			case 'get':
				if (isset($parameters[0])) {
					return $this->get($subject, $parameters[0]);
				}
				return $this->get($subject);
			
			case 'inspect':
				if (isset($parameters[0])) {
					return $this->inspect($subject, $parameters[0]);
				}
				return $this->inspect($subject);
			
			case 'prepare':
				if (isset($parameters[0])) {
					return $this->prepare($subject, $parameters[0]);
				}
				return $this->prepare($subject);
			
			case 'set':
				return $this->set($subject, $parameters[0]);
			
			// Related data methods
			case 'associate':
				$subject = fGrammar::singularize($subject);
				$subject = fGrammar::camelize($subject, TRUE);
				
				if (isset($parameters[1])) {
					return fORMRelated::associateRecords($class, $this->related_records, $subject, $parameters[0], $parameters[1]);
				}
				return fORMRelated::associateRecords($class, $this->related_records, $subject, $parameters[0]);
			
			case 'build':
				$subject = fGrammar::singularize($subject);
				$subject = fGrammar::camelize($subject, TRUE);
				
				if (isset($parameters[0])) {
					return fORMRelated::buildRecords($class, $this->values, $this->related_records, $subject, $parameters[0]);
				}
				return fORMRelated::buildRecords($class, $this->values, $this->related_records, $subject);
			
			case 'count':
				$subject = fGrammar::singularize($subject);
				$subject = fGrammar::camelize($subject, TRUE);
				
				if (isset($parameters[0])) {
					return fORMRelated::countRecords($class, $this->values, $this->related_records, $subject, $parameters[0]);
				}
				return fORMRelated::countRecords($class, $this->values, $this->related_records, $subject);
			
			case 'create':
				$subject = fGrammar::camelize($subject, TRUE);
				
				if (isset($parameters[0])) {
					return fORMRelated::createRecord($class, $this->values, $subject, $parameters[0]);
				}
				return fORMRelated::createRecord($class, $this->values, $subject);
			 
			case 'inject':
				$subject = fGrammar::singularize($subject);
				$subject = fGrammar::camelize($subject, TRUE);
				 
				if (isset($parameters[1])) {
					return fORMRelated::setRecordSet($class, $this->related_records, $subject, $parameters[0], $parameters[1]);
				}
				return fORMRelated::setRecordSet($class, $this->related_records, $subject, $parameters[0]);

			case 'link':
				$subject = fGrammar::singularize($subject);
				$subject = fGrammar::camelize($subject, TRUE);
				
				if (isset($parameters[0])) {
					return fORMRelated::linkRecords($class, $this->related_records, $subject, $parameters[0]);
				}
				return fORMRelated::linkRecords($class, $this->related_records, $subject);
			
			case 'list':
				$subject = fGrammar::singularize($subject);
				$subject = fGrammar::camelize($subject, TRUE);
				
				if (isset($parameters[0])) {
					return fORMRelated::getPrimaryKeys($class, $this->values, $this->related_records, $subject, $parameters[0]);
				}
				return fORMRelated::getPrimaryKeys($class, $this->values, $this->related_records, $subject);
			
			case 'populate':
				$subject = fGrammar::singularize($subject);
				$subject = fGrammar::camelize($subject, TRUE);
				
				if (isset($parameters[0])) {
					return fORMRelated::populateRecords($class, $this->related_records, $subject, $parameters[0]);
				}
				return fORMRelated::populateRecords($class, $this->related_records, $subject);
			
			case 'tally':
				$subject = fGrammar::singularize($subject);
				$subject = fGrammar::camelize($subject, TRUE);
				
				if (isset($parameters[1])) {
					return fORMRelated::setCount($class, $this->related_records, $subject, $parameters[0], $parameters[1]);
				}
				return fORMRelated::setCount($class, $this->related_records, $subject, $parameters[0]);
			
			// Error handler
			default:
				throw new fProgrammerException(
					'Unknown method, %s(), called',
					$method_name
				);
		}
	}
	
	
	/**
	 * Creates a clone of a record
	 * 
	 * If the record has an auto incrementing primary key, the primary key will
	 * be erased in the clone. If the primary key is not auto incrementing,
	 * the primary key will be left as-is in the clone. In either situation the
	 * clone will return `FALSE` from the ::exists() method until ::store() is
	 * called.
	 * 
	 * @internal
	 * 
	 * @return fActiveRecord
	 */
	public function __clone()
	{
		$class = get_class($this);
		
		// Copy values and cache, making sure objects are cloned to prevent reference issues
		$temp_values  = $this->values;
		$new_values   = array();
		$this->values =& $new_values;
		foreach ($temp_values as $column => $value) {
			$this->values[$column] = fORM::replicate($class, $column, $value);
		}
		
		$temp_cache  = $this->cache;
		$new_cache   = array();
		$this->cache =& $new_cache;
		foreach ($temp_cache as $key => $value) {
			if (is_object($value)) {
				$this->cache[$key] = clone $value;
			} else {
				$this->cache[$key] = $value;
			}		
		}
		
		// Related records are purged
		$new_related_records   = array();
		$this->related_records =& $new_related_records;
		
		// Old values are changed to look like the record is non-existant
		$new_old_values   = array();
		$this->old_values =& $new_old_values;
		
		foreach (array_keys($this->values) as $key) {
			$this->old_values[$key] = array(NULL);
		}
		
		// If we have a single auto incrementing primary key, remove the value
		$table      = fORM::tablize($class);
		$pk_columns = fORMSchema::retrieve()->getKeys($table, 'primary');
		
		if (sizeof($pk_columns) == 1 && fORMSchema::retrieve()->getColumnInfo($table, $pk_columns[0], 'auto_increment')) {
			$this->values[$pk_columns[0]] = NULL;
			unset($this->old_values[$pk_columns[0]]);
		}		
	}
	
	
	/**
	 * Creates a new record or loads one from the database - if a primary key or unique key is provided the record will be loaded
	 * 
	 * @throws fNotFoundException  When the record specified by `$key` can not be found in the database
	 * 
	 * @param  mixed $key  The primary key or unique key value(s) - single column primary keys will accept a scalar value, all others must be an associative array of `(string) {column} => (mixed) {value}`
	 * @return fActiveRecord
	 */
	public function __construct($key=NULL)
	{
		$class = get_class($this);
		
		// If the features of this class haven't been set yet, do it
		if (!isset(self::$configured[$class])) {
			$this->configure();
			self::$configured[$class] = TRUE;
			
			// If the configuration was forced, prevent the post::__construct() hook from
			// being triggered since it is not really a real record instantiation
			$trace = array_slice(debug_backtrace(), 0, 2);
			
			$is_forced = sizeof($trace) == 2;
			$is_forced = $is_forced && $trace[1]['function'] == 'forceConfigure';
			$is_forced = $is_forced && isset($trace[1]['class']);
			$is_forced = $is_forced && $trace[1]['type'] == '::';
			$is_forced = $is_forced && in_array($trace[1]['class'], array('fActiveRecord', $class));
			
			if ($is_forced) {
				return;	
			}
		}
		
		if (fORM::getActiveRecordMethod($class, '__construct')) {
			return $this->__call('__construct', array($key));
		}
		
		// Handle loading by a result object passed via the fRecordSet class
		if (is_object($key) && $key instanceof fResult) {
			
			if ($this->loadFromIdentityMap($key)) {
				return;
			}
			
			$this->loadFromResult($key);
		
		// Handle loading an object from the database
		} elseif ($key !== NULL) {
			
			$table      = fORM::tablize($class);
			$pk_columns = fORMSchema::retrieve()->getKeys($table, 'primary');
			
			// If the primary key does not look properly formatted, check to see if it is a UNIQUE key
			$is_unique_key = FALSE;
			if (is_array($key) && (sizeof($pk_columns) == 1 || array_keys($key) != $pk_columns)) {
				$unique_keys = fORMSchema::retrieve()->getKeys($table, 'unique');
				$key_keys    = array_keys($key);
				foreach ($unique_keys as $unique_key) {
					if ($key_keys == $unique_key) {
						$is_unique_key = TRUE;
					}
				}	
			}
			
			$wrong_keys = is_array($key) && array_keys($key) != $pk_columns;
			$wrong_type = !is_array($key) && (sizeof($pk_columns) != 1 || !is_scalar($key));
			
			// If we didn't find a UNIQUE key and primary key doesn't look right we fail
			if (!$is_unique_key && ($wrong_keys || $wrong_type)) {
				throw new fProgrammerException(
					'An invalidly formatted primary or unique key was passed to this %s object',
					fORM::getRecordName($class)
				);
			}
			
			if ($is_unique_key) {
				
				$result = $this->fetchResultFromUniqueKey($key);
				if ($this->loadFromIdentityMap($result)) {
					return;
				}
				$this->loadFromResult($result);
				
			} else {
				
				if ($this->loadFromIdentityMap($key)) {
					return;
				}
				
				// Assign the primary key values for loading
				if (is_array($key)) {
					foreach ($pk_columns as $pk_column) {
						$this->values[$pk_column] = $key[$pk_column];
					}
				} else {
					$this->values[$pk_columns[0]] = $key;
				}
				
				$this->load();
			}
			
		// Create an empty array for new objects
		} else {
			$column_info = fORMSchema::retrieve()->getColumnInfo(fORM::tablize($class));
			foreach ($column_info as $column => $info) {
				$this->values[$column] = NULL;
				if ($info['default'] !== NULL) {
					self::assign(
						$this->values,
						$this->old_values,
						$column,
						fORM::objectify($class, $column, $info['default'])
					);	
				}
			}
		}
		
		fORM::callHookCallbacks(
			$class,
			'post::__construct()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache
		);
	}
	
	
	/**
	 * All requests that hit this method should be requests for callbacks
	 * 
	 * @internal
	 * 
	 * @param  string $method  The method to create a callback for
	 * @return callback  The callback for the method requested
	 */
	public function __get($method)
	{
		return array($this, $method);		
	}
	
	
	/**
	 * Configure itself when coming out of the session. Records from the session are NOT hooked into the identity map.
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	public function __wakeup()
	{
		$class = get_class($this);
		
		if (!isset(self::$configured[$class])) {
			$this->configure();
			self::$configured[$class] = TRUE;
		}		
	}
	
	
	/**
	 * Allows the programmer to set features for the class
	 * 
	 * This method is only called once per page load for each class.
	 * 
	 * @return void
	 */
	protected function configure()
	{
	}
	
	
	/**
	 * Creates the SQL to insert this record
	 *
	 * @param  array $sql_values  The SQL-formatted values for this record
	 * @return string  The SQL insert statement
	 */
	protected function constructInsertSQL($sql_values)
	{
		$sql = 'INSERT INTO ' . fORM::tablize(get_class($this)) . ' (';
		
		$columns = '';
		$values  = '';
		
		$column_num = 0;
		foreach ($sql_values as $column => $sql_value) {
			if ($column_num) { $columns .= ', '; $values .= ', '; }
			$columns .= $column;
			$values  .= $sql_value;
			$column_num++;
		}
		$sql .= $columns . ') VALUES (' . $values . ')';
		return $sql;
	}
	
	
	/**
	 * Creates the SQL to update this record
	 *
	 * @param  array $sql_values  The SQL-formatted values for this record
	 * @return string  The SQL update statement
	 */
	protected function constructUpdateSQL($sql_values)
	{
		$table = fORM::tablize(get_class($this));
		
		$sql = 'UPDATE ' . $table . ' SET ';
		$column_num = 0;
		foreach ($sql_values as $column => $sql_value) {
			if ($column_num) { $sql .= ', '; }
			$sql .= $column . ' = ' . $sql_value;
			$column_num++;
		}
		
		$sql .= ' WHERE ' . fORMDatabase::createPrimaryKeyWhereClause($table, $table, $this->values, $this->old_values);
		
		return $sql;
	}
	
	
	/**
	 * Deletes a record from the database, but does not destroy the object
	 * 
	 * This method will start a database transaction if one is not already active.
	 * 
	 * @return fActiveRecord  The record object, to allow for method chaining
	 */
	public function delete()
	{
		$class = get_class($this);
		
		if (fORM::getActiveRecordMethod($class, 'delete')) {
			return $this->__call('delete', array());
		}
		
		if (!$this->exists()) {
			throw new fProgrammerException(
				'This %s object does not yet exist in the database, and thus can not be deleted',
				fORM::getRecordName($class)
			);
		}
		
		fORM::callHookCallbacks(
			$this, 'pre::delete()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache
		);
		
		$table = fORM::tablize($class);
		
		$inside_db_transaction = fORMDatabase::retrieve()->isInsideTransaction();
		
		try {
			
			if (!$inside_db_transaction) {
				fORMDatabase::retrieve()->translatedQuery('BEGIN');
			}
			
			fORM::callHookCallbacks(
				$this,
				'post-begin::delete()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->cache
			);
			
			// Check to ensure no foreign dependencies prevent deletion
			$one_to_many_relationships  = fORMSchema::retrieve()->getRelationships($table, 'one-to-many');
			$many_to_many_relationships = fORMSchema::retrieve()->getRelationships($table, 'many-to-many');
			
			$relationships = array_merge($one_to_many_relationships, $many_to_many_relationships);
			$records_sets_to_delete = array();
			
			$restriction_messages = array();
			
			foreach ($relationships as $relationship) {
				
				// Figure out how to check for related records
				$type = (isset($relationship['join_table'])) ? 'many-to-many' : 'one-to-many';
				$route = fORMSchema::getRouteNameFromRelationship($type, $relationship);
				
				$related_class   = fORM::classize($relationship['related_table']);
				$related_objects = fGrammar::pluralize($related_class);
				$method          = 'build' . $related_objects;
				
				// Grab the related records
				$record_set = $this->$method($route);
				
				// If there are none, we can just move on
				if (!$record_set->count()) {
					continue;
				}
				
				if ($type == 'one-to-many' && $relationship['on_delete'] == 'cascade') {
					$records_sets_to_delete[] = $record_set;
				}
				
				if ($relationship['on_delete'] == 'restrict' || $relationship['on_delete'] == 'no_action') {
					
					// Otherwise we have a restriction
					$related_class_name  = fORM::classize($relationship['related_table']);
					$related_record_name = fORM::getRecordName($related_class_name);
					$related_record_name = fGrammar::pluralize($related_record_name);
					
					$restriction_messages[] = self::compose("One or more %s references it", $related_record_name);
				}
			}
			
			if ($restriction_messages) {
				throw new fValidationException(
					sprintf(
						"<p>%1\$s</p>\n<ul>\n<li>%2\$s</li>\n</ul>",
						self::compose('This %s can not be deleted because:', fORM::getRecordName($class)),
						join("</li>\n<li>", $restriction_messages)
					)
				);
			}
			
			
			// Delete this record
			$sql    = 'DELETE FROM ' . $table . ' WHERE ' . fORMDatabase::createPrimaryKeyWhereClause($table, $table, $this->values, $this->old_values);
			$result = fORMDatabase::retrieve()->translatedQuery($sql);
			
			
			// Delete related records
			foreach ($records_sets_to_delete as $record_set) {
				foreach ($record_set as $record) {
					if ($record->exists()) {
						$record->delete();
					}
				}
			}
			
			fORM::callHookCallbacks(
				$this,
				'pre-commit::delete()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->cache
			);
			
			if (!$inside_db_transaction) {
				fORMDatabase::retrieve()->translatedQuery('COMMIT');
			}
			
			fORM::callHookCallbacks(
				$this,
				'post-commit::delete()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->cache
			);
			
			// If we just deleted an object that has an auto-incrementing primary key,
			// lets delete that value from the object since it is no longer valid
			$pk_columns  = fORMSchema::retrieve()->getKeys($table, 'primary');
			if (sizeof($pk_columns) == 1 && fORMSchema::retrieve()->getColumnInfo($table, $pk_columns[0], 'auto_increment')) {
				$this->values[$pk_columns[0]] = NULL;
				unset($this->old_values[$pk_columns[0]]);
			}
			
		} catch (fException $e) {
			if (!$inside_db_transaction) {
				fORMDatabase::retrieve()->translatedQuery('ROLLBACK');
			}
			
			fORM::callHookCallbacks(
				$this,
				'post-rollback::delete()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->cache
			);
			
			// Check to see if the validation exception came from a related record, and fix the message
			if ($e instanceof fValidationException) {
				$message = $e->getMessage();
				$search  = self::compose('This %s can not be deleted because:', fORM::getRecordName($class));
				if (stripos($message, $search) === FALSE) {
					$regex       = self::compose('This %s can not be deleted because:', '__');
					$regex_parts = explode('__', $regex);
					$regex       = '#(' . preg_quote($regex_parts[0], '#') . ').*?(' . preg_quote($regex_parts[0], '#') . ')#';
					
					$message = preg_replace($regex, '\1' . strtr(fORM::getRecordName($class), array('\\' => '\\\\', '$' => '\\$')) . '\2', $message);
					
					$find          = self::compose("One or more %s references it", '__');
					$find_parts    = explode('__', $find);
					$find_regex    = '#' . preg_quote($find_parts[0], '#') . '(.*?)' . preg_quote($find_parts[1], '#') . '#';
					
					$replace       = self::compose("One or more %s indirectly references it", '__');
					$replace_parts = explode('__', $replace);
					$replace_regex = strtr($replace_parts[0], array('\\' => '\\\\', '$' => '\\$')) . '\1' . strtr($replace_parts[1], array('\\' => '\\\\', '$' => '\\$'));
					
					$message = preg_replace($find_regex, $replace_regex, $regex);
					throw new fValidationException($message);
				}
			}
			
			throw $e;
		}
		
		fORM::callHookCallbacks(
			$this,
			'post::delete()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache
		);
		
		return $this;
	}
	
	
	/**
	 * Retrieves a value from the record and prepares it for output into an HTML form element.
	 * 
	 * Below are the transformations performed:
	 *  
	 *  - **varchar, char, text**: will run through fHTML::encode(), if `TRUE` is passed the text will be run through fHTML::convertNewLinks() and fHTML::makeLinks()
	 *  - **float**: takes 1 parameter to specify the number of decimal places
	 *  - **date, time, timestamp**: `format()` will be called on the fDate/fTime/fTimestamp object with the 1 parameter specified
	 *  - **objects**: the object will be converted to a string by `__toString()` or a `(string)` cast and then will be run through fHTML::encode()
	 *  - **all other data types**: the value will be run through fHTML::encode()
	 * 
	 * @param  string $column      The name of the column to retrieve
	 * @param  string $formatting  The formatting string
	 * @return string  The encoded value for the column specified
	 */
	protected function encode($column, $formatting=NULL)
	{
		if (!array_key_exists($column, $this->values)) {
			throw new fProgrammerException(
				'The column specified, %s, does not exist',
				$column
			);
		}
		
		$table       = fORM::tablize(get_class($this));
		$column_type = fORMSchema::retrieve()->getColumnInfo($table, $column, 'type');
		
		// Ensure the programmer is calling the function properly
		if ($column_type == 'blob') {
			throw new fProgrammerException(
				'The column specified, %s, does not support forming because it is a blob column',
				$column
			);
		}
		
		if ($formatting !== NULL && in_array($column_type, array('boolean', 'integer'))) {
			throw new fProgrammerException(
				'The column specified, %s, does not support any formatting options',
				$column
			);
		}
		
		// Grab the value for empty value checking
		$method_name = 'get' . fGrammar::camelize($column, TRUE);
		$value       = $this->$method_name();
		
		// Date/time objects
		if (is_object($value) && in_array($column_type, array('date', 'time', 'timestamp'))) {
			if ($formatting === NULL) {
				throw new fProgrammerException(
					'The column specified, %s, requires one formatting parameter, a valid date() formatting string',
					$column
				);
			}
			$value = $value->format($formatting);
		}
		
		// Other objects
		if (is_object($value) && is_callable(array($value, '__toString'))) {
			$value = $value->__toString();
		} elseif (is_object($value)) {
			$value = (string) $value;	
		}
		
		// Make sure we don't mangle a non-float value
		if ($column_type == 'float' && is_numeric($value)) {
			$column_decimal_places = fORMSchema::retrieve()->getColumnInfo($table, $column, 'decimal_places');
			
			// If the user passed in a formatting value, use it
			if ($formatting !== NULL && is_numeric($formatting)) {
				$decimal_places = (int) $formatting;
				
			// If the column has a pre-defined number of decimal places, use that
			} elseif ($column_decimal_places !== NULL) {
				$decimal_places = $column_decimal_places;
			
			// This figures out how many decimal places are part of the current value
			} else {
				$value_parts    = explode('.', $value);
				$decimal_places = (!isset($value_parts[1])) ? 0 : strlen($value_parts[1]);
			}
			
			return number_format($value, $decimal_places, '.', '');
		}
		
		// Turn line-breaks into breaks for text fields and add links
		if ($formatting === TRUE && in_array($column_type, array('varchar', 'char', 'text'))) {
			return fHTML::makeLinks(fHTML::convertNewlines(fHTML::encode($value)));
		}
		
		// Anything that has gotten to here is a string value or is not the proper data type for the column that contains it
		return fHTML::encode($value);
	}
	
	
	/**
	 * Checks to see if the record exists in the database
	 * 
	 * @return boolean  If the record exists in the database
	 */
	public function exists()
	{
		$class = get_class($this);
		
		if (fORM::getActiveRecordMethod($class, 'exists')) {
			return $this->__call('exists', array());
		}
		
		$pk_columns = fORMSchema::retrieve()->getKeys(fORM::tablize($class), 'primary');
		$exists     = FALSE;
		
		foreach ($pk_columns as $pk_column) {
			$has_old = self::hasOld($this->old_values, $pk_column);
			if (($has_old && self::retrieveOld($this->old_values, $pk_column) !== NULL) || (!$has_old && $this->values[$pk_column] !== NULL)) {
				$exists = TRUE;
			}
		}
		
		return $exists;
	}
	
	
	/**
	 * Loads a record from the database based on a UNIQUE key
	 * 
	 * @throws fNotFoundException
	 * 
	 * @param  array $values  The UNIQUE key values to try and load with
	 * @return void
	 */
	protected function fetchResultFromUniqueKey($values)
	{		
		$class = get_class($this);
		try {
			if ($values === array_combine(array_keys($values), array_fill(0, sizeof($values), NULL))) {
				throw new fExpectedException('The values specified for the unique key are all NULL');	
			}
			
			$table = fORM::tablize($class);
			$sql = 'SELECT * FROM ' . $table . ' WHERE ';
			$conditions = array();
			foreach ($values as $column => $value) {
				$conditions[] = $column . fORMDatabase::escapeBySchema($table, $column, $value, '=');	
			}
			$sql .= join(' AND ', $conditions);
		
			$result = fORMDatabase::retrieve()->translatedQuery($sql);
			$result->tossIfNoRows();
			
		} catch (fExpectedException $e) {
			throw new fNotFoundException(
				'The %s requested could not be found',
				fORM::getRecordName($class)
			);
		}
		
		return $result;
	}
	
	
	/**
	 * Retrieves a value from the record
	 * 
	 * @param  string $column  The name of the column to retrieve
	 * @return mixed  The value for the column specified
	 */
	protected function get($column)
	{
		if (!isset($this->values[$column]) && !array_key_exists($column, $this->values)) {
			throw new fProgrammerException(
				'The column specified, %s, does not exist',
				$column
			);
		}
		return $this->values[$column];
	}
	
	
	/**
	 * Takes a row of data or a primary key and makes a hash from the primary key
	 * 
	 * @param  mixed $data   An array of the records data, an array of primary key data or a scalar primary key value
	 * @return string  A hash of the record's primary key value
	 */
	protected function hash($data)
	{
		$class      = get_class($this);
		$pk_columns = fORMSchema::retrieve()->getKeys(fORM::tablize(get_class($this)), 'primary');
		
		// Build an array of just the primary key data
		$pk_data = array();
		foreach ($pk_columns as $pk_column) {
			$pk_data[$pk_column] = fORM::scalarize(
				$class,
				$pk_column,
				is_array($data) ? $data[$pk_column] : $data
			);
			if (is_numeric($pk_data[$pk_column]) || is_object($pk_data[$pk_column])) {
				$pk_data[$pk_column] = (string) $pk_data[$pk_column];	
			}
		}
		
		return md5(serialize($pk_data));
	}
	
	
	/**
	 * Retrieves information about a column
	 * 
	 * @param  string $column   The name of the column to inspect
	 * @param  string $element  The metadata element to retrieve
	 * @return mixed  The metadata array for the column, or the metadata element specified
	 */
	protected function inspect($column, $element=NULL)
	{
		if (!array_key_exists($column, $this->values)) {
			throw new fProgrammerException(
				'The column specified, %s, does not exist',
				$column
			);
		}
		
		$info = fORMSchema::retrieve()->getColumnInfo(fORM::tablize(get_class($this)), $column);
		
		if (!in_array($info['type'], array('varchar', 'char', 'text'))) {
			unset($info['valid_values']);
			unset($info['max_length']);
		}
		
		if ($info['type'] != 'float') {
			unset($info['decimal_places']);
		}
		
		if ($info['type'] != 'integer') {
			unset($info['auto_increment']);
		}
		
		$info['feature'] = NULL;
		
		if ($element) {
			if (!isset($info[$element])) {
				throw new fProgrammerException(
					'The element specified, %1$s, is invalid. Must be one of: %2$s.',
					$element,
					join(', ', array_keys($info))
				);
			}
			return $info[$element];
		}
		
		return $info;
	}
	
	
	/**
	 * Loads a record from the database
	 * 
	 * @throws fNotFoundException  When the record could not be found in the database
	 * 
	 * @return fActiveRecord  The record object, to allow for method chaining
	 */
	public function load()
	{
		$class = get_class($this);
		
		if (fORM::getActiveRecordMethod($class, 'load')) {
			return $this->__call('load', array());
		}
		
		try {
			$table = fORM::tablize($class);
			$sql = 'SELECT * FROM ' . $table . ' WHERE ' . fORMDatabase::createPrimaryKeyWhereClause($table, $table, $this->values, $this->old_values);
		
			$result = fORMDatabase::retrieve()->translatedQuery($sql);
			$result->tossIfNoRows();
			
		} catch (fExpectedException $e) {
			throw new fNotFoundException(
				'The %s requested could not be found',
				fORM::getRecordName($class)
			);
		}
		
		$this->loadFromResult($result);
		
		return $this;
	}
	
	
	/**
	 * Loads a record from the database directly from a result object
	 * 
	 * @param  fResult $result  The result object to use for loading the current object
	 * @return void
	 */
	protected function loadFromResult($result)
	{
		$class       = get_class($this);
		$row         = $result->current();
		$column_info = fORMSchema::retrieve()->getColumnInfo(fORM::tablize($class));
		
		$db = fORMDatabase::retrieve();
		
		foreach ($row as $column => $value) {
			if ($value !== NULL) {
				$value = $db->unescape($column_info[$column]['type'], $value);
			}
			
			$this->values[$column] = fORM::objectify($class, $column, $value);
		}
		
		// Save this object to the identity map
		$hash  = $this->hash($row);
		
		if (!isset(self::$identity_map[$class])) {
			self::$identity_map[$class] = array(); 		
		}
		self::$identity_map[$class][$hash] = $this;
		
		fORM::callHookCallbacks(
			$this,
			'post::loadFromResult()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache
		);
	}
	
	
	/**
	 * Tries to load the object (via references to class vars) from the fORM identity map
	 * 
	 * @param  fResult|array $source  The data source for the primary key values
	 * @return boolean  If the load was successful
	 */
	protected function loadFromIdentityMap($source)
	{
		if ($source instanceof fResult) {
			$row = $source->current();
		} else {
			$row = $source;
		}
		
		$class = get_class($this);
		
		if (!isset(self::$identity_map[$class])) {
			return FALSE;
		}
		
		$hash = $this->hash($row);
		
		if (!isset(self::$identity_map[$class][$hash])) {
			return FALSE;
		}
		
		$object = self::$identity_map[$class][$hash];
		
		// If we got a result back, it is the object we are creating
		$this->cache           = &$object->cache;
		$this->values          = &$object->values;
		$this->old_values      = &$object->old_values;
		$this->related_records = &$object->related_records;
		return TRUE;
	}
	
	
	/**
	 * Sets the values for this record by getting values from the request through the fRequest class
	 * 
	 * @return fActiveRecord  The record object, to allow for method chaining
	 */
	public function populate()
	{
		$class = get_class($this);
		
		if (fORM::getActiveRecordMethod($class, 'populate')) {
			return $this->__call('populate', array());
		}
		
		fORM::callHookCallbacks(
			$this,
			'pre::populate()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache
		);
		
		$table = fORM::tablize($class);
		
		$column_info = fORMSchema::retrieve()->getColumnInfo($table);
		foreach ($column_info as $column => $info) {
			if (fRequest::check($column)) {
				$method = 'set' . fGrammar::camelize($column, TRUE);
				$this->$method(fRequest::get($column));
			}
		}
		
		fORM::callHookCallbacks(
			$this,
			'post::populate()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache
		);
		
		return $this;
	}
	
	
	/**
	 * Retrieves a value from the record and prepares it for output into html.
	 * 
	 * Below are the transformations performed:
	 * 
	 *  - **varchar, char, text**: will run through fHTML::prepare(), if `TRUE` is passed the text will be run through fHTML::convertNewLinks() and fHTML::makeLinks()
	 *  - **boolean**: will return `'Yes'` or `'No'`
	 *  - **integer**: will add thousands/millions/etc. separators
	 *  - **float**: will add thousands/millions/etc. separators and takes 1 parameter to specify the number of decimal places
	 *  - **date, time, timestamp**: `format()` will be called on the fDate/fTime/fTimestamp object with the 1 parameter specified
	 *  - **objects**: the object will be converted to a string by `__toString()` or a `(string)` cast and then will be run through fHTML::prepare()
	 * 
	 * @param  string $column      The name of the column to retrieve
	 * @param  mixed  $formatting  The formatting parameter, if applicable
	 * @return string  The formatted value for the column specified
	 */
	protected function prepare($column, $formatting=NULL)
	{
		if (!array_key_exists($column, $this->values)) {
			throw new fProgrammerException(
				'The column specified, %s, does not exist',
				$column
			);
		}
		
		$column_info = fORMSchema::retrieve()->getColumnInfo(fORM::tablize(get_class($this)), $column);
		$column_type = $column_info['type'];
		
		// Ensure the programmer is calling the function properly
		if ($column_type == 'blob') {
			throw new fProgrammerException(
				'The column specified, %s, can not be prepared because it is a blob column',
				$column
			);
		}
		
		if ($formatting !== NULL && in_array($column_type, array('integer', 'boolean'))) {
			throw new fProgrammerException(
				'The column specified, %s, does not support any formatting options',
				$column
			);
		}
		
		// Grab the value for empty value checking
		$method_name = 'get' . fGrammar::camelize($column, TRUE);
		$value       = $this->$method_name();
		
		// Date/time objects
		if (is_object($value) && in_array($column_type, array('date', 'time', 'timestamp'))) {
			if ($formatting === NULL) {
				throw new fProgrammerException(
					'The column specified, %s, requires one formatting parameter, a valid date() formatting string',
					$column
				);
			}
			return $value->format($formatting);
		}
		
		// Other objects
		if (is_object($value) && is_callable(array($value, '__toString'))) {
			$value = $value->__toString();
		} elseif (is_object($value)) {
			$value = (string) $value;	
		}
		
		// Ensure the value matches the data type specified to prevent mangling
		if ($column_type == 'boolean' && is_bool($value)) {
			return ($value) ? 'Yes' : 'No';
		}
		
		if ($column_type == 'integer' && is_numeric($value)) {
			return number_format($value, 0, '', ',');
		}
		
		if ($column_type == 'float' && is_numeric($value)) {
			// If the user passed in a formatting value, use it
			if ($formatting !== NULL && is_numeric($formatting)) {
				$decimal_places = (int) $formatting;
				
			// If the column has a pre-defined number of decimal places, use that
			} elseif ($column_info['decimal_places'] !== NULL) {
				$decimal_places = $column_info['decimal_places'];
			
			// This figures out how many decimal places are part of the current value
			} else {
				$value_parts    = explode('.', $value);
				$decimal_places = (!isset($value_parts[1])) ? 0 : strlen($value_parts[1]);
			}
			
			return number_format($value, $decimal_places, '.', ',');
		}
		
		// Turn line-breaks into breaks for text fields and add links
		if ($formatting === TRUE && in_array($column_type, array('varchar', 'char', 'text'))) {
			return fHTML::makeLinks(fHTML::convertNewlines(fHTML::prepare($value)));
		}
		
		// Anything that has gotten to here is a string value, or is not the
		// proper data type for the column, so we just make sure it is marked
		// up properly for display in HTML
		return fHTML::prepare($value);
	}
	
	
	/**
	 * Generates a pre-formatted block of text containing the method signatures for all methods (including dynamic ones)
	 * 
	 * @param  boolean $include_doc_comments  If the doc block comments for each method should be included
	 * @return string  A preformatted block of text with the method signatures and optionally the doc comment
	 */
	public function reflect($include_doc_comments=FALSE)
	{
		$signatures = array();
		
		$class        = get_class($this);
		$columns_info = fORMSchema::retrieve()->getColumnInfo(fORM::tablize($class));
		foreach ($columns_info as $column => $column_info) {
			$camelized_column = fGrammar::camelize($column, TRUE);
			
			// Get and set methods
			$signature = '';
			if ($include_doc_comments) {
				$fixed_type = $column_info['type'];
				if ($fixed_type == 'blob') {
					$fixed_type = 'string';
				}
				if ($fixed_type == 'date') {
					$fixed_type = 'fDate';
				}
				if ($fixed_type == 'timestamp') {
					$fixed_type = 'fTimestamp';
				}
				if ($fixed_type == 'time') {
					$fixed_type = 'fTime';
				}
				
				$signature .= "/**\n";
				$signature .= " * Gets the current value of " . $column . "\n";
				$signature .= " * \n";
				$signature .= " * @return " . $fixed_type . "  The current value\n";
				$signature .= " */\n";
			}
			$get_method = 'get' . $camelized_column;
			$signature .= 'public function ' . $get_method . '()';
			
			$signatures[$get_method] = $signature;
			
			
			$signature = '';
			if ($include_doc_comments) {
				$fixed_type = $column_info['type'];
				if ($fixed_type == 'blob') {
					$fixed_type = 'string';
				}
				if ($fixed_type == 'date') {
					$fixed_type = 'fDate|string';
				}
				if ($fixed_type == 'timestamp') {
					$fixed_type = 'fTimestamp|string';
				}
				if ($fixed_type == 'time') {
					$fixed_type = 'fTime|string';
				}
				
				$signature .= "/**\n";
				$signature .= " * Sets the value for " . $column . "\n";
				$signature .= " * \n";
				$signature .= " * @param  " . $fixed_type . " \$" . $column . "  The new value\n";
				$signature .= " * @return void\n";
				$signature .= " */\n";
			}
			$set_method = 'set' . $camelized_column;
			$signature .= 'public function ' . $set_method . '($' . $column . ')';
			
			$signatures[$set_method] = $signature;
			
			
			// The encode method
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Encodes the value of " . $column . " for output into an HTML form\n";
				$signature .= " * \n";
				
				if (in_array($column_info['type'], array('time', 'timestamp', 'date'))) {
					$signature .= " * @param  string \$date_formatting_string  A date() compatible formatting string\n";
				}
				if (in_array($column_info['type'], array('float'))) {
					$signature .= " * @param  integer \$decimal_places  The number of decimal places to include - if not specified will default to the precision of the column or the current value\n";
				}
				
				$signature .= " * @return string  The HTML form-ready value\n";
				$signature .= " */\n";
			}
			$encode_method = 'encode' . $camelized_column;
			$signature .= 'public function ' . $encode_method . '(';
			if (in_array($column_info['type'], array('time', 'timestamp', 'date'))) {
				$signature .= '$date_formatting_string';
			}
			if (in_array($column_info['type'], array('float'))) {
				$signature .= '$decimal_places=NULL';
			}
			$signature .= ')';
			
			$signatures[$encode_method] = $signature;
			
			
			// The prepare method
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Prepares the value of " . $column . " for output into HTML\n";
				$signature .= " * \n";
				
				if (in_array($column_info['type'], array('time', 'timestamp', 'date'))) {
					$signature .= " * @param  string \$date_formatting_string  A date() compatible formatting string\n";
				}
				if (in_array($column_info['type'], array('float'))) {
					$signature .= " * @param  integer \$decimal_places  The number of decimal places to include - if not specified will default to the precision of the column or the current value\n";
				}
				if (in_array($column_info['type'], array('varchar', 'char', 'text'))) {
					$signature .= " * @param  boolean \$create_links_and_line_breaks  Will cause links to be automatically converted into [a] tags and line breaks into [br] tags \n";
				}
				
				$signature .= " * @return string  The HTML-ready value\n";
				$signature .= " */\n";
			}
			$prepare_method = 'prepare' . $camelized_column;
			$signature .= 'public function ' . $prepare_method . '(';
			if (in_array($column_info['type'], array('time', 'timestamp', 'date'))) {
				$signature .= '$date_formatting_string';
			}
			if (in_array($column_info['type'], array('float'))) {
				$signature .= '$decimal_places=NULL';
			}
			if (in_array($column_info['type'], array('varchar', 'char', 'text'))) {
				$signature .= '$create_links_and_line_breaks=FALSE';
			}
			$signature .= ')';
			
			$signatures[$prepare_method] = $signature;
			
			
			// The inspect method
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Returns metadata about " . $column . "\n";
				$signature .= " * \n";
				$elements = array('type', 'not_null', 'default');
				if (in_array($column_info['type'], array('varchar', 'char', 'text'))) {
					$elements[] = 'valid_values';
					$elements[] = 'max_length';
				}
				if ($column_info['type'] == 'float') {
					$elements[] = 'decimal_places';
				}
				if ($column_info['type'] == 'integer') {
					$elements[] = 'auto_increment';
				}
				$signature .= " * @param  string \$element  The element to return. Must be one of: '" . join("', '", $elements) . "'.\n";
				$signature .= " * @return mixed  The metadata array or a single element\n";
				$signature .= " */\n";
			}
			$inspect_method = 'inspect' . $camelized_column;
			$signature .= 'public function ' . $inspect_method . '($element=NULL)';
			
			$signatures[$inspect_method] = $signature;
		}
		
		fORMRelated::reflect($class, $signatures, $include_doc_comments);
		
		fORM::callReflectCallbacks($this, $signatures, $include_doc_comments);
		
		$reflection = new ReflectionClass($class);
		$methods    = $reflection->getMethods();
		
		foreach ($methods as $method) {
			$signature = '';
			
			if (!$method->isPublic() || $method->getName() == '__call') {
				continue;
			}
			
			if ($method->isFinal()) {
				$signature .= 'final ';
			}
			
			if ($method->isAbstract()) {
				$signature .= 'abstract ';
			}
			
			if ($method->isStatic()) {
				$signature .= 'static ';
			}
			
			$signature .= 'public function ';
			
			if ($method->returnsReference()) {
				$signature .= '&';
			}
			
			$signature .= $method->getName();
			$signature .= '(';
			
			$parameters = $method->getParameters();
			foreach ($parameters as $parameter) {
				if (substr($signature, -1) == '(') {
					$signature .= '';
				} else {
					$signature .= ', ';
				}
				
				if ($parameter->isArray()) {
					$signature .= 'array ';	
				}
				if ($parameter->getClass()) {
					$signature .= $parameter->getClass()->getName() . ' ';	
				}
				if ($parameter->isPassedByReference()) {
					$signature .= '&';	
				}
				$signature .= '$' . $parameter->getName();
				
				if ($parameter->isDefaultValueAvailable()) {
					$val = var_export($parameter->getDefaultValue(), TRUE);
					if ($val == 'true') {
						$val = 'TRUE';
					}
					if ($val == 'false') {
						$val = 'FALSE';
					}
					if (is_array($parameter->getDefaultValue())) {
						$val = preg_replace('#array\s+\(\s+#', 'array(', $val);
						$val = preg_replace('#,(\r)?\n  #', ', ', $val);
						$val = preg_replace('#,(\r)?\n\)#', ')', $val);
					}
					$signature .= '=' . $val;
				}
			}
			
			$signature .= ')';
			
			if ($include_doc_comments) {
				$comment = $method->getDocComment();
				$comment = preg_replace('#^\t+#m', '', $comment);
				$signature = $comment . "\n" . $signature;
			}
			$signatures[$method->getName()] = $signature;
		}
		
		ksort($signatures);
		
		return join("\n\n", $signatures);
	}
	
	
	/**
	 * Generates a clone of the current record, removing any auto incremented primary key value and allowing for replicating related records
	 * 
	 * This method will accept three different sets of parameters:
	 * 
	 *  - No parameters: this object will be cloned
	 *  - A single `TRUE` value: this object plus all many-to-many associations and all child records (recursively) will be cloned
	 *  - Any number of plural related record class names: the many-to-many associations or child records that correspond to the classes specified will be cloned
	 * 
	 * The class names specified can be a simple class name if there is only a
	 * single route between the two corresponding database tables. If there is 
	 * more than one route between the two tables, the class name should be
	 * substituted with a string in the format `'RelatedClass{route}'`.
	 * 
	 * @param  string $related_class  The plural related class to replicate - see method description for details
	 * @param  string ...
	 * @return fActiveRecord  The cloned record
	 */
	public function replicate($related_class=NULL)
	{
		fActiveRecord::$replicate_level++;
		
		$class = get_class($this);
		$hash  = self::hash($this->values);
		$table = fORM::tablize($class);
			
		// If the object has not been replicated yet, do it now
		if (!isset(fActiveRecord::$replicate_map[$class])) {
			fActiveRecord::$replicate_map[$class] = array();
		}
		if (!isset(fActiveRecord::$replicate_map[$class][$hash])) {
			fActiveRecord::$replicate_map[$class][$hash] = clone $this;
			
			// We need the primary key to get a hash, otherwise certain recursive relationships end up losing members
			$pk_columns = fORMSchema::retrieve()->getKeys($table, 'primary');
			if (sizeof($pk_columns) == 1 && fORMSchema::retrieve()->getColumnInfo($table, $pk_columns[0], 'auto_increment')) {
				fActiveRecord::$replicate_map[$class][$hash]->values[$pk_columns[0]] = $this->values[$pk_columns[0]];
			}
			
		}
		$clone = fActiveRecord::$replicate_map[$class][$hash];
		
		$parameters = func_get_args();
		
		$recursive                  = FALSE;
		$many_to_many_relationships = fORMSchema::retrieve()->getRelationships($table, 'many-to-many');
		$one_to_many_relationships  = fORMSchema::retrieve()->getRelationships($table, 'one-to-many');
		
		
		// When just TRUE is passed we recursively replicate all related records
		if (sizeof($parameters) == 1 && $parameters[0] === TRUE) {
			$parameters = array();
			$recursive  = TRUE;
			
			foreach ($many_to_many_relationships as $relationship) {
				$parameters[] = fGrammar::pluralize(fORM::classize($relationship['related_table'])) . '{' . $relationship['join_table'] . '}'; 		
			}
			foreach ($one_to_many_relationships as $relationship) {
				$parameters[] = fGrammar::pluralize(fORM::classize($relationship['related_table'])) . '{' . $relationship['related_column'] . '}'; 		
			}			
		}
		
		$record_sets = array();
		
		foreach ($parameters as $parameter) {
			
			// Parse the Class{route} strings
			if (strpos($parameter, '{') !== FALSE) {
				$brace         = strpos($parameter, '{');
				$related_class = fGrammar::singularize(substr($parameter, 0, $brace));
				$related_table = fORM::tablize($related_class);
				$route         = substr($parameter, $brace+1, -1);
			} else {
				$related_class = fGrammar::singularize($parameter);
				$related_table = fORM::tablize($related_class);
				$route         = fORMSchema::getRouteName($table, $related_table);
			}
			
			// Determine the kind of relationship
			$many_to_many = FALSE;
			$one_to_many  = FALSE;
			
			foreach ($many_to_many_relationships as $relationship) {
				if ($relationship['related_table'] == $related_table && $relationship['join_table'] == $route) {
					$many_to_many = TRUE;	
					break;
				}
			}
			
			foreach ($one_to_many_relationships as $relationship) {
				if ($relationship['related_table'] == $related_table && $relationship['related_column'] == $route) {
					$one_to_many = TRUE;
					break;
				}	
			}
			
			if (!$many_to_many && !$one_to_many) {
				throw new fProgrammerException(
					'The related class specified, %1$s, does not appear to be in a many-to-many or one-to-many relationship with %$2s',
					$parameter,
					get_class($this)
				);	
			}
			
			// Get the related records
			$record_set = fORMRelated::buildRecords($class, $this->values, $this->related_records, $related_class, $route);
			
			// One-to-many records need to be replicated, possibly recursively
			if ($one_to_many) {
				if ($recursive) {
					$records = $record_set->call('replicate', TRUE);
				} else {
					$records = $record_set->call('replicate');
				}
				$record_set = fRecordSet::buildFromRecords($related_class, $records);
				$record_set->call(
					'set' . fGrammar::camelize($route, TRUE),
					NULL
				);	
			}
			
			// Cause the related records to be associated with the new clone
			fORMRelated::associateRecords($class, $clone->related_records, $related_class, $record_set, $route);
		}
		
		fActiveRecord::$replicate_level--;
		if (!fActiveRecord::$replicate_level) {
			// This removes the primary keys we had added back in for proper duplicate detection
			foreach (fActiveRecord::$replicate_map as $class => $records) {
				$table      = fORM::tablize($class);
				$pk_columns = fORMSchema::retrieve()->getKeys($table, 'primary');
				if (sizeof($pk_columns) != 1 || !fORMSchema::retrieve()->getColumnInfo($table, $pk_columns[0], 'auto_increment')) {
					continue;
				}
				foreach ($records as $hash => $record) {
					$record->values[$pk_columns[0]] = NULL;		
				}	
			}
			fActiveRecord::$replicate_map = array();	
		}
		
		return $clone;
	}
	
	
	/**
	 * Sets a value to the record
	 * 
	 * @param  string $column  The column to set the value to
	 * @param  mixed  $value   The value to set
	 * @return void
	 */
	protected function set($column, $value)
	{
		if (!array_key_exists($column, $this->values)) {
			throw new fProgrammerException(
				'The column specified, %s, does not exist',
				$column
			);
		}
		
		// We consider an empty string to be equivalent to NULL
		if ($value === '') {
			$value = NULL;
		}
		
		$class = get_class($this);
		$value = fORM::objectify($class, $column, $value);
		
		// Float and int columns that look like numbers with commas will have the commas removed
		if (is_string($value)) {
			$table = fORM::tablize($class);
			$type  = fORMSchema::retrieve()->getColumnInfo($table, $column, 'type');
			if (in_array($type, array('integer', 'float')) && preg_match('#^(\d+,)+\d+(\.\d+)?$#', $value)) {
				$value = str_replace(',', '', $value);
			}
		}
		
		self::assign($this->values, $this->old_values, $column, $value);
	}
	
	
	/**
	 * Stores a record in the database, whether existing or new
	 * 
	 * This method will start database and filesystem transactions if they have
	 * not already been started.
	 * 
	 * @throws fValidationException  When ::validate() throws an exception
	 * 
	 * @return fActiveRecord  The record object, to allow for method chaining
	 */
	public function store()
	{
		$class = get_class($this);
		
		if (fORM::getActiveRecordMethod($class, 'store')) {
			return $this->__call('store', array());
		}
		
		fORM::callHookCallbacks(
			$this,
			'pre::store()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache
		);
		
		try {
			$table       = fORM::tablize($class);
			$column_info = fORMSchema::retrieve()->getColumnInfo($table);
			
			// New auto-incrementing records require lots of special stuff, so we'll detect them here
			$new_autoincrementing_record = FALSE;
			if (!$this->exists()) {
				$pk_columns = fORMSchema::retrieve()->getKeys($table, 'primary');
				
				if (sizeof($pk_columns) == 1 && $column_info[$pk_columns[0]]['auto_increment'] && !$this->values[$pk_columns[0]]) {
					$new_autoincrementing_record = TRUE;
					$pk_column = $pk_columns[0];
				}
			}
			
			$inside_db_transaction = fORMDatabase::retrieve()->isInsideTransaction();
			
			if (!$inside_db_transaction) {
				fORMDatabase::retrieve()->translatedQuery('BEGIN');
			}
			
			fORM::callHookCallbacks(
				$this,
				'post-begin::store()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->cache
			);
			
			$this->validate();
			
			fORM::callHookCallbacks(
				$this,
				'post-validate::store()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->cache
			);
			
			// Storing main table
			
			$sql_values = array();
			foreach ($column_info as $column => $info) {
				$value = fORM::scalarize($class, $column, $this->values[$column]);
				$sql_values[$column] = fORMDatabase::escapeBySchema($table, $column, $value);
			}
			
			// Most databases don't like the auto incrementing primary key to be set to NULL
			if ($new_autoincrementing_record && $sql_values[$pk_column] == 'NULL') {
				unset($sql_values[$pk_column]);
			}
			
			if (!$this->exists()) {
				$sql = $this->constructInsertSQL($sql_values);
			} else {
				$sql = $this->constructUpdateSQL($sql_values);
			}
			$result = fORMDatabase::retrieve()->translatedQuery($sql);
			
			
			// If there is an auto-incrementing primary key, grab the value from the database
			if ($new_autoincrementing_record) {
				$this->set($pk_column, $result->getAutoIncrementedValue());
			}
			
			
			// Storing *-to-many relationships
			fORMRelated::store($class, $this->values, $this->related_records);
			
			
			fORM::callHookCallbacks(
				$this,
				'pre-commit::store()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->cache
			);
			
			if (!$inside_db_transaction) {
				fORMDatabase::retrieve()->translatedQuery('COMMIT');
			}
			
			fORM::callHookCallbacks(
				$this,
				'post-commit::store()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->cache
			);
			
		} catch (fException $e) {
			
			if (!$inside_db_transaction) {
				fORMDatabase::retrieve()->translatedQuery('ROLLBACK');
			}
			
			fORM::callHookCallbacks(
				$this,
				'post-rollback::store()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->cache
			);
			
			if ($new_autoincrementing_record && self::hasOld($this->old_values, $pk_column)) {
				$this->values[$pk_column] = self::retrieveOld($this->old_values, $pk_column);
				unset($this->old_values[$pk_column]);
			}
			
			throw $e;
		}
		
		fORM::callHookCallbacks(
			$this,
			'post::store()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache
		);
		
		// If we got here we succefully stored, so update old values to make exists() work
		foreach ($this->values as $column => $value) {
			$this->old_values[$column] = array($value);
		}
		
		return $this;
	}
	
	
	/**
	 * Validates the values of the record against the database and any additional validation rules
	 * 
	 * @throws fValidationException  When the record, or one of the associated records, violates one of the validation rules for the class or can not be properly stored in the database
	 * 
	 * @param  boolean $return_messages  If an array of validation messages should be returned instead of an exception being thrown
	 * @return void|array  If $return_messages is TRUE, an array of validation messages will be returned
	 */
	public function validate($return_messages=FALSE)
	{
		$class = get_class($this);
		
		if (fORM::getActiveRecordMethod($class, 'validate')) {
			return $this->__call('validate', array($return_messages));
		}
		
		$validation_messages = array();
		
		fORM::callHookCallbacks(
			$this,
			'pre::validate()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache,
			$validation_messages
		);
		
		// Validate the local values
		$local_validation_messages = fORMValidation::validate($this, $this->values, $this->old_values);
		
		// Validate related records
		$related_validation_messages = fORMValidation::validateRelated($this, $this->values, $this->related_records);
		
		$validation_messages = array_merge($validation_messages, $local_validation_messages, $related_validation_messages);
		
		fORM::callHookCallbacks(
			$this,
			'post::validate()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->cache,
			$validation_messages
		);
		
		$validation_messages = array_unique($validation_messages);
		
		$validation_messages = fORMValidation::replaceMessages($class, $validation_messages);
		$validation_messages = fORMValidation::reorderMessages($class, $validation_messages);
		
		if ($return_messages) {
			return $validation_messages;
		}
		
		if (!empty($validation_messages)) {
			throw new fValidationException(
				sprintf(
					"<p>%1\$s</p>\n<ul>\n<li>%2\$s</li>\n</ul>",
					self::compose("The following problems were found:"),
					join("</li>\n<li>", $validation_messages)
				)
			);
		}
	}
}



/**
 * Copyright (c) 2007-2009 Will Bond <will@flourishlib.com>, others
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */