<?php 
/*****
 * Author: Ethan Gruber
 * Date: January 2021
 * Function: Process CSV extracted from the PAS JSON API for coins and hoards to look up unique Ordnance Survey URIs and align to Wikidata, export to CSV
 */

require_once('sparqllib.php');

$abc = generate_json("pas-abc-coins.csv");
$ric = generate_json("pas-ric-coins.csv");
$hoards = generate_json("pas-hoards.csv");
//generate an array of records for outputting
//$records = array();
$count = 1;
$places = array();
$placesQueried = array();

process_sheet($abc, $count);
process_sheet($ric, $count);
process_sheet($hoards, $count);

//write CSV
echo "Writing CSV.\n";

//write CSV for places that will be turned into RDF
$fp = fopen('places.csv', 'w');
fputcsv($fp, array('osID', 'uri', 'label', 'type', 'parent', 'closeMatch', 'wkt', 'lat', 'lon', 'geojson'));

foreach ($places as $place){
    $key = '';
    foreach ($placesQueried as $osID=>$concord){
        if ($place['uri'] == $concord){
            $key = $osID;
            break;
        }
    }
    
    $line = array('osID'=>$key, 'uri'=>$place['uri']);
    
    if (array_key_exists('label', $place)){
        $line['label'] = $place['label'];
    } else {
        $line['label'] = $place['originalLabel'];
    }
    
    if (array_key_exists('type', $place)){
        $line['type'] = $place['type'];
    } else {
        $line['type'] = '';
    }
    
    if (array_key_exists('parent', $place)){
        $line['parent'] = implode('|', $place['parent']);
    } else {
        $line['parent'] = '';
    }
    
    if (array_key_exists('closeMatch', $place)){
        $line['closeMatch'] = implode('|', $place['closeMatch']);
    } else {
        $line['closeMatch'] = '';
    }
    
    if (array_key_exists('wkt', $place)){
        $line['wkt'] = $place['wkt'];
    } else {
        $line['wkt'] = '';
    }
    
    if (array_key_exists('lat', $place)){
        $line['lat'] = $place['lat'];
    } else {
        $line['lat'] = '';
    }
    
    if (array_key_exists('lon', $place)){
        $line['lon'] = $place['lon'];
    } else {
        $line['lon'] = '';
    }
    
    if (array_key_exists('geojson', $place)){
        $line['geojson'] = $place['geojson'];
    } else {
        $line['geojson'] = '';
    }
    
    fputcsv($fp, $line);
}

fclose($fp);

//write concordance CSV of Ordnance Survey IDs to the chosen URI, either Wikidata or Ordnance Survey
$fp = fopen('place-concordance.csv', 'w');
fputcsv($fp, array('osID', 'uri'));
foreach ($placesQueried as $k=>$v){
    fputcsv($fp, array($k, $v));
}
fclose($fp);

/***** FUNCTIONS *****/
function process_sheet($data, $count){
    GLOBAL $places;
    GLOBAL $placesQueried;
    
    //generate an object of Wikidata geographic entities (and hierarchy) from available Ordnance Survey URIs
    foreach ($data as $row){
        if (strlen(trim($row['osID'])) > 0){
            $osID = "7" . number_pad($row['osID'], 15);
            $osgeoURI = "http://data.ordnancesurvey.co.uk/id/" . $osID;
            
            if (!array_key_exists($osID, $placesQueried)){
                echo "Processing {$osID}. \n";
                
                //query Ordnance Survey first
                $type = get_osgeo_type($osgeoURI);
                echo $type . "\n";
                
                $place = query_wikidata($osID, 'oslookup', $type);
                $place['type'] = $type;
                $place['originalLabel'] = $row['placeLabel'];
                
                //get and process the GeoJSON from PAS Github
                if ($type != 'http://data.ordnancesurvey.co.uk/ontology/admingeo/Parish'){
                    $place['geojson'] = parse_geoJSON($row['osID']);
                }
                
                
                //echo $place . "\n";
                
                if (isset($place['uri'])){
                    $places[$place['uri']] = $place;
                    
                    //var_dump($place);
                    $placesQueried[$osID] = $place['uri'];
                } else {
                    $placesQueried[$osID] = "http://data.ordnancesurvey.co.uk/id/{$osID}";
                }
                unset($place);
            }
            $count++;
        }
        //var_dump($places);
    }
}

function parse_geoJSON($osID){
    echo "Getting GeoJSON\n";
    
    $url = "https://raw.githubusercontent.com/findsorguk/findsorguk-geodata/master/geoJSON/{$osID}.geojson";
    $string = file_get_contents($url);
    $json = json_decode($string, true);
    
    //simplify the GeoJSON
    foreach ($json["features"] as $feature){
        $text = '{"type":"Polygon","coordinates":[[';
        $total = count($feature["geometry"]["coordinates"][0]);
        $count = 1;
        
        foreach ($feature["geometry"]["coordinates"][0] as $k=>$coord){
            $c1 = round($coord[0], 4);
            $c2 = round($coord[1], 4);
            
            if ($k % 4 == 0){
                $text .= "[{$c1},{$c2}]";
                if ($count < $total){
                    $text .= ",";
                }
            }
            
            //echo $coord[0] . "\n";
            
            $count++;
        }
        
        $text .= ']]}';
    }
    
    return $text;
}

//submit SPARQL query to the Ordnance Survey endpoint to get the feature type. Only Parishes should have lat-long coordinates
function get_osgeo_type($uri){
    $query = 'PREFIX spatial: <http://data.ordnancesurvey.co.uk/ontology/spatialrelations/>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>

SELECT ?type
WHERE {
<%URI%> rdf:type ?type
}';
    $url = "http://data.ordnancesurvey.co.uk/datasets/os-linked-data/apis/sparql?query=" . urlencode(str_replace('%URI%', $uri, $query));
    
    $ch = curl_init($url);
    #curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, "PHP/Ethan Gruber" );
    curl_setopt($ch, CURLOPT_HTTPHEADER,array (
        "Accept: application/json"
    ));
    
    $output = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    $json = json_decode($output);   
    
    foreach ($json->results->bindings as $result){
        if (isset($result->type->value)){
            return $result->type->value;
        } else {
            return null;
        }
    }
}

//parse the XML response from the Wikidata SPARQL query and generate a place data object
function parse_sparql_response($xml, $mode, $type){
    GLOBAL $places;
    
    $place = array('uri'=>null, 'closeMatch'=>array(), 'parent'=>array());
    
    $xmlDoc = new DOMDocument();
    $xmlDoc->loadXML($xml);
    $xpath = new DOMXpath($xmlDoc);    
    $xpath->registerNamespace('res', 'http://www.w3.org/2005/sparql-results#');
    
    $results = $xpath->query("//res:result");
    
    foreach ($results as $result){
        foreach ($result->getElementsByTagNameNS('http://www.w3.org/2005/sparql-results#', 'binding') as $binding){
            if ($binding->getAttribute('name') == 'place'){
                $uri = $binding->getElementsByTagNameNS('http://www.w3.org/2005/sparql-results#', 'uri')->item(0)->nodeValue;
                
                $place['uri'] = $uri;
            } elseif ($binding->getAttribute('name') == 'placeLabel'){
                $place['label'] = $binding->getElementsByTagNameNS('http://www.w3.org/2005/sparql-results#', 'literal')->item(0)->nodeValue;
            } elseif ($binding->getAttribute('name') == 'coord'){
                //attach coordinates for the ordnance survey lookups only
                if ($mode = 'oslookup'){
                    //only parse coordinates for parish level places
                    if ($type == 'http://data.ordnancesurvey.co.uk/ontology/admingeo/Parish'){
                        $coord = $binding->getElementsByTagNameNS('http://www.w3.org/2005/sparql-results#', 'literal')->item(0)->nodeValue;
                        
                        $place['wkt'] = $coord;
                        
                        //parse WKT into lat/long
                        $pieces = explode(' ', str_replace('Point(', '', str_replace(')', '', $coord)));
                        
                        $place['lat'] = $pieces[1];
                        $place['lon'] = $pieces[0];
                    }
                }
                
               
            } elseif ($binding->getAttribute('name') == 'parent'){
                $parentURI = $binding->getElementsByTagNameNS('http://www.w3.org/2005/sparql-results#', 'uri')->item(0)->nodeValue;
                if (!in_array($parentURI, $place['parent'])){
                    $place['parent'][] = $parentURI;
                    
                    //parse the hierarchy and add a new place if it doesn't exist already
                    if (!array_key_exists($parentURI, $places)){
                        $parent = query_wikidata($parentURI, 'wdlookup', $type);
                        $places[$parentURI] = $parent;                        
                    }
                }  
            } elseif ($binding->getAttribute('name') == 'parentLabel'){
                //ignore this
            } else {
                $match = $binding->getElementsByTagNameNS('http://www.w3.org/2005/sparql-results#', 'uri')->item(0)->nodeValue;
                if (!in_array($match, $place['closeMatch'])){
                    $place['closeMatch'][] = $match;
                }                
            }
        }
    }
    
    return $place;
}

function query_wikidata($id, $mode, $type){
    if ($mode == 'oslookup'){
        $query = 'PREFIX bd:  <http://www.bigdata.com/rdf#>
PREFIX wd: <http://www.wikidata.org/entity/>
PREFIX wdt: <http://www.wikidata.org/prop/direct/>
PREFIX wikibase:    <http://wikiba.se/ontology#>
SELECT ?place ?placeLabel ?osgeo ?tgn ?geonames ?pleiades ?parent ?coord WHERE {
  ?place wdt:P3120 "%ID%"
  OPTIONAL {?place wdt:P3120 ?osgeoid .
  	BIND (uri(concat("http://data.ordnancesurvey.co.uk/id/", ?osgeoid)) as ?osgeo)}
  OPTIONAL {?place wdt:P1667 ?tgnid .
  	BIND (uri(concat("http://vocab.getty.edu/tgn/", ?tgnid)) as ?tgn)}
  OPTIONAL {?place wdt:P1566 ?geonamesid .
  	BIND (uri(concat("http://sws.geonames.org/", ?geonamesid, "/")) as ?geonames)}
  OPTIONAL {?place wdt:P1584 ?pleiadesid .
  	BIND (uri(concat("https://pleiades.stoa.org/places/", ?pleiadesid)) as ?pleiades)}
  OPTIONAL {?place wdt:P131 ?parent}
  OPTIONAL {?place p:P625/ps:P625 ?coord}
  SERVICE wikibase:label {
	bd:serviceParam wikibase:language "en"
  }
}';
    } elseif ($mode == 'wdlookup'){
        $query = 'PREFIX bd:  <http://www.bigdata.com/rdf#>
PREFIX wd: <http://www.wikidata.org/entity/>
PREFIX wdt: <http://www.wikidata.org/prop/direct/>
PREFIX wikibase:    <http://wikiba.se/ontology#>
SELECT ?place ?placeLabel ?osgeo ?tgn ?geonames ?pleiades ?parent WHERE {
  BIND (<%ID%> as ?place)
  OPTIONAL {?place wdt:P3120 ?osgeoid .
  	BIND (uri(concat("http://data.ordnancesurvey.co.uk/id/", ?osgeoid)) as ?osgeo)}
  OPTIONAL {?place wdt:P1667 ?tgnid .
  	BIND (uri(concat("http://vocab.getty.edu/tgn/", ?tgnid)) as ?tgn)}
  OPTIONAL {?place wdt:P1566 ?geonamesid .
  	BIND (uri(concat("http://sws.geonames.org/", ?geonamesid, "/")) as ?geonames)}
  OPTIONAL {?place wdt:P1584 ?pleiadesid .
  	BIND (uri(concat("https://pleiades.stoa.org/places/", ?pleiadesid)) as ?pleiades)}
  OPTIONAL {?place wdt:P131 ?parent}
  SERVICE wikibase:label {
	bd:serviceParam wikibase:language "en"
  }
}';
    }
    
    $url = "https://query.wikidata.org/sparql?query=" . urlencode(str_replace('%ID%', $id, $query));
    
    
    $ch = curl_init($url);
    #curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, "PHP/Ethan Gruber" );
    curl_setopt($ch, CURLOPT_HTTPHEADER,array (
        "Accept: application/sparql-results+xml"
    ));
    
    $output = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    return parse_sparql_response($output, $mode, $type);
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