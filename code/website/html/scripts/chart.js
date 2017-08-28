function drawOrgChart(chartTitle, chartDivId, getDataUrl) {

	var jsonData = $.ajax({
	  url: getDataUrl,
	  dataType: "json",
	  async: false
	  }).responseText;

	// Create our data table out of JSON data loaded from server.
	var data = new google.visualization.DataTable(jsonData);

	var options = {
	    allowHtml:true
	};

	// Instantiate and draw our chart, passing in some options.
	var chart = new google.visualization.OrgChart(document.getElementById(chartDivId));

	// Every time the table fires the "select" event, it should call your selectHandler() function
	google.visualization.events.addListener(chart, 'select', selectHandler);

	function selectHandler(e) {

		var selection = chart.getSelection();
		var rowNo = selection[0].row;
		var dbId = data.getFormattedValue(rowNo, 3);
		doSelection(dbId, chartDivId);
	}

	chart.draw(data, options);

}//end draw organisation chart

function doSelection(dbId, chartDivId) {

	var activeIndex = $('#contentPane > div').index($('#' + chartDivId).parent()) + 1;
	//R1, C1, A1, T1, etc.
	idCode = dbId.charAt(0);
	idIndex = dbId.replace(/\D/g,'');
	if (idCode == 'R') {
		getFormUrl = 'rule.php?rule=' + idIndex + '&tab=' + activeIndex;
	} else if (idCode == 'A') {
		getFormUrl = 'action.php?action=' + idIndex + '&tab=' + activeIndex;
	} else if (idCode == 'U') {
		getFormUrl = 'actuator.php?actuator=' + idIndex + '&tab=' + activeIndex;
	} else if (idCode == 'S') {
		getFormUrl = 'sensor.php?sensor=' + idIndex + '&tab=' + activeIndex;
	} else if (idCode == 'I') {
		getFormUrl = 'impulse.php?impulse=' + idIndex + '&tab=' + activeIndex;
	} else if (idCode == 'M') {
		getFormUrl = 'measurand.php?meas=' + idIndex + '&tab=' + activeIndex;
	} else if (idCode == 'Y') {
		getFormUrl = 'actuatorType.php?type=' + idIndex + '&tab=' + activeIndex;
	} else if (idCode == 'P') {
		getFormUrl = 'user.php?user=' + idIndex + '&tab=' + activeIndex;
	} else if (idCode == 'Z') {
		getFormUrl = 'zone.php?zone=' + idIndex + '&tab=' + activeIndex;
	} else if (idCode == 'C') {
		getFormUrl = null;
	}
	if (getFormUrl !== null) {
		var formHtml = $.ajax({
		  url: getFormUrl,
		  dataType: "json",
		  async: false
		}).responseText;

		//Hide Chart & menu
		$('#' + chartDivId).hide();

		//Expose Form
		$('#' + chartDivId).nextAll('.orgEditorForm').html(formHtml);

	} else {
		alert('Use Conditions option to edit conditions');
	}
}

function drawComparisonChart(chartTitle, chartDivId, getDataUrl, measId, unitsLabel, graphMax, graphMin) {

	timebaseHrs = comparisonTimebaseArray[measId];
	var jsonData = null;
	$.ajax({
	  url: getDataUrl + '?meas=' + measId +
	  					'&timebasehrs=' + timebaseHrs,
	  dataType: "json",
	  success: function(jsonData) {

		// Create our data table out of JSON data loaded from server.
		var data = new google.visualization.DataTable(jsonData);

		var options = {
			title: chartTitle,
			colors: ["#aaaa11","#dc3912","#3366cc","#ff9900","#109618","#990099","#0099c6","#dd4477","#aaaa11","#22aa99","#994499"],
			height: 300,
			hAxis: {
			  gridlines: {
				  count: -1
			  }
			},
			vAxis: {
			  viewWindow: {
				  max: graphMax,
				  min: graphMin
			  }
			},
			legend : { position: 'top', textStyle: {color: 'blue', fontSize: 10}},
			chartArea: {'width': '80%', 'height': '70%'}
		};

		// Instantiate and draw our chart, passing in some options.
		var chartDiv = document.getElementById(chartDivId);
		//console.log('count: ' + chartDiv.childElementCount);
		var chart = new google.visualization.LineChart(chartDiv);
		google.visualization.events.addListener(chart, 'click', function () { comparisonClickHandler(measId) });

		// Create a formatter.
		var formatter = new google.visualization.DateFormat({pattern: "E HH:mm"});

		// Reformat our data.
		formatter.format(data, 0);

		chart.draw(data, options);

	  }
	});

}//end draw comparison chart

// The click handler. Change the chart's timebase
var comparisonClickHandler = function(measId) {
	var	timebase = comparisonTimebaseArray[measId];
	if (timebase > 3) {
		timebase = timebase/2;
	} else {
		timebase = maxTimebaseHrs;
	}
	comparisonTimebaseArray[measId] = timebase;
	window['drawComparisonChart' + measId]();
};

function drawComplexConditionChart(chartTitle, chartDivId, getDataUrl, series, vAxes, conditionId) {
	timebaseHrs = compCondTimebaseArray[conditionId];
	var jsonData = $.ajax({
	  url: getDataUrl + '?condition=' + conditionId +
	  					'&timebasehrs=' + timebaseHrs,
	  dataType: "json",
	  success: function(jsonData) {

		// Create our data table out of JSON data loaded from server.
		var data = new google.visualization.DataTable(jsonData);

		var options = {
			title: chartTitle,
			interpolateNulls: true,
			height: 300,
			// Gives each series an axis that matches the vAxes number below.
			series: series,
			vAxes: vAxes,
			hAxis: {
			  gridlines: {
				  count: -1
			  }
			},
			legend : { position: 'bottom', textStyle: {color: 'blue', fontSize: 10}},
			chartArea: {'width': '80%', 'height': '70%'}
		};

		// Instantiate and draw our chart, passing in some options.
		var chart = new google.visualization.LineChart(document.getElementById(chartDivId));
		google.visualization.events.addListener(chart, 'click', function () { clickHandler(conditionId) });

		// Create a formatter.
		var formatter = new google.visualization.DateFormat({pattern: "E HH:mm"});

		// Reformat our data.
		formatter.format(data, 0);

		chart.draw(data, options);
	  }
	});
}//end draw complex condition chart

// The click handler. Change the chart's timebase
var clickHandler = function(condId) {
	var	timebase = compCondTimebaseArray[condId];
	if (timebase > 3) {
		timebase = timebase/2;
	} else {
		timebase = maxTimebaseHrs;
	}
	compCondTimebaseArray[condId] = timebase;
	window['drawConditionChart' + condId]();
};