<?php
/**
 * dbSqlite : extension of dbSqlite.class, with features for delugeWatchWeb
 *
 * @author Philippe Hamel <naisspas@hotmail.ca>
 */
class database extends dbSqlite
{
	private $configData = NULL;
	private $databaseStructure = array(
		'config' => "CREATE TABLE config (
				id      INTEGER         PRIMARY KEY AUTOINCREMENT,
				[key]   VARCHAR( 50 )   NOT NULL UNIQUE ON CONFLICT ROLLBACK,
				value   VARCHAR( 255 ),
				section VARCHAR( 50 )   NOT NULL 
			);
			INSERT INTO config (key, value, section) VALUES ('host', '', 'deluge');
			INSERT INTO config (key, value, section) VALUES ('password', '', 'deluge');",
		'torrent' => "CREATE TABLE torrent (
				hashkey     TEXT    PRIMARY KEY,
				name        TEXT,
				timeAdded   INTEGER,
				totalSize   REAL,
				trackerHost TEXT 
			);",
		'torrentData' => "CREATE TABLE torrentData (
				torrentDataId       INTEGER PRIMARY KEY AUTOINCREMENT,
				hashkey             TEXT    REFERENCES torrent ( hashkey ),
				timeData            INTEGER,
				totalUploaded       REAL,
				ratio               NUMERIC,
				label               TEXT,
				progress            INT     DEFAULT ( 0 ),
				downloadPayloadRate REAL    DEFAULT ( 0 ),
				uploadPayloadRate   REAL    DEFAULT ( 0 ) 
			);"
	);
	
	function __construct($filename)
	{
		parent::__construct($filename);

		$this->verifyStructure();
		$this->loadConfigData();
	}
	
	/*public function readStats($hashkey){
		$stmt = $this->prepare(
			'SELECT *
			FROM torrentData WHERE hashkey=:hashkey
			ORDER BY timeData DESC'
		);
		$stmt->bindValue(':hashkey', $hashkey, SQLITE3_TEXT);
		$data = array();
		if($stmt!==false) {
			$result = $stmt->execute();
			$i=0;
			$currentTime = new DateTime();
			$timezoneOffset = $currentTime->format('Z');
			while($res = $result->fetchArray(SQLITE3_ASSOC)){
				$tmp = $res;
				$ts = $tmp['timeData'] + $timezoneOffset;
				$date = new DateTime("@$ts");
				$tmp['timeDataPHP'] = $date;
				$tmp['timeData_format'] = $date->format('Y-m-d H:i:s');
				$tmp['lastDataDiff_days'] = 0;
				$tmp['lastDataDiff_uploaded'] = 0;
				$data[] = $tmp;
				$i++;
			}
			
			for($i=0; $i<count($data); $i++){
				if($i<(count($data)-1)){
					$data[$i]['lastDataDiff_uploaded'] = $data[$i]['totalUploaded'] - $data[$i+1]['totalUploaded'];
					$date1 = $data[$i]['timeDataPHP'];
					$date2 = $data[$i+1]['timeDataPHP'];
					$dDiff = $date1->diff($date2);
					$data[$i]['lastDataDiff_days'] = $dDiff->days;
					
					$totalMinutes = $dDiff->days*1440 + $dDiff->h*60 + $dDiff->i;
					$data[$i]['lastDataDiff_minutes'] = $totalMinutes;
				}
				unset($data[$i]['timeDataPHP']);
				
			}
			
			$result = $stmt->close();
			return $data;
			
		}
		return $data;
	}*/
	public function getList($existingTorrentOnly = true, $fromDate = NULL, $toDate = NULL){
		$sql = "SELECT T.hashkey
					,T.name
					,T.timeAdded
					,T.totalSize
					,T.trackerHost
					,TDM.totalUploaded AS maxUploaded
					,TD.label
					,TD.timeData
					,TD.totalUploaded
					,TD.ratio
				FROM torrent T
				INNER JOIN torrentData TDM ON TDM.hashkey = T.hashkey
					AND TDM.timeData = (SELECT MAX(timeData) FROM torrentData WHERE hashkey = T.hashkey)
				INNER JOIN torrentData TD ON TD.hashkey = T.hashkey";
		$sqlWhere = array("T.name IS NOT NULL");
		$sqlOrder = array("T.name ASC","TD.timeData ASC");
		if($existingTorrentOnly) {
			$sqlWhere[] = "T.hashkey IN (
					SELECT hashkey
					FROM torrentData
					WHERE timeData = (
						SELECT MAX(timeData)
						FROM torrentData
				   ) 
				)";
		}
		$fromDateObj = NULL;
		$toDateObj = NULL;
		
		if(!empty($fromDate) || !empty($toDate)){
			$tmpSqlWhere = NULL;
			$fromDateObj = DateTime::createFromFormat('Y-m-d H:i:s', $fromDate);
			$toDateObj = DateTime::createFromFormat('Y-m-d H:i:s', $toDate);
			if($fromDateObj!==false && $toDateObj!==false){
				if($toDateObj > $fromDateObj){
					$tmpSqlWhere = "timeData BETWEEN :fromDate AND :toDate";
				}
			}
			else if($fromDateObj!==false){
				$tmpSqlWhere = "timeData >= :fromDate";
			}
			else if($toDateObj!==false){
				$tmpSqlWhere = "timeData <= :toDate";
			}
			if($tmpSqlWhere!=NULL){
				$sqlWhere[] = "TD.timeData IN (
					SELECT timeData
					FROM torrentData
					WHERE ".$tmpSqlWhere."
					GROUP BY timeData
				)";
			}
			unset($tmpSqlWhere);
		}
		
		if(count($sqlWhere)>0){
			$sql .= " WHERE ".implode(' AND ',$sqlWhere);
		}
		if(count($sqlOrder)>0){
			$sql .= " ORDER BY ".implode(', ',$sqlOrder);
		}
		
		$currentTime = new DateTime();
		$timezoneOffset = $currentTime->format('Z');

		$stmt = $this->prepare($sql);
		if(is_a($fromDateObj, 'DateTime')){
			$tmpDate = $fromDateObj->getTimestamp()-$timezoneOffset;
			$stmt->bindValue(':fromDate', $tmpDate, SQLITE3_INTEGER );
			unset($tmpDate);
		}
		if(is_a($toDateObj, 'DateTime')){
			$tmpDate = $toDateObj->getTimestamp()-$timezoneOffset;
			$stmt->bindValue(':toDate', $tmpDate, SQLITE3_INTEGER );
			unset($tmpDate);
		}		
		
		$data = array();
		$timeData = array();
		
		if($stmt!==false) {
			$result = $stmt->execute();
			while($res = $result->fetchArray(SQLITE3_ASSOC)){
				$tmp = $res;
				
				$ts = $tmp['timeAdded']+$timezoneOffset;
				$date = new DateTime("@$ts");
				$tmp['timeAdded'] = $date->format('Y-m-d H:i:s');
				
				if(!array_key_exists($tmp['hashkey'],$data)){
					$data[$tmp['hashkey']] = array(
						'haskey'		=> $tmp['hashkey'],
						'name'			=> $tmp['name'],
						'timeAdded'		=> $tmp['timeAdded'],
						'totalSize'		=> $tmp['totalSize'],
						'maxUploaded'	=> $tmp['maxUploaded'],
						'formatted'		=> array(
							'totalSize'		=> $this->formatBytes($tmp['totalSize'],3),
							'maxUploaded'	=> $this->formatBytes($tmp['maxUploaded'],3),
						),
						'trackerHost'	=> $tmp['trackerHost'],
						'label'			=> $tmp['label'],
						'data'			=> array()
					);
				}

				$ts = $tmp['timeData']+$timezoneOffset;
				$date = new DateTime("@$ts");
				
				if(!array_key_exists($tmp['timeData'],$timeData)){
					$timeData[$tmp['timeData']] = $date;
				}
				
				$gapData = array(
					'gap'					=> array('days' => 0, 'hours' => 0, 'minutes' => 0),
					'increasePercentage'	=> 0,
					'uploaded'				=> 0,
					'uploaded24h'			=> 0,
					'formatted'				=> array(
						'uploaded'				=> 0,
						'uploaded24h'			=> 0
					)
				);
				if(count($data[$tmp['hashkey']]['data'])>0){
					$lastData = end(array_values($data[$tmp['hashkey']]['data']));
					$gapData['uploaded'] = $tmp['totalUploaded'] - $lastData['totalUploaded'];
					if(!empty($lastData['totalUploaded'])){
						$gapData['increasePercentage'] = round(($gapData['uploaded'] * 100) / $lastData['totalUploaded'],2);
					}
					$gapData['formatted']['uploaded'] = $this->formatBytes(abs($gapData['uploaded']),3);

					$dateLastData = $timeData[$lastData['timeData']];
					
					$dDiff = $date->diff($dateLastData);
					$gapData['gap'] = array('days' => $dDiff->d, 'hours' => $dDiff->h, 'minutes' => $dDiff->i);
					$totalMinutes = $dDiff->d*1440 + $dDiff->h*60 + $dDiff->i;

					if(!is_int($totalMinutes)){ $totalMinutes = 0; }
					if(!empty($totalMinutes)){
						$gapData['uploaded24h'] = ($gapData['uploaded'] / $totalMinutes)*1440;
						$gapData['formatted']['uploaded24h'] = $this->formatBytes(abs($gapData['uploaded24h']),3);
					}
				}
				$data[$tmp['hashkey']]['data'][] = array(
					'timeData'					=> $tmp['timeData'],
					'totalUploaded'				=> $tmp['totalUploaded'],
					'ratio'						=> $tmp['ratio'],
					'gapData'					=> $gapData,
					'formatted'				=> array(
						'timeData'				=> $date->format('Y-m-d H:i:s'),
						'totalUploaded'			=> $this->formatBytes($tmp['totalUploaded'],3)
					)					
				);
			}
			$result = $stmt->close();
			return $data;
			
		}
		return $data;
	}
	public function syncNewData($currentTime, $torrentHashkey, $torrentData, $forceMaster=true){
		$result=false;
		if(is_object($torrentData) && !empty($torrentHashkey)) {
			$stmt = $this->prepare('SELECT COUNT(hashkey) AS EXIST FROM torrent WHERE hashkey=:hashkey');
			$stmt->bindValue(':hashkey', $torrentHashkey, SQLITE3_TEXT);
			$result = $stmt->execute();
			$tmp = $result->fetchArray();
			$masterExist = false;
			if($tmp['EXIST']==0) {
				if($forceMaster) {
					$stmt = $this->prepare(
						'INSERT INTO torrent (hashkey, name, timeAdded, totalSize, trackerHost)
						VALUES (:hashkey, :name, :timeAdded, :totalSize, :trackerHost)'
					);
					if($stmt!==false) {
						$stmt->bindValue(':hashkey', $torrentHashkey, SQLITE3_TEXT);
						$stmt->bindValue(':name', $torrentData->name, SQLITE3_TEXT);
						$stmt->bindValue(':timeAdded', $torrentData->time_added, SQLITE3_INTEGER );
						$stmt->bindValue(':totalSize', $torrentData->total_size );
						$stmt->bindValue(':trackerHost', $torrentData->tracker_host, SQLITE3_TEXT);

						$result = $stmt->execute();
						$result = $stmt->close();
						$masterExist=true;
					}
				}
			}
			else {
				$masterExist = true;
			}
			
			if($masterExist) {
				$stmt = $this->prepare('SELECT COUNT(hashkey) AS EXIST FROM torrentData
					WHERE hashkey=:hashkey AND timeData=:timeData' );
				$stmt->bindValue(':hashkey', $torrentHashkey, SQLITE3_TEXT);
				$stmt->bindValue(':timeData', $currentTime*1 , SQLITE3_INTEGER );
				$result = $stmt->execute();
				$tmp = $result->fetchArray();
				if($tmp['EXIST']==0) {
					$stmt = $this->prepare(
						'INSERT INTO torrentData (
							hashkey
							,timeData
							,totalUploaded
							,ratio
							,label
							,progress
							,downloadPayloadRate
							,uploadPayloadRate
						)
						VALUES (
							:hashkey
							, :timeData
							, :totalUploaded
							, :ratio
							, :label
							, :progress
							, :downloadPayloadRate
							, :uploadPayloadRate
						)'
					);
					if($stmt!==false) {
						$stmt->bindValue(':hashkey', $torrentHashkey, SQLITE3_TEXT);
						$stmt->bindValue(':timeData', $currentTime*1 , SQLITE3_INTEGER );
						$stmt->bindValue(':totalUploaded', $torrentData->total_uploaded);
						$stmt->bindValue(':ratio', $torrentData->ratio );
						$stmt->bindValue(':label', $torrentData->label, SQLITE3_TEXT);
						$stmt->bindValue(':progress', $torrentData->progress, SQLITE3_INTEGER);
						$stmt->bindValue(':downloadPayloadRate', $torrentData->download_payload_rate);
						$stmt->bindValue(':uploadPayloadRate', $torrentData->upload_payload_rate);

						$result = $stmt->execute();
						$stmt->close();
					}
				}
			}
		}
		return $result;
	}
	public function getBooleanForm($pValue) {
		if(is_bool($pValue)) { return $pValue; }
		if(is_numeric($pValue)) { return $pValue == 0 ? false : true; }
		if(is_string($pValue)){
			if(empty($pValue)) { return false; }
			switch($pValue) {
				case strtolower($pValue) === 'true': return true; break;
				case strtolower($pValue) === 'on': return true; break;
				case strtolower($pValue) === 'yes': return true; break;
				case strtolower($pValue) === 'y': return true; break;
				default: return false;
			}
		}
		return false;
	}
	private function verifyStructure(){
		$creation = 0;
		if(is_array($this->databaseStructure)){
			foreach($this->databaseStructure as $tableName => $SQL){
				$creation += ($this->createTable($tableName,$SQL)==true);
			}
		}
		if($creation>0){
			$this->loadTableDefinitions();
		}
	}

	public function loadConfigData(){
		
		if($this->status===true){
			$this->configData = array();
			$stmt = $this->prepare("SELECT * FROM config;");
			if($stmt!==false) {
				$result = $stmt->execute();
				while($res = $result->fetchArray(SQLITE3_ASSOC)){
					if(!isset($this->configData[$res['section']])){
						$this->configData[$res['section']] = array();
					}
					$this->configData[$res['section']][$res['key']]=$res['value'];
				}
			}
			$this->configData = (object) $this->configData;
		}
	}
	
	public function getConfigData($section=NULL, $key=NULL){
		if(empty($section) || empty($key)){
			return $this->configData;
		}
		if(property_exists($this->configData, $section)){
			if(array_key_exists($key,$this->configData->$section)){
				$sectionData = $this->configData->$section;
				return $sectionData[$key];
			}
		}
		return NULL;
	}
	public function updateConfig($section=NULL, $key=NULL, $value=NULL){
		if(!empty($section) && !empty($key)){
			if(property_exists($this->configData, $section)){
				if(array_key_exists($key,$this->configData->$section)){

					$config = array(
						'tableName' => 'config',
						'values' => array('value' => $value),
						'where' => array('key' => $key,'section' => $section)
					);
					$this->set($config);
				}
			}		
		}
	}
	private function formatBytes($size, $precision = 2){
		if($size==0) return $size;
		$base = log($size, 1024);
		$suffixes = array('', 'k', 'M', 'G', 'T');   

		return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
	}
}
?>