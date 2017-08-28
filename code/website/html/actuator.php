<?php

	//Actuator management...
	require_once '../private_html/hub_connect.php';
	require_once 'auth.inc';

	if (isset($_POST['ActuatorID'])) {

		$cmd = $_POST['Command'];
		$actuatorEntryId = $_POST['ActuatorEntryID'];

		if ($cmd == 'Remove') {

			//Process Posted Values as Delete

			$delQry = "DELETE FROM \"Actuator\" WHERE \"ActuatorEntryID\" = $actuatorEntryId;";
			$delResult = $db_object->query($delQry);

			if (MDB2::isError($delResult)) {
				error_log("Database Error Query: ".$delQry." ".$delResult->getMessage(), 0);
				die($delResult->getMessage());
			}//end db error

		}//end remove

		else {

			//Common Update, Insert, Add conversions

			$actuatorIdRaw = $_POST['ActuatorID'];
			$actuatorId = empty($actuatorIdRaw) ? $actuatorEntryId : $actuatorIdRaw;
			$actuatorNameRaw = $_POST['ActuatorName'];
			$actuatorName = empty($actuatorNameRaw) ? "NULL" : "'".pg_escape_string($actuatorNameRaw)."'";
			$actuatorDescRaw = $_POST['ActuatorDescription'];
			$actuatorDesc = empty($actuatorDescRaw) ? "NULL" : "'".pg_escape_string($actuatorDescRaw)."'";
			$actuatorTypeId = $_POST['ActuatorTypeID'];
			$zoneId = $_POST['ZoneID'];
			$actuatorFunctionRaw = $_POST['ActuatorFunction'];
			$actuatorFunction = empty($actuatorFunctionRaw) ? "NULL" : "'".pg_escape_string($actuatorFunctionRaw)."'";
			$actuatorEnabled = isset($_POST['Enabled']) && $_POST['Enabled'] ? 'true':'false';
			$actuatorWeb = isset($_POST['WebPresence']) && $_POST['WebPresence'] ? 'true':'false';
			$inAuto = isset($_POST['IsInAuto']) && $_POST['IsInAuto'] == 'Y' ? 'Y':'N';//Y, N

			if ($cmd == 'Update') {

				//Process Posted Values

				$updQry = "UPDATE \"Actuator\" SET \"ActuatorID\" = $actuatorId, \"ActuatorName\" = $actuatorName,
								\"ActuatorDescription\" = $actuatorDesc,
								\"ActuatorFunction\" = $actuatorFunction,
								\"ActuatorTypeID\" = $actuatorTypeId,
								\"ZoneID\" = $zoneId, \"Enabled\" = $actuatorEnabled,
								\"WebPresence\" = $actuatorWeb, \"IsInAuto\" = '$inAuto'
								WHERE \"ActuatorEntryID\" = $actuatorEntryId
							;";

				$updResult = $db_object->query($updQry);

				if (MDB2::isError($updResult)) {
					error_log("Database Error Query: ".$updQry." ".$updResult->getMessage(), 0);
					die($updResult->getMessage());
				}//end db error

			}//end update

			else if ($cmd == 'Duplicate' || $cmd == 'Add') {

				//Process Posted Values as Insert

				$actuatorId = (empty($actuatorIdRaw) ? $actuatorEntryId : $actuatorIdRaw).($cmd == 'Duplicate'?'99':'');
				$actuatorName = "'".(empty($actuatorNameRaw) ? 'Actuator'.$actuatorId : pg_escape_string($actuatorNameRaw))."'";

				$insQry = "INSERT INTO \"Actuator\" (\"ActuatorID\", \"ActuatorName\", \"ActuatorDescription\", \"ActuatorTypeID\",
											\"ZoneID\", \"ActuatorFunction\",
											\"Enabled\", \"WebPresence\", \"IsInAuto\")
										VALUES ($actuatorId, $actuatorName, $actuatorDesc, $actuatorTypeId,
												$zoneId, $actuatorFunction,	$actuatorEnabled,
												$actuatorWeb, '$inAuto')
							;";

				$insResult = $db_object->query($insQry);

				if (MDB2::isError($insResult)) {
					error_log("Database Error Query: ".$insQry." ".$insResult->getMessage(), 0);
					die($insQry.' - '.$insResult->getMessage());
				}//end db error

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

		if (isset($_GET['actuator'])) {
			$actuatorIdParam = $_GET['actuator'];
		} else {
			$actuatorIdParam = -1;
		}

		//run queries to get Actuator Types and Zone list data

		$typeQry = "SELECT \"ActuatorTypeID\", \"ActuatorTypeName\"
						FROM \"ActuatorType\"
						ORDER BY 1
					;";

		$typeResult = $db_object->query($typeQry);

		if (MDB2::isError($typeResult)) {
			error_log("Database Error Query: ".$typeQry." ".$typeResult->getMessage(), 0);
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

		if ($actuatorIdParam > 0) {

			//then the main query...

			$actuatorQry = "SELECT \"ActuatorEntryID\", \"ActuatorID\", \"ActuatorName\", \"ActuatorDescription\", \"ActuatorFunction\", \"Enabled\",
									\"ActuatorTypeID\", \"ZoneID\", \"CurrentValue\", to_char(\"LastUpdated\", 'DD/MM/YYYY HH24:MI') AS \"LastUpdated\", \"UpdatedBy\",
									\"WebPresence\", \"IsInAuto\"
							FROM \"Actuator\"
							WHERE \"ActuatorID\" = $actuatorIdParam
						;";

			$actuatorResult = $db_object->query($actuatorQry);

			if (MDB2::isError($actuatorResult)) {
				error_log("Database Error Query: ".$actuatorQry." ".$actuatorResult->getMessage(), 0);
				die($actuatorResult->getMessage());
			}//end db error

			$rowCount = $actuatorResult->numRows();

			if ($rowCount == 1) {
				$actuatorRow = $actuatorResult->fetchRow(DICTCURSOR);
			} else {
				echo('<p style="text-align:center">Actuator not found.</p>');
			}
		}

		if ($rowCount == 0 || $actuatorIdParam <= 0) {

			//initialise values for new actuator
			$isNewRecord = true;

			$nextIdQry = "SELECT max(\"ActuatorEntryID\") FROM \"Actuator\";";
			$nextIdResult = $db_object->query($nextIdQry);
			if (MDB2::isError($nextIdResult)) {
				error_log("Database Error Query: ".$nextIdQry." ".$nextIdResult->getMessage(), 0);
				die($nextIdResult->getMessage());
			}//end db error
			$nextId = $nextIdResult->fetchRow()[0] + 1;

			$actuatorRow = [
				'ActuatorEntryID' => $nextId,
				'ActuatorID' => $nextId,
				'ActuatorName' => 'Actuator '.$nextId,
				'ActuatorDescription' => NULL,
				'ActuatorTypeID' => 1,
				'ZoneID' => 0,
				'ActuatorFunction' => NULL,
				'CurrentValue' => NULL,
				'LastUpdated' => NULL,
				'UpdatedBy' => NULL,
				'Enabled' => false,
				'WebPresence' => false,
				'UpdatedBy' => NULL,
				'IsInAuto' => false
			];
		}

		$actuatorEntryId = $actuatorRow['ActuatorEntryID'];
		$actuatorId = $actuatorRow['ActuatorID'];
		$actuatorName = $actuatorRow['ActuatorName'];
		$actuatorDesc = $actuatorRow['ActuatorDescription'];
		$actuatorTypeId = $actuatorRow['ActuatorTypeID'];
		$zoneId = $actuatorRow['ZoneID'];
		$actuatorFunction = $actuatorRow['ActuatorFunction'];
		$curVal = $actuatorRow['CurrentValue'];
		$lastUpdated = $actuatorRow['LastUpdated'];
		$updatedBy = $actuatorRow['UpdatedBy'];
		$actuatorEnabled = $actuatorRow['Enabled'] == 't' ? 'checked="checked"' : '';
		$actuatorWeb = $actuatorRow['WebPresence'] == 't' ? 'checked="checked"' : '';
		$inAuto = $actuatorRow['IsInAuto'] == 'Y' ? 'checked="checked"' : '';//Y, N

		echo('<form action="actuator.php?tab='.$_GET['tab'].'" method="post">');

			echo('<input name="ActuatorEntryID" type="hidden" value="'.$actuatorEntryId.'" />');
			echo('<div class="EditorFormLabel">Actuator Number:</div><div class="EditorFormValue">U<input name="ActuatorID" type="number" min="1" value="'.$actuatorId.'" /></div>');
			echo('<div class="EditorFormLabel">Actuator Name:</div><div class="EditorFormValue"><input name="ActuatorName" type="text" value="'.$actuatorName.'" /></div>');
			echo('<div class="EditorFormLabel">Actuator Description:</div><div class="EditorFormValue"><input name="ActuatorDescription" type="text" value="'.$actuatorDesc.'" /></div>');
			echo('<div class="EditorFormLabel">Actuator Function:</div><div class="EditorFormValue"><input name="ActuatorFunction" type="text" value="'.$actuatorFunction.'" /></div>');

			//Types
			echo('<div class="EditorFormLabel">Type:</div><div class="EditorFormValue"><select name="ActuatorTypeID" >');
			while($typeRow = $typeResult->fetchRow(DICTCURSOR)) {
				$typeVal = $typeRow['ActuatorTypeID'];
				$typeName = $typeRow['ActuatorTypeName'];
				echo('<option value='.$typeVal.' '.($actuatorTypeId == $typeVal?"selected":"").'>'.$typeName.'</option>');
			}//wend
			echo('</select></div>');

			//Zones
			echo('<div class="EditorFormLabel">Zone:</div><div class="EditorFormValue"><select name="ZoneID">');
			while($zoneRow = $zoneResult->fetchRow(DICTCURSOR)) {
				$zoneVal = $zoneRow['ZoneID'];
				$zoneName = $zoneRow['ZoneName'];
				echo('<option value='.$zoneVal.' '.($zoneId == $zoneVal?"selected":"").'>'.$zoneName.'</option>');
			}//wend
			echo('</select></div>');

			echo('<div class="EditorFormLabel">Current Value:</div><div class="ReadOnlyEditorFormValue"><input type="text" readonly="readonly" value="'.$curVal.'" /></div>');
			echo('<div class="EditorFormLabel">Last Updated:</div><div class="ReadOnlyEditorFormValue"><input type="text" readonly="readonly" value="'.$lastUpdated.'" /></div>');
			echo('<div class="EditorFormLabel">Updated By:</div><div class="ReadOnlyEditorFormValue"><input type="text" readonly="readonly" value="'.$updatedBy.'" /></div>');

			echo('<div class="EditorFormLabel">In Auto:</div><div class="EditorFormValue"><input name="IsInAuto" value="Y" type="checkbox" '.$inAuto.' /></div>');
			echo('<div class="EditorFormLabel">Web Presence:</div><div class="EditorFormValue"><input name="WebPresence" type="checkbox" '.$actuatorWeb.' /></div>');
			echo('<div class="EditorFormLabel">Enabled:</div><div class="EditorFormValue"><input name="Enabled" type="checkbox" '.$actuatorEnabled.' /></div>');

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