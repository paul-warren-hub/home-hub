<?php

	//Loop through Actuators enabling manual/auto and intervention on/off...
	require_once '../private_html/hub_connect.php';
	require_once 'auth.inc';

	$actuatorQry = 'SELECT "ActuatorID", "ZoneName", "ActuatorName", "ActuatorDescription",
						"CurrentValue", "OnForMins", "OffForMins", "IsInAuto", "ActuatorTypeID"
				FROM "vwActuators"
				WHERE "WebPresence"
				ORDER BY "ActuatorID"
			   ';

	$actuatorResult = $db_object->query($actuatorQry);

	if (MDB2::isError($actuatorResult)) {
		error_log("Database Error Query: ".$actuatorQry." ".$actuatorResult->getMessage(), 0);
		die($actuatorResult->getMessage());
	}//end db error

	echo('<table class="actuatorGrid">');

	while ($actuatorRow = $actuatorResult->fetchRow(DICTCURSOR)) {

		echo('<tr>');

		$actId = $actuatorRow['ActuatorID'];
		$actName = $actuatorRow['ZoneName'].' '.$actuatorRow['ActuatorName'];
		$actType = $actuatorRow['ActuatorTypeID'];
		$actAutoFlag = $actuatorRow['IsInAuto'];
		$actAuto = ($actAutoFlag == 'Y');
		$actVal = $actuatorRow['CurrentValue'];
		$onFor = $actuatorRow['OnForMins'];
		$offFor = $actuatorRow['OffForMins'];
		$actOn = ($actVal > 0);

		//Auto/Manual
		echo('<td style="width: 30%;"><div class="switch-wrapper">');
		echo('<input class="autoManualSwitch" id="actAutoMan'.$actId.'" type="checkbox" ');
		echo('onchange="autoChange(this)" ');
		echo('data-initval="'.($actAuto?"true":"false").'" ');
		echo(($actAuto? 'checked':'').' /></div></td>');

		echo ('<td><span class="actuatorGridLabel">'.$actName.'</b></td>');

		//Current Value
		echo('<td width="36%">');
		if ($actType == 1) {
			//Digital On/Off
			echo('<div class="switch-wrapper">');
			echo('<input type="checkbox" id="actVal'.$actId.'" class="onOffSwitch" ');
			echo('data-initval="'.($actOn?"true":"false").'" ');
			echo('onchange="valueChange('.$actId.')" ');
			echo(($actOn ? 'checked':'').' '.($actAuto? 'disabled':'').' />');
			echo('</div>');
			echo('<div style="float: right;border:0px solid red;text-align: right;margin-top: 9px;font-size:smaller;">'.($actOn ? minsAsHrsMins($onFor) : minsAsHrsMins($offFor)).'</div>');
			echo('<br />');
		} else {
			echo('<input type="text" id="actVal'.$actId.'" class="spinnable" ');
			echo('data-initval="'.$actVal.'" ');
			echo('style="font-size: 1.5em;" size="4" value="'.$actVal.'" '.($actAuto? 'disabled':'').' />');
			echo('<button id="updVal'.$actId.'" onclick="valueChange('.$actId.')" '.($actAuto? 'disabled':'').' ');
			echo('style="margin-top:6px;margin-left: 18px;">Update</button>');
		}
		echo('</td></tr>');
	}

	echo('</table><br /><br />');

  function minsAsHrsMins($minsIn) {
  	$result = '';
	$hrs = floor($minsIn / 60);
	$mins = $minsIn % 60;
  	//Then if mins is less than 60 - just mins
  	if ($minsIn > 0 && $minsIn < 60) {
  		$result = 'for '.$minsIn.' min'.($minsIn > 1 ? 's' : '');
  	}
  	else if ($hrs > 0) {
  		$zpMins = $mins < 10 ? '0'.$mins : $mins;
  		$result = 'for '.$hrs.':'.$zpMins.' hrs';
  	}
  	return $result;
  }//end mins as hrs mins

?>
<script type="text/javascript">

    $( ".spinnable" ).spinner({
      step: 0.5,
      numberFormat: "n",
      alignment: 'horizontal'
    });
    $('.onOffSwitch').switchButton({
    	width: 50,
    	height: 20,
    	button_width: 35,
    	on_label: 'On',
  		off_label: 'Off' }
  	);
    $('.autoManualSwitch').switchButton({
    	width: 50,
    	height: 20,
    	button_width: 35,
    	on_label: 'Auto',
  		off_label: 'Hand'
  	});

  var updActuatorUrl = 'updateActuator.php';

  function autoChange(widget) {
    if (widget.checked != (widget.dataset.initval == 'true')) {
		//Update Database
		var status = $.ajax({
		  type: "POST",
		  url: updActuatorUrl,
		  data: 'actuatorId=' + widget.id +
							'&isInAuto=' + widget.checked,
		  dataType: 'text',
		  async: false
		}).responseText;
		if (status == '1') {
			topBar('Instruction successful.', 'infoType');
		} else {
			topBar('Instruction failed.', 'errorType');
		}
		//Update Initial Value
		widget.dataset.initval = widget.checked?'true':'false';
		var butt = document.getElementById('updVal' + widget.id.match(/\d+/)[0]);
		var tbox = document.getElementById('actVal' + widget.id.match(/\d+/)[0]);
		if (butt != null) {
			butt.disabled = widget.checked;
		}
		tbox.disabled = widget.checked;

    }//end state changed
  }//end auto change function

  function valueChange(actIndex) {
	  var widget = document.getElementById('actVal' + actIndex);
      //Get current value based on widget type
      var curVal = (widget.type == 'checkbox'?(widget.checked?'1.0':0.0):widget.value);
	  if ((widget.type == 'checkbox' && widget.checked != (widget.dataset.initval == 'true')) ||
	  	  (widget.type == 'text' && (widget.value != widget.dataset.initval))) {
        //Update Database
        var status = $.ajax({
        	type: "POST",
  	  	  	url: updActuatorUrl,
  	  	  	data: 'actuatorId=' + widget.id +
  	  	  					'&currentValue=' + curVal,
  	  	  	dataType: 'text',
  	  	  	async: false
  	  	}).responseText;

		if (status == '1') {
			topBar('Instruction successful.', 'infoType');
		} else {
			topBar('Instruction failed.', 'errorType');
		}

  	  	//Update Initial Value
  	  	if (widget.type == 'checkbox') {
  	  		widget.dataset.initval = widget.checked?'true':'false';
  	  	} else {
  	  		widget.dataset.initval = widget.value;
  	  	}
      }//end state changed
  }//end auto change function

</script>