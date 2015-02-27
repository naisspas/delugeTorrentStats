<?php

class dbSqlite extends SQLite3 {
	protected $status = false;
	private $tablesDefinition = array();
	
	public function __construct($filename)
	{
		if(!class_exists('SQLite3'))
		die("<div class=\"alert alert-warning \">php5-sqlite is not installed. Please install this requirement and restart your webserver before continuing.</div>");

		parent::__construct($filename);
	
		$this->status = true;
		$this->busyTimeout(10*1000);
		
		$this->loadTableDefinitions();
	}
	
	protected function loadTableDefinitions(){
		if($this->status===true){
			$stmt = $this->prepare("SELECT tbl_name FROM sqlite_master WHERE type='table' AND name!='sqlite_sequence';");
			if($stmt!==false) {
				$result = $stmt->execute();
				while($res = $result->fetchArray(SQLITE3_ASSOC)){
					$tableName = $res['tbl_name'];
					$stmt_info = $this->prepare("PRAGMA table_info(".$tableName.");");
					
					$data = array();
					$result_info = $stmt_info->execute();
					while($column = $result_info->fetchArray(SQLITE3_ASSOC)){
						$bindingType = NULL;
						$maxLength = NULL;
						if(strpos($column['type'],'(')>0){
							$column['type'] = str_replace(')','',$column['type']);
							$column['type'] = explode('(',$column['type']);
							$column['type'][1] = trim($column['type'][1]);
							$column['type'][0] = trim($column['type'][0]);
						}
						else{ $column['type'] = array($column['type']); }
						switch($column['type'][0]){
							case "INT":
							case "INTEGER":
							case "TINYINT":
							case "SMALLINT":
							case "MEDIUMINT":
							case "BIGINT":
							case "UNSIGNED BIG INT":
							case "INT2":
							case "INT8":
							case "BOOLEAN":
								$bindingType=SQLITE3_INTEGER;
								break;

							case "CHARACTER":
							case "VARCHAR":
							case "VARYING CHARACTER":
							case "NCHAR":
							case "NATIVE CHARACTER":
							case "NVARCHAR":
							case "TEXT":
							case "CLOB":
								$bindingType=SQLITE3_TEXT;
								if(count($column['type'])>1){ $maxLength = $column['type'][1]*1; }
								break;
							
							case "BLOB":
								$bindingType=SQLITE3_BLOB;
								break;
								
							case "REAL":
							case "DOUBLE":
							case "DOUBLE PRECISION":
							case "FLOAT":
							case "NUMERIC":
							case "DECIMAL":
							case "DATE":
							case "DATETIME":
								$bindingType=SQLITE3_FLOAT;
								break;
						}
						
						$data[$column['name']] = array(
							'type' 			=> $column['type'][0]
							,'bindingType' 	=> $bindingType
							,'maxLength'	=> $maxLength
							,'notNull' 		=> $column['notnull']
							,'primaryKey' 	=> $column['pk']
						);
					}
					
					$this->tablesDefinition[$tableName] = $data;
				}
			}
		}
		return $this->tablesDefinition;
	}
	public function getTableDefinitions(){ return $this->tablesDefinition; }
	
	public function getStatus(){ return $this->status; }
	
	public function set($config=NULL){
		/**
		 * @todo : can set multiples key/values at same time
		 * @todo : return errors/exceptions
		 */
		 
		$valid = is_array($config);
		if($valid) { $valid = isset($config['tableName']); }
		if($valid) { $valid = isset($config['values']); }
		if($valid) { $valid = isset($config['where']); }
		if($valid) { $valid = is_array($config['values']); }
		if($valid) { $valid = count($config['values'])>0; }

		if($valid) { $valid = is_array($this->tablesDefinition); }
		if($valid) { $valid = isset($this->tablesDefinition[$config['tableName']]); }
		if($valid) { $valid = is_array($this->tablesDefinition[$config['tableName']]); }
		
		if($this->status===true && $valid){
			$currentTableDefinition = $this->tablesDefinition[$config['tableName']];
			$whereValues = array(); $columValues = array(); $params = array();
			$columnError = false;
			foreach($config['values'] as $key => $value){
				$columValues[] = $key.'= :'.$key;
				if(!isset($currentTableDefinition[$key])){
					$columnError=true;
					break;
				}
				$columnDefinition = $currentTableDefinition[$key];
				if(is_string($value) && is_integer($columnDefinition['maxLength'])){
					$value = substr($value,0,$columnDefinition['maxLength']);
				}
				$params[':'.$key] = array('value' => $value, 'binding' => $columnDefinition['bindingType']);
			}
			unset($key,$value);
			if(!$columnError && is_array($config['where'])){
				foreach($config['where'] as $key => $value){
					if(!isset($currentTableDefinition[$key])){
						$columnError=true;
						break;
					}
					$whereValues[] = $key.'= :'.$key;
					$params[':'.$key] = array('value' => $value, 'binding' => $columnDefinition['bindingType']);
				}
				unset($key,$value);
			}
			
			if(!$columnError){
				$sql = 'UPDATE '.$config['tableName'].' SET '.implode(',',$columValues);
				if(count($whereValues)>0){ $sql .= ' WHERE '.implode(' AND ',$whereValues); }
				$stmt = $this->prepare($sql);
				if($stmt!==false) {
					foreach($params as $param => $data){
						if($data['value']==NULL){ $data['binding'] = SQLITE3_NULL; }
						$stmt->bindValue($param, $data['value'], $data['binding']);
					}
					unset($params,$param,$data);
					$result = $stmt->execute();
					$result = $stmt->close();
				}
			}
		}
	}
	protected function createTable($tableName, $sqlScript){
		if(is_string($tableName) && is_string($sqlScript) && $this->status===true){
			$exist = false;
			$stmt = $this->prepare("
				SELECT COUNT(*) AS exist
				FROM sqlite_master
				WHERE type='table' AND name=:tableName");
			$stmt->bindValue(':tableName', $tableName, SQLITE3_TEXT);
			$data = array();
			
			if($stmt!==false) {
				$result = $stmt->execute();
				$count = $result->fetchArray(SQLITE3_ASSOC);
				if($count){ $exist = $count['exist']; }
			}
			if(!$exist){
				$this->exec($sqlScript);				
				return true;
			}
		}
		return false;
	}

}

?>
