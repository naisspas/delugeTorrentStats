<?php
	class database extends SQLite3
	{
		private $configData = NULL; // only if default (plexWatchWeb db)
		private $status = false;
		function __construct($filename)
		{
			parent::__construct($filename);
			/**
			 * @todo : Rajouter vérification existance et conformité bd, sinon création
			 */
			$this->status = true;
			// Chargement des données config par défaut
			$this->loadConfigData();			 
		}
		
		public function readStats($hashkey){
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
						
						//$ts = $data[$i]['timeData']; $currentDate = new DateTime("@$ts");
						//$ts = $data[$i+1]['timeData']; $lastDate = new DateTime("@$ts");
						//$dDiff = $currentDate->diff($lastDate);
						$date1 = $data[$i]['timeDataPHP'];
						$date2 = $data[$i+1]['timeDataPHP'];
						$dDiff = $date1->diff($date2);
						$data[$i]['lastDataDiff_days'] = $dDiff->days;
						
						$totalMinutes = $dDiff->days*1440 + $dDiff->h*60 + $dDiff->i;
						$data[$i]['lastDataDiff_minutes'] = $totalMinutes;
					}
					unset($data[$i]['timeDataPHP']);
					
				}
				//$data[] = $i;
				
				$result = $stmt->close();
				return $data;
				
			}
			return $data;
		}
		public function readTorrent($onlyActive){
			$sql = 'SELECT * FROM torrent';
			if($onlyActive) {
			
				$stmt = $this->prepare('SELECT MAX(timeData) AS maxTimeData FROM torrentData');
				$result = $stmt->execute();
				$tmp = $result->fetchArray();
				$maxTimeData = $tmp['maxTimeData'];
				$stmt->close();
				
				// n'afficher que les torrents qui ont eu des données lors du dernier ajout (date la + récente)'
				$sql = "SELECT T.*
					FROM torrent T
						INNER JOIN torrentData TD ON TD.hashkey=T.hashkey AND TD.timeData = :maxTimeData
				";
			}
			$stmt = $this->prepare($sql);
			if($onlyActive) {
				$stmt->bindValue(':maxTimeData', $maxTimeData, SQLITE3_INTEGER);
			}
			$data = array();
			if($stmt!==false) {
				$result = $stmt->execute();
				$currentTime = new DateTime();
				$timezoneOffset = $currentTime->format('Z');
				while($res = $result->fetchArray(SQLITE3_ASSOC)){
					$tmp = $res;
					$ts = $tmp['timeAdded']+$timezoneOffset;
					$date = new DateTime("@$ts");
					$tmp['timeAdded_format'] = $date->format('Y-m-d H:i:s');
					$data[] = $tmp;
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
							//$stmt->bindValue(':savePath', $row->savePath, SQLITE3_TEXT);

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
		public function loadConfigData(){
			// TODO : remplir les données ...
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
		//TODO : faire le get.. pour renvoyer les données
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
	}
?>