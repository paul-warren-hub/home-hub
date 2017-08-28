<?php

	//Rule management...
	require_once '../private_html/hub_connect.php';
	require_once 'auth.inc';

	if (isset($_POST['RuleID'])) {

		$cmd = $_POST['Command'];
		$ruleId = preg_replace('/\D/', '', $_POST['RuleID']);

		if ($cmd == 'Remove') {

			//Process Posted Values as Delete

			$delQry = "DELETE FROM \"Rule\" WHERE \"RuleID\" = $ruleId;";
			$delResult = $db_object->query($delQry);

			if (MDB2::isError($delResult)) {
				error_log("Database Error Query: ".$delQry." ".$delResult->getMessage(), 0);
				die($delResult->getMessage());
			}//end db error

		}//end remove

		else {

			//Common Update, Insert, Add conversions

			$ruleNameRaw = $_POST['RuleName'];
			$ruleName = "'".(empty($ruleNameRaw) ? "Rule$ruleId" : pg_escape_string($ruleNameRaw)).($cmd == 'Duplicate' ? '99' : '')."'";
			$ruleDescRaw = $_POST['RuleDescription'];
			$ruleDesc = empty($ruleDescRaw) ? "NULL" : "'".pg_escape_string($ruleDescRaw)."'";
			$ruleSourceRaw = $_POST['Source'];
			$ruleSourceType = empty($ruleSourceRaw) ? "NULL" : "'".explode('|', $_POST['Source'])[0]."'";
			$ruleSourceID = empty($ruleSourceRaw) ? 0 : explode('|', $_POST['Source'])[1];
			$ruleActionIDRaw = $_POST['ActionID'];
			$ruleActionID = empty($ruleActionIDRaw) ? 0 : $ruleActionIDRaw;
			$ruleEnabled = isset($_POST['Enabled']) && $_POST['Enabled'] ? 'true':'false';

			if ($cmd == 'Update') {

				//Process Posted Values as Update

				$updQry = "UPDATE \"Rule\" SET \"RuleName\" = $ruleName, \"RuleDescription\" = $ruleDesc, \"SourceType\" = $ruleSourceType, \"SourceID\" = $ruleSourceID,
								\"ActionID\" = $ruleActionID, \"Enabled\" = $ruleEnabled
								WHERE \"RuleID\" = $ruleId
							;";

				$updResult = $db_object->query($updQry);

				if (MDB2::isError($updResult)) {
					error_log("Database Error Query: ".$updQry." ".$updResult->getMessage(), 0);
					die($updResult->getMessage());
				}//end db error

			}//end update

			else if ($cmd == 'Duplicate' || $cmd == 'Add') {

				//Process Posted Values as Duplicate

				$insQry = "INSERT INTO \"Rule\"(
								\"RuleName\", \"RuleDescription\", \"SourceType\", \"SourceID\",
								\"ActionID\", \"Enabled\")
							VALUES ($ruleName, $ruleDesc, $ruleSourceType, $ruleSourceID,
								$ruleActionID, $ruleEnabled);
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

		if (isset($_GET['rule'])) {
			$ruleIdParam = $_GET['rule'];
		} else {
			$ruleIdParam = -1;
		}

		//run queries to get Source and Action list data

		$sceQry = "SELECT \"SourceType\", \"SourceID\", \"SourceName\"
						FROM \"vwSources\"
						ORDER BY 1,2
					;";

		$sceResult = $db_object->query($sceQry);

		if (MDB2::isError($sceResult)) {
			error_log("Database Error Query: ".$sceQry." ".$sceResult->getMessage(), 0);
			die($sceQry.' - '.$sceResult->getMessage());
		}//end db error

		$actQry = "SELECT \"ActionID\", \"ActionName\"
						FROM \"Action\"
						ORDER BY 1
					;";

		$actResult = $db_object->query($actQry);

		if (MDB2::isError($actResult)) {
			error_log("Database Error Query: ".$actQry." ".$actResult->getMessage(), 0);
			die($actResult->getMessage());
		}//end db error

		$rowCount = 0;//assume not found
		$isNewRecord = false;

		if ($ruleIdParam > 0) {

			//then the main query...

			$ruleQry = "SELECT \"RuleID\", \"RuleName\", \"RuleDescription\", \"SourceType\", \"SourceID\",
							\"ActionID\", \"Enabled\"
							FROM \"Rule\"
							WHERE \"RuleID\" = $ruleIdParam
						;";

			$ruleResult = $db_object->query($ruleQry);

			if (MDB2::isError($ruleResult)) {
				error_log("Database Error Query: ".$ruleQry." ".$ruleResult->getMessage(), 0);
				die($ruleResult->getMessage());
			}//end db error

			$rowCount = $ruleResult->numRows();

			if ($rowCount == 1) {
				$ruleRow = $ruleResult->fetchRow(DICTCURSOR);
			} else {
				echo('<p style="text-align:center">Rule not found.</p>');
			}
		}

		if ($rowCount == 0 || $ruleIdParam <= 0) {

			//initialise values for new rule
			$isNewRecord = true;

			$nextIdQry = "SELECT max(\"RuleID\") FROM \"Rule\";";
			$nextIdResult = $db_object->query($nextIdQry);
			if (MDB2::isError($nextIdResult)) {
				error_log("Database Error Query: ".$nextIdQry." ".$nextIdResult->getMessage(), 0);
				die($nextIdResult->getMessage());
			}//end db error
			$nextId = $nextIdResult->fetchRow()[0] + 1;

			$ruleRow = [
				'RuleID' => $nextId,
				'RuleName' => 'Rule '.$nextId,
				'RuleDescription' => NULL,
				'SourceType' => NULL,
				'SourceID' => 0,
				'ActionID' => 0,
				'Enabled' => false
			];
		}

		$ruleId = $ruleRow['RuleID'];
		$ruleName = $ruleRow['RuleName'];
		$ruleDesc = $ruleRow['RuleDescription'];
		$ruleSourceType = $ruleRow['SourceType'];
		$ruleSourceID = $ruleRow['SourceID'];
		$ruleSource = $ruleSourceType.'|'.$ruleSourceID;
		$ruleActionID = $ruleRow['ActionID'];
		$ruleEnabled = $ruleRow['Enabled'] == 't' ? 'checked="checked"' : '';

		echo('<form action="rule.php?tab='.$_GET['tab'].'" method="post">');

			echo('<div class="EditorFormLabel">Rule ID:</div><div class="ReadOnlyEditorFormValue"><input name="RuleID" type="text" readonly="readonly" value="R'.$ruleId.'" /></div>');
			echo('<div class="EditorFormLabel">Rule Name:</div><div class="EditorFormValue"><input name="RuleName" type="text" value="'.$ruleName.'" /></div>');
			echo('<div class="EditorFormLabel">Rule Description:</div><div class="EditorFormValue"><input name="RuleDescription" type="text" value="'.$ruleDesc.'" /></div>');

			//Sources
			echo('<div class="EditorFormLabel">Source:</div><div class="EditorFormValue"><select name="Source" >');
			echo('<option value="">Choose...</option>');
			while($sceRow = $sceResult->fetchRow(DICTCURSOR)) {
				$sceId = $sceRow['SourceID'];
				$sceVal = $sceRow['SourceType'].'|'.$sceId;
				$sceName = $sceVal[0].$sceId.' - '.$sceRow['SourceName'];
				echo('<option value='.$sceVal.' '.($ruleSource == $sceVal?"selected":"").'>'.$sceName.'</option>');
			}
			echo('</select></div>');

			//Actions
			echo('<div class="EditorFormLabel">Action:</div><div class="EditorFormValue"><select name="ActionID">');
			echo('<option value="">Choose...</option>');
			while($actRow = $actResult->fetchRow(DICTCURSOR)) {
				$actVal = $actRow['ActionID'];
				$actName = 'A'.$actVal.' - '.$actRow['ActionName'];
				echo('<option value='.$actVal.' '.($ruleActionID == $actVal?"selected":"").'>'.$actName.'</option>');
			}
			echo('</select></div>');

			echo('<div class="EditorFormLabel">Enabled:</div><div class="EditorFormValue"><input name="Enabled" type="checkbox" '.$ruleEnabled.' /></div>');

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