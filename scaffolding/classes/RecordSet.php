	/**
	 * The <%= $class %> is a recordset representing a collection of
	 * <%= ActiveRecord::classFromRecordSet($class) %> objects / records.
	 *
	 * @author Matthew J. Sahagian [mjs] <gent@dotink.org>
	 * @copyright Copyright (c) 2011, Matthew J. Sahagian
	 */
	class <%= self::validateVariable($class) %> extends <%= self::validateVariable($parent_class) %>

	{
		/**
		 * Initializes the <%= $class %> Record Set
		 *
		 * @static
		 * @access public
		 * @param array $config The configuration array
		 * @param string $element The element name of the configuration array
		 * @return boolean TRUE if the initialization succeeds, FALSE otherwise
		 */
		static public function __init(array $config = array(), $element = NULL)
		{
			parent::__init($config, $element);
		}

		/**
		 * Builds a recordset using an arrays of where conditions and ordering
		 * information.
		 *
		 * @see fRecordSet::build()
		 * @static
		 * @access public
		 * @param array $wheres An array of where clauses
		 * @param array $ordering An array of order clauses
		 * @param integer $limit A limit to the number of records returned
		 * @param integer $page The page of records to return if limited
		 * @return <%= $class %> The resulting Record Set
		 */
		static public function build(array $wheres = array(), array $ordering = array(), $limit = NULL, $page = NULL)
		{
			return parent::build(
				'<%= ActiveRecord::classFromRecordSet($class) %>',
				$wheres,
				count($ordering)
					? $ordering
					: <%= ActiveRecord::classFromRecordSet($class) %>::getOrder(),
				$limit,
				$page
			);
		}

		/**
		 * Builds a recordset using an SQL statement.
		 *
		 * @see fRecordSet::buildFromSQL()
		 * @static
		 * @access public
		 * @param string $sql The SQL statement to build the recordset from
		 * @param string $no_limit_sql An SQL statement that counts all records
		 * @return <%= $class %> The resulting Record Set
		 */
		static public function buildFromSQL($sql, $no_limit_sql)
		{
			return parent::build(
				'<%= ActiveRecord::classFromRecordSet($class) %>',
				$sql,
				$no_limit_sql
			);
		}
	}
