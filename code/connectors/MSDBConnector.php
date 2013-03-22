<?php

/**
 * Base connector driver for MS based database apis
 * 
 * @package framework
 * @subpackage model
 */
abstract class MSDBConnector extends DBConnector {
	
	/**
	 * Name of the currently selected database
	 *
	 * @var string
	 */
	protected $selectedDatabase = null;

	function databaseError($message, $errorLevel = E_USER_ERROR) {
		
		// Append last error onto the error stack
		$lastError = $this->getLastError();
		if($lastError) $message .= "\nLast error: " . $lastError;
		
		// Throw error
		return parent::databaseError($message, $errorLevel);
	}

	public function getVersion() {
		return trim($this->query("SELECT CONVERT(char(15), SERVERPROPERTY('ProductVersion'))")->value());
	}

	public function getGeneratedID($table) {
		return $this->query("SELECT IDENT_CURRENT('$table')")->value();
	}

	public function getSelectedDatabase() {
		return $this->selectedDatabase;
	}

	public function unloadDatabase() {
		$this->selectedDatabase = null;
	}
	
	/**
	 * Quotes a string, including the "N" prefix so unicode
	 * strings are saved to the database correctly.
	 *
	 * @param string $string String to be encoded
	 * @return string Processed string ready for DB
	 */
	public function quoteString($value) {
		return "N'" . $this->escapeString($value) . "'";
	}
	
	
	public function escapeString($value) {
    	$value = str_replace("'", "''", $value);
    	$value = str_replace("\0", "[NULL]", $value);
    	return $value;
	}
}