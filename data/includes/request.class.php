<?php

/**
 * request : advanced request class
 *
 * @author Philippe Hamel <naisspas@hotmail.ca>
 */
class request {

	private $method = NULL;
	private $routing = array();
	private $parameters = array();
    /**
     * class constructor
	 *
     * @param array $requestTypes authorized request types
     */	
	public function __construct($requestTypes = array(), $routing)
    {
		$this->method = $_SERVER['REQUEST_METHOD'];
		if(is_array($requestTypes) && is_array($routing)){
			if(in_array($this->method,$requestTypes)){
				switch($this->method){
					case "POST" : $this->parameters = $_POST; break;
					case "GET" : $this->parameters = $_GET; break;
				}
			}
			$this->routing = $routing;
		}
    }
	
    /**
     * recursive forward match based on the request parameters
     *
     * @return object with 'found' status (boolean) and 'path' (string)
     */		
	private function matchForward($routing){
		$return = (object) array('found' => false, 'path' => '');
		foreach($routing as $path){
			if(array_key_exists('parameter',$path) && array_key_exists('value',$path)){
				$tmpParameter = $this->get($path['parameter']);
				if(!is_string($tmpParameter)){ $tmpParameter = ''; }
				if($tmpParameter == $path['value']){
					$return->found = true;
					$return->path = $path['value'];
					if(array_key_exists('next',$path)){
						$subReturn = (object) array('found' => false, 'path' => '');
						if(is_array($path['next'])){
							$subReturn = $this->matchForward($path['next']);
						}
						if($subReturn->found==true){
							$return->path .= ":".$subReturn->path;
						}
						else { $return->found = false; }
					}
					else if(array_key_exists('presence',$path)){
						$return->found = false;
						if(is_array($path['presence'])){
							foreach($path['presence'] as $value){
								if(!empty($this->get($value))){
									$return->found = true;
									break;
								}
							}
						}
					}
					if($return->found){
						return $return;
					}
				}
			}
		}
		$return = (object) array('found' => false, 'path' => '');
		return $return;
	}
	public function getRouting(){
		return $this->routing;
	}
	
    /**
     * find current forward based on the request parameters
     *
     * @return object with 'found' status (boolean) and 'path' (string)
     */		
	public function findForward(){
		if(array_key_exists($this->method,$this->routing)){
			return $this->matchForward($this->routing[$this->method]);
		}
	}
	
    /**
     * get parameter
     *
     * @param array $id of the parameter
	 *
     * @return string value of parameter (or null)
     */	
	public function get($id){
		if(is_array($this->parameters) && is_string($id)){
			if(array_key_exists($id,$this->parameters)){
				return $this->parameters[$id];
			}
		}
		return (object) array('exception' => 'missingParameter');
	}

    /**
     * get request method
     *
     * @return string method
     *
     */	
	public function getMethod(){
		return $this->method;
	}	
}