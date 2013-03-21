<?php

/**
 * A result-set from a MSSQL database.
 * 
 * @package sapphire
 * @subpackage model
 */
class MSSQLQuery extends SS_Query {

	/**
	 * The MSSQLConnector object that created this result set.
	 * 
	 * @var MSSQLConnector
	 */
	private $connector;

	/**
	 * The internal MSSQL handle that points to the result set.
	 * 
	 * @var resource
	 */
	private $handle;

	/**
	 * A list of field meta-data, such as column name and data type.
	 * 
	 * @var array
	 */
	private $fields = array();

	/**
	 * Hook the result-set given into a Query class, suitable for use by sapphire.
	 * 
	 * @param MSSQLConnector $connector The database object that created this query.
	 * @param handle the internal mssql handle that is points to the resultset.
	 */
	public function __construct(MSSQLConnector $connector, $handle) {
		$this->connector = $connector;
		$this->handle = $handle;

		// build a list of field meta-data for this query we'll use in nextRecord()
		// doing it here once saves us from calling mssql_fetch_field() in nextRecord()
		// potentially hundreds of times, which is unnecessary.
		if(is_resource($this->handle)) {
			for($i = 0; $i < mssql_num_fields($handle); $i++) {
				$this->fields[$i] = mssql_fetch_field($handle, $i);
			}
		}
	}

	public function __destruct() {
		if(is_resource($this->handle)) {
			mssql_free_result($this->handle);
		}
	}

	public function seek($row) {
		if(!is_resource($this->handle)) return false;

		return mssql_data_seek($this->handle, $row);
	}

	public function numRecords() {
		if(!is_resource($this->handle)) return false;
		
		return mssql_num_rows($this->handle);
	}

	public function nextRecord() {
		if(!is_resource($this->handle)) return false;

		$row = mssql_fetch_row($this->handle);
		if(empty($row)) return false;
		
		foreach($row as $i => $value) {
			$field = $this->fields[$i];

			// fix datetime formatting from format "Jan  1 2012 12:00:00:000AM" to "2012-01-01 12:00:00"
			// strtotime doesn't understand this format, so we need to do some modification of the value first
			if($field->type == 'datetime' && $value) {
				$value = date('Y-m-d H:i:s', strtotime(preg_replace('/:[0-9][0-9][0-9]([ap]m)$/i', ' \\1', $value)));
			}

			if(isset($value) || !isset($data[$field->name])) {
				$data[$field->name] = $value;
			}
		}
		return $data;
	}

}