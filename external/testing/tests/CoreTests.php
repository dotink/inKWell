<?php

	class CoreTests extends \Enhance\TestFixture
	{
		public $testConfigDir = NULL;

		/**
		 * Our setup for core doesn't do much, about all we want to do is make sure that we
		 * buffer all output from our actual tests, because iw::buildConfig() can be noisy.  A
		 * few commonly used variables are included too.
		 */
		public function setUp()
		{
			ob_start();

			$this->testConfigDir = implode(iw::DS, array(
				TEST_ROOT,
				'config',
				'default'
			));
		}

		public function iwCreateEmptyConfig()
		{
			\Enhance\Assert::areIdentical(
				array(),
				iw::createConfig(array())
			);
		}

		public function iwCreateSimpleConfig()
		{
			\Enhance\Assert::areIdentical(
				array('foo' => 'bar'),
				iw::createConfig(array('foo' => 'bar'))
			);
		}


		public function iwCreateConfigWithLowercaseType()
		{
			\Enhance\Assert::areIdentical(
				array('__type' => 'bar'),
				iw::createConfig('bar', array())
			);
		}

		public function iwCreateConfigWithUppercaseType()
		{
			\Enhance\Assert::areIdentical(
				array('__type' => 'bar'),
				iw::createConfig('Bar', array())
			);
		}

		public function iwGetRoot()
		{
			\Enhance\Assert::areIdentical(
				APPLICATION_ROOT,
				iw::getRoot()
			);
		}

		public function iwGetRootWithDefault()
		{
			\Enhance\Assert::areIdentical(
				APPLICATION_ROOT . iw::DS . 'default',
				iw::getRoot(NULL, 'default')
			);
		}

		public function iwGetRootWithInvalidElement()
		{
			\Enhance\Assert::areIdentical(
				APPLICATION_ROOT,
				iw::getRoot('football_monkey')
			);
		}

		public function iwGetRootWithInvalidElementAndDefault()
		{
			\Enhance\Assert::areIdentical(
				APPLICATION_ROOT . iw::DS . 'default',
				iw::getRoot('foo', 'default')
			);
		}

		public function iwBuildConfig()
		{
			\Enhance\Assert::isTrue(
				is_array(iw::buildConfig())
			);
		}


		public function iwBuildMergedConfig()
		{
			$config = iw::buildConfig($this->testConfigDir);

			\Enhance\Assert::isTrue(
				is_array($config)
			);
		}

		public function iwBuildMergedConfigAndVerifyValue()
		{
			$config = iw::buildConfig($this->testConfigDir);

			\Enhance\Assert::areIdentical(
				'foo',
				$config['test_element']['test_value']
			);
		}


		public function iwBuildMergedConfigAndVerifyOverloadedValue()
		{
			$config = iw::buildConfig($this->testConfigDir);

			\Enhance\Assert::areIdentical(
				'America/New_York',
				$config['inkwell']['default_timezone']
			);
		}

		public function iwInit()
		{
			\Enhance\Assert::isTrue(
				is_array(iw::init())
			);
		}

		public function iwInitFromDirectory()
		{
			\Enhance\Assert::areIdentical(
				iw::init(),
				iw::init(APPLICATION_ROOT . iw::DS . 'config' . iw::DS . 'default')
			);
		}

		public function iwInitFromNonDefaultDirectory()
		{
			\Enhance\Assert::isTrue(is_array(iw::init($this->testConfigDir)));
		}

		public function iwGetConfig()
		{
			iw::init();

			\Enhance\Assert::areIdentical(
				array(
					'disabled'  => TRUE,
					'databases' => array(
						'default::both' => array(
							'type' => NULL,
							'name' => NULL,
							'user'     => NULL,
							'password' => NULL,
							'hosts' => array('127.0.0.1'),
						),
					),
				),
				iw::getConfig('databases')
			);
		}

		public function iwGetConfigWithMerge()
		{
			iw::init($this->testConfigDir);

			\Enhance\Assert::areIdentical(
				array(
					'disabled'  => FALSE,
					'databases' => array(
						'default::both' => array(
							'type' => 'sqlite',
							'name' => APPLICATION_ROOT . iw::DS . implode(iw::DS, array(
								'external',
								'testing',
								'databases',
								'simple'
							)),
							'user'     => NULL,
							'password' => NULL,
							'hosts' => array('127.0.0.1'),
						),
					),
				),
				iw::getConfig('databases')
			);
		}

		public function iwGetConfigSubElement()
		{
			iw::init();

			\Enhance\Assert::areIdentical(
				array(
					'type' => NULL,
					'name' => NULL,
					'user'     => NULL,
					'password' => NULL,
					'hosts' => array('127.0.0.1'),
				),
				iw::getConfig('databases', 'databases', 'default::both')
			);
		}

		public function iwGetConfigSubElementWithMerged()
		{
			iw::init($this->testConfigDir);

			\Enhance\Assert::areIdentical(
				'America/New_York',
				iw::getConfig('inkwell', 'default_timezone')
			);
		}

		public function iwGetConfigsByType()
		{
			iw::init();

			\Enhance\Assert::areIdentical(
				array('autoloaders', 'databases', 'inkwell', 'routes'),
				array_keys(iw::getConfigsByType('core'))
			);
		}

		public function iwCheckSAPI()
		{
			iw::init();

			\Enhance\Assert::areIdentical(
				TRUE,
				iw::checkSAPI('cli')
			);
		}

		public function iwCheckSAPICaseInsensitive()
		{
			iw::init();

			\Enhance\Assert::areIdentical(
				TRUE,
				iw::checkSAPI('CLI')
			);
		}

		public function iwGetActiveDomain()
		{
			iw::init($this->testConfigDir);

			\Enhance\Assert::areIdentical(
				'localhost',
				iw::getActiveDomain()
			);
		}

		public function iwClassizeByConvention()
		{
			iw::init();

			\Enhance\Assert::areIdentical(
				'ActiveRecord',
				iw::classize('active_record')
			);
		}

		public function iwElementizeByConvention()
		{
			iw::init();

			\Enhance\Assert::areIdentical(
				'active_record',
				iw::elementize('ActiveRecord')
			);
		}


		public function iwClassizeCustom()
		{
			iw::init($this->testConfigDir);

			\Enhance\Assert::areIdentical(
				'ACustomClass',
				iw::classize('test_element')
			);
		}

		public function iwElementizeCustom()
		{
			iw::init($this->testConfigDir);

			\Enhance\Assert::areIdentical(
				'test_element',
				iw::elementize('ACustomClass')
			);
		}

		public function iwMakeTarget()
		{
			iw::init();

			\Enhance\Assert::areIdentical(
				'Foo::bar',
				iw::makeTarget('Foo', 'bar')
			);
		}

		public function iwMakeLinkWithURL()
		{
			iw::init();

			\Enhance\Assert::areIdentical(
				'http://www.google.com',
				iw::makeLink('http://www.google.com')
			);
		}

		public function iwMakeLinkWithURLAndParams()
		{
			iw::init();

			\Enhance\Assert::areIdentical(
				fCore::checkVersion('5.4')
					? 'http://www.google.com?q=inkwell%20framework'
					: 'http://www.google.com?q=inkwell+framework',
				iw::makeLink('http://www.google.com', array(
					'q' => 'inkwell framework'
				))
			);
		}

		public function iwMakeLinkWithURLAndParamsAndHash()
		{
			iw::init();

			\Enhance\Assert::areIdentical(
				fCore::checkVersion('5.4')
					? 'http://www.google.com?q=inkwell%20framework#bottom'
					: 'http://www.google.com?q=inkwell+framework#bottom',
				iw::makeLink('http://www.google.com', array(
					'q' => 'inkwell framework'
				), 'bottom')
			);
		}

		public function iwGetDatabase()
		{
			iw::init($this->testConfigDir);

			\Enhance\Assert::areIdentical(
				APPLICATION_ROOT . '/external/testing/databases/simple',
				iw::getDatabase('default')->getDatabase()
			);
		}

		public function iwGetDatabaseRead()
		{
			iw::init($this->testConfigDir);

			\Enhance\Assert::areIdentical(
				APPLICATION_ROOT . '/external/testing/databases/simple',
				iw::getDatabase('default', 'read')->getDatabase()
			);
		}

		public function iwGetDatabaseWrite()
		{
			iw::init($this->testConfigDir);

			\Enhance\Assert::areIdentical(
				APPLICATION_ROOT . '/external/testing/databases/simple',
				iw::getDatabase('default', 'write')->getDatabase()
			);
		}

		public function iwGetExecutionMode()
		{
			iw::init();

			\Enhance\Assert::areIdentical(
				'development',
				iw::getExecutionMode()
			);
		}

		public function iwGetExecutionModeOverloaded()
		{
			iw::init($this->testConfigDir);

			\Enhance\Assert::areIdentical(
				'testing',
				iw::getConfig('inkwell', 'execution_mode')
			);

			\Enhance\Assert::areIdentical(
				'development',
				iw::getExecutionMode()
			);
		}

		public function iwGetRootConfig()
		{
			iw::init();

			\Enhance\Assert::areIdentical(
				APPLICATION_ROOT . '/config',
				iw::getRoot('config')
			);
		}

		public function iwGetRootConfigWithAbsoluteConfigPath()
		{
			iw::init($this->testConfigDir);

			\Enhance\Assert::areIdentical(
				APPLICATION_ROOT . '/external/testing/config',
				iw::getRoot('config')
			);
		}

		public function iwWriteConfig()
		{
			iw::init($this->testConfigDir);

			iw::writeConfig(iw::buildConfig());

			\Enhance\Assert::areIdentical(
				TRUE,
				is_file(APPLICATION_ROOT . '/external/testing/config/.default')
			);

			unlink(APPLICATION_ROOT . '/external/testing/config/.default');
		}

		public function iwReadCachedConfig()
		{
			iw::init($this->testConfigDir);
			iw::writeConfig(iw::buildConfig());

			//
			// temporarily disable output buffering to make sure there's no output
			//

			ob_end_clean();

			\Enhance\Assert::areIdentical(
				TRUE,
				is_array(iw::init($this->testConfigDir))
			);

			ob_start();

			unlink(APPLICATION_ROOT . '/external/testing/config/.default');
		}

		/**
		 * End Output Buffering
		 */
		public function tearDown()
		{
			ob_end_clean();
		}
	}
