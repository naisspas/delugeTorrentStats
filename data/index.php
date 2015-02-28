<?php

	require_once("./includes/request.class.php");
	require_once("./includes/exceptions.class.php");
	require_once("./includes/dbSqlite.class.php");
	require_once("./includes/database.class.php");
	require_once("./includes/deluge.class.php");

	$exceptions = new exceptions(array('error','warning','info'),'error');
	if(!class_exists('SQLite3')){ $exceptions->add('missingSQLite3','error','php5-sqlite is not installed/activated. Please install/activate this requirement and restart your webserver before continuing.'); }
	if(!function_exists('curl_version')){ $exceptions->add('missingCurl','error','php-curl is not install/activated. Please install/activate this requirement and restart your webserver before continuing.'); }

	$routing = array(
		'GET' => array(
			array(
				'parameter' => 'type',
				'value'		=> 'delugeAPI',
				'next'		=> array(
					array(
						'parameter' => 'action',
						'value' 	=> 'getList'
					)
				)
			)
		),
		'POST' => array(
			array(
				'parameter' => 'type',
				'value' 	=> 'config',
				'next'		=> array(
					array(
						'parameter' => 'action',
						'value'		=> 'update',
						'presence'	=> array('host','password')
					)
				)
			),
			array(
				'parameter' => 'type',
				'value' 	=> 'torrent',
				'next'		=> array(
					array(
						'parameter' => 'action',
						'value'		=> 'list',
						'presence'	=> array('onlyActive')
					)
				)
			)			
		)	
	);
	$request = new request(
		array('POST','GET'),
		$routing
	);
	
	
	/*$type = $request->get('type');
	$action = $request->get('action');

	
	$validParameters = false;
	switch($type){
		case "config":
		case "delugeAPI":
		case "torrent":
		case "torrentData":
			break;
		default:
			$exceptions->add('unknownType','error');
			break;
	}
	if($exceptions->getCount()==0){
		switch($action){
			case "getList":
			case "read":
			case "update":
			case "create":
			case "destroy":
				break;
			default:
				$exceptions->add('unknownAction','error');
				break;
		}
	}*/
	

	//$outputData['routing'] = $request->getRouting();
	$outputData = array(
		"success" => true,
		"exceptions" => NULL
	);
	$forward = $request->findForward();
	if(!$forward->found){
		$exceptions->add('wrongParameters','error');
	}
	
	if($exceptions->getCount()==0){
		$db = new database('delugeWatchWeb.db');
		$deluge = new deluge($db, $exceptions);
		
		//$updated = $deluge->syncData();
		//$rows = $db->readTorrent($db->getBooleanForm(false));
		
		
		
		$call = NULL;
		switch($forward->path){
			case 'delugeAPI:getList': 
				$outputData['updated'] = $deluge->syncData();
				break;
			case 'torrent:list': 
				$outputData['rows'] = $db->readTorrent($db->getBooleanForm($request->get('onlyActive')));;
				break;
		}

	}
	$outputData ["success"] = $exceptions->getCount()==0;
	$outputData ["exceptions"] = $exceptions->getSummary();
	//if($valid) {
	
		/*$formData = file_get_contents('php://input');
		$jsonData = json_decode($formData);
		$db = new database();
		$error="";
		if(is_object($jsonData)) {
			$timeData = isset($_GET['timeData']) ? $_GET['timeData'] : NULL;
			if(!empty($timeData)) {
				$currentTime = new DateTime($timeData);
			}
			else { $currentTime = new DateTime(); }
			//$rows = $currentTime;
			foreach($jsonData->rows as $row) {
				switch($type){
					case "newData":
						$db->syncNewData($row, $currentTime->format('U'));
						break;
					case "stats":
						$hashkey = isset($_GET['hashkey']) ? $_GET['hashkey'] : NULL;
						if($hashkey){
							$currentTime = DateTime::createFromFormat('Y-m-d H:i:s', $row->timeData_format);
							if(!is_a($currentTime, 'DateTime')){ $currentTime = new DateTime(); }
							
							$row = (object) array(
								"hashkey"			=> $hashkey
								,"totalUploaded"	=> $row->totalUploaded
								,"ratio"			=> $row->ratio
							);
							
							$result = $db->syncNewData($row, $currentTime->format('U'), false);
						}
						else { $error ='missingHashskey'; }
						break;
				}
			}
		}
		else {
			switch($type){
				case "currentTorrent":
					$onlyActive = isset($_POST['onlyActive']) ? $_POST['onlyActive'] : false;
					$rows = $db->readTorrent($db->getBooleanForm($onlyActive));
					//$rows = "test";
					break;
				case "stats":
					$hashkey = isset($_POST['hashkey']) ? $_POST['hashkey'] : NULL;
					if(!empty($hashkey)){
						$rows = $db->readStats($hashkey);
					}
					break;
			}
		
		}*/
			/*if(property_exists($this->configData, $section)){
				if(property_exists($this->configData->$section, $key)){
					return $this->configData->$section->$key;
				}
			}*/
		/*$section = 'deluge';
		$key = 'host';
		$test2 = array();
		foreach($config->$section as $key2 => $value){ $test2[] = array('key' => $key2, 'value' => $value); }
		$test3 = $config->$section;
		$test = array(
			'section ?' => property_exists($config, $section),
			'section: ' => serialize($config->$section),
			'section (foreach): ' => $test2,
			
			'key ?' => array_key_exists($key,$config->$section),
			'key: ' => $test3[$key]
		);*/

	header('Cache-Control: no-cache, must-revalidate');
	header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
	header('Content-type: application/json');
	echo json_encode($outputData);
	//}	
	
?>