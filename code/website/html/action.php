<?php

	//Action management...
	require_once '../private_html/hub_connect.php';
	require_once 'auth.inc';

	if (isset($_POST['ActionID'])) {

		$cmd = $_POST['Command'];
		$actionId = preg_replace('/\D/', '', $_POST['ActionID']);

		if ($cmd == 'Remove') {

			//Process Posted Values as Delete

			$delQry = "DELETE FROM \"Action\" WHERE \"ActionID\" = $actionId;";
			$delResult = $db_object->query($delQry);

			if (MDB2::isError($delResult)) {
				error_log("Database Error Query: ".$delQry." ".$delResult->getMessage(), 0);
				die($delResult->getMessage());
			}//end db error

		}//end remove

		else {

			//Common Update, Insert, Add conversions

			$actionNameRaw = $_POST['ActionName'];
			$actionName = "'".(empty($actionNameRaw) ? "Action$actionId" : pg_escape_string($actionNameRaw)).($cmd == 'Duplicate' ? '99' : '')."'";
			$actionDescRaw = $_POST['ActionDescription'];
			$actionDesc = empty($actionDescRaw) ? "NULL" : "'".pg_escape_string($actionDescRaw)."'";
			$actionFunctionRaw = $_POST['ActionFunction'];
			$actionFunction = empty($actionFunctionRaw) ? "NULL" : "'".pg_escape_string($actionFunctionRaw)."'";
			$emailRecipientRaw = $_POST['EmailRecipient'];
			$emailRecipient = empty($emailRecipientRaw) ? "NULL" : "'".pg_escape_string($emailRecipientRaw)."'";
			$txtRecipientRaw = $_POST['TextRecipient'];
			$txtRecipient = empty($txtRecipientRaw) ? "NULL" : "'".pg_escape_string($txtRecipientRaw)."'";
			$actionEnabled = isset($_POST['Enabled']) && $_POST['Enabled'] ? 'true':'false';

			if ($cmd == 'Update') {

				//Process Posted Values

				$updQry = "UPDATE \"Action\" SET \"ActionName\" = $actionName, \"ActionDescription\" = $actionDesc, \"ActionFunction\" = $actionFunction,
								\"EmailRecipient\" = $emailRecipient,
								\"TextRecipient\" = $txtRecipient, \"Enabled\" = $actionEnabled
								WHERE \"ActionID\" = $actionId
							;";

				$updResult = $db_object->query($updQry);

				if (MDB2::isError($updResult)) {
					error_log("Database Error Query: ".$updQry." ".$updResult->getMessage(), 0);
					die($updResult->getMessage());
				}//end db error

			}//end update

			else if ($cmd == 'Duplicate' || $cmd == 'Add') {

				//Process Posted Values as Insert

				$insQry = "INSERT INTO \"Action\" (\"ActionName\", \"ActionDescription\", \"ActionFunction\",
											\"Enabled\", \"EmailRecipient\", \"TextRecipient\")
										VALUES ($actionName, $actionDesc, $actionFunction,
											$actionEnabled, $emailRecipient, $txtRecipient)
							;";
				$insResult = $db_object->query($insQry);

				if (MDB2::isError($insResult)) {
					error_log("Database Error Query: ".$insQry." ".$insResult->getMessage(), 0);
					die($insQry.' - '.$insResult->getMessage());
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

		if (isset($_GET['action'])) {
			$actionIdParam = $_GET['action'];
		} else {
			$actionIdParam = -1;
		}

		$rowCount = 0;//assume not found
		$isNewRecord = false;

		if ($actionIdParam > 0) {

			$actionQry = "SELECT \"ActionID\", \"ActionName\", \"ActionDescription\",
									\"ActionFunction\", \"Enabled\",
									\"EmailRecipient\", \"TextRecipient\"
							FROM \"Action\"
							WHERE \"ActionID\" = $actionIdParam;
						 ";

			$actionResult = $db_object->query($actionQry);

			if (MDB2::isError($actionResult)) {
				error_log("Database Error Query: ".$actionQry." ".$actionResult->getMessage(), 0);
				die($actionResult->getMessage());
			}//end db error

			$rowCount = $actionResult->numRows();

			if ($rowCount == 1) {
				$actionRow = $actionResult->fetchRow(DICTCURSOR);
			} else {
				echo('<p style="text-align:center">Action not found.</p>');
			}
		}

		if ($rowCount == 0 || $actionIdParam <= 0) {

			//initialise values for new action
			$isNewRecord = true;

			$nextIdQry = "SELECT max(\"ActionID\") FROM \"Action\";";
			$nextIdResult = $db_object->query($nextIdQry);
			if (MDB2::isError($nextIdResult)) {
				error_log("Database Error Query: ".$nextIdQry." ".$nextIdResult->getMessage(), 0);
				die($nextIdResult->getMessage());
			}//end db error
			$nextId = $nextIdResult->fetchRow()[0] + 1;

			$actionRow = [
				'ActionID' => $nextId,
				'ActionName' => 'Action '.$nextId,
				'ActionDescription' => NULL,
				'ActionFunction' => NULL,
				'EmailRecipient' => NULL,
				'TextRecipient' => NULL,
				'Enabled' => false
			];
		}

		$actionId = preg_replace('/\D/', '', $actionRow['ActionID']);
		$actionName = $actionRow['ActionName'];
		$actionDesc = $actionRow['ActionDescription'];
		$actionFunction = $actionRow['ActionFunction'];
		$emailRecipient = $actionRow['EmailRecipient'];
		$txtRecipient = $actionRow['TextRecipient'];
		$actionEnabled = $actionRow['Enabled'] == 't' ? 'checked="checked"' : '';

		echo('<form action="action.php?tab='.$_GET['tab'].'" method="post">');

			echo('<input name="ActionID" type="hidden" value="'.$actionId.'" />');
			echo('<div class="EditorFormLabel">Action ID:</div><div class="ReadOnlyEditorFormValue"><input type="text" readonly="readonly" value="A'.$actionId.'" /></div>');
			echo('<div class="EditorFormLabel">Action Name:</div><div class="EditorFormValue"><input name="ActionName" type="text" value="'.$actionName.'" /></div>');
			echo('<div class="EditorFormLabel">Action Description:</div><div class="EditorFormValue"><input name="ActionDescription" type="text" value="'.$actionDesc.'" /></div>');
			echo('<div class="EditorFormLabel">Action Function:</div><div class="EditorFormValue"><input name="ActionFunction" type="text" value="'.$actionFunction.'" /></div>');
			echo('<div class="EditorFormLabel">Email Recipient:</div><div class="EditorFormValue"><input name="EmailRecipient" type="text" value="'.$emailRecipient.'" /></div>');
			echo('<div class="EditorFormLabel">Txt Recipient:</div><div class="EditorFormValue"><input name="TextRecipient" type="text" value="'.$txtRecipient.'" /></div>');
			echo('<div class="EditorFormLabel">Enabled:</div><div class="EditorFormValue"><input name="Enabled" type="checkbox" '.$actionEnabled.' /></div>');

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