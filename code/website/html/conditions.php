<?php

	//Enable conditions to be edited
	require_once '../private_html/hub_connect.php';
	require_once 'auth.inc';

	//Loop through Conditions enabling value edits...

	//First get a list of sensors
	$sensQry = "SELECT * FROM \"vwSensorList\"";
	$sensResult = $db_object->query($sensQry);

	if (MDB2::isError($sensResult)) {
		error_log("Database Error Query: ".$sensQry." ".$sensResult->getMessage(), 0);
		die($sensResult->getMessage());
	}//end db error

	$sensList = array();
	while ($sensRow = $sensResult->fetchRow(DICTCURSOR)) {
			$sensName = $sensRow['SensorName'];
			$sensValue = $sensRow['SensorRef'];
			$sensList[$sensValue] = $sensName;
	}//wend

	$condQry = 'SELECT "ConditionID", "ConditionName", "ConditionDescription",
						"CurrentCondition", "SetExpression", "ResetExpression", "Source",
						"SetOperator", "SetThreshold", "ResetOperator", "ResetThreshold", "ConditionFormat", "Enabled"
				FROM "vwAllConditions"
				ORDER BY "ConditionFormat" DESC, "ConditionID"
			   ';

	$condResult = $db_object->query($condQry);

	if (MDB2::isError($condResult)) {
		error_log("Database Error Query: ".$condQry." ".$condResult->getMessage(), 0);
		die($condResult->getMessage());
	}//end db error

	while ($condRow = $condResult->fetchRow(DICTCURSOR)) {
	  processCondition($condRow, $sensList, false, -1);
	}//wend

	echo('<div style="border: 1px solid black;padding: 3px;">');
		echo('<p style="padding: 3px">New Condition - choose one of the following:</p>');
		echo('<label><input type="radio" name="condType" value="newTimeDiv" onchange="expose(this);" />&nbsp;Time-based</label><br />');

		if ($_SESSION['userrole'] != 'occupant') {

			echo('<label><input type="radio" name="condType" value="newSimpleDiv" onchange="expose(this);" />&nbsp;Sensor-based</label><br />');
			echo('<label><input type="radio" name="condType" value="newComplexDiv" onchange="expose(this);" />&nbsp;Complex</label><br />');
		}
		echo('<br /><br />');
		echo('<div class="newCondDiv" id="newSimpleDiv" style="display:none;">');

			$condRow = array(
				'ConditionID'=>-1,
				'ConditionName'=>'Enter Name...',
				'ConditionDescription'=>'Enter description...',
				'CurrentCondition'=>'f',
				'Source'=>'',
				'SetExpression'=>'',
				'ResetExpression'=>'',
				'SetThreshold'=>'',
				'ResetThreshold'=>'',
				'SetOperator'=>'',
				'ResetOperator'=>'',
				'ConditionFormat'=>'Simple',
				'Enabled'=>'f'
			);
			processCondition($condRow, $sensList, true, $condResult->numRows());

		echo('</div>');

		echo('<div class="newCondDiv" id="newTimeDiv" style="display:none;">');

			$condRow = array(
				'ConditionID'=>-2,
				'ConditionName'=>'Enter Name...',
				'ConditionDescription'=>'Enter description...',
				'CurrentCondition'=>'f',
				'Source'=>'',
				'SetExpression'=>'',
				'ResetExpression'=>'',
				'SetThreshold'=>'',
				'ResetThreshold'=>'',
				'SetOperator'=>'',
				'ResetOperator'=>'',
				'ConditionFormat'=>'Time',
				'Enabled'=>'f'
			);
			processCondition($condRow, $sensList, true, $condResult->numRows());

		echo('</div>');

		echo('<div class="newCondDiv" id="newComplexDiv" style="display:none;">');

			$condRow = array(
				'ConditionID'=>-3,
				'ConditionName'=>'Enter Name...',
				'ConditionDescription'=>'Enter description...',
				'CurrentCondition'=>'f',
				'Source'=>'',
				'SetExpression'=>'',
				'ResetExpression'=>'',
				'SetThreshold'=>'',
				'ResetThreshold'=>'',
				'SetOperator'=>'',
				'ResetOperator'=>'',
				'ConditionFormat'=>'Complex',
				'Enabled'=>'f'
			);
			processCondition($condRow, $sensList, true, $condResult->numRows());

		echo('</div>');

	echo('</div>');

	function processCondition($condRow, $sensList, $newCondition, $condResultRows) {

		$condId =  $condRow['ConditionID'];
		$condName =  $condRow['ConditionName'];
		$condDesc =  $condRow['ConditionDescription'];
		$curCond =  $condRow['CurrentCondition'];
		$source =  $condRow['Source'];
		$setExpr =  $condRow['SetExpression'];
		$rstExpr =  $condRow['ResetExpression'];
		$setThresh =  (string)$condRow['SetThreshold'];
		$resetThresh = (string)$condRow['ResetThreshold'];
		$excOp =  $condRow['SetOperator'];
		$normOp =  $condRow['ResetOperator'];
		$condFmt = $condRow['ConditionFormat'];
		$condEnab = ($condRow['Enabled'] == 't');

		if ($_SESSION['userrole'] != 'occupant' or $condFmt == 'Time') {

			echo('<span class="ConditionNumber">'.($condId < 0 ? $condResultRows+1 : $condId).'.&nbsp;</span>');
			echo('<input id="ConditionName'.$condId.'" class="ConditionNameEdit" value="'.$condName.'" /><br />');
			echo('<input id="ConditionDesc'.$condId.'" class="ConditionDescEdit" value="'.$condDesc.'" /><br />');
			echo('<div class="ConditionSummary">');
			echo('Current State: '.($curCond == 't'?'On':'Off').'<br />');
			serveEnabSwitch($condId, $condEnab);
			echo('</div>');

			switch ($condFmt) {

				case 'Time':

					echo('<div class="ThresholdForm" style="float:left;">');
						echo('<div class="TimeSpinnerWrapper" style="margin-left: 32px">');
							serveTimeSpinner('ExceptionThreshold'.$condId, $setThresh, 'green');
						echo('</div>');
						echo('<div class="TimeSpinnerWrapper" style="margin-right: 32px;">');
							echo('<div class="FromToLabel">&nbsp;to&nbsp;</div>');
							serveTimeSpinner('NormalThreshold'.$condId, $resetThresh, 'orange');
						echo('</div>');
						echo('<br style="clear:both;" />');
						echo('<select id="TimeSource'.$condId.'" name="ConditionSource" class="ConditionSource">');
							serveSourceDropdown($source);
						echo('</select>&nbsp;');
					echo('</div>');

					break;

				case 'Simple':

					echo('<div class="ThresholdTitle">Exception Threshold</div>');
					echo('<div class="ThresholdForm">');
							serveSensorDropdown($condId, $source, $sensList);
							echo('<br />');
							echo('<select id="ExceptionOperator'.$condId.'" name="ExceptionOperator" style="margin-left:0px;margin-right:0px">');
								serveOperatorDropdown($excOp);
							echo('</select>&nbsp;');
							serveThresholdTextbox('ExceptionThreshold'.$condId, $setThresh, 'green');
					echo('</div>');

					echo('<div class="ThresholdTitle">Return to Normal Threshold</div>');
					echo('<div class="ThresholdForm">');
						echo('<select id="NormalOperator'.$condId.'" name="NormalOperator" style="margin-left:24px;margin-right:48px">');
							serveOperatorDropdown($normOp);
						echo('</select>');
						serveThresholdTextbox('NormalThreshold'.$condId, $resetThresh, 'orange');
					echo('</div>');

					break;

				case 'Complex':

					echo('<textarea id="SetExpression'.$condId.'" class="ComplexExpressionEditor">'.$setExpr.'</textarea>');
					echo('<textarea id="ResetExpression'.$condId.'" class="ComplexExpressionEditor">'.$rstExpr.'</textarea>');

					break;

			}//end switch

			echo('<br style="clear:both;" />');
			echo('<div class="ConditionControls">');
				echo('<button class="ConditionButton" type="button" onclick="doUpdate('.$condId.',\''.$condFmt.'\',\''.$source.'\')">'.($newCondition?'Save':'Update').'</button>');
				echo('<button class="ConditionButton" type="button" onclick="doDuplicate('.$condId.',\''.$condFmt.'\',\''.$source.'\')"'.($newCondition?' disabled=disabled':'').'>Duplicate</button>');
				echo('<button class="ConditionButton" type="button" onclick="if (confirm(\'Are you sure?\')) {doRemove('.$condId.',\''.$condFmt.'\',\''.$source.'\')}"'.($newCondition?' disabled=disabled':'').'>Remove</button>');
			echo('</div><br />');// end ConditionControls

		}//end qualifying condition for user

	  }//end process condition

	function serveEnabSwitch($condId, $condEnab) {
		//Enable/Disable
		echo('<div class="switch-wrapper">');
		echo('<input class="enabSwitch" id="condEnabDisab'.$condId.'" type="checkbox" ');
		echo(($condEnab? 'checked':'').' /></div>');
	}//end serve enab switch

	function serveSensorDropdown($condId, $sce, $sensList) {
		echo('<select id="condSensSelect'.$condId.'" class="SensorSelect">');
	  	foreach ($sensList as $sensValue => $sensName) {
			echo('<option value="'.$sensValue.'"'.($sensValue == $sce?' selected="selected"':'').'>'.$sensName.'</option>');
		}//next sensor
		echo('</select>');
	}//end serve sensor dropdown

	function serveOperatorDropdown($op) {
		$options = array('>','>=','=','<','<=');
		foreach ($options as $k => $v) {
			echo('<option value="'.$k.'"'.($v == $op?' selected="selected"':'').'>'.$v);
			echo('</option>');
		}
	}//end serve operator dropdown

	function serveSourceDropdown($op) {
		$options = array('TOD' => 'Daily','TOWD' => 'Weekdays','TOWE' => 'Weekends');
		foreach ($options as $optionKey => $optionValue) {
			echo('<option value="'.$optionKey.'"'.($optionKey == $op?' selected="selected"':'').'>'.$optionValue);
			echo('</option>');
		}
	}//end serve operator dropdown

	function serveThresholdTextbox($id, $val, $col) {
		echo('<input class="spinnable" style="font-size: 2em;width:3em;color: '.$col.'" type="text" name="'.$id.'"id="'.$id.'" value="'.$val.'" />');
	}//end serve threshold textbox

	function serveTimeSpinner($id, $val, $col) {
		if (!isset($val) Or empty($val) Or $val == '') {
			$val = '00:00';
		}
		$hrsMins = explode(':', $val);
		$hrs = $hrsMins[0];
		$mins = $hrsMins[1];
		echo('<input class="spintimehrs" style="font-size: 2em;width:1.5em;color: '.$col.'" type="text" id="'.$id.'H" value="00" data-init="'.$hrs.'" />');
		echo('<span style="vertical-align: sub;font-size: 2em;width:1.5em;color: '.$col.'">:</span>');
		echo('<input class="spintimemins" style="font-size: 2em;width:1.5em;color: '.$col.'" type="text" id="'.$id.'M" value="00" data-init="'.$mins.'" />');
	}//end serve threshold textbox

?>
<script type="text/javascript">

	$( ".spinnable" ).spinner({
	  step: 0.5,
	  numberFormat: "n",
	  alignment: 'horizontal'
	});

	$(".spintimehrs, .spintimemins").focus(function () {
		$(this).blur();
	});

	$( ".spintimehrs" ).spinner({
	  step: 1,
	  numberFormat: "d2",
	  min: -1,
	  max: 24,
	  alignment: 'vertical',
	  start: function( event, ui ) {
		var startVal = $(this).spinner('value');
	  },
	  spin: function( event, ui ) {
		var startVal = ui.value;
		var sisterId = '#' + event.target.id.slice(0, -1) + 'M';

		if (startVal == 24) {
			$(this).spinner('value', 0);
			$(sisterId).spinner('stepUp');
			event.preventDefault();
		} else if (startVal == -1) {
			$(this).spinner('value', 23);
			$(sisterId).spinner('stepDown');
			event.preventDefault();
		}
	  },
	  create: function( event, ui ) {
		var initVal = $(this).attr('data-init');
		$(this).val(initVal);
	  }
	});

	$( ".spintimemins" ).spinner({
	  step: 1,
	  numberFormat: "d2",
	  min: -10,
	  max: 60,
	  alignment: 'vertical',
	  start: function( event, ui ) {
	  },
	  spin: function( event, ui ) {
		var startVal = ui.value;
		var sisterId = '#' + event.target.id.slice(0, -1) + 'H';

		if (startVal == 60) {
			$(this).spinner('value', 0);
			$(sisterId).spinner('stepUp');
			event.preventDefault();
		} else if (startVal == -10) {
			$(this).spinner('value', 50);
			$(sisterId).spinner('stepDown');
			event.preventDefault();
		}
	  },
	  create: function( event, ui ) {
		  var initVal = $(this).attr('data-init');
		  $(this).val(initVal);
	  }
  	});

	$('.enabSwitch').switchButton({
		width: 50,
		height: 20,
		button_width: 35,
		on_label: 'Enabled',
		off_label: 'Disabled'
	});

    function doUpdate(condId, format, source) {
		var updConditionsUrl = 'updateConditions.php';
    	var updData = 'ConditionAction=update&ConditionID=' + condId;
    	updData += '&ConditionFormat=' + format;
    	updData += '&ConditionName=' + $('#ConditionName' + condId).val();
    	updData += '&ConditionDescription=' + $('#ConditionDesc' + condId).val();
    	updData += '&Enabled=' + $('#condEnabDisab' + condId).is(':checked');
    	if (format == 'Time') {
	    	updData += '&ConditionSource=' + $('#TimeSource' + condId).val();
			updData += '&ExceptionThresholdHrs=' + $('#ExceptionThreshold' + condId + 'H').spinner('value');
			updData += '&ExceptionThresholdMins=' + $('#ExceptionThreshold' + condId + 'M').spinner('value');
			updData += '&NormalThresholdHrs=' + $('#NormalThreshold' + condId + 'H').spinner('value');
			updData += '&NormalThresholdMins=' + $('#NormalThreshold' + condId + 'M').spinner('value');
		} else if (format == 'Simple') {
	    	updData += '&ConditionSource=' + $('#condSensSelect' + condId).val();
			updData += '&ExceptionThreshold=' + $('#ExceptionThreshold' + condId).spinner('value');
			updData += '&NormalThreshold=' + $('#NormalThreshold' + condId).spinner('value');
			updData += '&ExceptionOperator=' +  $('#ExceptionOperator' + condId + ' option:selected').text();
			updData += '&NormalOperator=' +  $('#NormalOperator' + condId + ' option:selected').text();
		} else {
			//Complex
	    	updData += '&ConditionSource=' + source;
			updData += '&SetExpression=' + $('#SetExpression' + condId).val();
			updData += '&ResetExpression=' + $('#ResetExpression' + condId).val();
		}
	    var status = $.ajax({
		  type: "POST",
		  url: updConditionsUrl,
		  data: updData,
		  dataType: 'text',
		  async: false
	    }).responseText;
		if (status == '1') {
			topBar('Update successful.', 'infoType');
		} else {
			topBar('Update failed.', 'errorType');
	    	alert('Posted Result: ' + status);
		}

		var active = $( '#accordion' ).accordion( 'option', 'active' );
		loadSection(active, true);

    }//end do update

    function doDuplicate(condId, format, source) {
		var updConditionsUrl = 'updateConditions.php';
    	var dupData = 'ConditionAction=duplicate&ConditionID=' + condId;
	    var status = $.ajax({
		  type: "POST",
		  url: updConditionsUrl,
		  data: dupData,
		  dataType: 'text',
		  async: false
	    }).responseText;
		if (status == '1') {
			topBar('Duplicate successful.', 'infoType');
		} else {
			topBar('Duplicate failed.', 'errorType');
	    	alert('Posted Result: ' + status);
		}

		var active = $( '#accordion' ).accordion( 'option', 'active' );
	   	loadSection(active, true);

    }//end do duplicate

    function doRemove(condId, format, source) {
		var updConditionsUrl = 'updateConditions.php';
    	var delData = 'ConditionAction=remove&ConditionID=' + condId;
	    var status = $.ajax({
		  type: "POST",
		  url: updConditionsUrl,
		  data: delData,
		  dataType: 'text',
		  async: false
	    }).responseText;
		if (status == '1') {
			topBar('Delete successful.', 'infoType');
		} else {
			topBar('Delete failed.', 'errorType');
	    	alert('Posted Result: ' + status);
		}

		var active = $( '#accordion' ).accordion( 'option', 'active' );
	   	loadSection(active, true);

    }//end do remove

    function expose(radioChoice) {
    	$('.newCondDiv').hide();
    	$('#' + radioChoice.value).show();
    }//end expose

    // mock Globalize numberFormat for mins and secs using jQuery spinner ...
	if (!window.Globalize) window.Globalize = {
	        format: function(number, format) {
	                number = String(this.parseFloat(number, 10) * 1);
	                format = (m = String(format).match(/^[nd](\d+)$/)) ? m[1] : 2;
	                for (i = 0; i < format - number.length; i++)
	                        number = '0'+number;
	                return number;
	        },
	        parseFloat: function(number, radix) {
	                return parseFloat(number, radix || 10);
	        }
	};
</script>