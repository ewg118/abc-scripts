<?php 
/******
 * Author: Ethan Gruber
 * Date: Feburary 2021
 * Function: Combine the spreadsheet of BM-ABC references from Eleanor with the Iron Age British coins extracted from the British Museum database
 */
$coinTypes = array();
$objects = array();

$data = generate_json('bm-export.csv');
$refs = generate_json('bm-abc.csv');

$count = 1;

foreach ($data as $row){
    $newRow = array();
    foreach ($row as $k=>$v){
        $newRow[$k] = $v;
    }    
    
    $id = $row['Museum number'];
    
    foreach($refs as $ref){
        $regno = (strlen($ref['Registration Number - Year']) > 0 ? $ref['Registration Number - Year'] . ',' : '') . 
        (is_numeric($ref['Registration Number - Collection']) ? number_pad($ref['Registration Number - Collection'], 4) : $ref['Registration Number - Collection']) .
        '.' . $ref['Registration Number - Number'];
    
        if ($regno == $id){
            echo "Processing {$count}: {$id}\n";
            
            if (is_numeric($ref['Registration Number - Collection']) || $ref['Registration Number - Collection'] == 'R' || $ref['Registration Number - Collection'] == 'B'){
                $newRow['URI'] = 'https://www.britishmuseum.org/collection/object/C_' . str_replace('.', '-', str_replace(',', '-', $regno));
            }
            
            
            if (strlen($ref['ABC Number']) > 0 && is_numeric($ref['ABC Number'])){
                $coinType = "https://abc.arch.ox.ac.uk/id/abc." . number_pad(trim($ref['ABC Number']), 4);
                $valid = check_uri($coinType);
                
                if ($valid == true){
                    $newRow['ABC'] = $coinType;
                } else {
                    $newRow['ABC'] = '';
                }
            } else {
                $newRow['ABC'] = '';
            }
            $objects[] = $newRow;
            $count++;
            break;
        }
    }
}

//write CSV
$header = array();
foreach ($data[0] as $k=>$v){
    $header[] = $k;
}
$header[] = 'URI';
$header[] = 'ABC';

$fp = fopen('new-bm-abc.csv', 'w');
fputcsv($fp, $header);
foreach ($objects as $row) {
    fputcsv($fp, $row);
}
fclose($fp);

/***** FUNCTIONS *****/
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