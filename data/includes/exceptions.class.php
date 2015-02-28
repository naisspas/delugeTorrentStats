<?php

/**
 * exception : centralized management of exceptions
 *
 * @author Philippe Hamel <naisspas@hotmail.ca>
 */
class exceptions {

	private $data = array();
	private $idProperty = 'dbId';
	private $defaultLevel = NULL;
	private $exceptionsCount = 0;
    /**
     * class constructor
     *
     * @param array $mandatoryLevels array of exception levels to create by default
	 * @param string $defaultLevel defaut level
     * @param string $idProperty 'id' property used for display at summary
     *
     */	
	public function __construct(
		$mandatoryLevels = NULL,
		$defaultLevel = NULL,
		$idProperty = NULL
	)
    {
		if(is_string($idProperty) && !empty($idProperty)) { $this->idProperty = $idProperty; }
		if(is_string($defaultLevel) && !empty($defaultLevel)) { $this->defaultLevel = $defaultLevel; }
		if(is_array($mandatoryLevels)){
			foreach($mandatoryLevels as $level){
				if(is_string($level) &&  !empty($level)){ $this->data[$level] = array(); }
			}
			unset($level);
		}
    }
	
    /**
     * Add an exception to the existings one's
     *
     * @param array $id id of the exception
	 * @param string $level of exception
     * @param string $description (optionnal) description of the exception
     * @param string $idValue (optionnal) id of the object causing the exception (display as 'idProperty')
     *
     */	
	public function add($id=NULL, $level=NULL, $idValue=NULL){
		if((is_string($id) && !empty($id))
			&& is_array($this->data)
		){
			if(empty($description)){ $description = NULL; }
			if(empty($idValue)){ $idValue = NULL; }
			
			if(!is_string($level) || empty($level)){
				if(!empty($this->defaultLevel)) { $level = $this->defaultLevel; }
				else if(count($this->data)>0){ $level = reset($this->data); }
			}
			if(is_string($level) && !empty($level)){
				if(!isset($this->data[$level])){ $this->data[$level] = array(); }
				$this->data[$level][] = array('id' => $id, 'description' => $description, 'idProperty' => $idValue);
				$this->exceptionsCount++;
			}
		}
	}
	
    /**
     * return the first exception or a complete summary of all exceptions.
	 * 
     * @param boolean $onlyFirst if true, return only the first exception
     *
	 * @return array : list of all exceptions by level, or the first exception
     *
     */		
	public function getSummary($onlyFirst=false){
		$data = array();
		foreach($this->data as $level => $list){
			foreach($list as $exception){
				if(count($data)>0 && $onlyFirst===true) { break; }
				$currentException = array(
					'id' => $exception['id'],
					'level' => $level
				);
				if(!empty($exception['description'])){ $currentException['description'] = $exception['description']; }
				if(!empty($exception['idProperty'])){ $currentException[$this->idProperty] = $exception['idProperty']; }
				
				if($onlyFirst){ return $currentException; }
				$data[] = $currentException;
			}
		}
		return $data;
	}
	
    /**
     * return the total count of exceptions
     *
     * @param string $level if no level specified, return total count for all level
	 *
	 * @return integer total count
     *
     */		
	public function getCount($level=NULL){
		if(!empty($level)){
			if(isset($this->data[$level])){
				return count($this->data[$level]);
			}
		}
		else { return $this->exceptionsCount; }
		return 0;
	}
}