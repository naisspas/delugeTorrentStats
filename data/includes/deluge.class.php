<?php
/**
 * exceptions : centralized management of exceptions
 * inspired by {@link https://github.com/jacroe/alice/blob/master/modules/deluge.php}
 *
 * @author Philippe Hamel <naisspas@hotmail.ca>
 */
class deluge {
	
	private $database = NULL;
	private $apiCookies = NULL;
	private $exceptions = NULL;
	private $defaultListParams = array(
		'name',
		'progress',
		'download_payload_rate',
		'upload_payload_rate',
		'ratio',
		'time_added',
		'total_done',
		'total_uploaded',
		'total_size',
		'tracker_host',
		'label'
	);
	
	function __construct($database, &$exceptions = NULL)	{
		$valid = is_object($database);
		if($valid){ $valid = method_exists($database, 'getConfigData'); }
		if($valid){
			$config = $database->getConfigData();
			$valid = is_object($config);
		}
		if($valid){ $this->database = $database; }
		if(is_object($exceptions)
			&& (method_exists($exceptions,'add'))
			&& (method_exists($exceptions,'getSummary'))
			&& (method_exists($exceptions,'getCount')))
		{
			$this->exceptions = $exceptions;
		}
	}
	public function exceptions(){
		if(is_object($this->exceptions)){
			return array(
				'count' => $this->exceptions->getCount(),
				'summary' => $this->exceptions->getSummary()
			);
		}
	}
	
	private function hasExceptions(){
		if(is_object($this->exceptions)){ return $this->exceptions->getCount(); }
		return 0;
	}
	
	public function authentication(){
		if($this->hasExceptions()==0){
			if($this->database){
				$host = $this->database->getConfigData('deluge','host');
				$password = $this->database->getConfigData('deluge','password');
				if(!empty($host) && !empty($password)){
					$curl = curl_init();
					curl_setopt($curl, CURLOPT_URL, $host);
					curl_setopt($curl, CURLOPT_POSTFIELDS, '{"method": "auth.login", "params": ["'.$password.'"], "id": 1}');
					curl_setopt($curl, CURLOPT_HEADER, true);  
					curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
					curl_setopt($curl, CURLOPT_ENCODING, "gzip"); 
					$data = curl_exec($curl);
					curl_close($curl);
					preg_match_all('|Set-Cookie: (.*);|U', $data, $matches);   
					$this->apiCookies = implode('; ', $matches[1]);
					if(empty($this->apiCookies) && is_object($this->exceptions)){
						$this->exceptions->add('auth.login','error',$data);
					}
				}
				else if(is_object($this->exceptions)){
					$this->exceptions->add('wrongAuthenticationData','error',"missing host or password data in database 'config' table");
				}
			}
			else if(is_object($this->exceptions)){
				$this->exceptions->add('noDatabase','error');
			}
			return $this->apiCookies;
		}
	}
	public function getStatus(){
		if(empty($this->apiCookies)){
			$this->authentication();
		}
		if($this->hasExceptions()==0){
			if(!empty($this->apiCookies)){
				$host = $this->database->getConfigData('deluge','host');
				
				$curl = curl_init();
				curl_setopt($curl, CURLOPT_URL, $host);
				curl_setopt($curl, CURLOPT_POSTFIELDS, '{"method":"web.connected","params":[],"id":1}');
				curl_setopt($curl, CURLOPT_COOKIE, $this->apiCookies); 
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
				curl_setopt($curl, CURLOPT_ENCODING, "gzip"); 
				$data = curl_exec($curl);
				curl_close($curl);
				
				$data = json_decode($data);
				if(is_object($data)){
					if(property_exists($data, 'result')){
						return $data->result;
					}
				}
			}
			else if(is_object($this->exceptions)){
				$this->exceptions->add('noApiCookies','error');
			}
		}
		return false;
	}
	public function getList(){
		if($this->hasExceptions()==0){
			if(!$this->getStatus()){
				$this->authentication();
			}
			if($this->getStatus()){
				$host = $this->database->getConfigData('deluge','host');
				
				$curl = curl_init();
				curl_setopt($curl, CURLOPT_URL, $host);
				curl_setopt($curl, CURLOPT_POSTFIELDS, '{"id": 1, "method": "web.update_ui", "params": ['.json_encode($this->defaultListParams).',{}]}');
				curl_setopt($curl, CURLOPT_COOKIE, $this->apiCookies); 
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
				curl_setopt($curl, CURLOPT_ENCODING, "gzip"); 
				$data = curl_exec($curl);
				curl_close($curl);
				
				return json_decode($data);
			}
		}
	}
	public function syncData(){
		if($this->hasExceptions()==0){
			$data = $this->getList();
			$valid = is_object($data);
			if($valid){ $valid = property_exists($data, 'result'); }
			if($valid){ $valid = is_object($data->result); }
			if($valid){ $valid = property_exists($data->result, 'torrents'); }
			if($valid){ $valid = is_object($data->result->torrents); }
			if($valid){
				$currentTime = new DateTime();
				$currentTime = $currentTime->format('U');
				$count = 0;
				foreach($data->result->torrents as $torrentHashkey => $torrentData){
					$this->database->syncNewData($currentTime, $torrentHashkey, $torrentData);
					$count++;
				}
				/**
				 * @todo : improve sync return
				 */
				return $count;
			}
		}
	}
}
?>