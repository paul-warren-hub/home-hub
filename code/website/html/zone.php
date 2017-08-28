<?php

	//Zone management...
	require_once '../private_html/hub_connect.php';
	require_once 'auth.inc';

	if (isset($_POST['ZoneID'])) {

		$cmd = $_POST['Command'];
		$zoneId = preg_replace('/\D/', '', $_POST['ZoneID']);

		if ($cmd == 'Remove') {

			//Process Posted Values as Delete

			$delQry = "DELETE FROM \"Zone\" WHERE \"ZoneID\" = $zoneId;";
			$delResult = $db_object->query($delQry);

			if (MDB2::isError($delResult)) {
				error_log("Database Error Query: ".$delQry." ".$delResult->getMessage(), 0);
				die($delResult->getMessage());
			}//end db error

		}//end remove

		else {

			//Common Update, Insert, Add conversions

			$zoneNameRaw = $_POST['ZoneName'];
			$zoneName = "'".(empty($zoneNameRaw) ? "Zone$zoneId" : pg_escape_string($zoneNameRaw))."'";
			$zoneXRaw = $_POST['ZoneX'];
			$zoneX = empty($zoneXRaw) ? 0 : pg_escape_string($zoneXRaw);
			$zoneYRaw = $_POST['ZoneY'];
			$zoneY = empty($zoneYRaw) ? 0 : pg_escape_string($zoneYRaw);
			$zoneZRaw = $_POST['ZoneZ'];
			$zoneZ = empty($zoneYRaw) ? 1 : pg_escape_string($zoneZRaw);
			$zoneRowspanRaw = $_POST['ZoneRowspan'];
			$zoneRowspan = empty($zoneRowspanRaw) ? 1 : pg_escape_string($zoneRowspanRaw);
			$zoneColspanRaw = $_POST['ZoneColspan'];
			$zoneColspan = empty($zoneColspanRaw) ? 1 : pg_escape_string($zoneColspanRaw);

			if ($cmd == 'Update') {

				//Process Posted Values as Update

				$updQry = "UPDATE \"Zone\" SET \"ZoneName\" = $zoneName,
								\"ZoneX\" = $zoneX,
								\"ZoneY\" = $zoneY,
								\"ZoneZ\" = $zoneZ,
								\"ZoneRowspan\" = $zoneRowspan,
								\"ZoneColspan\" = $zoneColspan
								WHERE \"ZoneID\" = $zoneId;
						   ";

				$updResult = $db_object->query($updQry);

				if (MDB2::isError($updResult)) {
					error_log("Database Error Query: ".$updQry." ".$updResult->getMessage(), 0);
					die($updResult->getMessage());
				}//end db error

			}//end update

			else if ($cmd == 'Duplicate' || $cmd == 'Add') {

				//Process Posted Values as Insert

				$insQry = "INSERT INTO \"Zone\" (\"ZoneName\", \"ZoneX\", \"ZoneY\",
											\"ZoneZ\", \"ZoneRowspan\",\"ZoneColspan\")
										VALUES ($zoneName,
												$zoneX,
												$zoneY,
												$zoneZ,
												$zoneRowspan,
												$zoneColspan
										);
						  ";

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

		if (isset($_GET['zone'])) {
			$zoneIdParam = $_GET['zone'];
		} else {
			$zoneIdParam = -1;
		}

		$rowCount = 0;//assume not found
		$isNewRecord = false;

		if ($zoneIdParam > 0) {

			//run main query...

			$zonesQry = "SELECT \"ZoneID\", \"ZoneName\", \"ZoneX\", \"ZoneY\", \"ZoneZ\", \"ZoneRowspan\", \"ZoneColspan\"
						 FROM \"Zone\"
						 WHERE \"ZoneID\" = $zoneIdParam
						 ;";

			$zonesResult = $db_object->query($zonesQry);

			if (MDB2::isError($zonesResult)) {
				error_log("Database Error Query: ".$zonesQry." ".$zonesResult->getMessage(), 0);
				die($zonesResult->getMessage());
			}//end db error

			$rowCount = $zonesResult->numRows();

			if ($rowCount == 1) {
				$zonesRow = $zonesResult->fetchRow(DICTCURSOR);
			} else {
				echo('<p style="text-align:center">Zone not found.</p>');
			}
		}

		if ($rowCount == 0 || $zoneIdParam <= 0) {

			//initialise values for new zone
			$isNewRecord = true;

			$nextIdQry = "SELECT max(\"ZoneID\") FROM \"Zone\";";
			$nextIdResult = $db_object->query($nextIdQry);
			if (MDB2::isError($nextIdResult)) {
				error_log("Database Error Query: ".$nextIdQry." ".$nextIdResult->getMessage(), 0);
				die($nextIdResult->getMessage());
			}//end db error
			$nextId = $nextIdResult->fetchRow()[0] + 1;

			$zonesRow = [
			    'ZoneID' => $nextId,
			    'ZoneName' => 'Zone '.$nextId,
			    'ZoneX' => 0,
			    'ZoneY' => 0,
			    'ZoneZ' => 1,
			    'ZoneRowspan' => 1,
			    'ZoneColspan' => 1
			];
		}

		$zoneId = $zonesRow['ZoneID'];
		$name = $zonesRow['ZoneName'];
		$zoneX = $zonesRow['ZoneX'];
		$zoneY = $zonesRow['ZoneY'];
		$zoneZ = $zonesRow['ZoneZ'];
		$zoneRowspan = $zonesRow['ZoneRowspan'];
		$zoneColspan = $zonesRow['ZoneColspan'];

		echo('<form action="zone.php?tab='.$_GET['tab'].'" method="post">');

			echo('<div class="EditorFormLabel">Zone ID:</div><div class="ReadOnlyEditorFormValue"><input name="ZoneID" type="text" readonly="readonly" value="Z'.($zoneId>0?$zoneId:'').'" /></div>');
			echo('<div class="EditorFormLabel">Zonename:</div><div class="EditorFormValue"><input name="ZoneName" type="text" value="'.$name.'" /></div>');
			echo('<div class="EditorFormLabel">Zone X:</div><div class="EditorFormValue"><input name="ZoneX" type="number" min="0" value="'.$zoneX.'" /></div>');
			echo('<div class="EditorFormLabel">Zone Y:</div><div class="EditorFormValue"><input name="ZoneY" type="number" min="0" value="'.$zoneY.'" /></div>');
			echo('<div class="EditorFormLabel">Zone Z:</div><div class="EditorFormValue"><input name="ZoneZ" type="number" min="1" value="'.$zoneZ.'" /></div>');
			echo('<div class="EditorFormLabel">Zone Rowspan:</div><div class="EditorFormValue"><input name="ZoneRowspan" min="1" type="number" value="'.$zoneRowspan.'" /></div>');
			echo('<div class="EditorFormLabel">Zone Colspan:</div><div class="EditorFormValue"><input name="ZoneColspan" min="1" type="number" value="'.$zoneColspan.'" /></div>');

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