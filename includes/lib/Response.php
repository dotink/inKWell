<?php

	/**
	 * The inKWell Response
	 *
	 * The Response class is designed to allow for concise and clean representation of an entire
	 * HTTP response.  It handles the isolation of various response codes and a 'pluggable'
	 * renderer for varying response mime types.
	 *
	 * @author Matthew J. Sahagian [mjs] <gent@dotink.org>
	 * @copyright Copyright (c) 2012, Matthew J. Sahagian
	 * @license Please reference the LICENSE.txt file at the root of this distribution
	 *
	 * @package inKWell
	 */
	class Response implements inkwell
	{
		const DEFAULT_CACHE_DIRECTORY = 'cache/.responses';
		const DEFAULT_RESPONSE        = 'not_found';

		/**
		 * Location of the cache directory
		 *
		 * @static
		 * @access private
		 * @var string
		 */
		static private $cacheDirectory = NULL;

		/**
		 * Registered response for multi-response resolution
		 *
		 * @static
		 * @access private
		 * @var string
		 */
		static private $response  = NULL;

		/**
		 * A list of available responses
		 *
		 * @static
		 * @access private
		 * @var array
		 */
		static private $responses = array();

		/**
		 * A list of available renderers
		 *
		 * @static
		 * @access private
		 * @var array
		 */
		static private $renderers = array();

		/**
		 * A list of available render methods
		 *
		 * @static
		 * @access private
		 * @var array
		 */
		static private $render_methods = array();

		/**
		 * The view for the response
		 *
		 * @access protected
		 * @var mixed
		 */
		protected $view = NULL;

		/**
		 * The status of the response, ex: 'ok'
		 *
		 * @access private
		 * @var string
		 */
		private $status = NULL;

		/**
		 * The code of the response, ex: 200
		 *
		 * @access private
		 * @var integer
		 */
		private $code = NULL;

		/**
		 * The content/mime type of the response, ex: 'text/html'
		 *
		 * @access private
		 * @var string
		 */
		private $type = NULL;

		/**
		 * A list of headers to output if the response is sent
		 *
		 * @access private
		 * @var array
		 */
		private $headers = array();

		/**
		 * The render hooks which will be applied to the view on sending
		 *
		 * @access private
		 * @var array
		 */
		private $renderHooks = array();

		/**
		 * Initialize the class
		 *
		 * @param array $config The configuration array for the class
		 * @param string $element The element name
		 * @return boolean TRUE on sucess, FALSE on failure
		 */
		static public function __init($config, $element = NULL)
		{
			if (iw::classize($element) !== __CLASS__) {
				return TRUE;
			}

			self::$cacheDirectory = isset($config['cache_directory'])
				? iw::getWriteDirectory($config['cache_directory'])
				: iw::getWriteDirectory(self::DEFAULT_CACHE_DIRECTORY);

			$required_responses = array(
				'ok' => array(
					'code' => 200,
					'body' => NULL
				),
				'no_content' => array(
					'code' => 204,
					'body' => NULL
				),
				'not_found' => array(
					'code' => 404,
					'body' => 'The requested resource could not be found'
				),
				'not_allowed' => array(
					'code' => 405,
					'body' => 'The requested resource does not support this method'
				),
				'not_acceptable' => array(
					'code' => 406,
					'body' => 'The requested resource is not available in the accepted format'
				),
				'internal_error' => array(
					'code' => 500,
					'body' => 'The requested resource is not available due to an internal error'
				)
			);

			self::$responses = isset($config['responses'])
				? array_merge($required_responses, $config['responses'])
				: $required_responses;

			if (is_array($default_renderers = iw::getConfig('response', 'renderers'))) {
				self::$renderers = $default_renderers;
			}

			foreach (iw::getConfigsByType('Response') as $config) {
				if (isset($config['renderers'])) {
					self::$renderers = array_merge(self::$renderers, $config['renderers']);
				}
			}

			return TRUE;
		}

		/**
		 * Clear the registered response and return it.
		 *
		 * @static
		 * @access public
		 * @param void
		 * @return Response The previously registered response
		 */
		static public function clear()
		{
			$registered_response  = self::$response;
			self::$response       = NULL;

			return $registered_response;
		}

		/**
		 * Register a response to be resolved later.
		 *
		 * This will register a response (object or otherwise) which can be resolved by passing
		 * NULL to the Response::resolve() method.
		 *
		 * @static
		 * @access public
		 * @param void
		 * @return Response The previously registered response
		 */
		static public function register($response)
		{
			self::$response = $response;
		}

		/**
		 * Registers a render method for a particular class
		 *
		 * This allows for classes to modularly set a render method to be used during response
		 * resolution of a view.  In short, if the view ends up as an instance of the provided
		 * class, it will call the given method to render it.  This is an exact match so
		 * a class which might inherit from a class with an existing registered method needs to
		 * register its own again.
		 *
		 * @static
		 * @access public
		 * @param string $class The class to register a render method for.
		 * @param string $method The method to call on the object to render it
		 * @return void
		 */
		static public function registerRenderMethod($class, $method)
		{
			self::$render_methods[strtolower($class)] = $method;
		}

		/**
		 * Resolves a response one way or another.
		 *
		 * This will basically turn whatever you pass it into a response object.  The assumption
		 * is, if you actually pass it data, that you are looking to return "ok".  It will use the
		 * cache system to make attempts to determine the mime type and cache it for future use.
		 *
		 * @static
		 * @access public
		 * @param mixed $content The content to resolve to a response
		 * @return Response
		 */
		static public function resolve($content = NULL)
		{
			if (isset(self::$response)) {
				$content = self::resolve(self::clear());
			}

			if (!($content instanceof self)) {

				//
				// Previous versions of inKWell may have responded with objects such as View or
				// fImage.  The short answer here is that if we receive content to resolve, we
				// are going to assume that they want an OK, as controller::triggerError() was
				// still promoted.  If that is the case, we can create a response object
				// directly and use the content as we see fit.  The send() method will take care
				// of how to output it.
				//

				$content = new self('ok', NULL, array(), $content);

			}

			return $content;
		}

		/**
		 * Rendering callback for object typed views.
		 *
		 * @static
		 * @access protected
		 * @param Response $response The response to render
		 * @return void
		 */
		static protected function renderObject($response)
		{
			if (is_object($response->view)) {
				$view_class = get_class($response->view);
				$render_key = strtolower($view_class);

				if (isset(self::$render_methods[$render_key])) {
					$method         = self::$render_methods[$render_key];
					$response->view = $response->view->$method();
				} elseif (is_callable(array($response->view, '__toString'))) {
					$response->view = (string) $response->view;
				} else {
					$response->view = $view_class;
				}
			}
		}

		/**
		 * This will send a cache file for the current unique URL based on a mime type.
		 *
		 * The response will only be sent if the cached response is less than the $max_age
		 * parameter in seconds.  This defaults to 120, meaning that if the file is older
		 * than 2 minutes, this function will return.  Otherwise, the cache file is sent and
		 * the script exits.
		 *
		 * @static
		 * @access public
		 * @param string $type The mime type for the cached response
		 * @param string $max_age The time in seconds when the cache shouldn't be used, default 120
		 * @param string $entropy_data A string to calculate entropy from, default NULL
		 * @param string $max_entropy The maximum amount of entropy allowed, default 0
		 */
		static public function sendCache($type, $max_age = 120, $entropy = NULL, $max_entropy = 0)
		{
			return;
		}

		/**
		 * Resolves a response short name into the appropriate code
		 *
		 * @static
		 * @access protected
		 * @param string $response The response name, ex: 'ok' or 'not_found'
		 * @return int The response code
		 * @throws fProgrammerException if the response code is undefined or non-numeric
		 */
		static protected function translateCode($response_name)
		{
			$response_name = strtolower($response_name);

			if (isset(self::$responses[$response_name]['code'])) {
				$response_code = self::$responses[$response_name]['code'];

				if (is_numeric($response_code)) {
					return $response_code;
				}
			}

			throw new fProgrammerException(
				'Cannot create response with undefined or invalid code "%s"',
				$response_name
			);
		}

		/**
		 * Caches a file for the current unique URL using the data type as part of its id.
		 *
		 * @static
		 * @access private
		 * @param string $data_type The data type for the request to match the cache
		 * @param string $data The data to cache
		 */
		static private function cache($data_type, $data)
		{
			if (!$data_type) {
				$data_type = 'text/plain';
			}

			$cache_id   = md5(fURL::getWithQueryString() . $data_type);
			$cache_file = self::$cacheDirectory . iw::DS . $cache_id . '.txt';

			try {
				$cache_file = new fFile($cache_file);

				if ($cache_file->read() != $data) {
					$cache_file->write($data);
				}
			} catch (fValidationException $e) {
				$cache_file = fFile::create($cache_file, $data);
			}

			return $cache_file;
		}

		/**
		 * Create a new response object
		 *
		 * @access public
		 * @param string $status The status string, ex: 'ok', 'not_found', ...
		 * @param string $type The mimetype to send as
		 * @param array $headers Additional headers to output
		 * @param mixed $view The view to send, i.e. the content
		 * @return void
		 */
		public function __construct($status, $type = NULL, $headers = array(), $view = NULL)
		{
			$this->status   = $status;
			$this->code     = self::translateCode($status);
			$this->type     = ($type)
				? strtolower($type)
				: NULL;

			foreach (self::$renderers as $type_match => $callback) {
				if (preg_match('#' . $type_match . '#', $this->type)) {
					$this->renderHooks[] = $callback;
				}
			}

			//
			// Add our render object callback at the end in case our view turns out to be
			// an object not handled by previous renderers.
			//

			$this->renderHooks[] = iw::makeTarget('Response', 'renderObject');

			//
			// Set our headers and view.  This will vary depending on how many parameters they
			// provided.
			//

			if (func_num_args() > 3) {
				$this->headers = func_get_arg(2);
				$this->view    = func_get_arg(3);
			} else {
				$this->headers = array();
				$this->view    = func_get_arg(2);
			}
		}

		/**
		 * Set an individual header on the response
		 *
		 * @access public
		 * @param string $header The header to set
		 * @param string $value The value for it
		 * @return Response The response object for chaining
		 */
		public function setHeader($header, $value)
		{
			$this->headers[$header] = $value;
			return $this;
		}

		/**
		 * Sends the response to the screen
		 *
		 * @access public
		 * @param void
		 * @return void
		 */
		public function send()
		{
			$version  = end(explode($_SERVER['SERVER_PROTOCOL'], '/'));
			$aliases  = array(
				'1.0' => array( 405 => 400, 406 => 400 /* NO NEED FOR REDIRECTS */ ),
				'1.1' => array( /* CURRENT VERSION OF HTTP SO WE SHOULD BE GOOD */ )
			);

			//
			// If for some reason we have been provided a NULL view we should try to
			// see if we have a default body for the response type.  If we don't, let's
			// provide a legitimate no content response.
			//

			if ($this->view === NULL) {
				if (isset(self::$responses[$this->status]['body'])) {
					$this->view = fText::compose(
						self::$responses[$this->status]['body']
					);
				} else {
					$this->status = 'no_content';
					$this->view   = NULL;
				}
			}

			//
			// We want to let any renderers work their magic before doing anything else.  A good
			// renderer will do whatever it can to resolve the response to a string.  Otherwise
			// whatever the response is will be casted as a string and may not do what one
			// expects.
			//

			if ($this->view !== NULL && count($this->renderHooks)) {
				foreach ($this->renderHooks as $renderCallback) {
					if (is_callable($renderCallback)) {
						call_user_func($renderCallback, $this);
					}
				}
			}

			$this->view   = (string) $this->view;
			$this->status = ucwords(fGrammar::humanize($this->status));
			$this->code   = isset($aliases[$version][$this->code])
				? $aliases[$version][$this->code]
				: $this->code;

			//
			// If we don't have a type set we will try to determine the type by caching
			// our view as a file and getting it's mimeType.
			//

			$this->type = (!$this->type)
				? self::cache(NULL, $this->view)->getMimeType()
				: $this->type;

			//
			// Output all of our headers.
			//
			// Apparently fastCGI explicitly does not like the standard header format, so
			// so we send different leading headers based on that.  The content type downward,
			// however, is exactly the same.
			//

			header(!iw::checkSAPI('cgi-fcgi')
				? sprintf('%s %d %s', $_SERVER['SERVER_PROTOCOL'], $this->code, $this->status)
				: sprintf('Status: %d %s', $this->code, $this->status)
			);

			if ($this->code != 204) {
				header(sprintf('Content-Type: %s', $this->type));
			}

			foreach ($this->headers as $header => $value) {
				header($header . ': ' . $value);
			}

			//
			// Last, but not least, echo our view.
			//

			echo $this->view;
			exit(1);
		}
	}