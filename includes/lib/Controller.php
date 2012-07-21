<?php

	/**
	 * A base controller class which provides facilities for triggering various responses, and
	 * building higher level controllers.
	 *
	 * @author Matthew J. Sahagian [mjs] <gent@dotink.org>
	 * @copyright Copyright (c) 2012, Matthew J. Sahagian
	 * @license Please reference the LICENSE.txt file at the root of this distribution
	 *
	 * @package inKWell
	 */
	class Controller extends MoorBaseController implements inkwell
	{
		const SUFFIX                      = __CLASS__;

		const DEFAULT_CONTROLLER_ROOT     = 'user/controllers';

		const DEFAULT_SITE_SECTION        = 'default';
		const DEFAULT_USE_SSL             = FALSE;

		const MSG_TYPE_ERROR              = 'error';
		const MSG_TYPE_ALERT              = 'alert';
		const MSG_TYPE_SUCCESS            = 'success';

		/**
		 * The cached baseURL for the request, based on sitesections
		 *
		 * @static
		 * @access private
		 * @var string
		 */
		static private $baseURL = '/';

		/**
		 * All configured base URLs and their options
		 *
		 * @static
		 * @access private
		 * @var array
		 */
		static private $baseURLs = array();

		/**
		 * The Content-Type to send on sendHeader()
		 *
		 * @static
		 * @access private
		 * @var string
		 */
		static private $contentType = NULL;

		/**
		 * An array of error handlers used with triggerError()
		 *
		 * @static
		 * @access private
		 * @var array
		 */
		static private $error_handlers = array();

		/**
		 * A list of default accept mime types
		 *
		 * @static
		 * @access private
		 * @var array
		 */
		static private $defaultAcceptTypes = array();

		/**
		 * Matches whether or not a given class name is a potential
		 * Controller
		 *
		 * @static
		 * @access public
		 * @param string $class The name of the class to check
		 * @return boolean TRUE if it matches, FALSE otherwise
		 */
		static public function __match($class) {
			return preg_match('/(.*)' . self::SUFFIX . '/', $class);
		}

		/**
		 * Initializes the Controller class namely by establishing error handlers, headers, and
		 * messages.
		 *
		 * @static
		 * @access public
		 * @param array $config The configuration array
		 * @param string $element The element name of the configuration array
		 * @return boolean TRUE if the initialization succeeds, FALSE otherwise
		 */
		static public function __init(array $config = array(), $element = NULL)
		{
			if (iw::classize($element) != __CLASS__) {
				return TRUE;
			}

			$controller_configs           = iw::getConfigsByType('Controller');
			$controller_configs[$element] = $config;

			//
			// Build a list of all base URLs
			//

			foreach ($controller_configs as $config) {

				$base_url = isset($config['base_url'])
					? rtrim($config['base_url'], '/')
					: '/';

				if (!isset(self::$baseURLs[$base_url])) {
					self::$baseURLs[$base_url] = array(
						'use_ssl'        => FALSE,
						'error_handlers' => array(),
						'accept_types'   => array(
							'text/html',
							'application/json'
						)
					);
				}

				$base_url_config = &self::$baseURLs[$base_url];

				//
				// Configure whether or not we need to use SSL on this base URL
				//

				if (isset($config['use_ssl'])) {
					$base_url_config['use_ssl'] = $config['use_ssl'];
				}

				//
				// Figure out the current BaseURL
				//

				$request_path = Moor::getRequestPath();

				foreach (array_keys(self::$baseURLs) as $base_url) {
					if (stripos($request_path, $base_url) === 0) {
						if (strlen($base_url) > self::$baseURL) {
							self::$baseURL = $base_url;
						}
					}
				}

				//
				// See if we need to switch to SSL
				//

				if ($base_url_config['use_ssl'] && empty($_SERVER['HTTPS'])) {
					$domain     = fURL::getDomain();
					$request    = fURL::getWithQueryString();
					$ssl_domain = str_replace('http://', 'https://', $domain);

					self::redirect($ssl_domain . $request, NULL, 301);
				}


				//
				// Configure default accept types
				//

				if (isset($config['accept_types'])) {
					$base_url_config['accept_types'] = $config['accept_types'];
				}

				//
				// Configure error handlers
				//

				if (isset($config['error_handlers']) && is_array($config['error_handlers'])) {
					$base_url_config['error_handlers'] = array_merge(
						$base_url_config['error_handlers'],
						$config['error_handlers']
					);
				}
			}


		}

		/**
		 * Dynamically scaffolds a Controller class.
		 *
		 * @static
		 * @access public
		 * @param string $controller_class The class name to scaffold
		 * @param array $template_vars Requested template vars
		 * @return void
		 */
		static public function __make($controller_class, $template_vars = array())
		{
			Scaffolder::make('classes' . iw::DS . __CLASS__ . '.php', array_merge(
				array(
					'parent_class' => __CLASS__,
					'class'        => $controller_class
				),
				$template_vars
			));
		}

		/**
		 * A routable not found view.
		 *
		 * This will simply trigger the standard not found error, however, it catches the
		 * exception thrown by yield.
		 *
		 * @static
		 * @access public
		 * @param void
		 * @return void
		 */
		static public function notFound()
		{
			try {
				self::triggerError('not_found');
			} catch (MoorContinueException $e) {}

			return NULL;
		}

		/**
		 * Determines whether or not we should accept the request based on the mime type accepted
		 * by the user agent.  If no array or an empty array is passed the configured default
		 * accept types will be used.  If the request::format is provided in the request and the
		 * list of acceptable types does not support the provided accept headers a not_found error
		 * will be triggered.  If no request::format is provided in the request and the list of
		 * acceptable types does not support the provided accept headers the method will trigger
		 * a 'not_acceptable' error.
		 *
		 * @static
		 * @access protected
		 * @param array|string $accept_types An array of acceptable mime types
		 * @return mixed The best acceptable type upon success
		 */
		static protected function acceptTypes($accept_types = array())
		{
			if (!is_array($accept_types)) {
				$accept_types = func_get_args();
			}

			if (!count($accept_types)) {
				$accept_types = self::$baseURLs[self::getBaseURL()]['accept_types'];
			}

			//
			// The below mapping is used solely to normalize the request format to retrieve
			// the above listed format accept types.  This makes 'htm' equivalent to 'html'
			// and 'jpeg' equivalent to 'jpg'.  It's a bit verbose for what it does but it
			// is clear for extending for future supported types.
			//
			switch ($request_format = Request::getFormat()) {
				case 'htm':
				case 'html':
					$request_format_types = Request::getFormatTypes('html');
					break;
				case 'txt':
					$request_format_types = Request::getFormatTypes('txt');
					break;
				case 'css':
					$request_format_types = Request::getFormatTypes('css');
					break;
				case 'js':
					$request_format_types = Request::getFormatTypes('js');
					break;
				case 'json':
					$request_format_types = Request::getFormatTypes('json');
					break;
				case 'xml':
					$request_format_types = Request::getFormatTypes('xml');
					break;
				case 'php':
					$request_format_types = Request::getFormatTypes('php');
					break;
				case 'jpg':
				case 'jpeg':
					$request_format_types = Request::getFormatTypes('jpg');
					break;
				case 'gif':
					$request_format_types = Request::getFormatTypes('gif');
					break;
				case 'png':
					$request_format_types = Request::getFormatTypes('png');
					break;
				default:
					$request_format_types = NULL;
					break;
			}

			$best_accept_types = ($request_format_types)
				? array_intersect($accept_types, $request_format_types)
				: $accept_types;

			if (count($best_accept_types)) {
				$best_type = Request::getBestAcceptType($best_accept_types);
				if ($best_type !== FALSE) {
					if (!Request::getFormat()) {
						foreach(Request::getFormatTypes() as $format => $types) {
							if (in_array($best_type, $types)) {
								Request::set(Request::REQUEST_FORMAT_PARAM, $format);
								break;
							}
						}
					}

					return (self::$contentType = $best_type);
				}
				self::triggerError('not_acceptable');
			} else {
				self::triggerError('not_found');
			}
		}

		/**
		 * Determines whether or not we should accept the request based on the languages accepted
		 * by the user agent.
		 *
		 * @static
		 * @access protected
		 * @param array $accept_language An array of acceptable languages
		 * @return mixed The he best acceptable language upon success.
		 */
		static protected function acceptLanguages($accept_languages = array())
		{
			if (!is_array($accept_languages)) {
				$accept_languages = func_get_args();
			}

			if (!count($accept_languages)) {
				$accept_languages = self::$baseURLs[self::getBaseURL()]['accept_languages'];
			}

			return ($best_language = Request::getBestAcceptType($accept_languages))
				? $best_language
				: self::triggerError('not_acceptable');
		}

		/**
		 * Determines whether or not accept the request method is allowed.  If the current request
		 * method is not in the list of allowed methods, the method will trigger the 'not_allowed'
		 * error.
		 *
		 * @static
		 * @access protected
		 * @param array $methods An array of allowed request methods
		 * @return boolean TRUE if the current request method is in the array, FALSE otherwise
		 */
		static protected function allowMethods(array $methods = array())
		{
			$request_method  = strtolower($_SERVER['REQUEST_METHOD']);
			$allowed_methods = array_map('strtolower', $methods);

			if ($request_method == 'post' && Request::check(Request::REQUEST_METHOD_PARAM)) {
				$request_method = Request::get(Request::REQUEST_METHOD_PARAM, 'string', NULL);
			}

			if (!in_array($request_method, $allowed_methods)) {
				self::triggerError('not_allowed', NULL, NULL, array(
					'Allow: ' . implode(', ', $allowed_methods)
				));
				return FALSE;
			}

			return TRUE;
		}

		/**
		 * Determines the base URL from the server's request URI
		 *
		 * @static
		 * @access protected
		 * @param void
		 * @return string The Base URL
		 */
		static protected function getBaseURL()
		{
			return self::$baseURL;
		}

		/**
		 * A quick way to check against the current base URL.
		 *
		 * @static
		 * @access protected
		 * @param string $base_url The base URL to check against
		 * @return boolean TRUE if the base URL matched the current base URL, FALSE otherwise
		 */
		static protected function checkBaseURL($base_url)
		{
			return (self::getBaseURL() == (!$base_url ? '/' : $base_url));
		}

		/**
		 * Gets the current directly accessed entry
		 *
		 * @static
		 * @access protected
		 * @param void
		 * @return string The current directly accessed entry
		 */
		static protected function getEntry()
		{
			return Moor::getActiveShortClass();
		}

		/**
		 * Determines whether or not a particular class is the entry class being used by the
		 * router.
		 *
		 * @static
		 * @access protected
		 * @param string $class The class to check against the router
		 * @return void
		 */
		static protected function checkEntry($class)
		{
			return (self::getEntry() == $class);
		}

		/**
		 * Gets the current directly accessed action.
		 *
		 * @static
		 * @access protected
		 * @param void
		 * @return string The current directly accessed action
		 */
		static protected function getAction()
		{
			return Moor::getActiveShortMethod();
		}

		/**
		 * Determines whether or not a particular method is the action being used by the router.
		 *
		 * @static
		 * @access protected
		 * @param string $method The method name to check against the router
		 * @return void
		 */
		static protected function checkAction($method)
		{
			return (self::getAction() == $method);
		}

		/**
		 * Determines whether or not a particular class and method is the entry and action for the
		 * router.
		 *
		 * @static
		 * @access protected
		 * @param string $class The class to check against the router
		 * @param string $method The method name to check against the router
		 * @return void
		 */
		static protected function checkEntryAction($class, $method) {
			return (self::checkEntry($class) && self::checkAction($method));
		}

		/**
		 * Attempts to execute a target within the context of of Controller.
		 *
		 * @static
		 * @access protected
		 * @param string $target An inKWell target to execute
		 * @param mixed Additional parameters to pass to the callback
		 * @return mixed The return of the target action
		 */
		static protected function exec($target)
		{
			if (is_callable($target)) {
				$params = array_slice(func_get_args(), 1);
				return call_user_func_array($target, $params);
			}

			self::triggerError('not_found');
		}

		/**
		 * Redirect to a controller target.
		 *
		 * @static
		 * @access protected
		 * @param string $target an inKWell target to redirect to
		 * @param array $query an associative array containing parameters => values
		 * @param string $hash The hash/fragment for the redirect, without a leading #
		 * @param int $type 3xx HTTP Code to send (normalized for HTTP version), default 302/303
		 * @return void
		 */
		static protected function redirect($target = NULL, $query = array(), $hash = NULL, $type = 303)
		{
			$protocol = strtoupper($_SERVER['SERVER_PROTOCOL']);

			if ($protocol == 'HTTP/1.0') {
				switch ($type) {
					case 301:
						header('HTTP/1.0 Moved Permanently');
						break;
					case 302:
					case 303:
					case 307:
						header('HTTP/1.0 302 Moved Temporarily');
						break;
				}
			} elseif ($protocol == 'HTTP/1.1') {
				switch ($type) {
					case 301:
						header('HTTP/1.1 Moved Permanently');
						break;
					case 302:
						header('HTTP/1.1 302 Found');
						break;
					case 303:
						header('HTTP/1.1 303 See Other');
						break;
					case 307:
						header('HTTP/1.1 307 Temporary Redirect');
						break;
				}
			}

			$target = $target !== NULL
 				? iw::makeLink($target, $query, $hash, FALSE)
 				: NULL;

			fURL::redirect($target);
		}

		/**
		 * Triggers an error.
		 *
		 * This will attempt to use whatever error handlers have been configured, however, if
		 * no handler is available for the given error, it will result naturally in a hard error
		 * attempting to attach the most suitable view.
		 *
		 * In all cases the method throws an Exception.  The message provided on the exception will
		 * be the best available HTTP response header.
		 *
		 * By default this will also set the header available in the exception as well as any other
		 * headers provided in the $header argument as an array.  To disable sending headers simply
		 * set $headers to FALSE.
		 *
		 * @static
		 * @access protected
		 * @param string $error The error to be triggered
		 * @param array  $headers Additional headers to add
		 * @param string $message The message sent
		 * @throws MoorContinueException (by proxy)
		 * @return void
		 */
		static protected function triggerError($error, $headers = array(), $message = NULL)
		{
			$handler = NULL;

			//
			// Try to get a handler for the default base URL
			//

			$handler = isset(self::$baseURLs['/']['error_handlers'][$error])
				? self::$baseURLs['/']['error_handlers'][$error]
				: $handler;

			//
			// Try to get a custom handler
			//

			$handler = isset(self::$baseURLs[self::getBaseURL()]['error_handlers'][$error])
				? self::$baseURLs[self::getBaseURL()]['error_handlers'][$error]
				: $handler;

			if (is_callable($handler)) {
				Response::register(call_user_func($handler, $error, $headers, $message));
			} else {
				Response::register(new Response($error, self::acceptTypes(), $headers, $message));
			}

			self::yield();
		}

		/**
		 * Causes the current controller action to end before finishing.
		 *
		 * This method triggers a MoorContinueException.
		 */
		static protected function yield($message = NULL)
		{
			throw new MoorContinueException($message);
		}

		/**
		 * Do not instantiate controllers
		 *
		 * @final
		 * @access protected
		 * @param void
		 * @return void
		 */
		final private function __construct() {}
	}
