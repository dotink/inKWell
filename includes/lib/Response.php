<?php

	class Response implements inkwell
	{
		const DEFAULT_CACHE_DIRECTORY = '.response_cache';
		const DEFAULT_RESPONSE = 'not_found';

		/**
		 *
		 */
		static private $cacheDirectory = NULL;

		/**
		 *
		 */
		static private $response  = NULL;

		/**
		 *
		 */
		static private $responses = array();

		/**
		 *
		 */
		static private $renderers = array();

		/**
		 *
		 */
		protected $view = NULL;

		/**
		 *
		 */
		private $status = NULL;

		/**
		 *
		 */
		private $code = NULL;

		/**
		 *
		 */
		private $type = NULL;

		/**
		 *
		 */
		private $headers = array();

		/**
		 *
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
		 * Register a response to be resolved later.
		 *
		 * This will register a response (object or otherwise) which can be resolved by passing
		 * NULL to the Response::resolve() method.
		 */
		static public function register($response)
		{
			self::$response = $response;
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
			if ($content === NULL && self::$response) {
				$content = self::resolve(self::$response);

			} elseif (!($content instanceof self)) {

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
		 * Our default and global rendering callback.
		 *
		 * @static
		 * @access protected
		 * @param Response $response The response to render
		 * @return void
		 */
		static protected function renderAny($response)
		{
			if (is_object($response->view)) {
				switch (strtolower(get_class($response->view))) {
					case 'view':
						$response->view = $response->view->make();
						break;
					case 'fimage':
					case 'ffile':
						$response->view = $response->view->read();
						break;
					default:
						$response->view = is_callable(array($response->view, '__toString'))
							? (string) $response->view
							: get_class($this->view);
						break;
				}
			}
		}

		/**
		 * Renders a view (proper or not) to JSON
		 *
		 * @static
		 * @access protected
		 * @param Response $response The response to render
		 * @return void
		 */
		static protected function renderJSON($response)
		{
			if (is_string($response->view) && fJSON::decode($response->view) !== NULL) {
				return;
			}

			$response->view = fJSON::encode($response->view);
		}

		/**
		 * Renders a view (proper or not) to PHP
		 *
		 * @static
		 * @access protected
		 * @param Response $response The response to render
		 * @return void
		 */
		static protected function renderPHP($response)
		{
			$this->view = serialize($response->view);
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
			$this->status = $status;
			$this->code   = self::translateCode($status);
			$this->type   = strtolower($type);

			if (isset(self::$renderers['*'])) {
				$this->renderHooks[] = self::$renderers['*'];
			}

			foreach (self::$renderers as $type_match => $callback) {
				if ($type_match != '*' && preg_match('#' . $type_match . '#', $this->type)) {
					$this->renderHooks[] = $callback;
				}
			}

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
			// We want to let any renderers work their magic before deciding anything.
			//
			if (count($this->renderHooks)) {
				foreach ($this->renderHooks as $callback) {
					if (is_callable($this->renderHooks)) {
						call_user_func($callback, $this);
					}
				}
			}

			//
			// If after all rendering, we still don't have a view, we will try to get a
			// default body based on our configured responses.
			//

			if (!$this->view) {
				if (isset(self::$responses[$this->status]['body'])) {
					$this->view   = fText::compose(self::$responses[$this->status]['body']);
				} else {
					$this->status = 'no_content';
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

			if (!$this->type) {
				$this->type = self::cache(NULL, $this->view)->getMimeType();
			}

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