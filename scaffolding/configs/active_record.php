	return iw::createConfig('ActiveRecord', array(

		//
		// The database which holds the table for this active record model.  This is the database
		// alias name as configured in the 'databases' keys of the database.php not the 'name' key
		// or actual name of the database.
		//

		'database' => NULL,

		//
		// The table which maps to the active record, i.e., each instance of this class represents
		// a row on this table.  If your table is on an alternate schema, you can specify that
		// here as well.
		//

		'table' => NULL,

		//
		// The simplified name for the record.  By default this will be whatever the configuration
		// file is named.  It is predominately used in generic slugs.  i.e. /users/user-1 where
		// "user" is the record name.
		//

		'name' => NULL,

		//
		// The column which can naturally identify a record.  This column does not have to be
		// strictly unique, usually names, titles, or similar columns make good ID columns.
		//

		'id_column' => NULL,

		//
		// The column which stores an identifiable slug for a record.  This column must have a
		// a unique constraint consisting only of the column itself.  If an ID column is set and
		// slug column values are not set when the record is populated the value will be generated
		// from the URL friendly version of the id column value.
		//

		'slug_column' => NULL,

		//
		// The order in which records should be sorted by default when added to a recordset.  This
		// is an array of keys (columns) to values 'desc' (descending) or 'asc' (ascending).
		//

		'ordering' => NULL,

	));
