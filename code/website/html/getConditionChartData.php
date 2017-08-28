<?php

	// Get Chart Data for complex condition on multiple sensors

	// Require scripts
	require_once '../private_html/hub_connect.php';
	require_once 'hub.inc';

	$condId = $_GET['condition'];
	$timebaseHrs = $_GET['timebasehrs'];
	$colours = unserialize(MEASURAND_COLOURS);

	// First we need to find out the measurands/sensors/constants which are associated with this condition

	// Get a map of sensors => measurands
	$sensQry = 'SELECT "SensorID", "Units"
				FROM "vwSensorMeasurands"
			   ';

	$sensResult = $db_object->query($sensQry);

	if (MDB2::isError($sensResult)) {
		error_log("Database Error Query: ".$sensQry." ".$sensResult->getMessage(), 0);
		die($sensResult->getMessage());
	}// end db error

	// Assemble Sensor=>Measurand into dictionary
	$sensorMeasurand = array();

	while ($sensRow = $sensResult->fetchRow()) {
		$sensorMeasurand[$sensRow[0]] = $sensRow[1];
	}// wend

	$condQry = 'SELECT "ConditionID", "ConditionName",
						"SetExpression", "ResetExpression"
				FROM "vwComplexConditions"
				WHERE "ConditionID" = '.$condId
			   ;

	$condResult = $db_object->query($condQry);

	if (MDB2::isError($condResult)) {
		error_log("Database Error Query: ".$condQry." ".$condResult->getMessage(), 0);
		die($condResult->getMessage());
	}// end db error

	$condRow = $condResult->fetchRow(DICTCURSOR);

	$setExp = $condRow['SetExpression'];
	$resetExp = $condRow['ResetExpression'];

	$sensorTree = parseExpression($setExp.' and '.$resetExp, $sensorMeasurand);

	// Extract Sensors into list
	$allSensors = array();
	foreach ($sensorTree as $sensorGrp) {
		$allSensors = array_merge($allSensors, $sensorGrp[0]);
	}
	$sensCount = count($allSensors);
	$sensorList = '('.join(',', $allSensors).')';

	$dataTable = new stdClass;
	$rowsArray = array();// rows array
	$colsArray = array();// cols array
	if ($sensCount > 0) {

		//then we can fetch samples
		$sampleQry = "SELECT  	now() - interval '$timebaseHrs hour' AS \"Timestamp\",
							chartdate(now() - interval '$timebaseHrs hour') AS \"Timebase\",
							first(\"Value\") AS \"Value\",
							'E' AS \"Mode\", -1 AS \"SensorID\"
							FROM \"vwEventsForGraph\"
							WHERE \"ConditionID\" = $condId AND
							(\"Timestamp\" > (now() - interval '$timebaseHrs hour') OR \"Timestamp\" IS NULL)
							GROUP BY \"ConditionID\"

					  UNION

						SELECT \"Timestamp\",
						CASE
							WHEN \"Timestamp\" IS NULL THEN chartdate(now() - interval '$timebaseHrs hour')
							ELSE chartdate(\"Timestamp\")
						END AS \"Timebase\",
						\"Value\",
						'E' AS \"Mode\", -1
						FROM \"vwEventsForGraph\"
						WHERE \"ConditionID\" = $condId AND
						(\"Timestamp\" > (now() - interval '$timebaseHrs hour') OR \"Timestamp\" IS NULL)

					  UNION

						SELECT \"Timestamp\",chartdate(\"Timestamp\") AS \"Timebase\",\"Value\",'A', \"SensorID\"
						FROM \"vwSamples\"
						WHERE \"SensorID\" IN $sensorList AND
								\"Timestamp\" > (now() - interval '$timebaseHrs hour')

					  UNION

						SELECT now(), chartdate(now()),\"CurrentValue\",'A', \"SensorID\"
										FROM \"Sensor\"
						WHERE \"SensorID\" IN $sensorList

						ORDER BY \"Timestamp\"
				   ";

		$sampleResult = $db_object->query($sampleQry);

		if (MDB2::isError($sampleResult)) {
			error_log("Database Error Query: ".$sampleQry." ".$sampleResult->getMessage(), 0);
			die($sampleResult->getMessage());
		}//end db error

		/*
				  ['Time', 'Sensor1', 'Sensor2', 'Events', 'Threshold', 'Dotted', 'Threshold', 'Dotted'],
				  ['20:04',  1000,      400, 1, 70, 1, 25, 0],
				  ['20:05',  1170,      460, 1, 70, 1, 25, 0],
				  ['20:06',  660,       112, 1, 70, 1, 25, 0],
				  ['20:07',  1030,      540, 1, 70, 1, 25, 0]
		*/

		$xAxisCol = new stdClass;// x axis dt column definition
		$xAxisCol->type = 'date';// string = discrete, enumerable = continuous
		$xAxisCol->label = 'Time';
		$colsArray = array($xAxisCol);
		$axisNum = 0;
		$sensNum = 0;
		foreach ($sensorTree as $measName) {
			foreach ($measName[0] as $sensId) {
				$yAxisCol = new stdClass;// y axis dt column definition
				$yAxisCol->type = 'number';
				$yAxisCol->label = 'S'.$sensId;
				$yAxisCol->color = $colours[$axisNum];
				array_push($colsArray, $yAxisCol);
				$sensNum += 1;
			}// next sensor
			$axisNum += 1;
		}// next meas

		$colNum = $sensNum;

		// Then we're just left with the Event Column
		$yAxisCol = new stdClass;// y axis dt column definition
		$yAxisCol->type = 'number';
		$yAxisCol->label = 'Events';
		array_push($colsArray, $yAxisCol);
		$colNum += 1;

		// And finally the thresholds
		$axisNum = 0;
		foreach (array_keys($sensorTree) as $measNameUnits) {
			foreach ($sensorTree[$measNameUnits][1] as $threshVal) {
				$yAxisCol = new stdClass;// y axis dt column definition
				$yAxisCol->type = 'number';
				$yAxisCol->label = $threshVal.' '.$measNameUnits;
				array_push($colsArray, $yAxisCol);
				$colNum += 1;
			}
			$axisNum += 1;
		}

		$x = 0;
		$normThr = 20;
		$excThr = 10;
		while ($sampleRow = $sampleResult->fetchRow(DICTCURSOR)) {

			$xAxisCell = new stdClass;// x axis dt cell definition
			$xAxisCell->v = $sampleRow['Timebase'];
			$rowArray = array($xAxisCell);

			$sensNum = 0;
			foreach ($sensorTree as $measName) {
				foreach ($measName[0] as $sensId) {
					$yAxisCell = new stdClass;// y axis dt cell definition
					$yAxisCell->v = null;// default
					if ($sampleRow['Mode'] == 'A' and
						$sampleRow['SensorID'] == $sensId) {
							// Analogue
							$yAxisCell->v = $sampleRow['Value'];
					}
					array_push($rowArray, $yAxisCell);
					$sensNum += 1;
				}// next sensor
			}// next meas

			// Event Column
			$yAxisCell = new stdClass;//y axis dt cell definition
			$yAxisCell->v = null;//default
			if ($sampleRow['Mode'] == 'E') {
				$yAxisCell->v = $sampleRow['Value'];
			}
			array_push($rowArray, $yAxisCell);

			// Thresholds
			$axisNum = 0;
			foreach (array_keys($sensorTree) as $measNameUnits) {
				foreach ($sensorTree[$measNameUnits][1] as $threshVal) {
					$yAxisCell = new stdClass;//y axis dt cell definition
					$yAxisCell->v = $threshVal;
					array_push($rowArray, $yAxisCell);
					$colNum += 1;
				}
				$axisNum += 1;
			}

			$rowObject = new stdClass;
			$rowObject->c = $rowArray;

			array_push($rowsArray, $rowObject);
			$x++;
		}

		$dataTable->cols = $colsArray;
		$dataTable->rows = $rowsArray;

	}//end no sensors

	echo json_encode($dataTable);

?>