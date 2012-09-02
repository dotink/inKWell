<?php

	/**
	 * The Request class
	 *
	 * @author Matthew J. Sahagian [mjs] <gent@dotink.org>
	 * @copyright Copyright (c) 2012, Matthew J. Sahagian
	 * @license Please reference the LICENSE.txt file at the root of this distribution
	 *
	 * @package inKWell
	 */
	class Request extends fRequest
	{
		const REQUEST_FORMAT_PARAM = '_format';
		const REQUEST_METHOD_PARAM = '_method';

		/**
		 * The client IP address
		 *
		 * @static
		 * @access private
		 * @var string
		 */
		static private $clientIP = NULL;

		/**
		 * The current request format as sent by the client
		 *
		 * @static
		 * @access private
		 * @var string
		 */
		static private $format = NULL;

		/**
		 * A quick way to check against the current request format
		 *
		 * @static
		 * @access protected
		 * @param string $format The format to check for
		 * @return boolean TRUE if the format matches the current request format, FALSE otherwise
		 */
		static public function checkFormat($format)
		{
			return (strtolower($format) == self::getFormat());
		}

		/**
		 * A quick way to check against the current request method
		 *
		 * @static
		 * @access protected
		 * @param string $method The method to check for
		 * @return boolean TRUE if the method matches the current request method, FALSE otherwise
		 */
		static public function checkMethod($method)
		{
			return (strtolower($method) == self::getMethod());
		}

		/**
	 	 * Determines the client IP address, taking into account proxy information
		 *
		 * @static
		 * @access protected
		 * @param void
		 * @return string The client IP	address
		 */
		static public function getClientIP($ignore_forward = FALSE)
		{
			if (self::$clientIP) {
				return self::$clientIP;
			} elseif (!$ignore_forward && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$sources = explode(',',	$_SERVER['HTTP_X_FORWARDED_FOR']);
				$address = reset($sources);
			} else {
				$address = $_SERVER['REMOTE_ADDR'];
			}

			return (self::$clientIP = trim($address));
		}

		/**
		 * Gets format mime types for selected or all request formats
		 *
		 * @static
		 * @access protected
		 * @param string $format The particular format type to get
		 * @return array The format mime types for the requested format
		 */
		static public function getFormatTypes($format = NULL)
		{
			$format_types = array(

				'html'  => array(
					'text/html'
				),

				'css'   => array(
					'text/css'
				),

				'js'    => array(
					'text/javascript',
					'applicaiton/javascript'
				),

				'txt'   => array(
					'text/plain'
				),

				'json' => array(
					'application/json',
					'application/x-javascript'
				),

				'xml'   => array(
					'application/xml'
				),

				'php'   => array(
					'application/octet-stream'
				),

				'jpg'   => array(
					'image/jpeg'
				),

				'gif'   => array(
					'image/gif'
				),

				'png'   => array(
					'image/png'
				)
			);

			if ($format === NULL) {
				return $format_types;
			} elseif (isset($format_types[$format])) {
				return $format_types[$format];
			} else {
				return array();
			}
		}

		/**
		 * Determines the request format for the resource.
		 *
		 * The request format can be taken is as a get or URL parameter with the simple name
		 * '_format', but must be explicitly set on routes.
		 *
		 * @static
		 * @access protected
		 * @param void
		 * @return string The request format, i.e. 'html', 'xml', 'json', etc...
		 */
		static public function getFormat()
		{
			if (self::$format === NULL) {
				if ($format = self::get(self::REQUEST_FORMAT_PARAM, 'string', NULL)) {
					self::$format = $format;
				}
			}

			return self::$format;
		}

		/**
		 * Determines the request method for the resource.
		 *
		 * The request method can be taken is as a post parameter with the simple name '_method',
		 * allowing you to perform 'put' and 'delete' from non-supporting mediums and clients.
		 *
		 * @static
		 * @access protected
		 * @param void
		 * @return string The request method, i.e. 'get', 'put, 'post', etc...
		 */
		static public function getMethod()
		{
			return (self::isPost() && self::check(self::REQUEST_METHOD_PARAM))
				? self::get(self::REQUEST_METHOD_PARAM, 'string')
				: strtolower($_SERVER['REQUEST_METHOD']);
		}

	}