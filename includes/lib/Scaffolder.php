<?php

	/**
	 * The inKWell Scaffolder
	 *
	 * The Scaffolder class is an extremely lightweight "templating" class designed to allow you
	 * to easily template PHP within PHP.  It has a few helper methods for cleaning up variables
	 * and validating variable names as well as the primary make and build methods.
	 *
	 * @author Matthew J. Sahagian [mjs] <gent@dotink.org>
	 * @copyright Copyright (c) 2012, Matthew J. Sahagian
	 * @license Please reference the LICENSE.txt file at the root of this distribution
	 *
	 * @package inKWell
	 */
	class Scaffolder implements inkwell
	{

		const DEFAULT_SCAFFOLDING_ROOT = 'scaffolding';
		const DYNAMIC_SCAFFOLD_METHOD  = '__make';
		const FINAL_SCAFFOLD_METHOD    = '__build';

		/**
		 * The directory containing scaffolding templates
		 *
		 * @static
		 * @access private
		 * @var string|fDirectory
		 */
		static private $scaffoldingRoot = NULL;

		/**
		 * Whether or not we are in the process of building
		 *
		 * @static
		 * @access private
		 * @var boolean
		 */
		static private $isBuilding = FALSE;

		/**
		 * A list of classes to auto-scaffold
		 *
		 * @static
		 * @access private
		 * @var array
		 */
		static private $autoScaffoldClasses = array();

		/**
		 * Contains the last scaffolded code
		 *
		 * @static
		 * @access private
		 * @var array
		 */
		static private $lastScaffoldedCode = NULL;

		/**
		 * Initializses the Scaffolder class
		 *
		 * @static
		 * @access public
		 * @param array $config The configuration array
		 * @param string $element The element name of the configuration array
		 * @return void
		 */
		static public function __init(array $config = array(), $element = NULL)
		{
			if (isset($config['disabled']) && $config['disabled']) {
				return FALSE;
			}

			self::$scaffoldingRoot = iw::getRoot('scaffolding', self::DEFAULT_SCAFFOLDING_ROOT);

			spl_autoload_register(iw::makeTarget(__CLASS__, 'loadClass'));

			foreach (iw::getConfig() as $element => $config) {
				if (isset($config['auto_scaffold']) && $config['auto_scaffold']) {
					self::$autoScaffoldClasses[] = iw::classize($element);
				}
			}

			return TRUE;
		}

		/**
		 * Attempts to load a class via Scaffolder, i.e. performs on the fly
		 * scaffolding.
		 *
		 * @static
		 * @access public
		 * @param string $class The class to be loaded
		 * @return mixed Whether or not the class was successfully loaded and initialized
		 */
		static public function loadClass($class)
		{
			foreach (self::$autoScaffoldClasses as $loader) {

				$test = iw::makeTarget($loader, iw::MATCH_CLASS_METHOD);
				$make = iw::makeTarget($loader, self::DYNAMIC_SCAFFOLD_METHOD);

				if (is_callable($test) && is_callable($make)) {
					if (call_user_func($test, $class)) {
						if (call_user_func($make, $class)) {
							return iw::initializeClass($class);
						}
					}
				}
			}

			return FALSE;
		}

		/**
		 * Builds and writes out the scaffolding for a configured builder.
		 * The builder can be a class or a template.  If a template is passed
		 * it is assumed to require no additional template variables.
		 *
		 * @static
		 * @access public
		 * @param string $builder A valid class or template name
		 * @param string $target A valid class name or file location
		 * @param string
		 * @return void
		 */
		static public function build($builder, $target, $template_vars = array())
		{
			if (preg_match(iw::REGEX_ABSOLUTE_PATH, $target)) {
				$output_file = $target;
			} else {

				//
				// If we were not provided an absolute path then we can try to get a root
				// of the target.  If the target is a relative file this will likely not
				// match anything and we'll just get it relative to the APPLICATION_ROOT.
				//

				$output_file = iw::getRoot(iw::elementize($builder)) . iw::DS . $target;
			}

			//
			// In both circumstances, we want to make sure we have an extension
			//

			$output_file = !($extension = pathinfo($target, PATHINFO_EXTENSION))
				? $output_file . '.php'
				: $output_file;

			self::$isBuilding = TRUE;

			if (preg_match(iw::REGEX_VARIABLE, $builder) && class_exists($builder)) {

				$make  = iw::makeTarget($builder, self::DYNAMIC_SCAFFOLD_METHOD);
				$build = iw::makeTarget($builder, self::FINAL_SCAFFOLD_METHOD);

				if (is_callable($make)) {
					call_user_func($make, $target, $template_vars);
				}

				return (is_callable($build))
					? call_user_func($build, $target, $output_file, self::$lastScaffoldedCode)
					: fFile::create($output_file, self::$lastScaffoldedCode);
			}

			return fFile::create($output_file, self::make($builder, $template_vars, FALSE));
		}

		/**
		 * Runs the scaffolder with a particular template.  This method will
		 * generally be called from a class's __make() method
		 *
		 * @static
		 * @access public
		 * @param string $template The template to use for scaffolding
		 * @param array $template_vars An associative array of variables => values for templating
		 * @param boolean $eval Whether or not the code should be evalulated.
		 * @return string The code
		 */
		static public function make($template, $template_vars = array(), $eval = TRUE)
		{
			if (extract($template_vars, EXTR_SKIP) == sizeof($template_vars)) {

				$template  = self::$scaffoldingRoot . iw::DS . $template;
				$template  = ($extension = pathinfo($template, PATHINFO_EXTENSION))
					? $template
					: $template . '.php';

				if (is_readable($template)) {

					$code = self::capture($template, $template_vars);

					if (self::$isBuilding) {
						self::$lastScaffoldedCode = $code;
						self::$isBuilding         = FALSE;
					}

					if ($eval) {
						eval('?>' . $code);
					}

					return $code;
				}

				throw new fProgrammerException(
					'Cannot scaffold, unable to read template "%s"',
					$template
				);

			}

			throw new fProgrammerException(
				'Cannot scaffold, invalid template variable names'
			);
		}

		/**
		 * Captures scaffolded/templated code in an isolated area
		 *
		 * @static
		 * @private
		 * @param string $___template The template to include
		 * @param array $___vars The template variables
		 * @return string The scaffolded code
		 */
		static private function capture($___template, $___vars)
		{
			ob_start();
			extract($___vars);
			include $___template;
			return '<?php' . "\n\n" . ob_get_clean();
		}

		/**
		 * Exports variables in the same sense as var_export(), however does some cleanup for
		 * arrays and other types.
		 *
		 * @static
		 * @access private
		 * @param mixed $variable The variable to export
		 * @return string A PHP parseable version of the variable
		 */
		static private function exportVariable($variable)
		{
			$translated = var_export($variable, TRUE);
			$translated = str_replace("\n", '', $translated);
			if (is_array($variable)) {
				$translated = preg_replace('# (\d+) => #', '', $translated);
				$translated = str_replace(',)', ')', $translated);
				$translated = str_replace('( ', '(', $translated);
				$translated = str_replace('array ', 'array', $translated);
			}
			return $translated;
		}

		/**
		 * Validates a string as a variable/class name.
		 *
		 * @static
		 * @access private
		 * @param string $variable
		 * @return string The class name for inclusion if it is valid
		 * @throws fValidationException In the event the variable name is unsafe
		 */
		static private function validateVariable($variable)
		{
			if (preg_match(iw::REGEX_VARIABLE, $variable)) {
				return $variable;
			} else {
				throw new fValidationException(
					'Scaffolder template detected an invalid variable named %s',
					$variable
				);
			}
		}
	}
