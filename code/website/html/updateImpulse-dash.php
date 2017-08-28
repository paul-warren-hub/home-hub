<?php

	session_start();
	$updBy = ucfirst($_SESSION['username']);

	// Require scripts
	require_once '../private_html/hub_connect.php';

	//Check Postback for an VALUE update
	if (isset($_POST['impulseId'])) {
		$status = '1';
		$impulseId = $_POST['impulseId'];

		$insQry = 'INSERT INTO "EventQueue" ("SourceID", "SourceType", "SourceAgent", "Value") VALUES ('.$impulseId.', \'Impulse\', \''.$updBy.'\', 1);';

		$insResult = $db_object->query($insQry);

		if (MDB2::isError($insResult)) {
			error_log("Database Error Query: ".$insQry." ".$insResult->getMessage(), 0);
			$status = '0';
		}//end db error

		echo($status);

	}//end process postback

?>