<?php

	//Enable thresholds to be adjusted
	require_once '../private_html/hub_connect.php';
	require_once 'auth.inc';

	$status = '1';

	//Check Postback for an update
	if (isset($_POST['UpdateCondition'])) {
		$idArray = explode('|', $_POST['UpdateCondition']);
		$condId = $idArray[0];
		$excThrId = $idArray[1];
		$normThrId = $idArray[2];
		//echo($condId.' '.$excThrId.' '.$normThrId.'<br />');
		$excOp =  $_POST['ExceptionOperator'];
		$excThresh =  $_POST['ExceptionThreshold'];
		$normOp =  $_POST['NormalOperator'];
		$normThresh =  $_POST['NormalThreshold'];

		$updQry1 = "UPDATE \"Condition\"
					SET \"ExceptionOperator\" = '$excOp',
					\"NormalOperator\" = '$normOp'
					WHERE \"ConditionID\" = $condId;
				  ";

		$updQry2 = "UPDATE \"Threshold\"
					SET \"ThresholdValue\" = $excThresh
					WHERE \"ThresholdID\" = $excThrId;
				  ";

		$updQry3 = "UPDATE \"Threshold\"
					SET \"ThresholdValue\" = $normThresh
					WHERE \"ThresholdID\" = $normThrId;
				  ";

		//echo($updQry1.'<br /><br />'.$updQry2.'<br /><br />'.$updQry3);

		$updResult = $db_object->query($updQry1);

		if ($db->isError($updResult)) {
			error_log("Database Error Query: ".$updQry1." ".$updResult->getMessage(), 0);
			$status = '0';
		}//end db error

		$updResult = $db_object->query($updQry2);

		if ($db->isError($updResult)) {
			error_log("Database Error Query: ".$updQry2." ".$updResult->getMessage(), 0);
			$status = '0';
		}//end db error

		$updResult = $db_object->query($updQry3);

		if ($db->isError($updResult)) {
			error_log("Database Error Query: ".$updQry3." ".$updResult->getMessage(), 0);
			$status = '0';
		}//end db error

		echo($status);

	}//end process postback
	else {
		echo('0');
	}

?>