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
	class ResponseJSON extends Response
	{
		/**
		 * Renders a view (proper or not) to JSON
		 *
		 * @static
		 * @access protected
		 * @param Response $response The response to render
		 * @return void
		 */
		static protected function render($response)
		{
			if (is_string($response->view) && fJSON::decode($response->view) !== NULL) {
				return;
			}

			$response->view = fJSON::encode($response->view);
		}
	}