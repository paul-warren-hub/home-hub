<?php

	//Enable conditions to be adjusted
	require_once '../private_html/hub_connect.php';
	require_once 'auth.inc';

	$status = '1';

	//Check Postback for an update
	if (isset($_POST['ConditionID'])) {
		$condAction = $_POST['ConditionAction'];
		$condId = $_POST['ConditionID'];

		if ($condAction == 'duplicate') {

			//Duplicate the condition and the associated rule
			//First need the new condition id...
			$insQry1 = 'INSERT INTO "Condition"(
            	"ConditionName", "ConditionDescription", "SetOperator",
            	"SetThreshold", "SetExpression", "ResetOperator", "ResetThreshold",
            	"ResetExpression", "CurrentValue", "LastUpdated", "Enabled",
            	"Source", "ErrorMessage")
    		(SELECT "ConditionName", "ConditionDescription", "SetOperator",
       			"SetThreshold", "SetExpression", "ResetOperator", "ResetThreshold",
       			"ResetExpression", "CurrentValue", "LastUpdated", "Enabled",
       			"Source", NULL
  			FROM "Condition"
  			WHERE "ConditionID" = '.$condId.');
  			';
			$insResult1 = $db_object->query($insQry1);

			//Now we have to run a query to get the current value of the sequence
			$seqQry = 'SELECT last_value FROM "Condition_ConditionID_seq"';
			$seqResult = $db_object->query($seqQry);
			if (MDB2::isError($seqResult)) {
				error_log("Database Error Query: ".$seqQry." ".$seqResult->getMessage(), 0);
				$status = '0';
			}//end db error

			$seqRow = $seqResult->fetchRow();

			$ruleSourceId = $seqRow[0];//new condition id

			if (MDB2::isError($insResult1)) {
				error_log("Database Error Query: ".$insQry1." ".$insResult1->getMessage(), 0);
				$status = '0';
			}//end db error

			$insQry2 = 'INSERT INTO "Rule"(
			            "RuleName", "RuleDescription", "SourceType", "SourceID",
			            "ActionID", "Enabled")
			    (SELECT "RuleName", "RuleDescription", "SourceType", $ruleSourceId,
				       "ActionID", "Enabled"
  				FROM "Rule"
  				WHERE "SourceType" = \'Condition\' AND "SourceID" = '.$condId.');
  			';

			$insResult2 = $db_object->query($insQry2);

			if (MDB2::isError($insResult2)) {
				error_log("Database Error Query: ".$insQry2." ".$insResult2->getMessage(), 0);
				$status = '0';
			}//end db error

		} else if ($condAction == 'remove') {

			//Remove the condition and the associated rule

			$delQry1 = 'DELETE FROM "Condition" WHERE "ConditionID" = '.$condId.';';
			$delResult1 = $db_object->query($delQry1);

			if (MDB2::isError($delResult1)) {
				error_log("Database Error Query: ".$delQry1." ".$delResult1->getMessage(), 0);
				$status = '0';
			}//end db error

			$delQry2 = 'DELETE FROM "Rule" WHERE "SourceType" = \'Condition\' AND "SourceID" = '.$condId.';';
			$delResult2 = $db_object->query($delQry2);

			if (MDB2::isError($delResult2)) {
				error_log("Database Error Query: ".$delQry2." ".$delResult2->getMessage(), 0);
				$status = '0';
			}//end db error

		} else {

			//Update
			$condName = $_POST['ConditionName'];
			$condDesc = $_POST['ConditionDescription'];
			if ($condDesc == '') {
				$condDesc = 'description...';
			}
			$condSce = $_POST['ConditionSource'];
			$condFmt = $_POST['ConditionFormat'];
			$condEnabled = $_POST['Enabled'];

			if ($condFmt == 'Time') {
				$setThreshHrs =  $_POST['ExceptionThresholdHrs'];
				$setThreshMins =  $_POST['ExceptionThresholdMins'];
				$setThreshPadded = sprintf("%'.02d", $setThreshHrs).':'.sprintf("%'.02d", $setThreshMins);
				$resetThreshHrs =  $_POST['NormalThresholdHrs'];
				$resetThreshMins =  $_POST['NormalThresholdMins'];
				$resetThreshPadded = sprintf("%'.02d", $resetThreshHrs).':'.sprintf("%'.02d", $resetThreshMins);

				if ($condId == -1) {

					//Create a new Condition
					$updOrInsQry1 = "INSERT INTO \"Condition\" (
									\"ConditionName\", \"ConditionDescription\", \"Source\",
									\"SetOperator\", \"ResetOperator\", \"SetThreshold\",
									\"ResetThreshold\",	\"Enabled\"
								) VALUES (
									'$condName',
									'$condDesc',
									'$condSce',
									NULL,
									NULL,
								 	'$setThreshPadded',
									'$resetThreshPadded',
									$condEnabled
								);
							  ";

				} else {

					$updOrInsQry1 = "UPDATE \"Condition\"
								SET
								\"ConditionName\" = '$condName',
								\"ConditionDescription\" = '$condDesc',
								\"Source\" = '$condSce',
								\"SetOperator\" = NULL,
								\"ResetOperator\" = NULL,
								\"SetThreshold\" = '$setThreshPadded',
								\"ResetThreshold\" = '$resetThreshPadded',
								\"Enabled\" = $condEnabled
								WHERE \"ConditionID\" = $condId;
							  ";
				}

			} else if ($condFmt == 'Simple') {

				$excOp =  $_POST['ExceptionOperator'];
				$normOp =  $_POST['NormalOperator'];
				$setThresh =  $_POST['ExceptionThreshold'];
				$resetThresh =  $_POST['NormalThreshold'];

				if ($condId == -1) {

					//Create a new Condition
					$updOrInsQry1 = "INSERT INTO \"Condition\" (
									\"ConditionName\", \"ConditionDescription\", \"Source\",
									\"SetOperator\", \"ResetOperator\", \"SetThreshold\",
									\"ResetThreshold\",	\"Enabled\"
								) VALUES (
									'$condName',
									'$condDesc',
									'$condSce',
									'$excOp',
									'$normOp',
								 	'$setThresh',
									'$resetThresh',
									$condEnabled
								);
							  ";

				} else {

					$updOrInsQry1 = "UPDATE \"Condition\"
								SET
								\"ConditionName\" = '$condName',
								\"ConditionDescription\" = '$condDesc',
								\"Source\" = '$condSce',
								\"SetOperator\" = '$excOp',
								\"ResetOperator\" = '$normOp',
								\"SetThreshold\" = '$setThresh',
								\"ResetThreshold\" = '$resetThresh',
								\"Enabled\" = $condEnabled
								WHERE \"ConditionID\" = $condId;
							  ";
				}

			} else if ($condFmt == 'Complex') {

				$setExpr =  $_POST['SetExpression'];
				$resetExpr =  $_POST['ResetExpression'];

				if ($condId == -1) {

					//Create a new Condition
					$updOrInsQry1 = "INSERT INTO \"Condition\" (
									\"ConditionName\", \"ConditionDescription\", \"Source\",
									\"SetOperator\", \"ResetOperator\", \"SetThreshold\",
									\"ResetThreshold\",	\"SetExpression\",
									\"ResetExpression\", \"Enabled\"
								) VALUES (
									'$condName',
									'$condDesc',
									'$condSce',
									NULL,
									NULL,
								 	NULL,
									NULL,
									'$setExpr',
									'$resetExpr',
									$condEnabled
								);
							  ";
				} else {

					$updOrInsQry1 = "UPDATE \"Condition\"
							SET
							\"ConditionName\" = '$condName',
							\"ConditionDescription\" = '$condDesc',
							\"Source\" = '$condSce',
							\"SetOperator\" = NULL,
							\"ResetOperator\" = NULL,
							\"SetThreshold\" = NULL,
							\"ResetThreshold\" = NULL,
							\"SetExpression\" = '$setExpr',
							\"ResetExpression\" = '$resetExpr',
							\"Enabled\" = $condEnabled
							WHERE \"ConditionID\" = $condId;
						  ";
				}

			}//end complex

			$updOrInsResult1 = $db_object->query($updOrInsQry1);

			if (MDB2::isError($updOrInsResult1)) {
				error_log("Database Error Query: ".$updOrInsQry1." ".$updOrInsResult1->getMessage(), 0);
				$status = '0';
			}//end db error

		}//end update

		echo($status);

	}//end process postback
	else {
		echo('0');
	}

?>