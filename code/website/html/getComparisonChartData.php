<?php

// Get Chart Data for all sensors in a single measurand

// Require scripts
require_once '../private_html/hub_connect.php';

$measId = $_GET['meas'];
$timebaseHrs = $_GET['timebasehrs'];

//first we need to find out the sensors which measure this measurand
$sensQry = 'SELECT s."SensorID", CASE WHEN s."Name" IS NULL THEN "ZoneName" ELSE s."Name" END AS "SensorName"
			FROM "vwSensors" s
			INNER JOIN "SampleDef" d ON  s."SensorEntryID" = d."SensorEntryID"
			WHERE "MeasurandID" = '.$measId.
			'ORDER BY "ZoneID","SensorID"
		   ';

$sensResult = $db_object->query($sensQry);

if (MDB2::isError($sensResult)) {
	error_log("Database Error Query: ".$sensQry." ".$sensResult->getMessage(), 0);
	die($sensResult->getMessage());
}//end db error

//then we can fetch samples
$actQry = 'SELECT "Timestamp",chartdate("Timestamp") AS "Timebase", "SensorID", "Value"
			FROM "vwSamples"
			WHERE "MeasurandID" = '.$measId.' AND
				  "Timestamp" > (now() - interval \''.$timebaseHrs.' hour\')
			ORDER BY "Timestamp", "ZoneID", "SensorID"
		   ';

$actResult = $db_object->query($actQry);

if (MDB2::isError($actResult)) {
	error_log("Database Error Query: ".$actQry." ".$actResult->getMessage(), 0);
	die($actResult->getMessage());
}//end db error

/*
		  ['Time', 'Sensor1', 'Sensor2'],
		  ['20:04',  1000,      400],
		  ['20:05',  1170,      460],
		  ['20:06',  660,       1120],
		  ['20:07',  1030,      540]
*/

$dataTable = new stdClass;

$xAxisCol = new stdClass;//x axis dt column definition
$xAxisCol->type = 'date';//string = discrete, enumerable = continuous
$xAxisCol->label = 'Time';

$colsArray = array($xAxisCol);//array of col defs

//Now a column for each sensor...
while ($sensRow = $sensResult->fetchRow(DICTCURSOR)) {
	$sensId = $sensRow['SensorID'];
	$label = $sensRow['SensorName'];
	$yAxisCol = new stdClass;//y axis sensor column definition
	$yAxisCol->type = 'number';
	$yAxisCol->label = 'S'.$sensId.' '.$label;
	array_push($colsArray, $yAxisCol);
}//wend

$sensCount = count($colsArray) - 1;

$rowArray = array();//row array
$rowsArray = array();//rows array

if ($sensCount > 0) {

	$x = 0;
	$col = 0;
	while ($actRow = $actResult->fetchRow(DICTCURSOR)) {

		if ($col == 0) {
			$xAxisRow = new stdClass;//x axis dt row definition
			$xAxisRow->v = $actRow['Timebase'];
			$rowArray = array();//clear rows array
			array_push($rowArray, $xAxisRow);
		}

		$yAxisRow = new stdClass;//y axis row definition
		$yAxisRow->v = $actRow['Value'];
		array_push($rowArray, $yAxisRow);

		if ($col == ($sensCount - 1)) {
			$rowObject = new stdClass;
			$rowObject->c = $rowArray;

			array_push($rowsArray, $rowObject);
		}
		$x++;
		$col = $x % $sensCount;
	}
}//end some sensors
$dataTable->cols = $colsArray;
$dataTable->rows = $rowsArray;

echo json_encode($dataTable);

?>