<?php

	//Setting management...
	require_once '../private_html/hub_connect.php';

	$newNameDefault = '<Name>';
	$newValueDefault = '<Value>';

	if (isset($_POST['Command'])) {
		if ($_POST['Command'] == 'Update') {
			//Process Posted Values
			foreach( $_POST as $name => $value ) {

				if ($name !== 'Command') {

					$updQry = "UPDATE \"UserSetting\" SET \"Value\" = '$value' WHERE \"Name\" = '$name';";
					$updResult = $db_object->query($updQry);

					if (MDB2::isError($updResult)) {
						error_log("Database Error Query: ".$updQry." ".$updResult->getMessage(), 0);
						die($updResult->getMessage());
					}//end db error

				}//end real setting

			}//next setting

			//Now any New Values...
			$newName = $_POST['NewName'];
			if ($newName != $newNameDefault) {
				$initialValue = $_POST['InitialValue'];
				$newSettingQry = "INSERT INTO \"UserSetting\" (\"Name\", \"Value\")
									SELECT '$newName', '$initialValue'
									WHERE
										NOT EXISTS (
											SELECT \"Name\"
											FROM \"UserSetting\"
											WHERE \"Name\" = '$newName'
										);
									";

				$newSettingResult = $db_object->query($newSettingQry);

				if (MDB2::isError($newSettingResult)) {
					error_log("Database Error Query: ".$newSettingQry." ".$newSettingResult->getMessage(), 0);
					die($newSettingResult->getMessage());
				}//end db error
			}
		}//end update

		echo('<script type="text/javascript">');
		echo('setTimeout(function(){');
		echo('var newUrl = "home.php?page=Organisation&tab=9";');
		echo('document.location.href = newUrl;');
		echo('}, 0);');
		echo('</script>');

	} else {

		//Populate Form for editing...

		//run main query...

		$settingsQry = "SELECT \"Name\", \"Value\" FROM \"UserSetting\";";

		$settingsResult = $db_object->query($settingsQry);

		if (MDB2::isError($settingsResult)) {
			error_log("Database Error Query: ".$settingsQry." ".$settingsResult->getMessage(), 0);
			die($settingsResult->getMessage());
		}//end db error

		$rowCount = $settingsResult->numRows();

		echo('<form action="setting.php?tab=9" method="post"><div style="padding:12px">');
		$settingIndex = 0;
		echo('<table>');
		while ($settingsRow = $settingsResult->fetchRow(DICTCURSOR)) {

			echo('<tr><td></td>');

			$name = $settingsRow['Name'];
			$niceName = preg_replace('/(?!^)[A-Z]{2,}(?=[A-Z][a-z])|[A-Z][a-z]/', ' $0', $name);

			$value = $settingsRow['Value'];

			echo('<td style="white-space:nowrap;width: 95%;">'.$niceName.'</td><td><input name="'.$name.'" type="text" value="'.$value.'" style="text-align: right;padding-right: 4px;"/></td>');

			echo('</tr>');

		}//wend

		echo('<tr>');
		echo('<td><span>*</span></td><td><input name="NewName" type="text" value="'.$newNameDefault.'" style="color:darkGray;width: 95%;"/></td><td><input name="InitialValue" type="text" value="'.$newValueDefault.'" style="color:darkGray;text-align: right;padding-right: 4px;"/></td>');
		echo('</tr>');

		echo('</table>');

		echo('<br /><br /><div class="EditorButtonRow"><input name="Command" type="Submit" value="Cancel">&nbsp;<input name="Command" type="Submit" value="Update"></div>');

		echo('</div></form>');

	}//end GET

?>