<?php


	require_once("./includes/dbSqlite.class.php");
	require_once("./includes/database.class.php");
	require_once("./includes/deluge.class.php");
	
	$type = isset($_GET['type']) ? $_GET['type'] : NULL;
	$action = isset($_GET['action']) ? $_GET['action'] : NULL;
	
	$valid = false;
	switch($type){
		case "newData":
		case "stats":
			$valid=true;
			break;
	}
	switch($action){
		case "create":
		case "read":
			$valid=true;
			break;
	}
	$rows = array();
	
	$error="";
	
	$db = new database('delugeWatchWeb.db');
	$deluge = new deluge($db);
	
	$updated = $deluge->syncData();
	$rows = $db->readTorrent($db->getBooleanForm(false));
	
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
		
		$return = array(
			"success" => empty($error),
			"message" => $error,
			"totalUpdated" => $updated,
			"rows" => $rows
		);
	
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Content-type: application/json');
		echo json_encode($return);
	//}	
	
?>