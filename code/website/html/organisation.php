<!-- Plots Organisation Chart of System -->

<script type="text/javascript">

	$( function() {
		$('#vtabs li').click(function() {
			var rank = $('li').index($(this));
			selectVTab(rank);
			return false;
		});
		reqTab = getParameterByName('tab');
		if (reqTab == null) {
			reqTab = 0;
		} else {
			reqTab--;
		}
    	//$( "#tabs" ).tabs({ active: reqTab });
    	selectVTab(reqTab);
  	});

	function selectVTab(rank) {

		var theLi = $('#vtabs li').eq(rank);
		$('.orgLiOn').removeClass();
		$(theLi).toggleClass('orgLiOn');

		$('.orgTabOn').removeClass();
		var theDiv = $('#contentPane > div').eq(rank);
		$(theDiv).toggleClass('orgTabOn');

	}//end select vertical tab

	function drawOrganisationChart(element) {
		// Set a callback to run when the Google Visualization API is loaded.
					drawOrgChart("System Organisation",
									"orgChartDiv" + element,
									 "getMapChartData.php?element=" + element)
	};

	// Set a callback to run when the Google Visualization API is loaded.
	google.charts.setOnLoadCallback(
		function() {
			drawOrganisationChart('Zone');
			drawOrganisationChart('Sensor');
			drawOrganisationChart('Actuator');
			drawOrganisationChart('Impulse');
			drawOrganisationChart('Rule');
			drawOrganisationChart('Action');
			drawOrganisationChart('Org');
			drawOrganisationChart('User');
		}
	);
</script>
<table id="vtabs" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
			<ul>
				<li class="orgLiOn"><a href="#">Zones</a></li>
				<li><a href="#">Sensors</a></li>
				<li><a href="#">Actuators</a></li>
				<li><a href="#">Impulses</a></li>
				<li><a href="#">Rules</a></li>
				<li><a href="#">Actions</a></li>
				<li><a href="#">Conditions</a></li>
				<li><a href="#">Users</a></li>
				<li><a href="#">Settings</a></li>
			</ul>
		</td>
		<td style="width: 95%">
			<div id="contentPane">
				<div class="orgTabOn">
					<!-- Div that will hold the Zones org chart -->
					<div id="orgChartDivZone"></div><br />
					<div id="orgEditorFormZone" class="google-visualization-orgchart-node google-visualization-orgchart-nodesel orgEditorForm">
					  <span style="margin-right:120px">Select an element to edit or <a href="#" onclick="doSelection('Z0', 'orgChartDivZone');">add Zone</a></span>
					</div>
				</div>
				<div>
					<!-- Div that will hold the Sensors org chart -->
					<div id="orgChartDivSensor"></div><br />
					<div id="orgEditorFormSensor" class="google-visualization-orgchart-node google-visualization-orgchart-nodesel orgEditorForm">
					  <span style="margin-right:120px">Select an element to edit or add
						<a href="#" onclick="doSelection('S0', 'orgChartDivSensor');">Sensor</a>,
						<a href="#" onclick="doSelection('M0', 'orgChartDivSensor');">Measurand</a>
					  </span>
					</div>
				</div>
				<div>
					<!-- Div that will hold the Actuators org chart -->
					<div id="orgChartDivActuator"></div><br />
					<div id="orgEditorFormActuator" class="google-visualization-orgchart-node google-visualization-orgchart-nodesel orgEditorForm">
					  <span style="margin-right:120px">Select an element to edit or add
						<a href="#" onclick="doSelection('U0', 'orgChartDivActuator');">Actuator</a>,
						<a href="#" onclick="doSelection('Y0', 'orgChartDivActuator');">Type</a>
					  </span>
					</div>
				</div>
				<div>
					<!-- Div that will hold the Impulses org chart -->
					<div id="orgChartDivImpulse"></div><br />
					<div id="orgEditorFormImpulse" class="google-visualization-orgchart-node google-visualization-orgchart-nodesel orgEditorForm">
					  <span style="margin-right:120px">Select an element to edit or
						<a href="#" onclick="doSelection('I0', 'orgChartDivImpulse');">add Impulse</a>
					  </span>
					</div>
				</div>
				<div>
					<!-- Div that will hold the Rules org chart -->
					<div id="orgChartDivRule"></div><br />
					<div id="orgEditorFormRule" class="google-visualization-orgchart-node google-visualization-orgchart-nodesel orgEditorForm">
					  <span style="margin-right:120px">Select an element to edit or
						<a href="#" onclick="doSelection('R0', 'orgChartDivRule');">add Rule</a>
					  </span>
					</div>
				</div>
				<div>
					<!-- Div that will hold the Actions org chart -->
					<div id="orgChartDivAction"></div><br />
					<div id="orgEditorFormAction" class="google-visualization-orgchart-node google-visualization-orgchart-nodesel orgEditorForm">
					  <span style="margin-right:120px">Select an element to edit or
						<a href="#" onclick="doSelection('A0', 'orgChartDivAction');">add Action</a>
					  </span>
					</div>
				</div>
				<div>
					<!-- Div that will hold the Conditions org chart -->
					<div id="orgChartDivOrg"></div><br />
					<div id="orgEditorFormOrg" class="google-visualization-orgchart-node google-visualization-orgchart-nodesel orgEditorForm">
					  <span style="margin-right:120px">Select an element to edit</span>
					</div>
				</div>
				<div>
					<!-- Div that will hold the Users org chart -->
					<div id="orgChartDivUser"></div><br />
					<div id="orgEditorFormUser" class="google-visualization-orgchart-node google-visualization-orgchart-nodesel orgEditorForm">
					  <span style="margin-right:120px">Select an element to edit or <a href="#" onclick="doSelection('P0', 'orgChartDivUser');">add User</a></span>
					</div>
				</div>
				<div>
					<!-- Div that will hold the Settings -->
					<div id="orgEditorFormSetting" class="google-visualization-orgchart-node google-visualization-orgchart-nodesel orgEditorForm">
						<?php require 'setting.php'; ?>
					</div>
				</div>
			</div>
		</td>
	</tr>
</table>
