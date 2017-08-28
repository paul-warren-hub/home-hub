<?php

//require the PEAR::MDB2 classes.

require_once 'MDB2.php';

//Makes resultsets into column-name-addressable dictionaries
define("DICTCURSOR", MDB2_FETCHMODE_ASSOC);

//Define some constants
$db_engine = "pgsql";
$db_user = "postgres";
$db_pass = "raspberry";
$db_host = "localhost:5432";
$db_name = "hub";

//Assemble datasource name
$datasource = $db_engine.'://'.$db_user.':'.$db_pass.'@'.$db_host.'/'.$db_name;
//Define connection options
$options = array(
 'debug' => 2,
 'result_buffering' => true,
 'portability' => MDB2_PORTABILITY_NONE
);

$db_object = MDB2::connect($datasource, $options);

if (MDB2::isError($db_object)) {
	error_log("Database Error: ".$db_object->getMessage(), 0);
	die($db_object->getMessage());
}

?>