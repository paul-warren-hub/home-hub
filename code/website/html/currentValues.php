<?php

	//Draw the House Plan...
	require_once '../private_html/hub_connect.php';
	require_once 'auth.inc';
	require_once 'hub.inc';

	$windDirections = unserialize(WIND_DIRECTIONS);

	//First Loop through the Z Values representing major blocks 1st Floor/2nd Floor/Outside

	$floorQry = 'SELECT "ZoneZ", Max("ZoneName") AS "ZoneName", Count("ZoneID") AS "ZoneCount"
				FROM "Zone"
				GROUP BY "ZoneZ"
				ORDER BY "ZoneZ" Desc
			   ';

	$floorResult = $db_object->query($floorQry);

	if (MDB2::isError($floorResult)) {
		error_log("Database Error Query: ".$floorQry." ".$floorResult->getMessage(), 0);
		die($floorResult->getMessage());
	}//end db error

	while ($floorRow = $floorResult->fetchRow(DICTCURSOR)) {

		$zoneZ = $floorRow['ZoneZ'];
		$zoneIsWholeFloor = $floorRow['ZoneZ'] > 1;
		$zoneName = $floorRow['ZoneName'];

		echo('<div class="zoneFloor">');

			//If floor contains many zones - just label it 1st Floor, 2nd Floor, etc
			//Otherwise use Zone Name
			if ($zoneIsWholeFloor) {
				//echo($zoneName);
			} else {
				echo('<div class="ZoneCurrentValuesFloorTitle">'.($zoneZ == 1 ? 'Ground' : addOrdinalNumberSuffix($zoneZ)).' Floor</div>');
			}
			echo('<br />');

			//Now Loop through Zones on this Floor constructing table...

			$zoneQry = 'SELECT DISTINCT "ZoneID", "ZoneName", "ZoneX", "ZoneY", "ZoneRowspan", "ZoneColspan"
						FROM "Zone"
						WHERE "ZoneZ" = '.$zoneZ.
						'ORDER BY "ZoneY","ZoneX"
					   ';

			$zoneResult = $db_object->query($zoneQry);

			if (MDB2::isError($zoneResult)) {
				error_log("Database Error Query: ".$zoneQry." ".$zoneResult->getMessage(), 0);
				die($zoneResult->getMessage());
			}//end db error

			echo('<table class="ZoneCurrentValuesTable"><tr>');

			$tableRow = 0;

			while ($zoneRow = $zoneResult->fetchRow(DICTCURSOR)) {

				$zoneID = $zoneRow['ZoneID'];
				$zoneName = $zoneRow['ZoneName'];
				$zoneX = $zoneRow['ZoneX'];
				$zoneY = $zoneRow['ZoneY'];
				$zoneRowspan = $zoneRow['ZoneRowspan'];
				$zoneColspan = $zoneRow['ZoneColspan'];

				if ($zoneY != $tableRow) {
					echo('</tr><tr>');
					$tableRow = $zoneY;
				}

				echo('<td colspan="'.$zoneColspan.'"'.' rowspan="'.$zoneRowspan.'">');
					echo('<div class="ZoneCurrentValuesCellTitle">'.$zoneName.'</div><br />');

					//Now Loop through Sensors in this Zone...

					$sensorQry = 'SELECT "SensorID", "Name", "MeasurandName", "CurrentValue", "DecimalPlaces",
								"HighAlert", "LowAlert", "Units", to_char("LastUpdated", \'HH24:MI\') AS "LastUpdated"
								FROM "vwSensorsAndTypes"
								WHERE "ZoneID" = '.$zoneID.
								'ORDER BY "SensorID"
							   ';

					$sensorResult = $db_object->query($sensorQry);

					if (MDB2::isError($sensorResult)) {
						error_log("Database Error Query: ".$sensorQry." ".$sensorResult->getMessage(), 0);
						die($sensorResult->getMessage());
					}//end db error

					$curValDecPlaces = 1;//default if null

					while ($sensorRow = $sensorResult->fetchRow(DICTCURSOR)) {
						$sensName = $sensorRow['Name'];
						$prop = $sensorRow['MeasurandName'];
						$curVal = $sensorRow['CurrentValue'];
						$curValDecPlaces = $sensorRow['DecimalPlaces'];
						$lastUpd = $sensorRow['LastUpdated'];
						$units = $sensorRow['Units'];
						$highAlert = $sensorRow['HighAlert'];
						$lowAlert = $sensorRow['LowAlert'];
						$cssClass = 'CurrentValueNormal';
						if ($highAlert != '' && $curVal > $highAlert) {
							$cssClass = 'CurrentValueHigh';
						} else if ($lowAlert != '' && $curVal < $lowAlert) {
							$cssClass = 'CurrentValueLow';
						}
						if ($prop == 'Wind Direction') {
							$curVal = $windDirections[$curVal/22.5];
						} else {
							$curVal = number_format ($curVal, $curValDecPlaces).' '.$units;
						}
						echo('<span class="'.$cssClass.'">'.trim($sensName.' '.$prop).': '.$curVal.' ['.$lastUpd.']'.'</span><br /><br />');
					}

					//Now Loop through Actuators in this Zone...
					$digitalTypeIds = [1];

					$actuatorQry = 'SELECT "ActuatorID", "ActuatorTypeID", "ActuatorName",
											"IsInAuto", "CurrentValue",
											to_char("LastUpdated", \'Dy HH24:MI\') AS "LastUpdated",
											"UpdatedBy"
								FROM "Actuator"
								WHERE "ZoneID" = '.$zoneID.' AND "Enabled"
								ORDER BY "ActuatorID"
							   ';

					$actuatorResult = $db_object->query($actuatorQry);

					if (MDB2::isError($actuatorResult)) {
						error_log("Database Error Query: ".$actuatorQry." ".$actuatorResult->getMessage(), 0);
						die($actuatorResult->getMessage());
					}//end db error

					while ($actuatorRow = $actuatorResult->fetchRow(DICTCURSOR)) {

						$actId = $actuatorRow['ActuatorID'];
						$actName = $actuatorRow['ActuatorName'];
						$inAuto =  $actuatorRow['IsInAuto'];
						$typeId =  $actuatorRow['ActuatorTypeID'];
						$status = $actuatorRow['CurrentValue'];
						if (in_array($typeId, $digitalTypeIds)) {
							$status = ($status == 0.0 ? 'Off' : 'On');
						}
						$lastUpd =  $actuatorRow['LastUpdated'];
						$updBy = $actuatorRow['UpdatedBy'];
						echo('<div style="line-height: 26px;">'.$actName.': '.$status.' ['.$lastUpd.' '.$updBy.']');

						//Then addin any Impulses for that Actuator

						$impulseQry = 'SELECT "ImpulseID", "ImpulseName", "ImpulseDescription", "CurrentValue"
									FROM "vwImpulseRuleActionActuator"
									WHERE "Actuator" = \''.$actId.'\'
									ORDER BY "ImpulseID"
								   ';

						$impulseResult = $db_object->query($impulseQry);

						if (MDB2::isError($impulseResult)) {
							error_log("Database Error Query: ".$impulseQry." ".$impulseResult->getMessage(), 0);
							die($impulseResult->getMessage());
						}//end db error

						if ($impulseResult->numRows() > 0) {

							while ($impulseRow = $impulseResult->fetchRow(DICTCURSOR)) {

								$impId = $impulseRow['ImpulseID'];
								$impName = $impulseRow['ImpulseName'];
								$impDesc = $impulseRow['ImpulseDescription'];
								$curVal = $impulseRow['CurrentValue'];
								$impInstruction = ($curVal > 0 ? 'Turn Off' : 'Turn On');

								echo('&nbsp;<image title="'.$impDesc.'" onclick="processImpulseClick('.$impId.')" src="images/'.($curVal > 0 ? 'off' : 'on').'.png" style="vertical-align:middle;float:right;margin-right: 12px;" /></div>');

							}//wend

						}//end is some

						echo('<br />');

					}//wend actuator

					echo('<br />');

				echo('</td>');

			}//wend zone

			echo('</tr></table>');

		echo('</div><br />');
	}

?>