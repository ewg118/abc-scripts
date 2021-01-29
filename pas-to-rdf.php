<?php 
/*****
 * Author: Ethan Gruber
 * Date: January 2021
 * Function: Process CSV extracted from the PAS JSON API from parse-pas.php into Nomisma RDF for coins, hoards, and Wikidata-reconciled places
 */

$coins = generate_json("https://docs.google.com/spreadsheets/d/e/2PACX-1vQdIf_BR6WhDJjFBPxBDHp2mPMW3usN_Wvy97mFe7XwdXYxH9w5SY_jL0I9P7GtkQ-yN-Km6yj4UV7h/pub?output=csv");
$hoards = generate_json("https://docs.google.com/spreadsheets/d/e/2PACX-1vSrmIVpHlkD40HgVqEmnXh2wbV9_enZph9X8t7J4rmcrljfkm4YA9PxCsQ5sYk2v3P0cw_So0odp1DW/pub?output=csv");
$places = generate_json("https://docs.google.com/spreadsheets/d/e/2PACX-1vQWVPKgrRn_2QZ4oYBE6J86dL00wwUopMGBe8Ot2Gb2TTJsvZDfw1klRN-idU5bYW2MSM1OKEC-CV3r/pub?output=csv");

$placeLabels = array();
foreach ($places as $place){
    $placeLabels[$place['uri']] = $place['label'];
}

$count = 1;
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
    $writer->writeAttribute('xmlns:osgeo', 'http://data.ordnancesurvey.co.uk/ontology/geometry/');
    $writer->writeAttribute('xmlns:skos', "http://www.w3.org/2004/02/skos/core#");
    $writer->writeAttribute('xmlns:void', "http://rdfs.org/ns/void#");

    process_coins($writer, $coins, $count);
    process_hoards($writer, $hoards, $count);
    process_places($writer, $places);
    
//end RDF file
$writer->endElement();
$writer->flush();
    
/***** FUNCTIONS *****/
function process_coins($writer, $coins, $count){
    echo "Processing coins.\n";
    
    foreach ($coins as $record){
	    
		if (strlen($record['Type URI'])){
		    echo "{$count}: Processing {$record['URI']}\n";
		    
			$writer->startElement('nmo:NumismaticObject');
				$writer->writeAttribute('rdf:about', $record['URI']);
				
    			$writer->startElement('dcterms:title');
    				$writer->writeAttribute('xml:lang', 'en');
    				$writer->text($record['title']);
    			$writer->endElement();
    			
    			$writer->writeElement('dcterms:identifier', explode(':', $record['title'])[0]);			
    			$writer->startElement('nmo:hasTypeSeriesItem');
    			     $writer->writeAttribute('rdf:resource', $record['Type URI']);
    			$writer->endElement();
    			
			if (strlen($record['Hoard URI']) > 0){
				$writer->startElement('dcterms:isPartOf');
					$writer->writeAttribute('rdf:resource', $record['Hoard URI']);
				$writer->endElement();
			}

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
			
			//insert a findspot only if there isn't a hoard
			if (strlen($record['Findspot']) > 0 && strlen($record['Hoard URI']) == 0){
			    
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
    			                     $writer->writeAttribute('rdf:resource', $record['Findspot']);
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
			}

			//void:inDataset
			$writer->startElement('void:inDataset');
			     $writer->writeAttribute('rdf:resource', 'https://finds.org.uk/');
			$writer->endElement();

			//end nmo:NumismaticObject
			$writer->endElement();
		} else {
		    echo "{$count}: Skipping {$record['URI']}\n";
		}
		$count++;
	}
}

function process_hoards($writer, $hoards){
    GLOBAL $placeLabels;
    
    echo "Processing hoards.\n";
    
    foreach ($hoards as $hoard){
        $writer->startElement('nmo:Hoard');
            $writer->writeAttribute('rdf:about', $hoard['URI']);
            
            $writer->startElement('skos:prefLabel');
                $writer->writeAttribute('xml:lang', 'en');
                $writer->text($hoard['prefLabel']);
            $writer->endElement();
            
            if (strlen($hoard['closingEnd'])){
                $writer->startElement('nmo:hasClosingDate');
                    $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#gYear');
                    $writer->text(number_pad($hoard['closingEnd'], 4));
                $writer->endElement();
            }
            
            if (strlen($hoard['findspot']) > 0){
                $placeURI = $hoard['findspot'];
                
                $writer->startElement('nmo:hasFindspot');
                    $writer->startElement('nmo:Find');
                        if (strlen($hoard['discoveryStart']) > 0 && strlen($hoard['discoveryEnd']) > 0){
                            if ($hoard['discoveryStart'] == $hoard['discoveryEnd']){
                                $writer->startElement('nmo:hasDate');
                                    $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#date');
                                    $writer->text($hoard['discoveryStart']);
                                $writer->endElement();
                            } else {
                                $writer->startElement('nmo:hasStartDate');
                                    $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#date');
                                    $writer->text($hoard['discoveryStart']);
                                $writer->endElement();
                                $writer->startElement('nmo:hasEndDate');
                                    $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#date');
                                    $writer->text($hoard['discoveryEnd']);
                                $writer->endElement();                                
                            }                            

                        } elseif (strlen($hoard['discoveryStart']) > 0 && strlen($hoard['discoveryEnd']) == 0){
                            $writer->startElement('nmo:hasDate');
                                $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#date');
                                $writer->text($hoard['discoveryStart']);
                            $writer->endElement();
                        } elseif (strlen($hoard['discoveryStart']) == 0 && strlen($hoard['discoveryEnd']) > 0){
                            $writer->startElement('nmo:hasDate');
                                $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#date');
                                $writer->text($hoard['discoveryEnd']);
                            $writer->endElement();
                        }
                    
                        $writer->startElement('crm:P7_took_place_at');
                            $writer->startElement('crm:E53_Place');
                                $writer->startElement('rdfs:label');
                                    $writer->writeAttribute('xml:lang', 'en');
                                    $writer->text($placeLabels[$placeURI]);                                    
                                $writer->endElement();
                                $writer->startElement('crm:P89_falls_within');
                                    $writer->writeAttribute('rdf:resource', $placeURI);
                                $writer->endElement();
                            $writer->endElement();
                        $writer->endElement();
                        
                        
                    $writer->endElement();
                $writer->endElement();                
            }
            
            //void:inDataset
            $writer->startElement('void:inDataset');
                $writer->writeAttribute('rdf:resource', 'https://finds.org.uk/');
            $writer->endElement();
        $writer->endElement();
    }
}

function process_places($writer, $places){
    echo "Processing places.\n";
    
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
	       
	      
           if (strlen(trim($place['wkt'])) > 0 || strlen(trim($place['geojson'])) > 0) {
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
	    if (strlen(trim($place['wkt'])) > 0 || strlen(trim($place['geojson'])) > 0) {
            $writer->startElement('geo:SpatialThing');
               $writer->writeAttribute('rdf:about', $place['uri'] . '#this');
               $writer->startElement('rdf:type');
                   $writer->writeAttribute('rdf:resource', 'http://www.ics.forth.gr/isl/CRMgeo/SP5_Geometric_Place_Expression');
               $writer->endElement();
               $writer->startElement('crmgeo:Q9_is_expressed_in_terms_of');
                   $writer->writeAttribute('rdf:resource', 'http://www.wikidata.org/entity/Q215848');
               $writer->endElement();
               
               //display coordinates for parishes
               if (strlen(trim($place['wkt'])) > 0 ){
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
               } elseif (strlen(trim($place['geojson'])) > 0){
                   $writer->startElement('osgeo:asGeoJSON');
                        $writer->text(trim($place['geojson']));
                   $writer->endElement();
               }

            $writer->endElement();
	    }
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