<?php

	//Provide statistics on system
	require_once '../private_html/hub_connect.php';
	require_once 'auth.inc';

	// *** Simple List of Current Values ***

	$sensorQry = "SELECT *, COALESCE(s.\"Name\", z.\"ZoneName\")::text || ' ' || \"MeasurandName\" AS \"SensorTitle\",
					to_char(\"LastUpdated\", 'DD/MM/YYYY HH24:MI') AS \"LastUpdated\", \"ErrorMessage\"
					FROM \"Sensor\" s
					INNER JOIN \"Measurand\" m ON s.\"MeasurandID\" = m.\"MeasurandID\"
					INNER JOIN \"Zone\" z ON s.\"ZoneID\" = z.\"ZoneID\"
					ORDER BY \"SensorID\";";

	$sensorResult = $db_object->query($sensorQry);

	if (MDB2::isError($sensorResult)) {
		error_log("Database Error Query: ".$sensorQry." ".$sensorResult->getMessage(), 0);
		die($sensorResult->getMessage());
	}//end db error

	echo('<table class="statsGrid">');
	echo('<caption>Sensors</caption>');
	echo('<tr><th>Sensor</th><th>Name</th><th>Current Value</th><th>Last Updated</th><th>Enabled</th><th>Error</th></tr>');
	while ($sensorRow = $sensorResult->fetchRow(DICTCURSOR)) {
		$sensRef =  $sensorRow['SensorID'];
		$sensName =  $sensorRow['SensorTitle'];
		$units =  $sensorRow['Units'];
		$curVal = $sensorRow['CurrentValue'].' '.$sensorRow['Units'];
		$lastUpdated = $sensorRow['LastUpdated'];
		$enab = $sensorRow['Enabled'];
		$err = $sensorRow['ErrorMessage'];
			echo('<tr>');
				echo('<td>S'.$sensRef.'</td>');
				echo('<td>'.$sensName.'</td>');
				echo('<td>'.$curVal.'</td>');
				echo('<td>'.$lastUpdated.'</td>');
				echo('<td>'.($enab == "t"?"Yes":"No").'</td>');
				echo('<td>'.$err.'</td>');
			echo('</tr>');
	}//wend
	echo('</table><br/>');

	// *** Simple List of Actuator Values ***

	$actQry = "SELECT \"ActuatorID\", \"ActuatorName\", \"IsInAuto\", \"CurrentValue\",
						to_char(\"LastUpdated\", 'DD/MM/YYYY HH24:MI') AS \"LastUpdated\",
						\"UpdatedBy\", \"OnForMins\", \"OffForMins\", \"Enabled\"
					FROM \"Actuator\"
					ORDER BY \"ActuatorID\";";

	$actResult = $db_object->query($actQry);

	if (MDB2::isError($actResult)) {
		error_log("Database Error Query: ".$actQry." ".$actResult->getMessage(), 0);
		die($actResult->getMessage());
	}//end db error

	echo('<table class="statsGrid">');
	echo('<caption>Actuators</caption>');
	echo('<tr><th>Actuator</th><th>Name</th><th>Auto</th><th>Current Value</th><th>Last Updated</th><th>Updated By</th><th>On For [mins]</th><th>Off For [mins]</th><th>Enabled</th></tr>');
	while ($actRow = $actResult->fetchRow(DICTCURSOR)) {
		$actRef =  $actRow['ActuatorID'];
		$actName =  $actRow['ActuatorName'];
		$auto = $actRow['IsInAuto'];
		$curVal = $actRow['CurrentValue'];
		$lastUpd = $actRow['LastUpdated'];
		$updBy = $actRow['UpdatedBy'];
		$onFor = $actRow['OnForMins'];
		$offFor = $actRow['OffForMins'];
		$enab = $actRow['Enabled'] == 't' ? 'Y' : 'N';
		echo('<tr>');
			echo('<td>U'.$actRef.'</td>');
			echo('<td>'.$actName.'</td>');
			echo('<td style="text-align: center;">'.$auto.'</td>');
			echo('<td style="text-align: right;">'.$curVal.'</td>');
			echo('<td>'.$lastUpd.'</td>');
			echo('<td style="text-align: center;">'.$updBy.'</td>');
			echo('<td style="text-align: right;">'.$onFor.'</td>');
			echo('<td style="text-align: right;">'.$offFor.'</td>');
			echo('<td style="text-align: center;">'.$enab.'</td>');
		echo('</tr>');
	}//wend
	echo('</table><br/>');

	// *** List of Alert Values/Statuses ***

	$alertQry = "SELECT *, COALESCE(s.\"Name\", z.\"ZoneName\")::text || ' ' || \"MeasurandName\" AS \"SensorTitle\",
					to_char(\"LastUpdated\", 'DD/MM/YYYY HH24:MI') AS \"LastUpdated\"
					FROM \"Sensor\" s
					INNER JOIN \"Measurand\" m ON s.\"MeasurandID\" = m.\"MeasurandID\"
					INNER JOIN \"Zone\" z ON s.\"ZoneID\" = z.\"ZoneID\"
					ORDER BY \"SensorID\";";

	$alertResult = $db_object->query($alertQry);

	if (MDB2::isError($alertResult)) {
		error_log("Database Error Query: ".$alertQry." ".$alertResult->getMessage(), 0);
		die($alertResult->getMessage());
	}//end db error

	echo('<table class="statsGrid">');
	echo('<caption>Sensor Alerts</caption>');
	echo('<tr><th>Sensor</th><th>Name</th><th>Current Value</th><th>High</th><th>Low</th></tr>');
	while ($alertRow = $alertResult->fetchRow(DICTCURSOR)) {
		$sensRef =  $alertRow['SensorID'];
		$sensName =  $alertRow['SensorTitle'];
		$units =  $alertRow['Units'];
		$curVal = $alertRow['CurrentValue'];
		$curValWithUnits = $curVal.' '.$alertRow['Units'];
		if (array_key_exists('HighAlert', $alertRow)) {
			$highAlert = $alertRow['HighAlert'];
		} else {
			$highAlert = '';
		}
		if (array_key_exists('LowAlert', $alertRow)) {
			$lowAlert = $alertRow['LowAlert'];
		} else {
			$lowAlert = '';
		}
		$cssClass = 'CurrentValueNormal';
		if ($highAlert != '' && $curVal > $highAlert) {
			$cssClass = 'CurrentValueHigh';
		} else if ($lowAlert != '' && $curVal < $lowAlert) {
			$cssClass = 'CurrentValueLow';
		}
		echo('<tr class="'.$cssClass.'">');
			echo('<td>S'.$sensRef.'</td>');
			echo('<td>'.$sensName.'</td>');
			echo('<td>'.$curValWithUnits.'</td>');
			echo('<td>'.$highAlert.'</td>');
			echo('<td>'.$lowAlert.'</td>');
		echo('</tr>');
	}//wend
	echo('</table><br/>');

	// *** Long-term Statistics ***

	$statsQry = "SELECT *, COALESCE(s.\"Name\", z.\"ZoneName\")::text || ' ' || \"MeasurandName\" AS \"SensorTitle\",
					x.\"Value\" AS \"MaxValue\",
					n.\"Value\" AS \"MinValue\",
					to_char(x.\"Timestamp\", 'DD/MM/YYYY HH24:MI') AS \"MaxTimestamp\",
					to_char(n.\"Timestamp\", 'DD/MM/YYYY HH24:MI') AS \"MinTimestamp\"
					FROM \"Sensor\" s
					INNER JOIN \"Measurand\" m ON s.\"MeasurandID\" = m.\"MeasurandID\"
					INNER JOIN \"Zone\" z ON s.\"ZoneID\" = z.\"ZoneID\"
					INNER JOIN \"vwStatistics\" x ON s.\"SensorID\" = x.\"SensorID\" AND x.\"StatsFunction\" = 'Maximum'
					INNER JOIN \"vwStatistics\" n ON s.\"SensorID\" = n.\"SensorID\" AND n.\"StatsFunction\" = 'Minimum'
					WHERE x.\"Value\" <> n.\"Value\"
					ORDER BY GREATEST(x.\"Timestamp\", n.\"Timestamp\") DESC, s.\"SensorID\";";

	$statsResult = $db_object->query($statsQry);

	if (MDB2::isError($statsResult)) {
		print 'Statistics Not Supported<br /><br />';
	}//end db error
	else {

		echo('<table class="statsGrid">');
		echo('<caption>Sensor Max/Mins</caption>');
		echo('<tr><th>Sensor</th><th>Name</th><th>Current Value</th><th>Maximum</th><th>Date</th><th>Minimum</th><th>Date</th></tr>');
		while ($statsRow = $statsResult->fetchRow(DICTCURSOR)) {
			$sensRef =  $statsRow['SensorID'];
			$sensName =  $statsRow['SensorTitle'];
			$units =  $statsRow['Units'];
			$curVal = $statsRow['CurrentValue'].' '.$statsRow['Units'];
			$maxValue = $statsRow['MaxValue'].' '.$statsRow['Units'];
			$maxDate = $statsRow['MaxTimestamp'];
			$minValue = $statsRow['MinValue'].' '.$statsRow['Units'];
			$minDate = $statsRow['MinTimestamp'];
				echo('<tr>');
					echo('<td>S'.$sensRef.'</td>');
					echo('<td>'.$sensName.'</td>');
					echo('<td>'.$curVal.'</td>');
					echo('<td>'.$maxValue.'</td>');
					echo('<td>'.$maxDate.'</td>');
					echo('<td>'.$minValue.'</td>');
					echo('<td>'.$minDate.'</td>');
				echo('</tr>');
		}//wend
		echo('</table><br/>');
	}
	// *** Home Measurand Statistics ***

	$maxMinQry = "SELECT * FROM \"vwZoneMaxMinValues\";";
	$maxMinResult = $db_object->query($maxMinQry);

	if (MDB2::isError($maxMinResult)) {
		error_log("Database Error Query: ".$maxMinQry." ".$maxMinResult->getMessage(), 0);
		die($maxMinResult->getMessage());
	}//end db error

	echo('<table class="statsGrid">');
	echo('<caption>Measurand Max/Mins</caption>');
	echo('<tr><th>Measurand</th><th>Current Maximum</th><th>Current Minimum</th></tr>');
	while ($maxMinRow = $maxMinResult->fetchRow(DICTCURSOR)) {
		$meas =  $maxMinRow['MeasurandName'];
		$units =  $maxMinRow['Units'];
		$maxValue = $maxMinRow['MaxValue'];
		$maxZone = $maxMinRow['MaxZone'];
		$minValue = $maxMinRow['MinValue'];
		$minZone = $maxMinRow['MinZone'];
			echo('<tr>');
				echo('<td>'.$meas.'</td>');
				echo('<td>'.$maxValue.' '.$units.' ('.$maxZone.')</td>');
				echo('<td>'.$minValue.' '.$units.' ('.$minZone.')</td>');
			echo('</tr>');
	}//wend
	echo('</table><br/>');

	// *** Event Queue ***

	$eventQry = "SELECT *, to_char(\"Timestamp\", 'DD/MM/YYYY HH24:MI:SS') AS \"Timestamp\"
						FROM \"EventQueue\" e
						ORDER BY \"EventID\" DESC
						LIMIT 10;";

		$eventResult = $db_object->query($eventQry);

		if (MDB2::isError($eventResult)) {
			error_log("Database Error Query: ".$eventQry." ".$eventResult->getMessage(), 0);
			die($eventResult->getMessage());
		}//end db error

		echo('<table class="statsGrid">');
		echo('<caption>Event Queue</caption>');
		echo('<tr><th>Timestamp</th><th>Source</th><th>Current Value</th><th>Agent</th><th>Processed</th></tr>');
		while ($eventRow = $eventResult->fetchRow(DICTCURSOR)) {
			$source =  $eventRow['SourceType'].' '.$eventRow['SourceID'];
			$curVal = $eventRow['Value'];
			$proc = $eventRow['Processed'];
			$tstmp = $eventRow['Timestamp'];
			$agent = $eventRow['SourceAgent'];
				echo('<tr>');
					echo('<td>'.$tstmp.'</td>');
					echo('<td>'.$source.'</td>');
					echo('<td>'.$curVal.'</td>');
					echo('<td>'.$agent.'</td>');
					echo('<td>'.($proc == "t"?"Yes":"No").'</td>');
				echo('</tr>');
		}//wend
	echo('</table><br/>');

	// *** System Information ***

	$dbSizeQry = "SELECT pg_size_pretty(pg_database_size('hub')) AS size, version() AS version;";

	$dbSizeResult = $db_object->query($dbSizeQry);

	if (MDB2::isError($dbSizeResult)) {
		error_log("Database Error Query: ".$dbSizeQry." ".$dbSizeResult->getMessage(), 0);
		die($dbSizeResult->getMessage());
	}//end db error


	echo('<p>');
	echo('<h4>System Information</h4>');
	while ($dbSizeRow = $dbSizeResult->fetchRow(DICTCURSOR)) {
		$sizeText =  $dbSizeRow['size'];
		$verText =  $dbSizeRow['version'];
		echo('Database Size: '.$sizeText.'<br />');
		echo('Database Version: '.$verText.'<br />');
	}
	echo('Process: '.shell_exec(" ps aux | grep -i \"python main_sched.py\" | awk 'FNR == 2 {printf(\"%s Started: %s CPU: %s%% MEM: %s%%\", $12, $9, $3, $4)}' ").'<br />');
	echo('Free Memory: '.shell_exec("free | awk 'FNR == 3 {printf(\"%.1f%%\", $4/($3+$4)*100)}'").'<br />');
	echo('Disk Free Space: '.round(disk_free_space("/")/1e6)).' MB<br />';
	echo('Distribution: '.shell_exec( "head -n 1 /etc/*release | sed 's/=/\\n/g' | sed 's/\"//g' | tail -n 1").'<br />');
	echo('Firmware: '.shell_exec( "uname -a").'<br />');
	$brdRev = trim(shell_exec( "cat /proc/cpuinfo | grep Revision | sed 's/.*: //g'"));
	$brdRevs = array(
		"a22082" => "Pi 3 Model B 1GB (Embest, China)",
		"000e" => "Model B Revision 2.0 512MB",
		"a01041" => "Pi 2 Model B 1GB (Sony, UK)",
		"0008" => "Model A 256MB",
		"900093" => "PiZero 1.3 512MB",
		"9000c1" => "PiZero W 1.1 512MB"
	);
	echo('Board Revision: '.$brdRev.' - '.$brdRevs[$brdRev].'<br />');
	echo('</p>');
	?>
