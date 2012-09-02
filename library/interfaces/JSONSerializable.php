<?php

	/**
	 * Provides the JSONSerializable Interface if it does not exist
	 *
	 * @author Matthew J. Sahagian [mjs] <gent@dotink.org>
	 * @copyright Copyright (c) 2011, Matthew J. Sahagian
	 * @license http://www.gnu.org/licenses/agpl.html GNU Affero General Public License
	 *
	 * @package inKWell
	 */
	interface JSONSerializable
	{
		/**
		 * Gets an alternative data structure to be serialized
		 *
		 * @access public
		 * @param void
		 * @return mixed The data structure to be serialized
		 */
		public function jsonSerialize();
	}

