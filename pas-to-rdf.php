<?php 
/*****
 * Author: Ethan Gruber
 * Date: January 2021
 * Function: Process CSV extracted from the PAS JSON API from parse-pas.php into Nomisma RDF
 */

require_once('sparqllib.php');

$data = generate_json("abc-merged.csv");
$places = generate_json("places.csv");

//store successful hits
$coinTypes = array();

//generate an array of records for outputting
//$records = array();

$count = 1;
generate_rdf($data, $count, $places);

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

function generate_rdf($data, $count, $places){
    echo "Processing RDF.\n";
	//start RDF/XML file
	//use XML writer to generate RDF
	$writer = new XMLWriter();
	$writer->openURI("pas-abc.rdf");
	//$writer->openURI('php://output');
	$writer->startDocument('1.0','UTF-8');
	$writer->setIndent(true);
	//now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
	$writer->setIndentString("    ");

	$writer->startElement('rdf:RDF');
	$writer->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema#');
	$writer->writeAttribute('xmlns:nm', "http://nomisma.org/id/");
	$writer->writeAttribute('xmlns:nmo', "http://nomisma.org/ontology#");
	$writer->writeAttribute('xmlns:dcterms', "http://purl.org/dc/terms/");
	$writer->writeAttribute('xmlns:foaf', 'http://xmlns.com/foaf/0.1/');
	$writer->writeAttribute('xmlns:geo', "http://www.w3.org/2003/01/geo/wgs84_pos#");
	$writer->writeAttribute('xmlns:rdf', "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
	$writer->writeAttribute('xmlns:rdfs', "http://www.w3.org/2000/01/rdf-schema#");
	$writer->writeAttribute('xmlns:crm', "http://www.cidoc-crm.org/cidoc-crm/");
	$writer->writeAttribute('xmlns:crmgeo', "http://www.ics.forth.gr/isl/CRMgeo/");
	$writer->writeAttribute('xmlns:skos', "http://www.w3.org/2004/02/skos/core#");
	$writer->writeAttribute('xmlns:void', "http://rdfs.org/ns/void#");
	
	foreach ($data as $record){
	        
	    $coinType = null;
	    $valid = false;
	    
	    if (strlen($record['newABC']) > 0){
	        $abc = trim($record['newABC']);
	        //read newABC first
	        if (strlen($abc) == 4){
	            echo "true\n";
	            $coinType = "https://abc.arch.ox.ac.uk/id/abc." . $abc;
	            $valid = check_uri($coinType);
	        }   
	    } elseif (strlen($record['abcType']) > 0){
	        $abc = trim($record['abcType']);
	        
	        if (strpos($abc, '.') !== FALSE){
	            $id = explode('.', $abc)[0];
	            $coinType = "https://abc.arch.ox.ac.uk/id/abc." . number_pad($id, 4);
	            $valid = check_uri($coinType);
	        } elseif (is_numeric($abc)){
	            $coinType = "https://abc.arch.ox.ac.uk/id/abc." . number_pad($abc, 4);
	            $valid = check_uri($coinType);
	        }
	    } else {
	        echo "{$count}: Pass\n";
	    }
	    
		if (isset($coinType) && $valid == true){
		    echo "{$count}: Processing {$record['URI']}\n";
		    
			$writer->startElement('nmo:NumismaticObject');
				$writer->writeAttribute('rdf:about', $record['URI']);
				
    			$writer->startElement('dcterms:title');
    				$writer->writeAttribute('xml:lang', 'en');
    				$writer->text($record['title']);
    			$writer->endElement();
    			
    			$writer->writeElement('dcterms:identifier', explode(':', $record['title'])[0]);			
    			$writer->startElement('nmo:hasTypeSeriesItem');
    				$writer->writeAttribute('rdf:resource', $coinType);
    			$writer->endElement();
    			
			/*if (isset($record['hoard'])){
				$writer->startElement('dcterms:isPartOf');
					$writer->writeAttribute('rdf:resource', $record['hoard']);
				$writer->endElement();
			}*/

			//conditional measurement data
			if (strlen($record['weight']) > 0){
				$writer->startElement('nmo:hasWeight');
					$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
					$writer->text($record['weight']);
				$writer->endElement();
			}
			if (strlen($record['diameter']) > 0){
				$writer->startElement('nmo:hasDiameter');
					$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
					$writer->text($record['diameter']);
				$writer->endElement();
			}
			if (strlen($record['axis']) > 0){
				$writer->startElement('nmo:hasAxis');
					$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#integer');
					$writer->text($record['axis']);
				$writer->endElement();
			}
			if (strlen($record['thickness']) > 0){
			    $writer->startElement('nmo:hasDepth');
    			    $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
    			    $writer->text($record['thickness']);
			    $writer->endElement();
			}

			//conditional images
			if (strlen($record['image']) > 0){
			    $writer->startElement('foaf:depiction');
                    $writer->writeAttribute('rdf:resource', str_replace(' ', '%20', $record['image']));
			    $writer->endElement();
			}
			
			//findspot
			if (strlen($record['osID']) > 0){
			    $osID = "7" . number_pad($record['osID'], 15);
			    $osURI = "http://data.ordnancesurvey.co.uk/id/{$osID}";
			    
			    //set the $geoURI as the OS URI as default, to be overwritten if there's an equivalent Wikidata URI in the $places array
			    $geoURI = "http://data.ordnancesurvey.co.uk/id/{$osID}";
			    
			    foreach ($places as $place){
			        if ($place['osID'] == $osID){
			            $geoURI = $place['uri'];
			            break;
			        } else {
			            $matches = explode('|', $place['closeMatch']);
			            if (in_array($osURI, $matches)){
			                $geoURI = $place['uri'];
			                break;
			            }
			        }
			    }
			    
			    $writer->startElement('nmo:hasFindspot');
			         $writer->startElement('nmo:Find');
			             $writer->startElement('crm:P7_took_place_at');
			                 //place
			                 $writer->startElement('crm:E53_Place');
    			                 $writer->startElement('rdfs:label');
        			                 $writer->writeAttribute('xml:lang', 'en');
        			                 $writer->text($record['placeLabel']);
    			                 $writer->endElement();
    			                 
    			                 $writer->startElement('crm:P89_falls_within');
    			                     $writer->writeAttribute('rdf:resource', $geoURI);
    			                 $writer->endElement();
    			                 
    			                 //insert SpatialThing for a public coordinate
    			                 if (strlen($record['lat']) > 0 && strlen($record['lon']) > 0){
    			                     $writer->startElement('geo:location');
    			                         $writer->startElement('geo:SpatialThing');
    			                             $writer->startElement('rdf:type');
    			                                 $writer->writeAttribute('rdf:resource', 'http://www.ics.forth.gr/isl/CRMgeo/SP5_Geometric_Place_Expression');
    			                             $writer->endElement();
    			                             
    			                             $writer->startElement('geo:lat');
    			                                 $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
    			                                 $writer->text($record['lat']);
    			                             $writer->endElement();
    			                             $writer->startElement('geo:long');
        			                             $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
        			                             $writer->text($record['lon']);
    			                             $writer->endElement();
    			                         $writer->endElement();
    			                     $writer->endElement();
    			                 }
			                 $writer->endElement();
			                 
			                 //end place
			             $writer->endElement();			            
			         $writer->endElement();
			    $writer->endElement();
			    
			    unset($geoURI);
			}

			//void:inDataset
			$writer->startElement('void:inDataset');
			     $writer->writeAttribute('rdf:resource', 'https://finds.org.uk/');
			$writer->endElement();

			//end nmo:NumismaticObject
			$writer->endElement();
		}
		
		$count++;
	}
	
	//iterate through Wikidata places to create SKOS concepts
	foreach ($places as $place){
	    $writer->startElement('crm:E53_Place');
	       $writer->writeAttribute('rdf:about', $place['uri']);
	       $writer->writeElement('rdfs:label', $place['label']);
	       
	       if (array_key_exists('parent', $place)){
	           foreach (explode('|', $place['parent']) as $parent){
	               $writer->startElement('crm:P89_falls_within');
	                   $writer->writeAttribute('rdf:resource', $parent);
	               $writer->endElement();
	           }
	       }
	       
	       if (array_key_exists('closeMatch', $place)){
	           foreach (explode('|', $place['closeMatch']) as $match){
	               $writer->startElement('skos:closeMatch');
	                   $writer->writeAttribute('rdf:resource', $match);
	               $writer->endElement();
	           }
	       }
	       
	      
           if (strlen(trim($place['wkt'])) > 0) {
	           $writer->startElement('geo:location');
	               $writer->writeAttribute('rdf:resource', $place['uri'] . '#this');
	           $writer->endElement();
	           $writer->startElement('crm:P168_place_is_defined_by');
	               $writer->writeAttribute('rdf:resource', $place['uri'] . '#this');
	           $writer->endElement();
           }
	       	       
        //end nmo:Numismatic Object
	    $writer->endElement();
	    
	    //geo:SpatialThing
	    if (strlen(trim($place['wkt'])) > 0) {
            $writer->startElement('geo:SpatialThing');
               $writer->writeAttribute('rdf:about', $place['uri'] . '#this');
               $writer->startElement('rdf:type');
                   $writer->writeAttribute('rdf:resource', 'http://www.ics.forth.gr/isl/CRMgeo/SP5_Geometric_Place_Expression');
               $writer->endElement();
               $writer->startElement('crmgeo:Q9_is_expressed_in_terms_of');
                   $writer->writeAttribute('rdf:resource', 'http://www.wikidata.org/entity/Q215848');
               $writer->endElement();
               $writer->startElement('geo:lat');
                   $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
                   $writer->text($place['lat']);
               $writer->endElement();
               $writer->startElement('geo:long');
    	           $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
    	           $writer->text($place['lon']);
               $writer->endElement();
               $writer->startElement('crmgeo:asWKT');
    	           $writer->writeAttribute('rdf:datatype', 'http://www.opengis.net/ont/geosparql#wktLiteral');
    	           $writer->text($place['wkt']);
               $writer->endElement();
            $writer->endElement();
	    }
	}	

	//end RDF file
	$writer->endElement();
	$writer->flush();
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