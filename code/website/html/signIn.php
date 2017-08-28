<?php
  session_start();

  require_once '../private_html/hub_connect.php';
  // Require above scripts

  if (IsSet($_POST['username']) && IsSet($_POST['password'])) {
	//Login by Form
  	$username = $_POST['username'];
  	$password = sha1($_POST['password']);
  }

  $selQry  = "SELECT \"Username\",\"Role\" ";
  $selQry .= "FROM \"User\" ";
  $selQry .= "WHERE \"Username\"='$username' AND \"Password\"='$password'";

  $qryResult = $db_object->query($selQry);

  if (MDB2::isError($qryResult)) {
	error_log("Database Error Query: ".$selQry." ".$selResult->getMessage(), 0);
	die();
  }//end db error

  // clear out any existing session that may exist
  session_destroy();
  session_start();
  $rowCount = $qryResult->numRows();
  if ($rowCount === 1) {
    $userRow = $qryResult->fetchRow(DICTCURSOR);
    $_SESSION['signed_in'] = true;
    $_SESSION['username'] = $username;
    $_SESSION['userrole'] = $userRow['Role'];
    $locUrl = 'home.php';
    if (isset($_GET['page'])) {
    	$locUrl .= '?page='.$_GET['page'];
    }
    if (isset($_GET['impulse'])) {
    	$locUrl .= '&impulse='.$_GET['impulse'];
    }
    header("Location: $locUrl");
  } else {
    $_SESSION['flash_error'] = "Invalid username or password";
    $_SESSION['signed_in'] = false;
    $_SESSION['username'] = null;
    $_SESSION['userrole'] = null;
    header("Location: login.php");
  }
?>