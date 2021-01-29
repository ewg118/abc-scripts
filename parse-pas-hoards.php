<?php 
/*****
 * Author: Ethan Gruber
 * Last modified: January 2021
 * Function: Parse JSON feed for PAS for Iron Age hoards
 *****/

$places = generate_json("https://docs.google.com/spreadsheets/d/e/2PACX-1vQWVPKgrRn_2QZ4oYBE6J86dL00wwUopMGBe8Ot2Gb2TTJsvZDfw1klRN-idU5bYW2MSM1OKEC-CV3r/pub?output=csv");

$objects = array();
$page = 1;

query_page($page);

//write csv
$fp = fopen('pas-hoards.csv', 'w');
$header = array('URI', 'prefLabel', 'altLabel', 'TID','findspot', 'closingStart', 'closingEnd', 'discoveryStart', 'discoveryEnd', 'isParish', 'osID');
fputcsv($fp, $header);
foreach ($objects as $object) {
    $line = array();
    foreach ($header as $h){
        if (array_key_exists($h, $object)){
            $line[$h] = $object[$h];
        } else {
            $line[$h] = "";
        }
    }
    fputcsv($fp, $line);
    unset($line);
}

fclose($fp);


/***** FUNCTIONS *****/

//an iterative query of the PAS JSON response
function query_page($page) {
    GLOBAL $objects;
    GLOBAL $places;
    
    $start = ($page - 1) * 20;
    
    $service = "https://finds.org.uk/database/search/results/objecttype/HOARD/broadperiod/IRON+AGE/page/{$page}/format/json";    
    $json = file_get_contents($service);
    $data = json_decode($json);    
    $total = $data->meta->totalResults;
    
    echo "Processing records {$start} to " . ($start + 20) . " of {$total}\n";
    //var_dump($data->results);
    
    foreach ($data->results as $row){
        echo "Processing " . $row->id . "\n";
        
        $object = array();
        
        $id = $row->findIdentifier;
        
        if (strpos($id, 'finds-') !== FALSE){
            $object['URI'] = "https://finds.org.uk/database/artefacts/record/id/" . $row->id;
        } elseif (strpos($id, 'hoards-') !== FALSE){
            $object['URI'] = "https://finds.org.uk/database/hoards/record/id/" . $row->id;
        }
        
        if (isset($row->knownas)){
            $object['prefLabel'] = $row->knownas . " Hoard";
        } else {
            $label = $row->old_findID;
            if (isset($row->parish)){
                $label .= ' (' . $row->parish . ')';
            } elseif (isset($row->district)){
                $label .= ' (' . $row->district . ')';
            } elseif (isset($row->county)){
                $label .= ' (' . $row->county . ')';
            }
            
            $label .= " Hoard";
            
            $object['prefLabel'] = $label;
        }
        
        
        if (isset($row->alsoknownas)){
            $object['altLabel'] = $row->alsoknownas . " Hoard";
        }
        
        
        if (isset($row->TID)){
            $object['TID'] = $row->TID;
        }
        
        if (isset($row->fromdate)){
            $object['closingStart'] = $row->fromdate;
        }
        if (isset($row->todate)){
            $object['closingEnd'] = $row->todate;
        }
        if (isset($row->datefound1)){
            $object['discoveryStart'] = explode('T', $row->datefound1)[0];
        }
        if (isset($row->datefound2)){
            $object['discoveryEnd'] = explode('T', $row->datefound2)[0];
        }
        
        //get OrdnanceSurvey ID
        if (isset($row->parishID)){
            $object['isParish'] = TRUE;
            $object['osID'] = $row->parishID;
        } elseif (isset($row->districtID)){
            $object['osID'] = $row->districtID;
        } elseif (isset($row->countyID)){
            $object['osID'] = $row->countyID; 
        }
        
        //if the osID exists, then look it up in the places spreadsheet
        if (array_key_exists('osID', $object)){
            $osID = "7" . number_pad($object['osID'], 15);
            
            foreach($places as $place){
                if ($place['osID'] == $osID){
                    $object['findspot'] = $place['uri'];
                    break;
                }
            }
        }
        
        $objects[] = $object;
        
    }
    
    //iterate
    if ($start < $total) {
        $page+= 1;
        query_page($page);
    }
    
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