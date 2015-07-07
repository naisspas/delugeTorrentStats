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

		switch($forward->path){
			case 'delugeAPI:getList': 
				$outputData['updated'] = $deluge->syncData();
				break;
			case 'torrent:list': 
				$outputData['rows'] = $db->getList($db->getBooleanForm($request->get('onlyActive')));
				break;
			case 'config:update':
				$host = $request->get('host');
				if(!is_object($host)){ $db->updateConfig('deluge','host',$host); }
				$password = $request->get('password');
				if(!is_object($password)){ $db->updateConfig('deluge','password',$password); }
				break;
		}

	}
	$outputData ["success"] = $exceptions->getCount()==0;
	$outputData ["exceptions"] = $exceptions->getSummary();


	header('Cache-Control: no-cache, must-revalidate');
	header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
	header('Content-type: application/json');
	echo json_encode($outputData);
	
?>