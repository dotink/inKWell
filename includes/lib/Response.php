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
				// Sometimes our content is just a string or a number or whatever.  We attempt
				// to cache the response and then create a new one based on its mime type.
				//

				$cache_file = self::cache(NULL, $content);

				return new self($response, $cache_file->getMimeType(), array(), $content);

			} elseif ($content instanceof self) {

				//
				// If our content is already a response our job is easy
				//

				return $content;

			}

			//
			// Previous versions of inKWell may have responded with objects such as View or
			// fImage.  The short answer here is that if we receive content to resolve, we
			// are going to assume that they want an OK, as controller::triggerError() was
			// still promoted.  If that is the case, we can create a response object
			// directly and use the content as we see fit.  The send() method will take care
			// of how to output it.
			//

			return new self('ok', NULL, array(), $content);
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
			$version  = end(explode($_SERVER['SERVER_PROTOCOL'], '/'));
			$aliases  = array(
				'1.0' => array( 405 => 400, 406 => 400 /* NO NEED FOR REDIRECTS */ ),
				'1.1' => array( /* CURRENT VERSION OF HTTP SO WE SHOULD BE GOOD */ )
			);

			$response = ucwords(fGrammar::humanize($this->status));
			$status   = isset($aliases[$version][$this->code])
				? $aliases[$version][$this->code]
				: $this->code;

			$content  = is_callable($this->renderer)
				? call_user_func($this->renderer, $this->view, $this)
				: $this->view;

			//
			// Output all of our headers.
			//
			// Apparently fastCGI explicitly does not like the standard header format, so
			// so we send different leading headers based on that.  The content type downward,
			// however, is exactly the same.
			//

			header(!iw::checkSAPI('cgi-fcgi')
				? sprintf('%s %d %s', $_SERVER['SERVER_PROTOCOL'], $status, $response)
				: sprintf('Status: %d %s', $status, $response)
			);

			header(sprintf('Content-Type: %s', $this->type));

			foreach ($this->headers as $header => $value) {
				header($header . ': ' . $value);
			}

			//
			// Older versions of inKWell used to do something like this in the index file.
			// Basically, we want to determine if our final content is an object of some sorts
			// and call different methods depending.
			//

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

			//
			// Last, but not least, if our content appears to be just some normal variable,
			// echo it.
			//

			echo $content;
			exit(1);
		}
	}