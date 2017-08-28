<?php

	//Impulse management...
	require_once '../private_html/hub_connect.php';
	require_once 'auth.inc';

	if (isset($_POST['ImpulseID'])) {

		$cmd = $_POST['Command'];
		$impulseId = preg_replace('/\D/', '', $_POST['ImpulseID']);

		if ($cmd == 'Remove') {

			//Process Posted Values as Delete

			$delQry = "DELETE FROM \"Impulse\" WHERE \"ImpulseID\" = $impulseId;";

			$delResult = $db_object->query($delQry);

			if (MDB2::isError($delResult)) {
				error_log("Database Error Query: ".$delQry." ".$delResult->getMessage(), 0);
				die($delResult->getMessage());
			}//end db error

		}//end remove

		else {

			//Common Update, Insert, Add conversions
			$impulseNameRaw = $_POST['ImpulseName'];
			$impulseName = "'".(empty($impulseNameRaw) ? "Impulse$impulseId" : pg_escape_string($impulseNameRaw))."'";
			$impulseDescRaw = $_POST['ImpulseDescription'];
			$impulseDesc = empty($impulseDescRaw) ? "NULL" : "'".pg_escape_string($impulseDescRaw)."'";
			$bcmPinRaw = $_POST['BCMPinNumber'];
			$bcmPin = empty($bcmPinRaw) ? 0 : $bcmPinRaw;
			$macAddressRaw = $_POST['MacAddress'];
			$macAddress = empty($macAddressRaw) ? "NULL" : "'".pg_escape_string($macAddressRaw)."'";
			$zoneId = $_POST['ZoneID'];
			$impulseWeb = isset($_POST['WebPresence']) && $_POST['WebPresence'] ? 'true':'false';


			if ($cmd == 'Update') {

				//Process Posted Values as Update

				$updQry = "UPDATE \"Impulse\" SET \"ImpulseName\" = $impulseName, \"ImpulseDescription\" = $impulseDesc,
								\"BCMPinNumber\" = $bcmPin, \"MacAddress\" = $macAddress,
								\"ZoneID\" = $zoneId, \"WebPresence\" = $impulseWeb
								WHERE \"ImpulseID\" = $impulseId
							;";

				$updResult = $db_object->query($updQry);

				if (MDB2::isError($updResult)) {
					error_log("Database Error Query: ".$updQry." ".$updResult->getMessage(), 0);
					die($updResult->getMessage());
				}//end db error

			}//end update

			else if ($cmd == 'Duplicate' || $cmd == 'Add') {

				//Process Posted Values as Insert

				$insQry = "INSERT INTO \"Impulse\" (\"ImpulseName\", \"ImpulseDescription\", \"BCMPinNumber\",
											\"MacAddress\", \"ZoneID\", \"WebPresence\")
										VALUES ($impulseName, $impulseDesc, $bcmPin,
												$macAddress, $zoneId, $impulseWeb)
							;";

				$insResult = $db_object->query($insQry);

				if (MDB2::isError($insResult)) {
					error_log("Database Error Query: ".$insQry." ".$insResult->getMessage(), 0);
					die($insResult->getMessage());
				}//end db error

			}//end duplicate

		}//end update, insert, add

		echo('<script type="text/javascript">');
		echo('setTimeout(function(){');
		echo('var newUrl = "home.php?page=Organisation&tab='.$_GET['tab'].'";');
		echo('document.location.href = newUrl;');
		echo('}, 0);');
		echo('</script>');

	} else {

		//Populate Form for editing...

		if (isset($_GET['impulse'])) {
			$impulseIdParam = $_GET['impulse'];
		} else {
			$impulseIdParam = -1;
		}

		$rowCount = 0;//assume not found
		$isNewRecord = false;

		//run queries to get Zone list data

		$zoneQry = "SELECT \"ZoneID\", \"ZoneName\"
						FROM \"Zone\"
						ORDER BY 1
					;";

		$zoneResult = $db_object->query($zoneQry);

		if (MDB2::isError($zoneResult)) {
			error_log("Database Error Query: ".$zoneQry." ".$zoneResult->getMessage(), 0);
			die($zoneResult->getMessage());
		}//end db error

		if ($impulseIdParam > 0) {

			//then the main query...

			$impulseQry = "SELECT \"ImpulseID\", \"ImpulseName\", \"ImpulseDescription\",
									\"BCMPinNumber\", \"MacAddress\", \"ZoneID\", to_char(\"LastUpdated\", 'DD/MM/YYYY HH24:MI') AS \"LastUpdated\",
									\"WebPresence\"
							FROM \"Impulse\"
							WHERE \"ImpulseID\" = $impulseIdParam
						;";

			$impulseResult = $db_object->query($impulseQry);

			if (MDB2::isError($impulseResult)) {
				error_log("Database Error Query: ".$impulseQry." ".$impulseResult->getMessage(), 0);
				die($impulseResult->getMessage());
			}//end db error

			$rowCount = $impulseResult->numRows();

			if ($rowCount == 1) {
				$impulseRow = $impulseResult->fetchRow(DICTCURSOR);
			} else {
				echo('<p style="text-align:center">Impulse not found.</p>');
			}
		}

		if ($rowCount == 0 || $impulseIdParam <= 0) {

			//initialise values for new impulse
			$isNewRecord = true;

			$nextIdQry = "SELECT max(\"ImpulseID\") FROM \"Impulse\";";
			$nextIdResult = $db_object->query($nextIdQry);
			if (MDB2::isError($nextIdResult)) {
				error_log("Database Error Query: ".$nextIdQry." ".$nextIdResult->getMessage(), 0);
				die($nextIdResult->getMessage());
			}//end db error
			$nextId = $nextIdResult->fetchRow()[0] + 1;

			$impulseRow = [
				'ImpulseID' => $nextId,
				'ImpulseName' => 'Impulse '.$nextId,
				'ImpulseDescription' => NULL,
				'BCMPinNumber' => 0,
				'MacAddress' => NULL,
				'ZoneID' => 0,
				'LastUpdated' => NULL,
				'WebPresence' => false
			];
		}

		$impulseId = $impulseRow['ImpulseID'];
		$impulseName = $impulseRow['ImpulseName'];
		$impulseDesc = $impulseRow['ImpulseDescription'];
		$bcmPin = $impulseRow['BCMPinNumber'];
		$macAddress = $impulseRow['MacAddress'];
		$zoneId = $impulseRow['ZoneID'];
		$lastUpdated = $impulseRow['LastUpdated'];
		$impulseWeb = $impulseRow['WebPresence'] == 't' ? 'checked="checked"' : '';

		echo('<form action="impulse.php?tab='.$_GET['tab'].'" method="post">');

			echo('<div class="EditorFormLabel">Impulse Number:</div><div class="ReadOnlyEditorFormValue"><input name="ImpulseID" type="text" readonly="readonly" value="'.($impulseId>0?'I'.$impulseId:'').'" /></div>');
			echo('<div class="EditorFormLabel">Impulse Name:</div><div class="EditorFormValue"><input name="ImpulseName" type="text" value="'.$impulseName.'" /></div>');
			echo('<div class="EditorFormLabel">Impulse Description:</div><div class="EditorFormValue"><input name="ImpulseDescription" type="text" value="'.$impulseDesc.'" /></div>');
			echo('<div class="EditorFormLabel">BCM Pin:</div><div class="EditorFormValue"><input name="BCMPinNumber" type="number" min="0" value="'.$bcmPin.'" /></div>');
			echo('<div class="EditorFormLabel">MAC Address:</div><div class="EditorFormValue"><input name="MacAddress" type="text" value="'.$macAddress.'" /></div>');

			//Zones
			echo('<div class="EditorFormLabel">Zone:</div><div class="EditorFormValue"><select name="ZoneID">');
			while($zoneRow = $zoneResult->fetchRow(DICTCURSOR)) {
				$zoneVal = $zoneRow['ZoneID'];
				$zoneName = $zoneRow['ZoneName'];
				echo('<option value='.$zoneVal.' '.($zoneId == $zoneVal?"selected":"").'>'.$zoneName.'</option>');
			}
			echo('</select></div>');

			echo('<div class="EditorFormLabel">Last Updated:</div><div class="ReadOnlyEditorFormValue"><input type="text" readonly="readonly" value="'.$lastUpdated.'" /></div>');
			echo('<div class="EditorFormLabel">Web Presence:</div><div class="EditorFormValue"><input name="WebPresence" type="checkbox" '.$impulseWeb.' /></div>');

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