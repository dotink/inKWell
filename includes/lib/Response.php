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
		static private $defaultResponse = NULL;

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
		private $code = NULL;

		/**
		 *
		 */
		private $headers = array();

		/**
		 *
		 */
		private $renderer = NULL;

		/**
		 *
		 */
		private $status = NULL;

		/**
		 *
		 */
		private $type = NULL;

		/**
		 *
		 */
		private $view = NULL;

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

			self::$responses = isset($config['responses'])
				? $config['responses']
				: array();

			self::$defaultResponse = isset($config['default'])
				? $config['default']
				: self::DEFAULT_RESPONSE;

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
		 */
		static public function resolve($content = NULL)
		{
			if ($content === NULL && self::$response) {
				return self::resolve(self::$response);
			}

			if (!is_object($content)) {

				if ($content === NULL) {
					$response = self::$defaultResponse;
					$content  = isset(self::$responses[self::$defaultResponse]['body'])
						? self::$responses[self::$defaultResponse]['body']
						: NULL;

				} else {
					$response = 'ok';
				}

				//
				// Sometimes our response is just a string or a number or whatever.  This allows
				// us to take any such response and hopefully cache it.  We make two passes
				//

				$cache_file = self::cache(NULL, $content);

				return new self($response, $cache_file->getMimeType(), array(), $content);

			} elseif ($response instanceof self) {

				return $response;

			}

			//
			// This will support legacy methods whereby content type and headers was
			// sent more manually inside the controller
			//

			return new self('ok', NULL, array(), $response);
		}

		/**
		 * This will send a cache file for the current unique URL based on a mime type.
		 *
		 * The response will only be sent if the cached response is less than the $max_age
		 * parameter in seconds.  This defaults to 120, meaning that if the file is older
		 * than 2 minutes, this function will return.  Otherwise, the cache file is sent and
		 * the script exits.
		 *
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
		 * Renders a view (proper or not) to json
		 *
		 * @param mixed $view The data to attempt to render to JSON
		 * @return string A valid JSON string
		 * @throws fProgrammerException if the response code is undefined
		 */
		static protected function renderJSON($response)
		{
			if (is_string($view) && fJSON::decode($view) !== NULL) {
				return $view;
			}

			return fJSON::encode($view);
		}

		/**
		 * Resolves a response short name into the appropriate code
		 *
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
		 * @param
		 */
		public function __construct($status, $type = NULL, $headers = array(), $view = NULL)
		{
			$this->status   = $status;
			$this->code     = self::translateCode($status);
			$this->type     = strtolower($type);
			$this->renderer = isset(self::$renderers[$type])
				? self::$renderers[$type]
				: NULL;

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
		 */
		public function send()
		{
			$version = end(explode($_SERVER['SERVER_PROTOCOL'], '/'));
			$aliases = array(
				'1.0' => array( 405 => 400, 406 => 400 /* NO NEED FOR REDIRECTS */ ),
				'1.1' => array( /* CURRENT VERSION OF HTTP SO WE SHOULD BE GOOD */ )
			);

			$response = ucwords(fGrammar::humanize($this->status));
			$status   = isset($aliases[$version][$this->code])
				? $aliases[$version][$this->code]
				: $this->code;

			if (!iw::checkSAPI('cgi-fcgi')) {
				header(sprintf('%s %d %s', $_SERVER['SERVER_PROTOCOL'], $status, $response));
			} else {
				$this->headers['Status'] = sprintf('%d %s', $status, $response);
			}

			if (is_callable($this->renderer)) {
				$content = call_user_func($this->renderer, $this->view, $this);
			} else {
				$content = $this->view;
			}

			$this->headers['Content-Type'] = $this->type;

			foreach ($this->headers as $header => $value) {
				header($header . ': ' . $value);
			}

			if (is_object($content)) {
				switch (strtolower(get_class($content))) {
					case 'view':
						$content->render();
						exit(1);
					case 'fimage':
					case 'ffile':
						$content->output();
						exit(1);
				}
			}

			echo $content;
			exit(1);
		}
	}