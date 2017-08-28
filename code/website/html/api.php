<?php

// The api can accept POST Actuator Actions, or GET Sensor Reading requests
//
// e.g. http://slave-hub/api.php?sensor=57 => [[57, 14.00, "2016-10-30 12:00:00"],0]
// e.g. http://slave-hub/api.php?sensor=55,56 =>
/*
{
	"results": [{
		"id": 55,
		"value": 10.00,
		"updated": "2016-10-30 09:00:00"
	}, {
		"id": 56,
		"value": 12.00,
		"updated": "2016-10-30 09:00:00"
	}],
	"status": 0
}
*/
// Require scripts
require_once '../private_html/hub_connect.php';

// Initialise Variables
/*
	0 = success,
	-1 = request method error,
	-2 = no target defined error,
	-3 = db query error
	-4 = no data available error
*/
$results = '';
$status = 0;//assume success
$curVal = 0.0;//status confirms validity
$lastUpdated = '01/01/1900';//default

// Decode the Request Method
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if (!IsSet($_POST['actuator'])) {
		//echo 'error - no actuator defined';
		$status = -2;
	} else {

		$actuatorId = $_POST['actuator'];
		$state = $_POST['state'];
		$updatedBy = $_POST['updatedby'];

		$updQry = "UPDATE \"Actuator\"
					SET \"CurrentValue\" = $state,
					\"LastUpdated\" = current_timestamp,
					\"UpdatedBy\" = '$updatedBy'
					WHERE \"ActuatorID\" = $actuatorId;
				  ";

		$updResult = $db_object->query($updQry);

		if (MDB2::isError($updResult)) {
			error_log("Database Error Query: ".$updQry." ".$updResult->getMessage(), 0);
			$status = -3;
			$lastUpdated = $updatedBy;

		}//end db error

	}//end valid actuator

} else if ($_SERVER['REQUEST_METHOD'] == 'GET') {

	// Initialise Array Strings
	$sensorResult = '';

	if (!IsSet($_GET['sensor'])) {
		$status = -2;
	} else {

		$sensorId = $_GET['sensor'];

		$actQry = 'SELECT "SensorID", "CurrentValue", "LastUpdated"
					FROM "Sensor"
					WHERE "SensorID" IN ('.$sensorId.')
					ORDER BY "SensorID";';

		$actResult = $db_object->query($actQry);

		if (MDB2::isError($actResult)) {
			error_log("Database Error Query: ".$actQry." ".$actResult->getMessage(), 0);
			$status = -3;
		}//end db error

		$rowCount = $actResult->numRows();

		if ($rowCount == 0) {
			$status = -4;
		} else {
			$sensorCount = 0;
			while ($actRow = $actResult->fetchRow(DICTCURSOR)) {

				$sensId = $actRow['SensorID'];
				$curVal = $actRow['CurrentValue'];
				$lastUpdated = $actRow['LastUpdated'];
				$sensorResult = '{"id":'.$sensId.',"value":'.$curVal.',"updated":"'.$lastUpdated.'"}';
				if ($sensorCount > 0) $results .= ',';
				$results .= $sensorResult;
				$sensorCount++;

			}//wend

		}//end some rows

	}//end valid sensor id

}//end GET
else {
	//Invalid request method
	$status = -1;
}

echo '{"results":['.$results.'],"status":'.$status.'}';

?>