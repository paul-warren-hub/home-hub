<?php

// Get Chart Data for all sensors linked through conditions, rules, actions to actuators

require_once '../private_html/hub_connect.php';
// Require above scripts

$chartIndex = 1;
if (isset($_GET['element'])) {
	$chartIndex = $_GET['element'];
}

//get data from map view

$mapQry = "SELECT \"ChainNum\", \"NodeID\", \"Enabled\", \"Description\", \"ParentID\", \"DbId\"
			FROM \"vwMap$chartIndex\"
		   ";
$mapResult = $db_object->query($mapQry);

if (MDB2::isError($mapResult)) {
	error_log("Database Error Query: ".$mapQry." ".$mapResult->getMessage(), 0);
	die($mapQry.' - '.$mapResult->getMessage());
}//end db error

$rowCount = $mapResult->numRows();

$dataTable = new stdClass;

$nodeCol = new stdClass;//Node
$nodeCol->id = 'Node';
$nodeCol->label = 'Node';
$nodeCol->type = 'string';
$parentCol = new stdClass;//Parent or null for roots
$parentCol->id = 'Parent';
$parentCol->label = 'Parent';
$parentCol->type = 'string';
$tooltipCol = new stdClass;//Tooltip
$tooltipCol->id = 'ToolTip';
$tooltipCol->label = 'ToolTip';
$tooltipCol->type = 'string';
$dbIdCol = new stdClass;//Tooltip
$dbIdCol->id = 'DbId';
$dbIdCol->label = 'DbId';
$dbIdCol->type = 'string';


$colsArray = array($nodeCol, $parentCol, $tooltipCol, $dbIdCol);//array of col defs

$rowArray = array();//row array
$rowsArray = array();//rows array

if ($rowCount > 0) {

	$x = 0;
	while ($mapRow = $mapResult->fetchRow(DICTCURSOR)) {

		$nodeId = $mapRow['NodeID'];

		if ($nodeId != null && $nodeId != '') {

			$chainNum = $mapRow['ChainNum'];
			$dbId = $mapRow['DbId'];
			$parId = $mapRow['ParentID'];
			$desc = $mapRow['Description'];

			$rowArray = array();//clear rows array
			$rowObject = new stdClass;

			$mapNode = new stdClass;//Node definition
			$mapNode->v = $chainNum.$nodeId;
			if ($mapRow['Enabled'] == 't') {
				$mapNode->f = '<div style="color: black;">'.$nodeId.'</div>';
			} else {
				$mapNode->f = '<div style="color: silver;">'.$nodeId.'</div>';
			}
			$mapParent = new stdClass;//Node definition
			$mapParent->v = ($parId != ''?$chainNum:'').$parId;
			$mapToolTip = new stdClass;//Node definition
			$mapToolTip->v = $mapRow['Description'];
			$mapDbIdent = new stdClass;//db key
			$mapDbIdent->v = $dbId;
			array_push($rowArray, $mapNode, $mapParent, $mapToolTip, $mapDbIdent);
			$rowObject->c = $rowArray;
			array_push($rowsArray, $rowObject);
			$x++;

		}//end valid node

	}//wend
}//end some nodes
$dataTable->cols = $colsArray;
$dataTable->rows = $rowsArray;
echo json_encode($dataTable);
?>