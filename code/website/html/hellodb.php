<?php

   //
   // hellodb.php script to show PostgreSQL and PHP working together
   //

   // Required scripts
   require_once '../private_html/hub_connect.php';

   $qry = "SELECT * FROM \"Zone\"";

   $result = $db_object->query($qry);

   if (MDB2::isError($result)) {
     die($result->getMessage());
   }//end db error

   echo "Show me the zones:<br />";
     while ($row = $result->fetchRow(DICTCURSOR)) {
     print $row["ZoneID"].' '.$row["ZoneName"].'<br />';
   }//end while row

?>