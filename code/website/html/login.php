<?php
	session_start();
	// check the 'flash_error' key exists
	if (isset($_SESSION['flash_error'])) {
		$msg = $_SESSION['flash_error'];
	} else {
		$msg = 'Please sign in';
	}
?>
<html>
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="styles/hub.css">
	<title>Home Hub Login</title>
</head>
<body>
<form method="post" action="signIn.php">
	<div class="loginForm">
		<span class="flashMessage"><?= $msg ?></span><br /><br />
		<label for="username">Username</label>
		<input type="text" name="username"><br /><br />
		<label for="password">Password</label>
		<input type="password" name="password">
		<input type="submit" value="Sign in">
	</div>
</form>
</body>
</html>