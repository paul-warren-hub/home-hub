<?php

	//Draw the House Plan...
	
	//First Loop through the Z Values representing major blocks 1st Floor/2nd Floor/Outside

			$floorResult = [
    			[
    				'ZoneID' => 1,
    				'ZoneX' => 0,
    				'ZoneY' => 0,
    				'ZoneZ' => 1,
    				'ZoneName' => 'Kitchen'
    			],
    			[
    				'ZoneID' => 2,
    				'ZoneX' => 1,
    				'ZoneY' => 1,
    				'ZoneZ' => 1,
    				'ZoneName' => 'Bathroom'
    			]
			];
			
    $zoneCount = count($floorResult);
    $zoneIndex = 0;
	while ($zoneIndex < $zoneCount) {
	    
	    $floorRow = $floorResult[$zoneIndex];

		$zoneZ = $floorRow['ZoneZ'];
		$zoneIsWholeFloor = $floorRow['ZoneZ'] > 1;
		$zoneName = $floorRow['ZoneName'];

		echo('<div class="zoneFloor">');

			//If floor contains many zones - just label it 1st Floor, 2nd Floor, etc
			//Otherwise use Zone Name
			if ($zoneIsWholeFloor) {
				//echo($zoneName);
			} else {
				echo('<div class="ZoneCurrentValuesFloorTitle">'.($zoneZ == 1 ? 'Ground' : addOrdinalNumberSuffix($zoneZ)).' Floor</div>');
			}
			echo('<br />');

			//Now Loop through Zones on this Floor constructing table...
            /*
			$zoneQry = "SELECT DISTINCT \"ZoneID\", \"ZoneName\",\"ZoneX\",\"ZoneY\",\"ZoneRowspan\",\"ZoneColspan\"
						FROM \"Zone\"
						WHERE \"ZoneZ\" = $zoneZ
						ORDER BY \"ZoneY\",\"ZoneX\"
					   ";

			$zoneResult = $db_object->query($zoneQry);

			if (MDB2::isError($zoneResult)) {
				error_log("Database Error Query: ".$zoneQry." ".$zoneResult->getMessage(), 0);
				die($zoneResult->getMessage());
			}//end db error
            */
			echo('<table class="ZoneCurrentValuesTable" border="1"><tr>');

			$tableRow = 0;

            $zoneFloorIndex = 0;
	        while ($zoneFloorIndex < $zoneCount) {
	            
        	    $zoneFloorRow = $floorResult[$zoneFloorIndex];
				$zoneFloorZ = $zoneFloorRow['ZoneZ'];
	            if ($zoneFloorZ == $zoneZ) {

    				$zoneID = $zoneFloorRow['ZoneID'];
    				$zoneName = $zoneFloorRow['ZoneName'];
    				$zoneX = $zoneFloorRow['ZoneX'];
    				$zoneY = $zoneFloorRow['ZoneY'];
    				$zoneRowspan = $zoneFloorRow['ZoneRowspan'];
    				$zoneColspan = $zoneFloorRow['ZoneColspan'];
    
    				if ($zoneY != $tableRow) {
    					echo('</tr><tr>');
    					$tableRow = $zoneY;
    				}
    
    				echo('<td colspan="'.$zoneColspan.'"'.' rowspan="'.$zoneRowspan.'">');
    					echo('<div class="ZoneCurrentValuesCellTitle">'.$zoneName.'</div><br />');
    
    				echo('</td>');
	            }
	            
                $zoneFloorIndex += 1;
                
			}//wend zone

			echo('</tr></table>');

		echo('</div><br />');
		
        $zoneIndex += 1;

	}
    function addOrdinalNumberSuffix($num) {
        if (!in_array(($num % 100),array(11,12,13))){
            switch ($num % 10) {
                // Handle 1st, 2nd, 3rd
                case 1:  return $num.'st';
                case 2:  return $num.'nd';
                case 3:  return $num.'rd';
            }
        }
        return $num.'th';
    }//end add ordinal number suffix
?>