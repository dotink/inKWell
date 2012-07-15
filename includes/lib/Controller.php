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
		static private $baseURL = NULL;

		/**
		 * The Content-Type to send on sendHeader()
		 *
		 * @static
		 * @access private
		 * @var string
		 */
		static private $contentType = NULL;

		/**
		 * The path from which relative controllers are loaded
		 *
		 * @static
		 * @access private
		 * @var string|fDirectory
		 */
		static private $controllerRoot = NULL;

		/**
		 * An array of error handlers used with triggerError()
		 *
		 * @static
		 * @access private
		 * @var array
		 */
		static private $errors = array();

		/**
		 * The default error name.
		 *
		 * @static
		 * @access private
		 * @var string
		 */
		static private $defaultError = NULL;

		/**
		 * A list of default accept mime types
		 *
		 * @static
		 * @access private
		 * @var array
		 */
		static private $defaultAcceptTypes = array();

		/**
		 * An array of available site sections and related data
		 *
		 * @static
		 * @access private
		 * @var array
		 */
		static private $siteSections = array();

		/**
		 * Whether or not Content-Type headers were sent
		 *
		 * @static
		 * @access private
		 * @var boolean
		 */
		static private $typeHeadersSent = FALSE;

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

			//
			// Build our site sections
			//
			$controller_configs = iw::getConfigsByType('controller');
			array_unshift($controller_configs, iw::getConfig('controller'));

			foreach ($controller_configs as $controller_config) {
				if (isset($controller_config['sections'])) {
					if (is_array($controller_config['sections'])) {
						self::$siteSections = array_merge(
							self::$siteSections,
							$controller_config['sections']
						);
					}
				}
			}

			//
			// Redirect to https:// if required for the section
			//
			$section = self::getBaseURL();

			$use_ssl = (isset(self::$siteSections[$section]['use_ssl']))
				? self::$siteSections[$section]['use_ssl']
				: self::DEFAULT_USE_SSL;

			if ($use_ssl && empty($_SERVER['HTTPS'])) {
				$domain     = fURL::getDomain();
				$request    = fURL::getWithQueryString();
				$ssl_domain = str_replace('http://', 'https://', $domain);

				self::redirect($ssl_domain . $request, NULL, 301);
			}

			//
			// Configure our Controller Root
			//
			self::$controllerRoot = implode(DIRECTORY_SEPARATOR, array(
				iw::getRoot(),
				($root_directory = iw::getRoot($element))
					? $root_directory
					: self::DEFAULT_CONTROLLER_ROOT
			));

			self::$controllerRoot = new fDirectory(self::$controllerRoot);

			//
			// Configure default accept types
			//
			if (isset($config['default_accept_types'])) {
				self::$defaultAcceptTypes = $config['default_accept_types'];
			} else {
				self::$defaultAcceptTypes = array(
					'text/html',
					'application/json',
					'application/xml'
				);
			}

			//
			// Configure errors and error handlers
			//
			if (isset($config['errors']) && is_array($config['errors'])) {
				foreach ($config['errors'] as $error => $info) {
					if (!is_array($info)) {
						throw new fProgrammerException (
							'Error %s must be configured as an array.',
							$error
						);
					}

					$handler = isset($info['handler'])
						? $handler = $info['handler']
						: NULL;

					$header = isset($info['header'])
						? $header = $info['header']
						: NULL;

					$message = isset($info['message'])
						? $message = $info['message']
						: NULL;


					self::$errors[$error]['handler'] = $handler;
					self::$errors[$error]['header']  = $header;
					self::$errors[$error]['message'] = $message;
				}

				self::$defaultError = isset($config['default_error'])
					? $config['default_error']
					: NULL;
			}
		}

		/**
		 * Dynamically scaffolds a Controller class.
		 *
		 * @static
		 * @access public
		 * @param string $record_class The class name to scaffold
		 * @return boolean TRUE if the controller class was scaffolded, FALSE otherwise
		 */
		static public function __make($controller_class)
		{
			$template = implode(DIRECTORY_SEPARATOR, array(
				'classes',
				__CLASS__ . '.php'
			));

			Scaffolder::make($template, array(
				'class' => $controller_class
			), __CLASS__);

			if (class_exists($controller_class, FALSE)) {
				return TRUE;
			}

			return FALSE;
		}

		/**
		 * Triggers the default error and returns the view
		 *
		 * This is the last ditch attempt to retrieve an error view / response by the
		 * Controller system.  Although this method is public it should not be used
		 * directly.
		 *
		 * @static
		 * @access public
		 * @param void
		 * @return View The default attached view after running the default error
		 */
		static public function __error()
		{
			try {

				//
				// Trigger our default error so it attaches its view.
				//
				self::triggerError();

			} catch (MoorContinueException $e) {
				//
				// Purposefully catch the error.
				//
			}

			return View::retrieve();
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
				$accept_types = self::$defaultAcceptTypes;
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
		 * @param array $language An array of acceptable languages
		 * @return mixed The he best acceptable language upon success.
		 */
		static protected function acceptLanguages(array $languages)
		{
			return ($best_language = Request::getBestAcceptType($languages))
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
			$request_method  = strtoupper($_SERVER['REQUEST_METHOD']);
			$allowed_methods = array_map('strtoupper', $methods);

			if ($request_method == 'POST' && Request::check(Request::REQUEST_METHOD_PARAM)) {
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
			if (self::$baseURL == NULL) {
				self::$baseURL  = self::DEFAULT_SITE_SECTION;
				$request_info   = parse_url(Moor::getRequestPath());
				$request_path   = ltrim($request_info['path'], '/');
				$request_parts  = explode('/', $request_path);
				$site_sections  = array_keys(self::$siteSections);

				//
				// If the request meets these conditions it will overwrite the
				// base URL.
				//
				$has_base_url   = (in_array($request_parts[0], $site_sections));
				$is_not_default = ($request_parts[0] != self::$baseURL);
				$is_sub_request = (count($request_parts) > 1);

				if ($has_base_url && $is_not_default && $is_sub_request) {
					self::$baseURL = array_shift($request_parts);
				}
			}

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
			return (self::getBaseURL() == $base_url);
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
		 * @param int $type 3xx HTTP Code to send (normalized for HTTP version), default 302/303
		 * @return void
		 */
		static protected function redirect($target = NULL, $query = array(), $type = 303)
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

			if ($target === NULL) {
				fURL::redirect();
			}

			fURL::redirect(iw::makeLink($target, $query, NULL, FALSE));
		}

		/**
		 * Sends the appropriate headers.  Headers will be determined by the use of the
		 * acceptTypes() method.  If it has not been run prior to this method, it will be run
		 * with configured default accept types.
		 *
		 * @static
		 * @access protected
		 * @param array $headers Additional headers aside from content type to send
		 * @param boolean $send_content_type Whether or not we should send the content type header
		 * @return void
		 */
		static protected function sendHeader($headers = array(), $send_content_type = TRUE)
		{
			if (!self::$typeHeadersSent && $send_content_type) {

				if (!self::$contentType) {

					//
					// If the contentType is not set then acceptTypes was never called.
					// we can call it now with the default accept types which will set
					// both the request format and the contentType.
					//
					self::acceptTypes();
				}

				header('Content-Type: ' . self::$contentType);
				self::$typeHeadersSent = TRUE;
			}

			foreach ($headers as $header => $value) {
				header($header . ': ' . $value);
			}
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
		 * @param boolean|array $headers Whether to and, optionally, which headers to set
		 * @param string $message The message to be displayed
		 * @throws MoorContinueException
		 * @return void
		 */
		static protected function triggerError($error = NULL, $headers = TRUE, $message = NULL)
		{

			$error = ($error === NULL)
				? self::$defaultError
				: $error;

			$error_info = array(
				'handler' => NULL,
				'header'  => $_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error',
				'message' => ($message)
					? fText::compose($message)
					: fText::compose('An Unknown error occurred.')
			);

			if (isset(self::$errors[$error])) {
				if (isset(self::$errors[$error]['handler'])) {
					$error_info['handler'] = self::$errors[$error]['handler'];
				}

				if (isset(self::$errors[$error]['header'])) {
					$error_info['header'] = self::$errors[$error]['header'];
				}

				if (isset(self::$errors[$error]['message']) && !$message) {
					$error_info['message'] = self::$errors[$error]['message'];
				}
			}

			if ($error_info['handler']) {
				$view = self::exec($error_info['handler'], $error_info['message']);
			} else {
				$view = View::create(NULL, array(
					'id'      => $error,
					'classes' => array(self::MSG_TYPE_ERROR),
					'title'   => fGrammar::humanize($error),
					'error'   => $error_info['message']
				));

				$accept_types = Request::getFormat()
					? Request::getFormatTypes(Request::getFormat())
					: array();

				switch (Request::getBestAcceptType($accept_types)) {
					case 'text/html':
						$view = $view->load('html.php');
						break;
					case 'application/json':
						$view = fJSON::encode($view);
						break;
					case 'application/xml':
						$view = fXML::encode($view);
						break;
					default:
						$view = $error_info['message'];
						break;
				}
			}

			if ($headers) {
				@header($error_info['header']);

				if (is_array($headers)) {
					foreach ($headers as $header) {
						@header($header);
					}
				}
			}

			View::attach($view);

			self::yield($error_info['header']);
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
