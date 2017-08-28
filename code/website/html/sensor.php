<?php

	//Sensor management...
	require_once '../private_html/hub_connect.php';
	require_once 'auth.inc';

	if (isset($_POST['SensorID'])) {

		$cmd = $_POST['Command'];
		$sensorEntryId = $_POST['SensorEntryID'];

		if ($_POST['Command'] == 'Remove') {

			//Process Posted Values as Delete
			
			//First delete sample def if exists
			$delSampleDefQry = "DELETE FROM \"SampleDef\" WHERE \"SensorEntryID\" = $sensorEntryId;";
			$delSampleDefResult = $db_object->query($delSampleDefQry);

			if (MDB2::isError($delSampleDefResult)) {
				error_log("Database Error Query: ".$delSampleDefQry." ".$delSampleDefResult->getMessage(), 0);
				die($delSampleDefResult->getMessage());
			}//end db error

			$delQry = "DELETE FROM \"Sensor\" WHERE \"SensorEntryID\" = $sensorEntryId;";
			$delResult = $db_object->query($delQry);

			if (MDB2::isError($delResult)) {
				error_log("Database Error Query: ".$delQry." ".$delResult->getMessage(), 0);
				die($delResult->getMessage());
			}//end db error

		}//end remove

		else {

			//Common Update, Insert, Add conversions

			$sensorIdRaw = $_POST['SensorID'];
			$sensorId = empty($sensorIdRaw) ? $sensorEntryId : $sensorIdRaw;
			$sensorNameRaw = $_POST['SensorName'];
			$sensorName = empty($sensorNameRaw) ? "NULL" : "'".pg_escape_string($sensorNameRaw)."'";
			$sensorDescRaw = $_POST['SensorDescription'];
			$sensorDesc = empty($sensorDescRaw) ? "NULL" : "'".pg_escape_string($sensorDescRaw)."'";
			$zoneId = $_POST['ZoneID'];
			$measId = $_POST['MeasurandID'];
			$sensorFunctionRaw = $_POST['SensorFunction'];
			$sensorFunction = empty($sensorFunctionRaw) ? "NULL" : "'".pg_escape_string($sensorFunctionRaw)."'";
			$sensorEnabled = isset($_POST['Enabled']) && $_POST['Enabled'] ? 'true':'false';
			$maxDeltaRaw = $_POST['MaxDelta'];
			$maxDelta = empty($maxDeltaRaw) ? 'NULL' : $maxDeltaRaw;
			$highAlertRaw = $_POST['HighAlert'];
			$highAlert = empty($highAlertRaw) ? 'NULL' : $highAlertRaw;
			$lowAlertRaw = $_POST['LowAlert'];
			$lowAlert = empty($lowAlertRaw) ? 'NULL' : $lowAlertRaw;
			$emailRecipientRaw = $_POST['EmailRecipient'];
			$emailRecipient = empty($emailRecipientRaw) ? "NULL" : "'".pg_escape_string($emailRecipientRaw)."'";
			$txtRecipientRaw = $_POST['TextRecipient'];
			$txtRecipient = empty($txtRecipientRaw) ? "NULL" : "'".pg_escape_string($txtRecipientRaw)."'";


			if ($cmd == 'Update') {

				//Process Posted Values as Update

				$updQry = "UPDATE \"Sensor\" SET \"SensorID\" = $sensorId, \"Name\" = $sensorName, \"SensorDescription\" = $sensorDesc,
								\"SensorFunction\" = $sensorFunction,
								\"MeasurandID\" = $measId,
								\"ZoneID\" = $zoneId, \"Enabled\" = $sensorEnabled,
								\"MaxDelta\" = $maxDelta, \"HighAlert\" = $highAlert, \"LowAlert\" = $lowAlert,
								\"EmailRecipient\" = $emailRecipient,
								\"TextRecipient\" = $txtRecipient
								WHERE \"SensorEntryID\" = $sensorEntryId
							;";

				$updResult = $db_object->query($updQry);

				if (MDB2::isError($updResult)) {
					error_log("Database Error Query: ".$updQry." ".$updResult->getMessage(), 0);
					die($updResult->getMessage());
				}//end db error

				//Process Sample Definition
				$sampTypeId = $_POST['SampleTypeID'];

				if ($sampTypeId == 0) {
					//Delete Query
					$sampChangeQry = "DELETE FROM \"SampleDef\" WHERE \"SensorEntryID\" = $sensorEntryId;";
				} else {
					//Insert if missing
					$sampChangeQry = "INSERT INTO \"SampleDef\" (\"SensorEntryID\", \"SampleTypeID\")
										SELECT $sensorEntryId, $sampTypeId
										WHERE
											NOT EXISTS (
												SELECT \"SampleDefID\"
												FROM \"SampleDef\"
												WHERE \"SensorEntryID\" = $sensorEntryId AND \"SampleTypeID\" = $sampTypeId
											);
										";
				}

				$sampChangeResult = $db_object->query($sampChangeQry);

				if (MDB2::isError($sampChangeResult)) {
					error_log("Database Error Query: ".$sampChangeQry." ".$sampChangeResult->getMessage(), 0);
					die($sampChangeResult->getMessage());
				}//end db error

			}//end update

			else if ($cmd == 'Duplicate' || $cmd == 'Add') {

				//Process Posted Values as Insert

				$sensorId = $sensorId.($cmd == 'Duplicate' ? '99':'');
				$sensorName = "'".(empty($sensorNameRaw) ? 'Sensor'.$sensorId : pg_escape_string($sensorNameRaw))."'";

				$insQry = "INSERT INTO \"Sensor\" (\"SensorID\", \"Name\",
										\"SensorDescription\", \"MeasurandID\",
										\"ZoneID\", \"SensorFunction\",
            							\"Enabled\", \"MaxDelta\", \"HighAlert\", \"LowAlert\",
            							\"EmailRecipient\", \"TextRecipient\")
    								VALUES ($sensorId, $sensorName, $sensorDesc, $measId,
    										$zoneId, $sensorFunction, $sensorEnabled, $maxDelta, $highAlert, $lowAlert,
    										$emailRecipient, $txtRecipient);
						  ";

				$insResult = $db_object->query($insQry);

				if (MDB2::isError($insResult)) {
					error_log("Database Error Query: ".$insQry." ".$insResult->getMessage(), 0);
					die($insResult->getMessage());
				}//end db error

				//Cannot mirror the Sample Definition because we don't yet know the sensor entry id.

			}//end duplicate, add

		}//end update, insert, add


		echo('<script type="text/javascript">');
		echo('setTimeout(function(){');
		echo('var newUrl = "home.php?page=Organisation&tab='.$_GET['tab'].'";');
		echo('document.location.href = newUrl;');
		echo('}, 0);');
		echo('</script>');

	} else {

		//Populate Form for editing...

		if (isset($_GET['sensor'])) {
			$sensorIdParam = $_GET['sensor'];
		} else {
			$sensorIdParam = -1;
		}

		//run queries to get Measurand Types, Sample Types and Zone list data

		$measTypeQry = "SELECT \"MeasurandID\", \"MeasurandName\"
						FROM \"Measurand\"
						ORDER BY 1
					;";

		$measTypeResult = $db_object->query($measTypeQry);

		if (MDB2::isError($measTypeResult)) {
			error_log("Database Error Query: ".$measTypeQry." ".$measTypeResult->getMessage(), 0);
			die($typeResult->getMessage());
		}//end db error

		$sampTypeQry = "SELECT \"SampleTypeID\", \"SampleTypeName\"
						FROM \"SampleType\"
						ORDER BY 1
					;";

		$sampTypeResult = $db_object->query($sampTypeQry);

		if (MDB2::isError($sampTypeResult)) {
			error_log("Database Error Query: ".$sampTypeQry." ".$sampTypeResult->getMessage(), 0);
			die($typeResult->getMessage());
		}//end db error

		$zoneQry = "SELECT \"ZoneID\", \"ZoneName\"
						FROM \"Zone\"
						ORDER BY 1
					;";

		$zoneResult = $db_object->query($zoneQry);

		if (MDB2::isError($zoneResult)) {
			error_log("Database Error Query: ".$zoneQry." ".$zoneResult->getMessage(), 0);
			die($zoneResult->getMessage());
		}//end db error

		$rowCount = 0;//assume not found
		$isNewRecord = false;

		if ($sensorIdParam > 0) {

			//then the main query...

			$sensorQry = "SELECT s.\"SensorEntryID\", s.\"SensorID\", \"Name\", \"SensorDescription\", \"SensorFunction\", \"Enabled\",
									\"MeasurandID\", \"ZoneID\", \"CurrentValue\", to_char(\"LastUpdated\", 'DD/MM/YYYY HH24:MI') AS \"LastUpdated\",
									\"MaxDelta\", \"HighAlert\", \"LowAlert\", \"EmailRecipient\", \"TextRecipient\", \"SampleTypeID\"
							FROM \"Sensor\" s
							LEFT JOIN \"SampleDef\" d ON s.\"SensorEntryID\" = d.\"SensorEntryID\"
							WHERE s.\"SensorID\" = $sensorIdParam
						;";

			$sensorResult = $db_object->query($sensorQry);

			if (MDB2::isError($sensorResult)) {
				error_log("Database Error Query: ".$sensorQry." ".$sensorResult->getMessage(), 0);
				die($sensorResult->getMessage());
			}//end db error

			$rowCount = $sensorResult->numRows();

			if ($rowCount == 1) {
				$sensorRow = $sensorResult->fetchRow(DICTCURSOR);
			} else {
				echo('<p style="text-align:center">Sensor not found.</p>');
			}
		}

		if ($rowCount == 0 || $sensorIdParam <= 0) {

			//initialise values for new sensor
			$isNewRecord = true;

			$nextIdQry = "SELECT max(\"SensorEntryID\") FROM \"Sensor\";";
			$nextIdResult = $db_object->query($nextIdQry);
			if (MDB2::isError($nextIdResult)) {
				error_log("Database Error Query: ".$nextIdQry." ".$nextIdResult->getMessage(), 0);
				die($nextIdResult->getMessage());
			}//end db error
			$nextId = $nextIdResult->fetchRow()[0] + 1;

			$sensorRow = [
				'SensorEntryID' => $nextId,
				'SensorID' => $nextId,
				'Name' => 'Sensor '.$nextId,
				'SensorDescription' => NULL,
				'MeasurandID' => 0,
				'ZoneID' => 0,
				'SensorFunction' => NULL,
				'CurrentValue' => NULL,
				'LastUpdated' => NULL,
				'Enabled' => false,
				'MaxDelta' => 0,
				'HighAlert' => 1,
				'LowAlert' => NULL,
				'EmailRecipient' => false,
				'TextRecipient' => NULL,
				'MaxDelta' => 100,
				'HighAlert' => NULL,
				'LowAlert' => NULL,
				'SampleTypeID' => 0
			];
		}

		$sensorEntryId = $sensorRow['SensorEntryID'];
		$sensorId = $sensorRow['SensorID'];
		$sensorName = $sensorRow['Name'];
		$sensorDesc = $sensorRow['SensorDescription'];
		$measId = $sensorRow['MeasurandID'];
		$zoneId = $sensorRow['ZoneID'];
		$sensorFunction = $sensorRow['SensorFunction'];
		$curVal = $sensorRow['CurrentValue'];
		$lastUpdated = $sensorRow['LastUpdated'];
		$sensorEnabled = $sensorRow['Enabled'] == 't' ? 'checked="checked"' : '';
		$maxDelta = $sensorRow['MaxDelta'];
		$highAlert = $sensorRow['HighAlert'];
		$lowAlert = $sensorRow['LowAlert'];
		$emailRecipient = $sensorRow['EmailRecipient'];
		$txtRecipient = $sensorRow['TextRecipient'];
		$sampTypeId = $sensorRow['SampleTypeID'] == null ? 0 : $sensorRow['SampleTypeID'];

		echo('<form action="sensor.php?tab='.$_GET['tab'].'" method="post">');

			echo('<input name="SensorEntryID" type="hidden" value="'.$sensorEntryId.'" />');
			echo('<div class="EditorFormLabel">Sensor Number:</div><div class="EditorFormValue">S<input name="SensorID" type="number" min="1" value="'.$sensorId.'" /></div>');
			echo('<div class="EditorFormLabel">Sensor Name:</div><div class="EditorFormValue"><input name="SensorName" type="text" value="'.$sensorName.'" /></div>');
			echo('<div class="EditorFormLabel">Sensor Description:</div><div class="EditorFormValue"><input name="SensorDescription" type="text" value="'.$sensorDesc.'" /></div>');
			echo('<div class="EditorFormLabel">Sensor Function:</div><div class="EditorFormValue"><input name="SensorFunction" type="text" value="'.$sensorFunction.'" /></div>');

			//Measurands
			echo('<div class="EditorFormLabel">Measurand:</div><div class="EditorFormValue"><select name="MeasurandID" >');
			while($typeRow = $measTypeResult->fetchRow(DICTCURSOR)) {
				$typeVal = $typeRow['MeasurandID'];
				$typeName = $typeRow['MeasurandName'];
				echo('<option value='.$typeVal.' '.($measId == $typeVal?"selected":"").'>'.$typeName.'</option>');
			}
			echo('</select></div>');

			//Zones
			echo('<div class="EditorFormLabel">Zone:</div><div class="EditorFormValue"><select name="ZoneID">');
			while($zoneRow = $zoneResult->fetchRow(DICTCURSOR)) {
				$zoneVal = $zoneRow['ZoneID'];
				$zoneName = $zoneRow['ZoneName'];
				echo('<option value='.$zoneVal.' '.($zoneId == $zoneVal?"selected":"").'>'.$zoneName.'</option>');
			}
			echo('</select></div>');

			echo('<div class="EditorFormLabel">Current Value:</div><div class="ReadOnlyEditorFormValue"><input type="text" readonly="readonly" value="'.$curVal.'" /></div>');
			echo('<div class="EditorFormLabel">Last Updated:</div><div class="ReadOnlyEditorFormValue"><input type="text" readonly="readonly" value="'.$lastUpdated.'" /></div>');

			echo('<div class="EditorFormLabel">Max Delta:</div><div class="EditorFormValue"><input name="MaxDelta" type="text" value="'.$maxDelta.'" /></div>');
			echo('<div class="EditorFormLabel">High Alert:</div><div class="EditorFormValue"><input name="HighAlert" type="text" value="'.$highAlert.'" /></div>');
			echo('<div class="EditorFormLabel">Low Alert:</div><div class="EditorFormValue"><input name="LowAlert" type="text" value="'.$lowAlert.'" /></div>');

			echo('<div class="EditorFormLabel">Email Recipient:</div><div class="EditorFormValue"><input name="EmailRecipient" type="text" value="'.$emailRecipient.'" /></div>');
			echo('<div class="EditorFormLabel">Txt Recipient:</div><div class="EditorFormValue"><input name="TextRecipient" type="text" value="'.$txtRecipient.'" /></div>');

			//Sample Definition
			echo('<div class="EditorFormLabel">Sample Type:</div><div class="EditorFormValue"><select name="SampleTypeID">');
			echo('<option value="0" '.($sampTypeId == 0?"selected":"").'>Not Sampled</option>');
			while($sampTypeRow = $sampTypeResult->fetchRow(DICTCURSOR)) {
				$sampTypeVal = $sampTypeRow['SampleTypeID'];
				$sampTypeName = $sampTypeRow['SampleTypeName'];
				echo('<option value='.$sampTypeVal.' '.($sampTypeId == $sampTypeVal?"selected":"").'>'.$sampTypeName.'</option>');
			}
			echo('</select></div>');

			echo('<div class="EditorFormLabel">Enabled:</div><div class="EditorFormValue"><input name="Enabled" type="checkbox" '.$sensorEnabled.' /></div>');

			echo('<div class="EditorButtonRow">');
				echo('<input name="Command" type="Submit" value="Cancel">');
				if ($isNewRecord) {
					echo('<input name="Command" type="Submit" value="Add">');
				} else {
					echo('<input name="Command" type="Submit" value="Update">');
					echo('<input name="Command" type="Submit" value="Duplicate">');
					echo('<input name="Command" type="Submit" value="Remove" onclick="return confirm(\'Are you sure you want to remove this element?\')">');
				}
			echo('</div>');

		echo('</form>');

	}//end GET

?>