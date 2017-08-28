<?php

	//Actuator Type management...
	require_once '../private_html/hub_connect.php';
	require_once 'auth.inc';

	if (isset($_POST['ActuatorTypeID'])) {

		$cmd = $_POST['Command'];
		$typeId = preg_replace('/\D/', '', $_POST['ActuatorTypeID']);

		if ($cmd == 'Remove') {

			//Process Posted Values as Delete

			$delQry = "DELETE FROM \"ActuatorType\" WHERE \"ActuatorTypeID\" = $typeId;";
			$delResult = $db_object->query($delQry);

			if (MDB2::isError($delResult)) {
				error_log("Database Error Query: ".$delQry." ".$delResult->getMessage(), 0);
				print $delResult->getMessage();
			}//end db error

		}//end remove

		else {

			//Common Update, Insert, Add conversions

			$typeNameRaw = $_POST['ActuatorTypeName'];
			$typeName = "'".(empty($typeNameRaw) ? "ActuatorType$typeId" : pg_escape_string($typeNameRaw))."'";
			$typeDescRaw = $_POST['ActuatorTypeDescription'];
			$typeDesc = empty($typeDescRaw) ? "NULL" : "'".pg_escape_string($typeDescRaw)."'";

			if ($cmd == 'Update') {

				//Process Posted Values as Update

				$updQry = "UPDATE \"ActuatorType\"
							SET \"ActuatorTypeName\" = $typeName,
								\"ActuatorTypeDescription\" = $typeDesc
								WHERE \"ActuatorTypeID\" = $typeId;
						  ";

				$updResult = $db_object->query($updQry);

				if (MDB2::isError($updResult)) {
					error_log("Database Error Query: ".$updQry." ".$updResult->getMessage(), 0);
					die($updQry.' - '.$updResult->getMessage());
				}//end db error

			}//end update

			else if ($cmd == 'Duplicate' || $cmd == 'Add') {

				//Process Posted Values as Insert

				$insQry = "INSERT INTO \"ActuatorType\"
							(\"ActuatorTypeName\", \"ActuatorTypeDescription\")
							VALUES ($typeName, $typeDesc);
						  ";

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

		if (isset($_GET['type'])) {
			$typeIdParam = $_GET['type'];
		} else {
			$typeIdParam = -1;
		}

		$rowCount = 0;//assume not found
		$isNewRecord = false;

		if ($typeIdParam > 0) {

			$typeQry = "SELECT \"ActuatorTypeID\", \"ActuatorTypeName\", \"ActuatorTypeDescription\"
						FROM \"ActuatorType\"
						WHERE \"ActuatorTypeID\" = $typeIdParam;
						";

			$typeResult = $db_object->query($typeQry);

			if (MDB2::isError($typeResult)) {
				error_log("Database Error Query: ".$typeQry." ".$typeResult->getMessage(), 0);
				die($typeQry.' - '.$typeResult->getMessage());
			}//end db error

			$rowCount = $typeResult->numRows();

			if ($rowCount == 1) {
				$typeRow = $typeResult->fetchRow(DICTCURSOR);
			} else {
				echo('<p style="text-align:center">Type not found.</p>');
			}
		}

		if ($rowCount == 0 || $typeIdParam <= 0) {

			//initialise values for new action
			$isNewRecord = true;

			$nextIdQry = "SELECT max(\"ActuatorTypeID\") FROM \"ActuatorType\";";
			$nextIdResult = $db_object->query($nextIdQry);
			if (MDB2::isError($nextIdResult)) {
				error_log("Database Error Query: ".$nextIdQry." ".$nextIdResult->getMessage(), 0);
				die($nextIdResult->getMessage());
			}//end db error
			$nextId = $nextIdResult->fetchRow()[0] + 1;

			$typeRow = [
				'ActuatorTypeID' => $nextId,
				'ActuatorTypeName' => 'Actuator Type '.$nextId,
				'ActuatorTypeDescription' => NULL
			];
		}

		$typeId = $typeRow['ActuatorTypeID'];
		$typeName = $typeRow['ActuatorTypeName'];
		$typeDesc = $typeRow['ActuatorTypeDescription'];

		echo('<form action="actuatorType.php?tab='.$_GET['tab'].'" method="post">');

			echo('<div class="EditorFormLabel">Actuator Type Number:</div><div class="ReadOnlyEditorFormValue"><input name="ActuatorTypeID" type="text" readonly="readonly" value="Y'.$typeId.'" /></div>');
			echo('<div class="EditorFormLabel">Actuator Type Name:</div><div class="EditorFormValue"><input name="ActuatorTypeName" type="text" value="'.$typeName.'" /></div>');
			echo('<div class="EditorFormLabel">Description:</div><div class="EditorFormValue"><input name="ActuatorTypeDescription" type="text" value="'.$typeDesc.'" /></div>');

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