<?php

	/**
	 * The inKWELL view class
	 *
	 * Views are instantiated objects which loosely couple data to templated languages.  These
	 * objects are primarily designed to work with HTML, so a number of helper methods are provided
	 * to more easily work with markup.
	 *
	 * @author Matthew J. Sahagian [mjs] <gent@dotink.org>
	 * @copyright Copyright (c) 2012, Matthew J. Sahagian
	 * @license Please reference the LICENSE.txt file at the root of this distribution
	 *
	 * @package inKWell
	 */
	class View extends fTemplating implements inkwell, ArrayAccess, JSONSerializable
	{
		const DEFAULT_VIEW_ROOT     = 'views';
		const DEFAULT_CACHE_DIR     = 'cache';
		const PRIMARY_VIEW_ELEMENT  = '__main__'; // This must match Flourish
		const MASTER                = 'default';

		/**
		 * The path from which relative views are loaded
		 *
		 * @static
		 * @access private
		 * @var string|fDirectory
		 */
		static private $viewRoot = NULL;

		/**
		 * The path where cached views and compressed styles/scripts are stored
		 *
		 * @static
		 * @access private
		 * @var string|fDirectory
		 */
		static private $cacheDirectory = NULL;

		/**
		 * The minification mode for compressing styles/scripts
		 *
		 * @static
		 * @access private
		 * @var string|NULL
		 */
		static private $minificationMode = NULL;

		/**
		 * The data storage area
		 *
		 * @access private
		 * @var array
		 */
		private $data = array();

		/**
		 * If the template represents string content
		 *
		 * @access private
		 * @var boolean
		 */
		private $isFood = FALSE;

		/**
		 * Initializes the templating engine
		 *
		 * @static
		 * @access public
		 * @param array $config The configuration array
		 * @param string $element The element name of the configuration array
		 * @return void
		 */
		static public function __init(array $config = array(), $element = NULL)
		{

			self::$viewRoot = implode(DIRECTORY_SEPARATOR, array(
				iw::getRoot(),
				($root_directory = iw::getRoot($element))
					? $root_directory
					: self::DEFAULT_VIEW_ROOT
			));

			self::$viewRoot       = new fDirectory(self::$viewRoot);
			self::$cacheDirectory = iw::getWriteDirectory(
				isset($config['cache_directory'])
					? $config['cache_directory']
					: self::DEFAULT_CACHE_DIRECTORY
			);

			try {
				self::$cacheDirectory = new fDirectory(self::$cacheDirectory);
			} catch (fValidationException $e) {
				throw new fEnvironmentException (
					'Cache directory %s does not exist',
					self::$cacheDirectory
				);
			}

			if (!self::$cacheDirectory->isWritable()) {
				throw new fEnvironmentException (
					'Cache directory %s is not writable',
					self::$cacheDirectory
				);
			}

			if (!isset($config['disable_minification']) || !$config['disable_minification']) {
				if (isset($config['minification_mode'])) {
					if ($config['minification_mode']) {
						self::$minificationMode = $config['minification_mode'];
					}
				} else {
					self::$minificationMode = iw::getExecutionMode();
				}
			}

			//
			// Attaching a NULL view as the default prevents exceptions in the event of
			// no views being attached later.
			//
			self::attach(NULL);
		}

		/**
		 * A simple factory method for creating new views based on a file.
		 *
		 * @static
		 * @access public
		 * @param string|array $view_file The view file or an array of candidate view files to load
		 * @param array $data An optional array of initial data
		 * @param array $elements An optional array of child elements
		 * @return View The view object with the view file loaded
		 */
		static public function create($view_file, $data = array(), $elements = array())
		{
			$view = new self();
			$view->load($view_file);
			$view->pack($data);
			$view->set($elements);

			return $view;
		}

		/**
		 * Check whether or not a particular view file exists relative to the
		 * view root.
		 *
		 * @static
		 * @access public
		 * @param string $view_file The relative path to the view file to check
		 * @return boolean TRUE if the view file is readable, FALSE otherwise
		 */
		static public function exists($view_file)
		{
			if (!preg_match(iw::REGEX_ABSOLUTE_PATH, $view_file)) {
				$view_file = self::$viewRoot . $view_file;
			}

			return is_readable($view_file);
		}

		/**
		 * Normalizes a data set.
		 *
		 * @static
		 * @access private
		 * @param mixed $data_set The data set key or data set
		 * @param mixed $value The value of $data_set if provided a key
		 * @return array The data set represented as an array
		 * @throws fProgrammerException if the data set is not valid.
		 */
		static private function normalizeDataSet($data_set, $value = NULL)
		{
			if (!is_array($data_set)) {
				if (is_string($data_set) || is_int($data_set)) {
					return $data_set = array($data_set => $value);
				} else {
					throw new fProgrammerException(
						'Invalid data set supplied, must be a string or integer'
					);
				}
			}

			return $data_set;
		}

		/**
		 * Creates a new view object.
		 *
		 * @access public
		 * @param string $view_root An overried for the configured root_directory for views
		 * @return void
		 */
		public function __construct($view_root = NULL)
		{
			parent::__construct(
				$view_root === NULL
					? self::$viewRoot
					: $view_root
			);

			if (self::$minificationMode) {
				$this->enableMinification(
					self::$minificationMode,
					self::$cacheDirectory
				);
			}
		}

		/**
		 * Loads a view file.  If the file begins with a '/' it will be looked for relative to the
		 * document root.  If the file does not it will be relative to the configured view root.
		 * If the first parameter is an array of files, the first one to exist will be used.
		 *
		 * @access public
		 * @param string|array $file The path to the view file or an array of candidate files
		 * @return View The view object, to allow for method chaining
		 */
		public function load($file)
		{
			if (is_array($file)) {
				foreach ($file as $candidate_file) {
					if (self::exists($candidate_file)) {
						$file = $candidate_file;
						break;
					}
				}
			}

			$this->set(self::PRIMARY_VIEW_ELEMENT, $file);

			return $this;
		}

		/**
		 * Gets the output of the view or a particular view element.
		 *
		 * @param string $element An optional name of an element to output
		 * @return string The output of the view
		 */
		public function make($element = NULL)
		{
			try {
				ob_start();
				$this->place($element);
				return ob_get_clean();
			} catch (fException $e) {
				ob_end_clean();
				throw $e;
			}
		}

		/**
		 * Outputs the view or a particular view element to the screen.
		 *
		 * @access public
		 * @param string $element An optional name of an element to output
		 * @return void
		 */
		public function render($element = NULL)
		{
			echo $this->make($element);
		}

		/**
		 * Places a view or view element.
		 *
		 * @access public
		 * @param string $element The name of the element
		 * @param string $file_type An optional forced file_type (irrelevant for feeds)
		 * @return void
		 */
		public function place($element = NULL, $file_type = NULL)
		{
			if ($element === NULL) {
				$element = self::PRIMARY_VIEW_ELEMENT;
			}

			if ($this->isFood) {
				echo $this->get($element);
			} else {
				return parent::place($element, $file_type);
			}
		}

		/**
		 * Adds string content to a view object as referenced by the element.
		 *
		 * When the element is placed the string content is directly outputted.  This is generally
		 * only used in special circumstances where a placed element may be either a view template
		 * file *or* a string.  In short, this is for non-data elements which do not have template
		 * files.  If the second parameter is NULL content will be loaded directly into the view.
		 *
		 * @access public
		 * @param string $element The name of the element, or content for primary element
		 * @param string $content Content to consume for a specific element
		 * @return View The view object to allow for method chaining
		 */
		public function digest($element, $content = NULL)
		{
			if ($content === NULL) {
				$this->isFood = TRUE;
				$this->load($element);
			} else {
				$view         = new self();
				$view->isFood = TRUE;
				$view->load($content);
				$this->add($element, $view);
			}

			return $this;
		}

		/**
		 * Provides isset test functionality when view is used as an array
		 *
		 * @param mixed $offset The key or index to check for existence
		 * @return boolean TRUE if the data element referred to by $offset exists, FALSE otherwise
		 */
		public function offsetExists($offset)
		{
			return isset($this->data[$offset]);
		}

		/**
		 * Provides the ability to access data elements using the view object as an array
		 *
		 * @param mixed $offset The key or index to get
		 * @return mixed The value of the data referred to by $offset
		 */
		public function offsetGet($offset)
		{
			return $this->pull($offset);
		}

		/**
		 * Provides the ability to set data elements using the view object as an array
		 *
		 * @param mixed $offset The key or index to set
		 * @return void
		 */
		public function offsetSet($offset, $value)
		{
			$this->pack($offset, $value);
		}

		/**
		 * Provides the ability to unset data elements using the view object as an array
		 *
		 * @param mixed $offset The key or index to unset
		 * @return void
		 */
		public function offsetUnset($offset)
		{
			unset($this->data[$offset]);
		}

		/**
		 * Pack's data into the view object's data referenced by the data set.
		 *
		 * This method destroys any existing data referenced by the data set.
		 *
		 * @access public
		 * @param string|array $data_set A string indicating the data set to pack into
		 * @param mixed $value The value to pack into the data set
		 * @return View The view object to allow for method chaining
		 */
		public function pack($data_set, $value = NULL)
		{
		 	$data_set = self::normalizeDataSet($data_set, $value);

			foreach ($data_set as $key => $value) {
				$this->data[$key] = $value;
			}

			return $this;
		}

		/**
		 * Pushes data onto the end of the view object's data referenced by the data set.
		 *
		 * If an element is pushed onto data which is not an array, it will become one.
		 *
		 * @access public
		 * @param string $data_set A string indicating the data set to push into
		 * @param mixed $value The value to push into the data set
		 * @return View The view object to allow for method chaining
		 */
		 public function push($data_set, $value = NULL)
		 {
		 	$data_set = self::normalizeDataSet($data_set, $value);

		 	foreach ($data_set as $key => $value) {
		 		if (!array_key_exists($key, $this->data)) {
		 			$this->data[$key] = array();
		 		} elseif (!is_array($this->data[$key])) {
		 			$this->data[$key] = array($this->data[$key]);
		 		}

		 		$this->data[$key][] = $value;
		 	}

		 	return $this;
		 }

		/**
		 * Pulls data referenced by a key from the view object's data.
		 *
		 * Optionally the data can be destroyed after being pulled and will no longer be accessible
		 * through future calls.
		 *
		 * @access public
		 * @param string $key The key of the data to try an pull
		 * @param mixed $default The default value if the key is not found
		 * @param boolean $destructive Whether or not to destroy the data
		 * @return mixed The pulled data from the data storage array
		 */
		public function pull($key, $default = NULL, $destructive = FALSE)
		{
			if (array_key_exists($key, $this->data)) {

				$data = $this->data[$key];

				if ($destructive) {
					unset($this->data[$key]);
				}

				return $data;

			}  elseif (func_num_args() > 1) {
				return $default;
			} else {
				throw new fProgrammerException (
					'Data referenced by %s does not exist.', $key
				);
			}
		}

		/**
		 * Peels data off the end of the referenced view object's data.
		 *
		 * Optionally the data can be destroyed.  Please note, that if the data is not an array
		 * this becomes functionally equivalent to pull.
		 *
		 * @access public
		 * @param string $key The key of the data from which to pop a value
		 * @param mixed $default The default value if the key is not found
		 * @param boolean $destructive Whether or not to destroy the data
		 * @return mixed The peeled data from the end of the data storage array
		 */
		public function peel($key, $default = NULL, $destructive = FALSE)
		{
			if (array_key_exists($key, $this->data)) {
				if (is_array($this->data[$key])) {

					if ($destructive) {
						return array_pop($this->data[$key]);
					} else {
						return end($this->data[$key]);
					}

				} else {

					return $this->pull($key, $destructive);
				}

			} elseif (func_num_args() > 1) {
				return $default;
			} else {
				throw new fProgrammerException (
					'Data referenced by %s does not exist.', $key
				);
			}
		}

		/**
		 * Iterates over an element and outputs a partial or callable emitter for each member.
		 *
		 * If the emitter is a callback it can accept up to three arguments, the first being the
		 * current value in the element stack, the second being it's index / key, and the third
		 * being the current view object.  As of php 5.4 the third parameter is deprecated as the
		 * view should be available as $this.
		 *
		 * If the emitter is an array, the current value in the element stack will be available
		 * in the partial as the variable which matches the provided key in the array, the index
		 * / key for the value will always be $i, and the view object is accessible through $this.
		 *
		 * Examples:
		 *
		 * $this->repeat('users', array('user' => 'partials/user.php'));
		 *
		 * $this->repeat('users', function($user, $i, $this){
		 *		echo $user->prepareName();
		 * })
		 *
		 * The $key parameter can alternatively be an array to iterate over.
		 *
		 * @acces public
		 * @param string|array $key The key of the view object data which to repeat, or an array
		 * @param array|callback $emitter The function or partial which will be emitted
		 * @return View The view object to allow for method chaining
		 */
		public function repeat($key, $emitter)
		{
			if (is_array($key) || is_object($key)) {
				$data = $key;
			} else {
				$is_traversable = (
					array_key_exists($key, $this->data) &&
					(
						$this->data[$key] instanceof Traversable ||
						is_array($this->data[$key])              ||
						is_object($this->data[$key])
					)
				);

				if ($is_traversable) {
					$data = $this->data[$key];
				} else {
					throw new fProgrammerException (
						'Data referenced by %s does not exist or is not traversable.', $key
					);
				}
			}

			if (is_callable($emitter)) {

				foreach ($data as $i => $value) {
					$emitter($value, $i, $this);
				}

			} elseif (is_array($emitter)) {

				$element = key($emitter);

				if (!preg_match(iw::REGEX_VARIABLE, $element)) {
					throw new fProgrammerException (
						'Array emitter key must be valid variable name.'
					);
				}

				$partial = reset($emitter);

				if (!preg_match(iw::REGEX_ABSOLUTE_PATH, $partial)) {
					$partial = self::$viewRoot . $partial;
				}

				if (!self::exists($partial)) {
					throw new fProgrammerException (
						'The partial %s is unreadable',
						$partial
					);
				}

				foreach ($data as $i => $$element) {
					include $partial;
				}

			} else {
				throw new fProgrammerException (
					'Invalid data type for emitter, must be a callback or an array'
				);
			}
		}

		/**
		 * Verifies/Checks view data.
		 *
		 * View data is checked by ensuring that *all* elements identified by the keys of $matches
		 * equal their respective value in the $matches array.  If the value is itself an array,
		 * then the value of the provided key in the View object's data array is checked against
		 * all values of the array, requiring only one match.  If $matches is not an array, but
		 * a string, the method merely checks to see if that key exists in the View object's data
		 * array (it says nothing about the value resolving to TRUE).
		 *
		 * @access public
		 * @param array|string $matches An array of key (data element) / value (to match against) pairs or a key to check for.
		 * @return boolean TRUE if the data element identified by the key matches or is contained in the value.
		 */
		public function check($matches)
		{
			if (!is_array($matches)) {
				return array_key_exists($matches, $this->data) && $this->data[$matches];
			}

			foreach ($matches as $key => $active_value) {
				$match = FALSE;
				if (array_key_exists($key, $this->data)) {
					if (is_array($this->data[$key])) {
						$match = in_array($active_value, $this->data[$key]);
					} else {
						$match = $this->data[$key] == $active_value;
					}
				}
				if (!$match) {
					return FALSE;
				}
			}

			return TRUE;
		}

		/**
		 * Combines the view element $element together with the separate provided by $separator.
		 *
		 * @access public
		 * @param string $element The name of the element to combine
		 * @param string $separator The string which separates the pieces
		 * @return string The string of combined elements, or an empty string if the element was not an array
		 */
		public function combine($key, $separator = ' :: ') {
			if (array_key_exists($key, $this->data)) {
				if (is_array($this->data[$key])) {
					return implode($separator, $this->data[$key]);
				} else {
					return $this->data[$key];
				}
			}

			return '';
		}

		/**
		 * Reverse combines the view element $element together with the separator provided by
		 * $separator.
		 *
		 * @access public
		 * @param string $key The key of the data which to combine
		 * @param string $separator The string which separates the pieces
		 * @return string The string of combined elements, or an empty string if the element was not an array
		 */
		public function rcombine($key, $separator = ' :: ') {
			if (array_key_exists($key, $this->data)) {
				if (is_array($this->data[$key])) {
					return implode($separator, array_reverse($this->data[$key]));
				} else {
					return $this->data[$key];
				}
			}

			return '';
		}

		/**
		 * Preps the View for JSON Serialization
		 *
		 * @access public
		 * @return object A JSON encodable object of all the data in the view
		 */
		public function jsonSerialize()
		{
			return (object) $this->data;
		}
	}
