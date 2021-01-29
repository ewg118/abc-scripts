<?php 
 /*****
 * Author: Ethan Gruber
 * Last modified: January 2021
 * Function: Merge Courtney's ABC references from the augmented PAS export into the ABC export.
 *****/

$data = generate_json("pas-abc-coins.csv");
$places = generate_json("https://docs.google.com/spreadsheets/d/e/2PACX-1vQWVPKgrRn_2QZ4oYBE6J86dL00wwUopMGBe8Ot2Gb2TTJsvZDfw1klRN-idU5bYW2MSM1OKEC-CV3r/pub?output=csv");

$coinTypes = array();

$new = array();
$count = 1;
foreach($data as $row){
    $newRow = array();
    foreach ($row as $k=>$v){
        $newRow[$k] = $v;
    }
    
    echo "Processing {$count}\n";
    
    //if the osID exists, then look it up in the places spreadsheet
    if (array_key_exists('osID', $newRow)){
        if (strlen($newRow['osID']) > 0){
            $osID = "7" . number_pad($newRow['osID'], 15);
            
            foreach($places as $place){
                if ($place['osID'] == $osID){
                    $newRow['findspot'] = $place['uri'];
                    break;
                }
            }
        }
        
    }
	
	/*foreach($concord as $line){
	    if ($line['pas_id'] == $id){
	        echo "Processing {$count}\n";
	        if (strlen(trim($line['in hoard?'])) > 0){
	            $newRow['inHoard'] = $line['in hoard?'];
	        } else {
	            $newRow['inHoard'] = '';
	        }
	        if (strlen(trim($line['Leins_Hoard Name'])) > 0){
	            $newRow['Hoard Name'] = $line['Leins_Hoard Name'];
	        } else {
	            $newRow['Hoard Name'] = '';
	        }	       
	        if (strlen(trim($line['date found'])) > 0){
	            $newRow['Discovery Date'] = $line['date found'];
	        } else {
	            $newRow['Discovery Date'] = '';
	        }
	        if (strlen(trim($line['discoveryMethod'])) > 0){
	            $newRow['Discovery Method'] = $line['discoveryMethod'];
	        } else {
	            $newRow['Discovery Method'] = '';
	        }
	        if (strlen(trim($line['currentLocation'])) > 0){
	            $newRow['currentLocation'] = $line['currentLocation'];
	        } else {
	            $newRow['currentLocation'] = '';
	        }
	        
	        //validate and insert URI
	        $coinType = null;
	        $valid = false;
	        
	        if (strlen($row['newABC']) > 0){
	            $abc = trim($row['newABC']);
	            //read newABC first
	            if (strlen($abc) == 4){
	                echo "true\n";
	                $coinType = "https://abc.arch.ox.ac.uk/id/abc." . $abc;
	                $valid = check_uri($coinType);
	            }
	        } elseif (strlen($row['abcType']) > 0){
	            $abc = trim($row['abcType']);
	            
	            if (strpos($abc, '.') !== FALSE){
	                $id = explode('.', $abc)[0];
	                $coinType = "https://abc.arch.ox.ac.uk/id/abc." . number_pad($id, 4);
	                $valid = check_uri($coinType);
	            } elseif (is_numeric($abc)){
	                $coinType = "https://abc.arch.ox.ac.uk/id/abc." . number_pad($abc, 4);
	                $valid = check_uri($coinType);
	            }
	        } else {
	            echo "Pass\n";
	        }
	        
	        if (isset($coinType) && $valid == true){
	            $newRow['Type URI'] = $coinType;
	        } else {
	            $newRow['Type URI'] = '';
	        }
	        
	        $count++;
	        break;
	    }
	}*/
    $count++;
	$new[] = $newRow;
}

//var_dump($new);

$header = array();
foreach ($data[0] as $k=>$v){
    $header[] = $k;   
}
$header[] = 'Findspot';

$fp = fopen('new-abc.csv', 'w');
fputcsv($fp, $header);
foreach ($new as $row) {
    fputcsv($fp, $row);
}
fclose($fp);


/***** FUNCTIONS *****/
//check validity of coin type URI
function check_uri($uri){
    GLOBAL $coinTypes;
    
    //if the URI is in the array
    if (array_key_exists($uri, $coinTypes)){
        if ($coinTypes[$uri] == true) {
            echo "Found {$uri}\n";
            $valid = true;
        } else {
            echo "Did not find {$uri}\n";
            $valid = false;
        }
    } else {
        $file_headers = @get_headers($uri);
        if (strpos($file_headers[0], '200') !== FALSE){
            echo "Matched new {$uri}\n";
            $coinTypes[$uri] = true;
            $valid = true;
        } else {
            echo "Did not find {$uri}\n";
            $coinTypes[$uri] = false;
            $valid = false;
        }
    }
    
    return $valid;
}

function number_pad($number,$n) {
    if ($number > 0){
        $gYear = str_pad((int) $number,$n,"0",STR_PAD_LEFT);
    } elseif ($number < 0) {
        $gYear = '-' . str_pad((int) abs($number),$n,"0",STR_PAD_LEFT);
    }
    return $gYear;
}

function generate_json($doc){
	$keys = array();
	$geoData = array();
	
	$data = csvToArray($doc, ',');
	
	// Set number of elements (minus 1 because we shift off the first row)
	$count = count($data) - 1;
	
	//Use first row for names
	$labels = array_shift($data);
	
	foreach ($labels as $label) {
		$keys[] = $label;
	}
	
	// Bring it all together
	for ($j = 0; $j < $count; $j++) {
		$d = array_combine($keys, $data[$j]);
		$geoData[$j] = $d;
	}
	return $geoData;
}

// Function to convert CSV into associative array
function csvToArray($file, $delimiter) {
	if (($handle = fopen($file, 'r')) !== FALSE) {
		$i = 0;
		while (($lineArray = fgetcsv($handle, 4000, $delimiter, '"')) !== FALSE) {
			for ($j = 0; $j < count($lineArray); $j++) {
				$arr[$i][$j] = $lineArray[$j];
			}
			$i++;
		}
		fclose($handle);
	}
	return $arr;
}

?>