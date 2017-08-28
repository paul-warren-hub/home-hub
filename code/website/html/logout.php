<?php
	session_start();
    $_SESSION['flash_error'] = "User Logged Out";
    $_SESSION['signed_in'] = false;
    $_SESSION['username'] = null;
    $_SESSION['userrole'] = null;
    header("Location: login.php");
?>