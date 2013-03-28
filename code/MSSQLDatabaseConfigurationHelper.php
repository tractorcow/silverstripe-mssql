<?php

/**
 * This is a helper class for the SS installer.
 * 
 * It does all the specific checking for MSSQLDatabase
 * to ensure that the configuration is setup correctly.
 * 
 * @package mssql
 */
class MSSQLDatabaseConfigurationHelper extends DatabaseConfigurationHelper {
	
	protected function isAzure($databaseConfig) {
		return $databaseConfig['type'] === 'MSSQLAzureDatabase';
	}
	
	/**
	 * Generates a basic DBConnector from a parameter list.
	 * Roughly implements both MSSQLDatabase with built in connectors.yml info
	 * 
	 * @param array $databaseConfig
	 * @return DBConnector
	 */
	public function makeConnection($databaseConfig) {
		$azure = false;
		switch($databaseConfig['type']) {
			case 'MSSQLPDODatabase':
				$connector = new PDOConnector();
				break;
			case 'MSSQLDatabase':
				$connector = new SQLServerConnector();
				break;
			case 'MSSQLAzureDatabase':
				$connector = new SQLServerConnector();
				$databaseConfig['multipleactiveresultsets'] = 0;
				$azure = true;
				break;
			default:
				return null;
		}
		
		// Connect
		$databaseConfig['driver'] = 'sqlsrv';
		@$connector->connect($databaseConfig, $azure);
		return $connector;
	}

	/**
	 * Ensure that the database function for connectivity is available.
	 * If it is, we assume the PHP module for this database has been setup correctly.
	 * 
	 * @param array $databaseConfig Associative array of database configuration, e.g. "server", "username" etc
	 * @return boolean
	 */
	public function requireDatabaseFunctions($databaseConfig) {
		switch($databaseConfig['type']) {
			case 'MSSQLPDODatabase':
				return class_exists('PDO') && in_array('sqlsrv', PDO::getAvailableDrivers());
			case 'MSSQLDatabase':
			case 'MSSQLAzureDatabase':
				return function_exists('sqlsrv_connect');
			default:
				return false;
		}
	}

	/**
	 * Ensure that the SQL Server version is at least 10.00.2531 (SQL Server 2008 SP1).
	 * @see http://www.sqlteam.com/article/sql-server-versions
	 * @param array $databaseConfig Associative array of db configuration, e.g. "server", "username" etc
	 * @return array Result - e.g. array('success' => true, 'error' => 'details of error')
	 */
	public function requireDatabaseVersion($databaseConfig) {
		$success = false;
		$error = '';
		$version = $this->getDatabaseVersion($databaseConfig);

		if($version) {
			$success = version_compare($version, '10.00.2531', '>=');
			if(!$success) {
				$error = "Your SQL Server version is $version. It's recommended you use at least 10.00.2531 (SQL Server 2008 SP1).";
			}
		} else {
			$error = "Your SQL Server version could not be determined.";
		}

		return array(
			'success' => $success,
			'error' => $error
		);
	}

	/**
	 * Ensure that the database connection is able to use an existing database,
	 * or be able to create one if it doesn't exist.
	 * 
	 * @param array $databaseConfig Associative array of db configuration, e.g. "server", "username" etc
	 * @return array Result - e.g. array('success' => true, 'alreadyExists' => 'true')
	 */
	public function requireDatabaseOrCreatePermissions($databaseConfig) {
		$success = false;
		$alreadyExists = false;

		$check = $this->requireDatabaseConnection($databaseConfig);
		$conn = $check['connection'];
		
		if(!$conn || !$check['success']) {
			// No success
		} elseif($databaseConfig['type'] == 'MSSQLAzureDatabase') {
			// Don't bother with DB selection for azure, as it's not supported
			$success = true;
			$alreadyExists = true;
		} else {
			// does this database exist already?
			$list = $conn->query('SELECT NAME FROM sys.sysdatabases')->column();
			if(in_array($databaseConfig['database'], $list)) {
				$success = true;
				$alreadyExists = true;
			} else{
				$alreadyExists = false;
				$success = $conn->query("select COUNT(*) from sys.fn_my_permissions('','') where permission_name like 'CREATE ANY DATABASE';")->value();
			}
		}

		return array(
			'success' => $success,
			'alreadyExists' => $alreadyExists
		);
	}
	
	/**
	 * Ensure we have permissions to alter tables.
	 * 
	 * @param array $databaseConfig Associative array of db configuration, e.g. "server", "username" etc
	 * @return array Result - e.g. array('okay' => true, 'applies' => true), where applies is whether
	 * the test is relevant for the database
	 */
	public function requireDatabaseAlterPermissions($databaseConfig) {
		
		$check = $this->requireDatabaseConnection($databaseConfig);
		$conn = $check['connection'];
		
		// Check connection
		if(!$conn || !$check['success']) {
			$success = false;
		} else {
			if(!$this->isAzure($databaseConfig)) {
				// Make sure to select the current database when checking permission against this database
				$conn->selectDatabase($databaseConfig['database']);
			}
			$success = $conn->query("select COUNT(*) from sys.fn_my_permissions(NULL,'DATABASE') WHERE permission_name like 'create table';")->value();
		}
		
		return array(
			'success' => $success,
			'applies' => true
		);
	}
}