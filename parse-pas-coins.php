<?php 
/*****
 * Author: Ethan Gruber
 * Last modified: December 2020
 * Function: Parse JSON feed for PAS for Iron Age coins and extract ABC or VA numbers
 *****/

$objects = array();
$page = 1;

query_page($page);

//write csv
$fp = fopen('abc.csv', 'w');
$header = array('URI', 'title', 'diameter', 'weight', 'thickness', 'axis', 'isParish', 'osID','placeLabel','lat', 'lon', 'image', 'mackType', 'allenType', 'abcType', 'vaType','cciNumber');
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
    
    $start = ($page - 1) * 20;
    
    $service = "https://finds.org.uk/database/search/results/objecttype/COIN/broadperiod/IRON+AGE/page/{$page}/format/json";    
    $json = file_get_contents($service);
    $data = json_decode($json);    
    $total = $data->meta->totalResults;
    
    echo "Processing records {$start} to " . ($start + 20) . " of {$total}\n";
    //var_dump($data->results);
    
    foreach ($data->results as $row){
        $object = array();
        
        $object['URI'] = "https://finds.org.uk/database/artefacts/record/id/" . $row->id;
        
        if (!isset($row->tribeName) || $row->tribeName == "Uninscribed") {
            $object['title'] = $row->old_findID . ": An uninscribed Iron Age coin";
        } else {
            $object['title'] = $row->old_findID . ": An Iron Age coin attributed to the " . $row->tribeName;
        }
        
        //measurements
        if (isset($row->diameter)){
            $object['diameter'] = $row->diameter;
        }
        if (isset($row->weight)){
            $object['weight'] = $row->weight;
        }
        if (isset($row->thickness)){
            $object['thickness'] = $row->thickness;
        }
        if (isset($row->axis)){
            $object['axis'] = $row->axis;
        }
        
        //get OrdnanceSurvey ID
        if (isset($row->parishID)){
            $object['isParish'] = TRUE;
            $object['osID'] = $row->parishID;
            $object['placeLabel'] = $row->parish;
        } elseif (isset($row->districtID)){
            $object['osID'] = $row->districtID;
            $object['placeLabel'] = $row->district;
        } elseif (isset($row->countyID)){
            $object['osID'] = $row->countyID;            
            $object['placeLabel'] = $row->county;
        }
        
        //use knownas if available
        if (isset($row->knownas)){
            $object['placeLabel'] = $row->knownas;
        }
        
        //coordinates
        if (isset($row->fourFigureLat) && isset($row->fourFigureLon)){
            $object['lat'] = $row->fourFigureLat;
            $object['lon'] = $row->fourFigureLon;
        }
        
        if (isset($row->imagedir) && isset($row->filename)){
            $object['image'] = "https://finds.org.uk/{$row->imagedir}{$row->filename}";
        }
        
        if (isset($row->mackType)){
            $object['mackType'] = $row->mackType;
        }
        if (isset($row->allenType)){
            $object['allenType'] = $row->allenType;
        }
        if (isset($row->abcType)){
            $object['abcType'] = $row->abcType;
        }
        if (isset($row->vaType)){
            $object['vaType'] = $row->vaType;
        }
        
        if (isset($row->cciNumber)){
            $object['cciNumber'] = $row->cciNumber;
        }
        
        $objects[] = $object;
        
    }
    
    //iterate
    if ($start < $total) {
        $page+= 1;
        query_page($page);
    }
    
}

?>