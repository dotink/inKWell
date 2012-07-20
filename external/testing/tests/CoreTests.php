<?php

	class CoreTests extends \Enhance\TestFixture
	{
		public function setUp()
		{
			ob_start();
		}

		public function iwCreateConfig()
		{
			\Enhance\Assert::areIdentical(
				array(),
				iw::createConfig(array())
			);

			\Enhance\Assert::areIdentical(
				array('foo' => 'bar'),
				iw::createConfig(array('foo' => 'bar'))
			);
		}

		public function iwCreateConfigWithType()
		{
			\Enhance\Assert::areIdentical(
				array('__type' => 'bar'),
				iw::createConfig('Bar', array())
			);

			\Enhance\Assert::areIdentical(
				array('__type' => 'bar'),
				iw::createConfig('bar', array())
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

		public function iwGetRootWithDefaultAndInvalidElement()
		{
			\Enhance\Assert::areIdentical(
				APPLICATION_ROOT . iw::DS . 'default',
				iw::getRoot('foo', 'default')
			);
		}

		public function iwBuildConfig()
		{
			\Enhance\Assert::isTrue(is_array(iw::buildConfig()));
		}


		public function iwBuildMergedConfig()
		{
			$config = iw::buildConfig(implode(iw::DS, array(
				TEST_ROOT,
				'config'
			)));

			\Enhance\Assert::isTrue(is_array($config));
		}

		public function iwBuildMergedConfigAndVerifyValue()
		{
			$config = iw::buildConfig(implode(iw::DS, array(
				TEST_ROOT,
				'config'
			)));

			\Enhance\Assert::areIdentical('foo', $config['test_element']['test_value']);
		}


		public function iwBuildMergedConfigAndVerifyOverloadedValue()
		{
			$config = iw::buildConfig(implode(iw::DS, array(
				TEST_ROOT,
				'config'
			)));

			\Enhance\Assert::areIdentical('testing', $config['inkwell']['execution_mode']);
		}

		public function iwInit()
		{
			\Enhance\Assert::isTrue(is_array(iw::init()));
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
			$directory = implode(iw::DS, array(
				TEST_ROOT,
				'config'
			));

			\Enhance\Assert::isTrue(is_array(iw::init($directory)));
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
				iw::getConfig('database')
			);
		}

		public function iwGetConfigWithMerge()
		{
			$directory = implode(iw::DS, array(
				TEST_ROOT,
				'config'
			));

			iw::init($directory);

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
				iw::getConfig('database')
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
				iw::getConfig('database', 'databases', 'default::both')
			);
		}

		public function iwGetConfigSubElementWithMerged()
		{
			$directory = implode(iw::DS, array(
				TEST_ROOT,
				'config'
			));

			iw::init($directory);

			\Enhance\Assert::areIdentical(
				'testing',
				iw::getConfig('inkwell', 'execution_mode')
			);
		}

		public function iwGetConfigsByType()
		{
			iw::init();

			\Enhance\Assert::areIdentical(
				array('autoloaders', 'database', 'inkwell', 'routes'),
				array_keys(iw::getConfigsByType('core'))
			);

		}

		public function tearDown()
		{
			ob_end_clean();
		}
	}