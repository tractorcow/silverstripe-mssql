<?php

/**
 * Database connector driver for sqlsrv_ library
 * 
 * @package framework
 * @subpackage model
 */
class SQLServerConnector extends DBConnector {
	
	/**
	 * Connection to the DBMS.
	 * 
	 * @var resource
	 */
	protected $dbConn = null;

	/**
	 * Stores the affected rows of the last query.
	 * Used by sqlsrv functions only, as sqlsrv_rows_affected
	 * accepts a result instead of a database handle.
	 * 
	 * @var integer
	 */
	protected $lastAffectedRows;

	public function connect($parameters) {

		// Disable default warnings as errors behaviour for sqlsrv to keep it in line with mssql functions
		if(ini_get('sqlsrv.WarningsReturnAsErrors')) {
			ini_set('sqlsrv.WarningsReturnAsErrors', 'Off');
		}

		$options = array(
			'CharacterSet' => 'UTF-8',
			'MultipleActiveResultSets' => true
		);
		
		if( !(defined('MSSQL_USE_WINDOWS_AUTHENTICATION') && MSSQL_USE_WINDOWS_AUTHENTICATION == true)
			&& empty($parameters['windowsauthentication'])
		) {
			$options['UID'] = $parameters['username'];
			$options['PWD'] = $parameters['password'];
		}

		$this->dbConn = sqlsrv_connect($parameters['server'], $options);
		
		if(empty($this->dbConn)) {
			$this->databaseError("Couldn't connect to SQL Server database");
		}
	}
	
	public function transactionEnd() {
		$result = sqlsrv_commit($this->dbConn);
		if (!$result) {
			$this->databaseError("Couldn't commit the transaction.", E_USER_ERROR);
		}
	}

	public function affectedRows() {
		
	}

	public function escapeString($value) {
		
	}

	public function getGeneratedID($table) {
		
	}

	public function getLastError() {
		
	}

	public function getSelectedDatabase() {
		
	}

	public function getVersion() {
		
	}

	public function isActive() {
		
	}

	public function preparedQuery($sql, $parameters, $errorLevel = E_USER_ERROR) {
		
	}

	public function query($sql, $errorLevel = E_USER_ERROR) {

		// Check if we should only preview this query
		if ($this->previewWrite($sql)) return;
		
		// Benchmark query
		$dbConn = $this->dbConn;
		$handle = $this->benchmarkQuery($sql, function($sql) use($dbConn) {
			return sqlsrv_query($dbConn, $sql);
		});
		
		if($handle) {
			$this->lastAffectedRows = sqlsrv_rows_affected($handle);
		} elseif(!$errorLevel) {
			return null;
		} else {
			$this->databaseError("Couldn't run query: $sql", $errorLevel);
		}
		
		return new SQLServerQuery($this, $handle);
	}

	public function quoteString($value) {
		
	}

	public function selectDatabase($name) {
		
	}

	public function unloadDatabase() {
		
	}

	public function __destruct() {
		if(is_resource($this->dbConn)) {
			sqlsrv_close($this->dbConn);
		}
	}
}