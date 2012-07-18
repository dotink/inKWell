<?php

	/**
	 * The RecordController, a standard controller class.
	 *
	 * @author Matthew J. Sahagian [mjs] <gent@dotink.org>
	 * @copyright Copyright (c) 2011, Matthew J. Sahagian
	 */
	abstract class RecordController extends Controller
	{
		/**
		 * The master view to use, relative to the view root
		 *
		 * @static
		 * @access private
		 * @var string
		 */
		static private $masterView = NULL;

		/**
		 * The record classes for initialized controllers
		 *
		 * @static
		 * @access private
		 * @var array
		 */
		static private $recordClasses = array();

		/**
		 * The record set classes for initialized controllers
		 *
		 * @static
		 * @access private
		 * @var array
		 */
		static private $recordSetClasses = array();

		/**
		 * Initializes all static class information for the RecordController class
		 *
		 * @static
		 * @access public
		 * @param array $config The configuration array
		 * @param string $element The element name of the configuration array
		 * @return boolean TRUE if the initialization succeeds, FALSE otherwise
		 */
		static public function __init(array $config = array(), $element = NULL)
		{
			// All custom initialization goes here, make sure to check any
			// configuration you're setting up for errors and return FALSE
			// in the event the class cannot be initialized with the provided
			// configuration.

			$class = iw::classize($element);

			if ($class == __CLASS__) {
				self::$masterView = isset($config['master_view'])
					? $config['master_view']
					: 'html.php';

				return TRUE;
			}

			self::$recordSetClasses[$class] = isset($config['record_set_class'])
				? $config['record_set_class']
				: self::getRecordSetClass($class);

			return TRUE;
		}

		/**
		 * Gets a record set class name for the records a RecordController handles
		 *
		 * @static
		 * @access public
		 * @param $class The Record class name
		 * @return string The record set class for the controller
		 */
		static public function getRecordSetClass($class)
		{
			if (!isset(self::$recordSetClasses[$class])) {
				self::$recordSetClasses[$class] = str_replace(self::SUFFIX, '', $class);
			}

			return self::$recordSetClasses[$class];
		}

		/**
		 * Gets a record class name for the records a RecordController handles
		 *
		 * @static
		 * @access public
		 * @param $class The Record class name
		 * @return string The record set class for the controller
		 */
		static public function getRecordClass($class)
		{
			if (!isset(self::$recordSetClasses[$class])) {
				self::$recordClasses[$class] = ActiveRecord::classFromRecordSet(
					self::getRecordSetClass($class);
				);
			}

			return self::$recordClasses[$class];
		}

		/**
		 * The routable interface for creating a new instance of a RecordController's record class
		 *
		 * @static
		 * @access public
		 * @return View The view to create a new instance of a RecordController's record class
		 */
		static public function create($class)
		{
			self::allowMethods('get', 'post');
			self::acceptTypes('text/html', 'application/json');

			$self             = iw::makeTarget($class, __FUNCTION__);
			$record_set_class = self::getRecordSetClass($class);
			$record_class     = self::getRecordClass($class);
			$record           = new $record_class();

			$record->populate($record_class);

			if (Request::checkMethod('post')) {

				//
				// Authorize the user to create a record
				//

				try {
					$record->store();

					fMessaging(self::MSG_TYPE_SUCCESS, $self, fText::compose(
						'%s "%s" was successfully created',
						fText::compose(fORM::getRecordName($record_class)),
						(string) $record
					));

					self::redirect();

				} catch (fValidationException $e) {
					//
					// TODO: Handle failure
					//
				}
			}

			//
			// Authorize the user to view the record
			//

			return View::create(self::$masterView, array(
					'record'   => $record,
					'error'    => fMessaging::retrieve(self::MSG_TYPE_ERROR,   $self),
					'success'  => fMessaging::retrieve(self::MSG_TYPE_SUCCESS, $self)
				), array(
					'contents' => implode(DIRECTORY_SEPARATOR, array(
						'user',
						strtolower($record_set_class),
						__FUNCTION__ . '.php'
					)
				)
			);
		}

		/**
		 * The routable interface for removing an instance of a RecordController's record class
		 *
		 * @static
		 * @access public
		 * @return View The view to remove an instance of a RecordController's record class
		 */
		static public function remove($class, $record = NULL
		{
			self::allowMethod('get', 'delete');
			self::acceptTypes('text/html', 'application/json');

			$self             = iw::makeTarget($class, __FUNCTION__);
			$record_set_class = self::getRecordSetClass($class);
			$record_class     = self::getRecordClass($class);

			try {
				$record = self::getRequestedRecord($record_class, $record);
			} catch (fNotFoundException $e) {
				self::triggerError('not_found');

			}

			if (self::checkMethod('delete')) {

				//
				// Authorize the user for this action
				//

				try {
					$record->delete();

					fMessaging(self::MSG_TYPE_SUCCESS, $self, fText::compose(
						'%s "%s" was successfully deleted',
						fText::compose(fORM::getRecordName($record_class)),
						(string) $record
					));

					self::redirect();

				} catch (fValidationException $e) {
					//
					// TODO: Handle failure
					//
				}
			}

			//
			// Authorize the user to view the record
			//

			return View::create(self::$masterView, array(
					'record'   => $record,
					'error'    => fMessaging::retrieve(self::MSG_TYPE_ERROR,   $self),
					'success'  => fMessaging::retrieve(self::MSG_TYPE_SUCCESS, $self)
				), array(
					'contents' => implode(DIRECTORY_SEPARATOR, array(
						'user',
						strtolower($record_set_class),
						__FUNCTION__ . '.php'
					)
				)
			);
		}

		/**
		 * The routable interface for updating instances of a RecordController's record class
		 *
		 * @static
		 * @access public
		 * @return View The view to update an instance of a RecordController's record class
		 */
		static public function update($class, $record = NULL)
		{
			self::allowMethods('get', 'put');
			self::acceptTypes('text/html', 'application/json');

			$self             = iw::makeTarget($class, __FUNCTION__);
			$record_set_class = self::getRecordSetClass($class);
			$record_class     = self::getRecordClass($class);

			try {
				$record = self::getRequestedRecord($record_class, $record);
			} catch (fNotFoundException $e) {
				self::triggerError('not_found');

			}

			$record->populate($record_class);

			if (self::checkMethod('put')) {

				//
				// Authorize the user for this action
				//

				try {
					$record->store();

					fMessaging(self::MSG_TYPE_SUCCESS, $self, fText::compose(
						'%s "%s" was successfully updated',
						fORM::getRecordName($record_class),
						(string) $record,
						$action
					));

					self::redirect();

				} catch (fValidationException $e) {

					//
					// TODO: Handle failure
					//

				}
			}

			//
			// Authorize the user to view the record
			//

			return View::create(self::$masterView, array(
					'record'   => $record,
					'error'    => fMessaging::retrieve(self::MSG_TYPE_ERROR,   $self),
					'success'  => fMessaging::retrieve(self::MSG_TYPE_SUCCESS, $self)
				), array(
					'contents' => implode(DIRECTORY_SEPARATOR, array(
						'user',
						strtolower($record_set_class),
						__FUNCTION__ . '.php'
					)
				)
			);
		}

		/**
		 * The routable interface for managing instances of a RecordController's record class
		 *
		 * @static
		 * @access public
		 * @return View The view to manage instances of a RecordController's record class
		 */
		static public function manage($class, $filter = NULL, $order = NULL, $limit = 15, $page = 1)
		{
			self::allowMethods('get', 'post', 'delete');
			self::acceptTypes('text/html', 'application/json');

			$self   = iw::makeTarget(__CLASS__, __FUNCTION__);
			$action = Request::get('action', 'string', Request::getMethod());

			switch ($action) {
				case 'create':
				case 'post':
					return call_user_func(iw::makeTarget($class, 'create'));
			}

			//
			// Authorize the user to view the records
			//

			$record_set_class = self::getRecordSetClass($class);
			$record_class     = self::getRecordClass($class);
			$limit            = Request::get('limit', 'integer', $limit);
			$page             = Request::get('page',  'integer', $page);


			$records = call_user_func(
				iw::makeTarget($record_set_class, 'build'),
				$filter,
				$order,
				$limit,
				$page
			);

			return View::create(self::$masterView, array(
					'records'  => $records,
					'error'    => fMessaging::retrieve(self::MSG_TYPE_ERROR,   $self),
					'success'  => fMessaging::retrieve(self::MSG_TYPE_SUCCESS, $self)
				), array(
					'contents' => implode(DIRECTORY_SEPARATOR, array(
						'user',
						strtolower($record_set_class),
						__FUNCTION__ . '.php'
					)
				)
			);
		}

		/**
		 * The routable interface for selecting an instance of a RecordController's record class
		 *
		 * @static
		 * @access public
		 * @return View The view to select (show) an instance of a RecordController's record class
		 */
		static public function select($class, $record = NULL)
		{
			self::allowMethods('get', 'put', 'delete');
			self::acceptTypes('text/html', 'application/json');

			$self   = iw::makeTarget(__CLASS__, __FUNCTION__);
			$action = Request::get('action', 'string', Request::getMethod());

			switch ($action) {
				case 'update':
				case 'put':
					return call_user_func(iw::makeTarget($class, 'update'));
				case 'remove':
				case 'delete':
					return call_user_func(iw::makeTarget($class, 'remove'));
			}

			//
			// Authorize the user to view the record
			//

			$record_set_class = self::getRecordSetClass($class);
			$record_class     = self::getRecordClass($class);

			try {
				$record = self::getRequestedRecord($record_class, $record);
			} catch (fNotFoundException $e) {
				self::triggerError('not_found');

			}

			return View::create(self::$masterView, array(
					'record'   => $records,
					'error'    => fMessaging::retrieve(self::MSG_TYPE_ERROR,   $self),
					'success'  => fMessaging::retrieve(self::MSG_TYPE_SUCCESS, $self)
				), array(
					'contents' => implode(DIRECTORY_SEPARATOR, array(
						'user',
						strtolower($record_set_class),
						__FUNCTION__ . '.php'
					)
				)
			);
		}

		/**
		 * Gets a requested record from GET, POST, PUT, or DELETE parameters
		 *
		 * @static
		 * @access private
		 * @param string $record_class The record class of the request record
		 * @return ActiveRecord The instance of the record
		 */
		static private function getRequestedRecord($record_class, $record = NULL)
		{
			$slug = Request::get('slug', 'string', NULL);

			if (!$record && !$slug) {
				throw new fNotFoundException();
			}

			return $slug
				? ActiveRecord::createFromSlug($record_class, $slug)
				: $record;
		}
	}
