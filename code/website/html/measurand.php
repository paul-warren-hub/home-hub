<?php

	//Measurand management...
	require_once '../private_html/hub_connect.php';
	require_once 'auth.inc';

	if (isset($_POST['MeasurandID'])) {

		$cmd = $_POST['Command'];
		$measurandId = preg_replace('/\D/', '', $_POST['MeasurandID']);

		if ($cmd == 'Remove') {

			//Process Posted Values as Delete

			$delQry = "DELETE FROM \"Measurand\" WHERE \"MeasurandID\" = $measurandId;";
			$delResult = $db_object->query($delQry);

			if (MDB2::isError($delResult)) {
				error_log("Database Error Query: ".$delQry." ".$delResult->getMessage(), 0);
				print $delResult->getMessage();
			}//end db error

		}//end remove

		else {

			//Common Update, Insert, Add conversions

			$measurandNameRaw = $_POST['MeasurandName'];
			$measurandName = "'".(empty($measurandNameRaw) ?  "Measurand$measurandId" : pg_escape_string($measurandNameRaw))."'";
			$measUnitsRaw = $_POST['Units'];
			$measUnits = empty($measUnitsRaw) ? "NULL" : "'".pg_escape_string($measUnitsRaw)."'";;
			$measTextUnitsRaw = $_POST['TextUnits'];
			$measTextUnits = empty($measTextUnitsRaw) ? "NULL" : "'".pg_escape_string($measTextUnitsRaw)."'";;
			$maxValRaw = $_POST['MaxValue'];
			$maxVal = empty($maxValRaw) ? "Null" : pg_escape_string($maxValRaw);
			$minValRaw = $_POST['MinValue'];
			$minVal = empty($minValRaw) ? "Null" : pg_escape_string($minValRaw);
			$graphScaleMaxRaw = $_POST['GraphScaleMax'];
			$graphScaleMax = empty($graphScaleMaxRaw) ? "Null" : pg_escape_string($graphScaleMaxRaw);
			$graphScaleMinRaw = $_POST['GraphScaleMin'];
			$graphScaleMin = empty($graphScaleMinRaw) ? "Null" : pg_escape_string($graphScaleMinRaw);
			$decimalPlacesRaw = $_POST['DecimalPlaces'];
			$decimalPlaces = empty($decimalPlacesRaw) ? 0 : pg_escape_string($decimalPlacesRaw);

			if ($cmd == 'Update') {

				//Process Posted Values

				$updQry = "UPDATE \"Measurand\" SET \"MeasurandName\" = $measurandName,
							\"Units\" = $measUnits, \"TextUnits\" = $measTextUnits,
							\"MaxValue\" = $maxVal, \"MinValue\" = $minVal,
							\"GraphScaleMax\" = $graphScaleMax, \"GraphScaleMin\" = $graphScaleMin,
							\"DecimalPlaces\" = $decimalPlaces
							WHERE \"MeasurandID\" = $measurandId
						;";

				$updResult = $db_object->query($updQry);

				if (MDB2::isError($updResult)) {
					error_log("Database Error Query: ".$updQry." ".$updResult->getMessage(), 0);
					die($updResult->getMessage());
				}//end db error

			}//end update

			else if ($cmd == 'Duplicate' || $cmd == 'Add') {

				//Process Posted Values as Insert

				$insQry = "INSERT INTO \"Measurand\" (\"MeasurandName\", \"Units\", \"TextUnits\",
										\"MaxValue\", \"MinValue\", \"GraphScaleMax\", \"GraphScaleMin\", \"DecimalPlaces\")
    								VALUES ($measurandName, $measUnits, $measTextUnits,
    										$maxVal, $minVal, $graphScaleMax, $graphScaleMin, $decimalPlaces);
						  ";

				$insResult = $db_object->query($insQry);

				if (MDB2::isError($insResult)) {
					error_log("Database Error Query: ".$insQry." ".$insResult->getMessage(), 0);
					die($insResult->getMessage());
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

		if (isset($_GET['meas'])) {
			$measurandIdParam = $_GET['meas'];
		} else {
			$measurandIdParam = -1;
		}

		$rowCount = 0;//assume not found
		$isNewRecord = false;

		if ($measurandIdParam > 0) {

			$measurandQry = "SELECT \"MeasurandID\", \"MeasurandName\", \"Units\", \"TextUnits\",
										\"MaxValue\", \"MinValue\", \"GraphScaleMax\", \"GraphScaleMin\",
										\"DecimalPlaces\"
								FROM \"Measurand\"
								WHERE \"MeasurandID\" = $measurandIdParam;
							";

			$measurandResult = $db_object->query($measurandQry);

			if (MDB2::isError($measurandResult)) {
				error_log("Database Error Query: ".$measurandQry." ".$measurandResult->getMessage(), 0);
				die($measurandQry.' - '.$measurandResult->getMessage());
			}//end db error

			$rowCount = $measurandResult->numRows();

			if ($rowCount == 1) {
				$measurandRow = $measurandResult->fetchRow(DICTCURSOR);
			} else {
				echo('<p style="text-align:center">Measurand not found.</p>');
			}
		}

		if ($rowCount == 0 || $measurandIdParam <= 0) {

			//initialise values for new measurand
			$isNewRecord = true;

			$nextIdQry = "SELECT max(\"MeasurandID\") FROM \"Measurand\";";
			$nextIdResult = $db_object->query($nextIdQry);
			if (MDB2::isError($nextIdResult)) {
				error_log("Database Error Query: ".$nextIdQry." ".$nextIdResult->getMessage(), 0);
				die($nextIdResult->getMessage());
			}//end db error
			$nextId = $nextIdResult->fetchRow()[0] + 1;

			$measurandRow = [
				'MeasurandID' => $nextId,
				'MeasurandName' => 'Measurand '.$nextId,
				'Units' => 'units',
				'TextUnits' => 'units',
				'MaxValue' => NULL,
				'MinValue' => NULL,
				'GraphScaleMax' => NULL,
				'GraphScaleMin' => NULL,
				'DecimalPlaces' => 0
			];
		}

		$measurandId = $measurandRow['MeasurandID'];
		$measurandName = $measurandRow['MeasurandName'];
		$measUnits = $measurandRow['Units'];
		$measTextUnits = $measurandRow['TextUnits'];
		$maxVal = $measurandRow['MaxValue'];
		$minVal = $measurandRow['MinValue'];
		$graphScaleMax = $measurandRow['GraphScaleMax'];
		$graphScaleMin = $measurandRow['GraphScaleMin'];
		$decimalPlaces = $measurandRow['DecimalPlaces'];

		echo('<form action="measurand.php?tab='.$_GET['tab'].'" method="post">');

			echo('<div class="EditorFormLabel">Measurand Number:</div><div class="ReadOnlyEditorFormValue"><input name="MeasurandID" type="text" readonly="readonly" value="M'.$measurandId.'" /></div>');
			echo('<div class="EditorFormLabel">Measurand Name:</div><div class="EditorFormValue"><input name="MeasurandName" type="text" value="'.$measurandName.'" /></div>');
			echo('<div class="EditorFormLabel">Units:</div><div class="EditorFormValue"><input name="Units" type="text" value="'.$measUnits.'" /></div>');
			echo('<div class="EditorFormLabel">Text Units:</div><div class="EditorFormValue"><input name="TextUnits" type="text" value="'.$measTextUnits.'" /></div>');
			echo('<div class="EditorFormLabel">Max Value:</div><div class="EditorFormValue"><input name="MaxValue" type="number" value="'.$maxVal.'" /></div>');
			echo('<div class="EditorFormLabel">Min Value:</div><div class="EditorFormValue"><input name="MinValue" type="number" value="'.$minVal.'" /></div>');
			echo('<div class="EditorFormLabel">Graph Scale Max:</div><div class="EditorFormValue"><input name="GraphScaleMax" type="number" value="'.$graphScaleMax.'" /></div>');
			echo('<div class="EditorFormLabel">Graph Scale Min:</div><div class="EditorFormValue"><input name="GraphScaleMin" type="number" value="'.$graphScaleMin.'" /></div>');
			echo('<div class="EditorFormLabel">Decimal Places:</div><div class="EditorFormValue"><input name="DecimalPlaces" type="number" min="0" max="2" value="'.$decimalPlaces.'" /></div>');

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