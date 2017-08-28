<?php

	// Plots Graphs of Samples plus Complex Conditional Events
	require_once '../private_html/hub_connect.php';
	require_once 'auth.inc';
	require_once 'hub.inc';

	$startTimebaseHrs = 1;
	$colours = unserialize(SENSOR_COLOURS);
	$setColours = unserialize(SET_COLOURS);
	$resetColours = unserialize(RESET_COLOURS);
	$lineDashStyles = unserialize(LINE_DASH_STYLES);

	$sensQry = 'SELECT "SensorID", "MeasurandName" || \' \' || "Units", "GraphScaleMax", "GraphScaleMin"
				FROM "vwSensorMeasurands"
			   ';

	$sensResult = $db_object->query($sensQry);

	if (MDB2::isError($sensResult)) {
		error_log("Database Error Query: ".$sensQry." ".$sensResult->getMessage(), 0);
		die($sensResult->getMessage());
	}// end db error

	// Assemble Sensor=>Measurand and Measurand=>GraphMax,Measurand=>GraphMin into dictionaries
	$sensorMeasurand = array();
	$measurandGraphMax = array();
	$measurandGraphMin = array();
	while ($sensRow = $sensResult->fetchRow()) {
		$sensorMeasurand[$sensRow[0]] = $sensRow[1];
		if (!array_key_exists($sensRow[1], $measurandGraphMax)) {
			$measurandGraphMax[$sensRow[1]] = $sensRow[2];
		}
		if (!array_key_exists($sensRow[1], $measurandGraphMin)) {
			$measurandGraphMin[$sensRow[1]] = $sensRow[3];
		}
	}// wend

	$condQry = 'SELECT "ConditionID", "ConditionName",
						"SetExpression", "ResetExpression"
				FROM "vwComplexConditions"
				WHERE "ConditionFormat" <> \'Time\'
				ORDER BY "ConditionID"
			   ';

	$condResult = $db_object->query($condQry);

	if (MDB2::isError($condResult)) {
		error_log("Database Error Query: ".$condQry." ".$condResult->getMessage(), 0);
		die($condResult->getMessage());
	}// end db error

	while ($condRow = $condResult->fetchRow(DICTCURSOR)) {

		$condId = $condRow['ConditionID'];
		// Div that will hold the line chart
		echo('<div class="complexConditionChart" id="complexCondChartDiv'.$condId.'" style="width: 100%; height: 50%;"></div>');

	}// wend condition chart div

	// Re-run query for js functions
	$condResult = $db_object->query($condQry);

	echo('<script type="text/javascript">');
	echo('condArray=[];');
	while ($condRow = $condResult->fetchRow(DICTCURSOR)) {

		$condId = $condRow['ConditionID'];
		echo('var timebaseHrs'.$condId.' = '.$startTimebaseHrs.';');
		$condName = $condRow['ConditionName'];

		$setExp = $condRow['SetExpression'];
		$resetExp = $condRow['ResetExpression'];

		$chartTitle = $condId.'. '.$condName.' ['.$setExp.';'.$resetExp.' ]';
		$sensorTree = parseExpression($setExp.' and '.$resetExp, $sensorMeasurand);

		// Assemble series object that links data-columns to axes
		$series = new stdClass;
		$vaxes = new stdClass;
		$colNum = 0;
		$colorNum = 0;
		$axisNum = 0;
		foreach (array_keys($sensorTree) as $measNameUnits) {
			$vaxis = new stdClass;
			$vaxis->title = $measNameUnits;
			$gridLines = new stdClass;
			$gridLines->count = 6;
			$vaxis->gridlines = $gridLines;
			$viewWindow = new stdClass;
			$viewWindow->max = $measurandGraphMax[$measNameUnits];
			$viewWindow->min = $measurandGraphMin[$measNameUnits];
			$vaxis->viewWindow = $viewWindow;

			$vaxes->{$axisNum} = $vaxis;
			foreach ($sensorTree[$measNameUnits][0] as $sensId) {
				$tgt = new stdClass;
				$tgt->targetAxisIndex = $axisNum;
				$tgt->color = $colours[$colorNum++];
				$series->{$colNum} = $tgt;
				$colNum += 1;
			}
			$axisNum += 1;
		}
		// Then tack on Events as final variable series

		$tgt = new stdClass;
		$tgt->targetAxisIndex = $axisNum;
		$tgt->color = 'red';
		$series->{$colNum} = $tgt;
		$vaxis = new stdClass;
		$gridLines = new stdClass;
		$gridLines->count = 0;
		$vaxis->gridlines = $gridLines;
		$viewWindow = new stdClass;
		$viewWindow->max = 6;
		$viewWindow->min = -0.5;
		$vaxis->viewWindow = $viewWindow;
		$vaxis->baselineColor = '#EEEEEE';
		$vaxes->{$axisNum} = $vaxis;
		$colNum += 1;
		$axisNum += 1;

		// Then add Thresholds
		$axisNum = 0;
		foreach (array_keys($sensorTree) as $measNameUnits) {
			foreach ($sensorTree[$measNameUnits][1] as $threshId) {
				$tgt = new stdClass;
				$tgt->targetAxisIndex = $axisNum;
				if ($colNum % 2 == 0) {
					$tgt->color = $colours[$colorNum++];
				} else {
					$tgt->color = $colours[$colorNum++];
				}
				$tgt->lineDashStyle = $lineDashStyles[$colNum % 2];
				$series->{$colNum} = $tgt;
				$colNum += 1;
			}
			$axisNum += 1;
		}

		$jsonSeries = json_encode($series);

		$jsonVaxes = json_encode($vaxes);

			echo('if (typeof compCondTimebaseArray['.$condId.']==="undefined") {
						//console.log("array: " + compCondTimebaseArray['.$condId.']);
						compCondTimebaseArray['.$condId.'] = initTimebaseHrs;
						//console.log("reset timebase");
				}');

			echo('function drawConditionChart'.$condId.'() {
					//console.log("ComplexConditionGraphs.php function drawConditionChartN calling drawComplexConditionChartN...'.$condId.'");
					drawComplexConditionChart("'.$chartTitle.'",
								"complexCondChartDiv'.$condId.'",
									"getConditionChartData.php",'.
										$jsonSeries.','.
											$jsonVaxes.','.
												$condId.'
												)};
				');

			// Set a callback to run when the Google Visualization API is loaded.
			echo('google.charts.setOnLoadCallback( function() {drawConditionChart'.$condId.'()});');
			echo('condArray.push('.$condId.');');

	}// wend condition
	echo('</script>');
?>