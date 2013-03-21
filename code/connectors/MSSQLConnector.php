<?php

/**
 * Database connector driver for mssql_ library
 * 
 * @package framework
 * @subpackage model
 */
class MSSQLConnector extends DBConnector {
	
	/**
	 * Connection to the DBMS.
	 * 
	 * @var resource
	 */
	protected $dbConn = null;

	public function connect($parameters) {
		// Switch to utf8 connection charset
		ini_set('mssql.charset', 'utf8');
		$this->dbConn = mssql_connect($parameters['server'], $parameters['username'], $parameters['password'], true);

		if(empty($this->dbConn)) {
			$this->databaseError("Couldn't connect to MS SQL database");
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
			return mssql_query($sql, $dbConn);
		});
		
		if(!$handle) {
			if(!$errorLevel) return null;
			$this->databaseError("Couldn't run query: $sql", $errorLevel);
		}
		
		return new MSSQLQuery($this, $handle);
	}

	public function quoteString($value) {
		
	}

	public function selectDatabase($name) {
		
	}

	public function unloadDatabase() {
		
	}

	public function __destruct() {
		if(is_resource($this->dbConn)) {
			mssql_close($this->dbConn);
		}
	}	
}