<?php

	session_start();
	$updBy = ucfirst($_SESSION['username']);

	// Required scripts
	require_once '../private_html/hub_connect.php';
	require_once 'auth.inc';

	//Check Postback for an AUTO update
	if (isset($_POST['isInAuto'])) {
		$status = '1';
		$widgetId = $_POST['actuatorId'];
		$actId = filter_var($widgetId, FILTER_SANITIZE_NUMBER_INT);
		$isInAuto =  $_POST['isInAuto'];
		$autoChar = ($isInAuto == 'true'?'Y':'N');

		$updQry = 'UPDATE "Actuator"
					SET "IsInAuto" = \''.$autoChar.'\',
					"LastUpdated" = current_timestamp,
					"UpdatedBy" = \''.$updBy.'\'
					WHERE "ActuatorID" = '.$actId.';
				  ';

		$updResult = $db_object->query($updQry);

		if (MDB2::isError($updResult)) {
			error_log("Database Error Query: ".$updQry." ".$updResult->getMessage(), 0);
			$status = '0';
		}//end db error

		echo($status);

	}//end process postback

	//Check Postback for an VALUE update
	else if (isset($_POST['currentValue'])) {
		$status = '1';
		$widgetId = $_POST['actuatorId'];
		$actId = filter_var($widgetId, FILTER_SANITIZE_NUMBER_INT);
		$currentValue =  $_POST['currentValue'];

		$updQry = 'UPDATE "Actuator"
					SET "CurrentValue" = '.$currentValue.',
					"LastUpdated" = current_timestamp,
					"UpdatedBy" = \''.$updBy.'\'
					WHERE "ActuatorID" = '.$actId.'
					AND "IsInAuto" = \'N\';
				  ';

		$updResult = $db_object->query($updQry);

		if (MDB2::isError($updResult)) {
			error_log("Database Error Query: ".$updQry." ".$updResult->getMessage(), 0);
			$status = '0';
		}//end db error

		echo($status);

	}//end process postback


?>