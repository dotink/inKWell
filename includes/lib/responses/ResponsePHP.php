<?php

	/**
	 * The inKWell JSON Response
	 *
	 * @author Matthew J. Sahagian [mjs] <gent@dotink.org>
	 * @copyright Copyright (c) 2012, Matthew J. Sahagian
	 * @license Please reference the LICENSE.txt file at the root of this distribution
	 *
	 * @package inKWell
	 */
	class ResponsePHP extends Response
	{
		/**
		 * Renders a view (proper or not) to PHP
		 *
		 * @static
		 * @access protected
		 * @param Response $response The response to render
		 * @return void
		 */
		static protected function render($response)
		{
			$this->view = serialize($response->view);
		}
	}