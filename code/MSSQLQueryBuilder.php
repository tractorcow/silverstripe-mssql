<?php


class MSSQLQueryBuilder extends DBQueryBuilder {
	
	protected function buildSelectQuery(SQLSelect $query, array &$parameters) {
		list($offset, $limit) = $this->parseLimit($query);
		
		// If not using ofset then query generation is quite straightforward
		// note that an offset function relies on a limit being supplied too
		if(empty($offset) || empty($limit)) {
			$sql = parent::buildSelectQuery($query, $parameters);
			// Inject limit into SELECT fragment
			if(!empty($limit)) {
				$sql = preg_replace('/^(SELECT (DISTINCT)?)/i', '${1} TOP '.$limit, $sql);
			}
			return $sql;
		}
		
		// When using offset we must use a subselect
		// @see http://stackoverflow.com/questions/2135418/equivalent-of-limit-and-offset-for-sql-server
		$orderby = $query->getOrderBy();

		// workaround for subselect not working with alias functions
		// just use the function directly in the order by instead of the alias
		$selects = $query->getSelect();
		foreach($orderby as $field => $dir) {
			if(preg_match('/_SortColumn/', $field)) {
				unset($orderby[$field]);
				$orderby[$selects[str_replace('"', '', $field)]] = $dir;
			}
		}

		if($orderby) {
			$orderByClause = parent::buildOrderByFragment($query, $parameters);
		} else {
			$selects = $query->getSelect();
			$firstCol = reset($selects);
			$orderByClause = "ORDER BY $firstCol";
		}
		
		// Build main query SQL
		$sql = parent::buildSelectQuery($query, $parameters);
		
		// Inject row number into selection
		$sql = preg_replace('/^(SELECT (DISTINCT)?)/i', '${1} ROW_NUMBER() OVER ('.$orderByClause.') AS Number, ', $sql);
		
		// Sub-query this SQL
		$beginNumber = ($offset+1);
		$endNumber = ($offset+$limit);
		return "SELECT * FROM ($sql) AS Numbered WHERE Number BETWEEN $beginNumber AND $endNumber ORDER BY Number";
	}
	
	public function buildOrderByFragment(SQLSelect $query, array &$parameters) {
		// If doing a limit/offset at the same time then don't build the orde by fragment here
		list($offset, $limit) = $this->parseLimit($query);
		if(empty($offset) || empty($limit)) {
			return parent::buildOrderByFragment($query, $parameters);
		}
		return '';
	}
	
	
	
	/**
	 * Extracts the limit and offset from the limit clause
	 * 
	 * @param SQLSelect $query
	 * @return array Two item array with $limit and $offset as values
	 * @throws InvalidArgumentException
	 */
	protected function parseLimit(SQLSelect $query) {
		
		$limit = '';
		$offset = '0';
		if(is_array($query->getLimit())) {
			$limitArr = $query->getLimit();
			if(isset($limitArr['limit'])) $limit = $limitArr['limit'];
			if(isset($limitArr['start'])) $offset = $limitArr['start'];
		} else if(preg_match('/^([0-9]+) offset ([0-9]+)$/i', trim($query->getLimit()), $matches)) {
			$limit = $matches[1];
			$offset = $matches[2];
		} else {
			//could be a comma delimited string
			$bits = explode(',', $query->getLimit());
			if(sizeof($bits) > 1) {
				list($offset, $limit) = $bits;
			} else {
				$limit = $bits[0];
			}
		}
		return array($limit, $offset);
	}
}