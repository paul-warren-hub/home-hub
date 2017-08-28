<?php
# define constant, serialize array
define ("MEASURAND_COLOURS", serialize (array ("#aa6000", "#20B2AA")));
define ("SENSOR_COLOURS", serialize (array ("#aaaa11","#3366cc","#ff9900","#109618","#990099","#0099c6","#dd4477","#aaaa11","#22aa99","#994499","#dc5912")));
define ("SET_COLOURS", serialize (array ("#aa6000", "#20B2AA")));
define ("RESET_COLOURS", serialize (array ("#aa6000", "#20B2AA")));
define ("LINE_DASH_STYLES", serialize (array (array(1, 4), array(2, 2, 20, 2, 20, 2))));
define ("WIND_DIRECTIONS",  serialize (array ('N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW')));


function addOrdinalNumberSuffix($num) {
    if (!in_array(($num % 100),array(11,12,13))){
        switch ($num % 10) {
            // Handle 1st, 2nd, 3rd
            case 1:  return $num.'st';
            case 2:  return $num.'nd';
            case 3:  return $num.'rd';
        }
    }
    return $num.'th';
}//end add ordinal number suffix

function getFromExpression($pattern, $exp) {
	$subject = $exp;
	$count = preg_match($pattern, $subject, $matches);
	$result = null;
	if ($count > 0) {
		$result = $matches[0];
	}
	return $result;
}
function splitExpressionIntoLogicalParts($exp) {

	$pattern = '/[andorxor]/';
	$string = $exp;
	$result = array();
	$rawResult = preg_split( $pattern, $string );
	foreach ($rawResult as $value) {
		if (trim($value) != '') {
			array_push($result, trim($value));
		}
    }
	return $result;
	
}//end split expression into logical parts

function parseExpression($exp, $sensorMeasurand) {

	$results = array();

	$parts = splitExpressionIntoLogicalParts($exp);

	foreach ($parts as $part) {
		$sensorName = getFromExpression('/[S][0-9.]+/', $part);
		$sensId = filter_var($sensorName, FILTER_SANITIZE_NUMBER_INT);
		if (array_key_exists($sensId, $sensorMeasurand)) {
			$constant = getFromExpression('/(?<![A-Za-z0-9.])[0-9.]+/', $part);
			$measId = $sensorMeasurand[$sensId];
			if (!array_key_exists($measId, $results)) {
				$results[$measId][0] = array();//Sensors
				$results[$measId][1] = array();//Constants			
			}
			
			if (!in_array($sensId, $results[$measId][0])) {
				array_push($results[$measId][0], $sensId);
			}
			if (!in_array($constant, $results[$measId][1])) {
				array_push($results[$measId][1], $constant);
			}
		}		
	}//next part
	return $results;
}//end parse expression
?>