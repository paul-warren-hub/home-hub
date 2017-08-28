<?php

	//Plots Graphs of Samples for each Measurand
	require_once '../private_html/hub_connect.php';
	require_once 'auth.inc';
	$timebaseHrs = 24;
	
	$measQry = 'SELECT m."MeasurandID","MeasurandName", "Units", "GraphScaleMax", "GraphScaleMin"
				FROM "Measurand" m
				LEFT JOIN "Sensor" s ON m."MeasurandID" = s."MeasurandID"
				INNER JOIN "SampleDef" d ON s."SensorEntryID" = d."SensorEntryID"
				WHERE s."Enabled"
				GROUP BY m."MeasurandID"
				ORDER BY m."MeasurandID"
			   ';

	$measResult = $db_object->query($measQry);

	if (MDB2::isError($measResult)) {
		error_log("Database Error Query: ".$measQry." ".$measResult->getMessage(), 0);
		die($measResult->getMessage());
	}//end db error

	if ($measResult->numRows() == 0) {
		echo('There are no Comparisons to graph.');
	} else {

		echo('<script type="text/javascript">');
		echo('compArray=[];');
		while ($measRow = $measResult->fetchRow(DICTCURSOR)) {

			$measId = $measRow['MeasurandID'];
			$measName = $measRow['MeasurandName'];
			$measUnits = $measRow['Units'];
			$measurandGraphMax = $measRow['GraphScaleMax'];
			$measurandGraphMin = $measRow['GraphScaleMin'];

			echo('if (typeof comparisonTimebaseArray['.$measId.']==="undefined") {
						comparisonTimebaseArray['.$measId.'] = initTimebaseHrs;
				}');


			echo('function drawComparisonChart'.$measId.'() {
				// Set a callback to run when the Google Visualization API is loaded.
							drawComparisonChart("'.$measName.'",
										"compChartDiv'.$measId.'",
											"getComparisonChartData.php",'.
												$measId.',"'.$measUnits.'",'.$measurandGraphMax.','.$measurandGraphMin.'
							)};
				 ');

			// Set a callback to run when the Google Visualization API is loaded.
			echo('google.charts.setOnLoadCallback( function() {drawComparisonChart'.$measId.'()});');
			echo('compArray.push('.$measId.');');

		}//wend
		echo('</script>');
	}//end is rows

	$measResult = $db_object->query($measQry);

	if (MDB2::isError($measResult)) {
		error_log("Database Error Query: ".$measQry." ".$measResult->getMessage(), 0);
		die($measResult->getMessage());
	}//end db error

	while ($measRow = $measResult->fetchRow(DICTCURSOR)) {

		$condId = $measRow['MeasurandID'];
		//Div that will hold the line chart
		echo('<div id="compChartDiv'.$condId.'" style="width: 100%; height: 50%;"></div>');
	}

?>