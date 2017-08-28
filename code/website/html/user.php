<?php

	//User management...
	require_once '../private_html/hub_connect.php';
	require_once 'auth.inc';

	$passwordMask = '**********';//Prevent display of hashed password

	if (isset($_POST['UserID'])) {

		$cmd = $_POST['Command'];
		$userId = $_POST['UserID'];

		if ($cmd == 'Remove') {

			//Process Posted Values as Delete

			$userId = $_POST['UserID'];
			$delQry = "DELETE FROM \"User\" WHERE \"UserID\" = $userId;";
			$delResult = $db_object->query($delQry);

			if (MDB2::isError($delResult)) {
				error_log("Database Error Query: ".$delQry." ".$delResult->getMessage(), 0);
				die($delResult->getMessage());
			}//end db error

		}//end remove

		else {

			//Common Update, Insert, Add conversions

				$userNameRaw = $_POST['Username'];
				$userName = "'".(empty($userNameRaw) ? "User$userId" : pg_escape_string($userNameRaw)).($cmd == 'Duplicate' ? '99' : '')."'";
				$rawPassword = $_POST['Password'];
				$password = sha1($rawPassword);
				$userRole = "'".$_POST['Role']."'";
				$userMobileRaw = $_POST['MobilePhoneNum'];
				$userMobile = "'".empty($userMobileRaw) ? "NULL" : pg_escape_string($userMobileRaw)."'";

			if ($cmd == 'Update') {

				//Process Posted Values as Delete

				//Only update password if it has been edited
				$passwordSpec = '';
				if ($rawPassword != $passwordMask) {
					$passwordSpec = "\"Password\" = '$password',";
				}

				$updQry = "UPDATE \"User\" SET \"Username\" = $userName,
								$passwordSpec
								\"Role\" = $userRole,
								\"MobilePhoneNum\" = $userMobile
								WHERE \"UserID\" = $userId;
						   ";

				$updResult = $db_object->query($updQry);

				if (MDB2::isError($updResult)) {
					error_log("Database Error Query: ".$updQry." ".$updResult->getMessage(), 0);
					die($updResult->getMessage());
				}//end db error

			}//end update

			else if ($cmd == 'Duplicate') {

				//Process Posted Values as Insert
				$passwordSpec = '"Password",';
				$dupeQry = "INSERT INTO \"User\" (\"Username\", \"Password\", \"Role\",
											\"MobilePhoneNum\") (
										SELECT  $userName,
												$passwordSpec
												$userRole,
												$userMobile
										FROM \"User\"
										WHERE \"UserID\" = $userId
							);";

				$dupeResult = $db_object->query($dupeQry);

				if (MDB2::isError($dupeResult)) {
					error_log("Database Error Query: ".$dupeQry." ".$dupeResult->getMessage(), 0);
					die($dupeResult->getMessage());
				}//end db error

			}//end duplicate

			else if ($cmd == 'Add') {

				//Process Posted Values as Insert

				$passwordSpec = "'$password',";

				$insQry = "INSERT INTO \"User\" (\"Username\", \"Password\", \"Role\",
											\"MobilePhoneNum\")
										VALUES ( $userName,
												 $passwordSpec
												 $userRole,
												 $userMobile
										);
						  ";

				$insResult = $db_object->query($insQry);

				if (MDB2::isError($insResult)) {
					error_log("Database Error Query: ".$insQry." ".$insResult->getMessage(), 0);
					die($insResult->getMessage());
				}//end db error

			}//end add


		}//end update, insert, add

		echo('<script type="text/javascript">');
		echo('setTimeout(function(){');
		echo('var newUrl = "home.php?page=Organisation&tab='.$_GET['tab'].'";');
		echo('document.location.href = newUrl;');
		echo('}, 0);');
		echo('</script>');

	} else {

		//Populate Form for editing...

		if (isset($_GET['user'])) {
			$userIdParam = $_GET['user'];
		} else {
			$userIdParam = -1;
		}

		//run check query so we don't lose sole admin user

		$adminsQry = "SELECT Count(\"UserID\") AS \"OtherAdmins\"
					 FROM \"User\"
					 WHERE \"Role\" = 'admin'
					 AND \"UserID\" != $userIdParam
					 ;";

		$adminsResult = $db_object->query($adminsQry);

		if (MDB2::isError($adminsResult)) {
			error_log("Database Error Query: ".$adminsQry." ".$adminsResult->getMessage(), 0);
			die($adminsResult->getMessage());
		}//end db error

		$adminsRow = $adminsResult->fetchRow(DICTCURSOR);
		$keepAdmin = ($adminsRow['OtherAdmins'] == 0);

		$rowCount = 0;//assume not found
		$isNewRecord = false;

		if ($userIdParam > 0) {

			//run main query...don't display hashed password

			$usersQry = "SELECT \"UserID\", \"Username\", '$passwordMask' AS \"Password\", \"Role\", \"MobilePhoneNum\"
						 FROM \"User\"
						 WHERE \"UserID\" = $userIdParam
						 ;";

			$usersResult = $db_object->query($usersQry);

			if (MDB2::isError($usersResult)) {
				error_log("Database Error Query: ".$usersQry." ".$usersResult->getMessage(), 0);
				die($usersResult->getMessage());
			}//end db error

			$rowCount = $usersResult->numRows();
			if ($rowCount == 1) {
				$usersRow = $usersResult->fetchRow(DICTCURSOR);
			} else {
				echo('<p style="text-align:center">User not found.</p>');
			}

		}

		if ($rowCount == 0 || $userIdParam <= 0) {

			//initialise values for new user
			$isNewRecord = true;

			$nextIdQry = "SELECT max(\"UserID\") FROM \"User\";";
			$nextIdResult = $db_object->query($nextIdQry);
			if (MDB2::isError($nextIdResult)) {
				error_log("Database Error Query: ".$nextIdQry." ".$nextIdResult->getMessage(), 0);
				die($nextIdResult->getMessage());
			}//end db error
			$nextId = $nextIdResult->fetchRow()[0] + 1;

			$usersRow = [
				'UserID' => $nextId,
				'Username' => 'user'.$nextId,
				'Password' => NULL,
				'Role' => 'occupant',
				'MobilePhoneNum' => NULL
			];
		}

		$userId = $usersRow['UserID'];
		$name = $usersRow['Username'];
		$password = $usersRow['Password'];
		$role = $usersRow['Role'];
		$mobile = $usersRow['MobilePhoneNum'];

		echo('<form action="user.php?tab='.$_GET['tab'].'" method="post"><div style="padding:12px">');

			echo('<div class="EditorFormLabel">User ID:</div><div class="ReadOnlyEditorFormValue"><input name="UserID" type="text" readonly="readonly" value="'.$userId.'" /></div>');
			echo('<div class="EditorFormLabel">Username:</div><div class="EditorFormValue"><input name="Username" type="text" value="'.$name.'" /></div>');
			echo('<div class="EditorFormLabel">Password:</div><div class="EditorFormValue"><input name="Password" type="password" value="'.$password.'" /></div>');
			echo('<div class="EditorFormLabel">Mobile:</div><div class="EditorFormValue"><input name="MobilePhoneNum" type="text" value="'.$mobile.'" /></div>');

			//Roles
			echo('<div class="EditorFormLabel">Role:</div><div class="EditorFormValue">');
				if ($keepAdmin) {
					echo('<input type="hidden" name="Role" value="admin" />');
					echo('<select name="Role" title="Cannot change sole administrator" disabled>');
				} else {
					echo('<select name="Role">');
				}
				echo('<option value="occupant"'.($role == 'occupant'?" selected":"").'>Occupant</option>');
				echo('<option value="admin"'.($role == 'admin'?" selected":"").'>Admin</option>');
			echo('</select></div>');

			echo('<div class="EditorButtonRow">');
				echo('<input name="Command" type="Submit" value="Cancel">');
				if ($isNewRecord) {
					echo('<input name="Command" type="Submit" value="Add">');
				} else {
					echo('<input name="Command" type="Submit" value="Update">');
					echo('<input name="Command" type="Submit" value="Duplicate">');
					echo('<input name="Command" type="Submit" value="Remove"'.($keepAdmin?' title="Cannot remove sole administrator" disabled':'').' onclick="return confirm(\'Are you sure you want to remove this element?\')">');
				}
			echo('</div>');

		echo('</form>');

	}//end GET

?>