<?php
	require_once 'auth.inc';
?>

<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Hub Home Page</title>
	<link rel="stylesheet" href="scripts/jquery-ui-1.11.4.custom/jquery-ui.css">
	<link rel="stylesheet" href="styles/hub.css">
	<link rel="stylesheet" href="styles/jquery.switchButton.css">
	<link rel="stylesheet" href="styles/jquery.ui.spinner.css">

	<script src="scripts/jquery-ui-1.11.4.custom/external/jquery/jquery.js"></script>
	<script src="scripts/jquery-ui-1.11.4.custom/jquery-ui.js"></script>
	<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
	<script type="text/javascript" src="scripts/chart.js"></script>
	<script type="text/javascript" src="scripts/jquery.switchButton.js"></script>
	<script src="scripts/jquery.ui.spinner.js"></script>
	<script type="text/javascript" src="scripts/lib.js"></script>
	<script>
	var pagePos = 0;
	var initTimebaseHrs = 24;
	var maxTimebaseHrs = 96;
	var compCondTimebaseArray = [];
	var comparisonTimebaseArray = [];
	var condArray = [];
	var compArray = [];

	$(document).ready(function() {

	// Load the Visualization API and the chart package.
	google.charts.load('current', {'packages':['corechart','table','orgchart']});

	var page = getParameterByName('page');
	if (page != null) {
		//Find the heading by name
		if ($.isNumeric(page)) {
			//1-based index passed in
			pagePos = page - 1;
			if (page <= 0) pagePos = false;
		} else {
			//Convert page heading to the actual index of the heading
			pagePos = $('#accordion').find('a.acc:contains(' + page + ')').parent().index()/2;
		}
	} else {
		pagePos = false;
	}
	$( "#accordion" ).accordion({
		heightStyle: "content",
		collapsible: true,
		active : pagePos,
		activate: function (e, ui) {
			loadSection(ui.newHeader.index()/2);
		},
		create: function (e, ui) {
			loadSection(ui.header.index()/2);
		}
	});

	setInterval(function(){
		var active = $( '#accordion' ).accordion( 'option', 'active' );
		var refreshable = $($('#accordion h3')[active]).attr('data-refreshable');
		var sectAnchor = $('#accordion').find('a.acc')[active];
		var isGraph = typeof sectAnchor !== 'undefined' && sectAnchor.href.indexOf('Graph') !== -1;
		var populated = (/<[a-z][\s\S]*>/i.test($(sectAnchor).parent().next().html()));
		if (refreshable == 'true' && populated) {
			loadSection(active);
		}
		if (isGraph) {
			if (condArray.length > 0) {
				compArray = [];
				condArray.forEach(function(cond){window["drawConditionChart" + cond]()});
			}
			if (compArray.length > 0) {
				condArray = [];
				compArray.forEach(function(meas){window["drawComparisonChart" + meas]()});
			}
		} else {
			condArray = [];
			compArray = [];
		}
	}, 30000);

	});

	function loadSection(index) {
		if (index >= 0) {
			var sectAnchor = $('#accordion').find('a.acc')[index];
			jqUrl = $(sectAnchor).attr('href');
			if (window.location.search != '') {
				if (jqUrl.indexOf('?') == -1) {
					jqUrl += window.location.search;
				} else {
					jqUrl += '&' + window.location.search.substring(1);
				}
			}
			$.ajax({
				type: "GET",
				url: jqUrl,
				success: function (data) {
					$(sectAnchor).parent().next().html(data);
				},
				error: function (xhr, ajaxOptions, thrownError) {
					$(sectAnchor).parent().next().html(thrownError);
      			}
			});
		}
	}//end load section

  	function topBar(message, msgType) {
  		  $("<div />", { 'class': 'infoErrorType ' + msgType, text: message }).hide().prependTo("body")
  		      .slideDown('fast').delay(2000).slideUp(function() { $(this).remove(); });
	}

	var updImpulseUrl = 'updateImpulse.php';
	function processImpulseClick(impId) {

		//Update Database
		var status = $.ajax({
			type: "POST",
			url: updImpulseUrl,
			data: 'impulseId=' + impId,
			dataType: 'text',
			async: false
		}).responseText;
		if (status == '1') {
			topBar('Impulse instruction successful.', 'infoType');
		} else {
			topBar('Impulse instruction failed.', 'errorType');
		}
	}//end process impulse click
  </script>
</head>
<body>
		<?php
			//Home Automation Hub - Main Page
			$waitMessage = 'Loading. Please wait...';
			if (IsSet($_SESSION['username'])) {
				echo('<div id="usrDetails">User: '.$_SESSION['username'].' [ '.$_SESSION['userrole'].' ]<a href="logout.php">logout</a></div>');
			}
			echo('<div id="accordion">');

			//items prefixed with underscore will not auto-refresh
			$pages = array('Current Values', '_Organisation', '_Conditions', '_Condition Graphs','_Comparison Graphs', 'Actuators', 'Statistics');
			if ($_SESSION['userrole'] == 'occupant') {
				unset(	$pages[array_search('_Organisation', $pages)],
						$pages[array_search('_Condition Graphs', $pages)],
						$pages[array_search('Actuators', $pages)],
						$pages[array_search('Statistics', $pages)]
				);
			}//end customize
			else {
				//admin - move conditions to top for better viewing on phone
				$pageToPrioritise = '_Condition Graphs';
				$key = array_search($pageToPrioritise, $pages);
				$reduced = array_diff($pages, array($pageToPrioritise));
				$pages = array_merge(array($pageToPrioritise), $reduced);
			}
			$i = 0;
			foreach ($pages as $page) {
				$refresh = (substr( $page, 0, 1 ) !== "_");
				$hdg = ltrim($page, "_");
				$url = lcfirst(ltrim(str_replace(' ', '', $page), "_")).'.php';
				echo "<h3 id='$hdg'";
				if ($refresh) {
					echo " data-refreshable=true >";
				} else {
					echo " data-refreshable=false >";
				}
				echo "<a class='acc' href='$url'>$hdg</a></h3>";
				echo "<div class='accSection'>";
				//include $page;
				echo '<p class="loadMessage">'.$waitMessage.'</p>';
				echo "</div>";
				$i++;
			}
			echo('</div>');
		?>
	</div>
</body>
</html>