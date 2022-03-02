<?php 
 /*****
 * Author: Ethan Gruber
 * Last modified: February 2022
 * Function: Process CCID spreadsheet into NUDS
 *****/

$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vSNbNjFpUz5DS1a2u8sa7g9e_dKDlkawMfDIqgtieAdxtbjMjgLeichvidEwrdl9oJsKehNCyBH49zK/pub?output=csv');
$images = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vSpSU905tlJab-PL_McGYY6S4tP0veMPOEOLvdH22Jngtju_H9GYqR4mLT4-5mElJIiZKLaoblaG9RU/pub?output=csv');

$findspots = array();

//$records = array();

$count = 1;

foreach($data as $row){
	//call generate_nuds twice to generate two sets of NUDS records
    generate_nuds($row, $count);
    $count++;
}

//functions

function generate_nuds($row, $count){	
    GLOBAL $findspots;
    GLOBAL $images;
    
    $iiif_base = 'https://cci.arch.ox.ac.uk/images/';
    
	$recordId = trim($row['cciNumber']);	
	
	if (strlen($recordId) > 0){
		echo "Processing {$recordId}\n";
		
		//extract data from Wikidata, if necessary
		if (strlen($row['Findspot']) > 0){
		    $uri = $row['Findspot'];
		    if (!array_key_exists($uri, $findspots)){
		        $findspot = parse_findspot($uri);
		        
		        $findspots[$uri] = $findspot;
		    }
		}   
		
		$doc = new XMLWriter();
		
		//$doc->openUri('php://output');
		$doc->openUri('cci-nuds/' . $recordId . '.xml');
		$doc->setIndent(true);
		//now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
		$doc->setIndentString("    ");
		
		$doc->startDocument('1.0','UTF-8');
		
		$doc->startElement('nuds');
			$doc->writeAttribute('xmlns', 'http://nomisma.org/nuds');
				$doc->writeAttribute('xmlns:xs', 'http://www.w3.org/2001/XMLSchema');
				$doc->writeAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
				$doc->writeAttribute('xmlns:tei', 'http://www.tei-c.org/ns/1.0');	
				$doc->writeAttribute('xmlns:mets', 'http://www.loc.gov/METS/');
				$doc->writeAttribute('xmlns:gml', 'http://www.opengis.net/gml');	
				$doc->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
				$doc->writeAttribute('xsi:schemaLocation', 'http://nomisma.org/nuds http://nomisma.org/nuds.xsd');
				$doc->writeAttribute('recordType', 'physical');
			
			//control
			$doc->startElement('control');
				$doc->writeElement('recordId', $recordId);			
				
				//other record IDs
				if (strlen($row['PAS URI']) > 0){
				    $doc->startElement('otherRecordId');
    				    $doc->writeAttribute('semantic', 'dcterms:replaces');
    				    $doc->text($row['PAS URI']);
				    $doc->endElement();
				}
				
				if (strlen($row['leins_ID']) > 0) {
				    $doc->startElement('otherRecordId');
				        $doc->writeAttribute('localType', 'leinsID');
				        $doc->text($row['leins_ID']);
				    $doc->endElement();
				}
				
				$doc->writeElement('publicationStatus', 'approved');
				
				$doc->writeElement('maintenanceStatus', 'derived');
				$doc->startElement('maintenanceAgency');
					$doc->writeElement('agencyName', 'Institute of Archaeology, Oxford University');
				$doc->endElement();				
				
				//maintenanceHistory
				$doc->startElement('maintenanceHistory');
					$doc->startElement('maintenanceEvent');
						$doc->writeElement('eventType', 'derived');
						$doc->startElement('eventDateTime');
							$doc->writeAttribute('standardDateTime', date(DATE_W3C));
							$doc->text(date(DATE_RFC2822));
						$doc->endElement();
						$doc->writeElement('agentType', 'machine');
						$doc->writeElement('agent', 'PHP');
						$doc->writeElement('eventDescription', 'Generated from CSV from Google Drive.');
					$doc->endElement();
				$doc->endElement();
				
				//rightsStmt
				$doc->startElement('rightsStmt');
					$doc->writeElement('copyrightHolder', 'Institute of Archaeology, Oxford University');
					$doc->startElement('license');
						$doc->writeAttribute('xlink:type', 'simple');
						$doc->writeAttribute('xlink:href', 'https://creativecommons.org/licenses/by-nc-sa/4.0/');
						$doc->text('Creative Commons BY-NC-SA 4.0');
					$doc->endElement();
				$doc->endElement();
				
				//semanticDeclaration
				$doc->startElement('semanticDeclaration');
					$doc->writeElement('prefix', 'dcterms');
					$doc->writeElement('namespace', 'http://purl.org/dc/terms/');
				$doc->endElement();
				
				$doc->startElement('semanticDeclaration');
					$doc->writeElement('prefix', 'nmo');
					$doc->writeElement('namespace', 'http://nomisma.org/ontology#');
				$doc->endElement();
				
				$doc->startElement('semanticDeclaration');
					$doc->writeElement('prefix', 'skos');
					$doc->writeElement('namespace', 'http://www.w3.org/2004/02/skos/core#');
				$doc->endElement();
			//end control
			$doc->endElement();
		
			//start descMeta
			$doc->startElement('descMeta');
		
    			//title			
    			$doc->startElement('title');
        			$doc->writeAttribute('xml:lang', 'en');
        			$doc->text("CCI " . str_replace("CCI-", "", $recordId));
    			$doc->endElement();
    			
    			/***** NOTES *****/
    			if (strlen(trim($row['cci_obv_inscription'])) > 0 || strlen(trim($row['cci_rev_inscription'])) > 0){
    				$doc->startElement('noteSet');
    				if (strlen(trim($row['cci_obv_inscription'])) > 0){
    					$doc->startElement('note');
    						$doc->writeAttribute('xml:lang', 'en');
    						$doc->writeAttribute('localType', 'obvLegend');
    						$doc->text(trim($row['cci_obv_inscription']));
    					$doc->endElement();
    				}
    				if (strlen(trim($row['cci_rev_inscription'])) > 0){
    				    $doc->startElement('note');
        				    $doc->writeAttribute('xml:lang', 'en');
        				    $doc->writeAttribute('localType', 'revLegend');
        				    $doc->text(trim($row['cci_rev_inscription']));
    				    $doc->endElement();
    				}
    				$doc->endElement();
    			}
    			
    			/***** TYPEDESC *****/
    			$doc->startElement('typeDesc');
    			
    			if (strlen($row['ABC URI']) > 0){
    			    $doc->writeAttribute('xlink:type', 'simple');
    			    $doc->writeAttribute('xlink:href', trim($row['ABC URI']));
    			} else {
    			    $doc->startElement('objectType');
    			         $doc->writeAttribute('xlink:type', 'simple');
    			         $doc->writeAttribute('xlink:href', 'http://nomisma.org/id/coin');
    			         $doc->text('Coin');
    			    $doc->endElement();
    			}
    				
    			//end typeDesc
                $doc->endElement();
                
                /***** PHYSDESC *****/
                if (strlen($row['weight']) > 0 || strlen($row['diameter']) > 0 || strlen($row['thickness']) > 0 || strlen($row['gold']) > 0 || strlen($row['silver']) > 0 || strlen($row['copper']) > 0 || strlen($row['tin']) > 0 || strlen($row['spec gravity']) > 0) {
                    $doc->startElement('physDesc');
                    
                    //measurements
                    if (strlen($row['weight']) > 0 || strlen($row['diameter']) > 0 || strlen($row['thickness']) > 0 || strlen($row['spec gravity']) > 0) {
                        $doc->startElement('measurementsSet');
                            if (strlen($row['weight']) > 0 && is_numeric($row['weight'])){
                                $doc->startElement('weight');
                                    $doc->writeAttribute('units', 'g');
                                    $doc->text(trim($row['weight']));
                                $doc->endElement();
                            }
                            if (strlen($row['diameter']) > 0 && is_numeric($row['diameter'])){
                                $doc->startElement('diameter');
                                    $doc->writeAttribute('units', 'mm');
                                    $doc->text(intval(ceil(trim($row['diameter']))));
                                $doc->endElement();
                            }
                            if (strlen($row['thickness']) > 0 && is_numeric($row['thickness'])){
                                $doc->startElement('thickness');
                                    $doc->writeAttribute('units', 'mm');
                                    $doc->text(trim($row['thickness']));
                                $doc->endElement();
                            }
                            if (strlen($row['spec gravity']) > 0 && is_numeric($row['spec gravity'])){
                                $doc->startElement('specificGravity');
                                    $doc->text(trim($row['spec gravity']));
                                $doc->endElement();
                            }
                        $doc->endElement();
                    }
                    
                    //chemical analysis
                    if (strlen($row['gold']) > 0 || strlen($row['silver']) > 0 || strlen($row['copper']) > 0 || strlen($row['tin']) > 0) {
                        $doc->startElement('chemicalAnalysis');
                            $doc->startElement('components');
                                if (strlen($row['gold']) > 0 && is_numeric($row['gold'])) {
                                    $doc->startElement('component');
                                        $doc->writeAttribute('xlink:type', 'simple');
                                        $doc->writeAttribute('xlink:href', 'http://nomisma.org/id/av');
                                        $doc->writeAttribute('percentage', $row['gold']);
                                        $doc->text('Gold');
                                    $doc->endElement();
                                }
                                if (strlen($row['silver']) > 0 && is_numeric($row['silver'])) {
                                    $doc->startElement('component');
                                        $doc->writeAttribute('xlink:type', 'simple');
                                        $doc->writeAttribute('xlink:href', 'http://nomisma.org/id/ar');
                                        $doc->writeAttribute('percentage', $row['silver']);
                                        $doc->text('Silver');
                                    $doc->endElement();
                                }
                                if (strlen($row['copper']) > 0 && is_numeric($row['copper'])) {
                                    $doc->startElement('component');
                                        $doc->writeAttribute('xlink:type', 'simple');
                                        $doc->writeAttribute('xlink:href', 'http://nomisma.org/id/cu');
                                        $doc->writeAttribute('percentage', $row['copper']);
                                        $doc->text('Copper');
                                    $doc->endElement();
                                }
                                if (strlen($row['tin']) > 0 && is_numeric($row['tin'])) {
                                    $doc->startElement('component');
                                        $doc->writeAttribute('xlink:type', 'simple');
                                        $doc->writeAttribute('xlink:href', 'http://nomisma.org/id/sn');
                                        $doc->writeAttribute('percentage', $row['tin']);
                                        $doc->text('Tin');
                                    $doc->endElement();
                                }                                
                                
                            $doc->endElement();
                        $doc->endElement();
                    }
                        

                    $doc->endElement();
                }
                
                /***** FINDSPOTDESC *****/                
                if (strlen($row['Hoard URI']) > 0 || strlen($row['Findspot']) > 0){
                    $doc->startElement('findspotDesc');
                    if (strlen($row['Hoard URI']) > 0){
                        $doc->startElement('hoard');
                            $doc->writeAttribute('xlink:type', 'simple');
                            $doc->writeAttribute('xlink:href', $row['Hoard URI']);
                            $doc->text($row['Hoard Name']);
                        $doc->endElement();
                    }
                    if (strlen($row['Findspot'])){
                        $placeURI = trim($row['Findspot']);
                        
                        $doc->startElement('findspot');
                            $doc->startElement('description');
                                $doc->writeAttribute('xml:lang', 'en');
                                $doc->text($row['placeLabel']);
                            $doc->endElement();
                            $doc->startElement('fallsWithin');
                            
                                //use Wikidata API to extract coordinates
                                if (array_key_exists($placeURI, $findspots)){
                                    $doc->startElement('geogname');
                                        $doc->writeAttribute('xlink:type', 'simple');
                                        $doc->writeAttribute('xlink:role', 'findspot');
                                        $doc->writeAttribute('xlink:href', $placeURI);
                                        $doc->text($findspots[$placeURI]['label']);
                                    $doc->endElement();
                                    
                                    if (array_key_exists('lat', $findspots[$placeURI]) && array_key_exists('lon', $findspots[$placeURI])){
                                        $doc->startElement('gml:location');
                                            $doc->startElement('gml:Point');
                                                $doc->writeElement('gml:coordinates', $findspots[$placeURI]['lon'] . ',' . $findspots[$placeURI]['lat']);
                                            $doc->endElement();
                                        $doc->endElement();
                                    }                                    
                                }                                
                            $doc->endElement();
                        $doc->endElement();
                    }
                    $doc->endElement();
                }
                
                /***** ADMINDESC *****/	
                if (strlen($row['currentLocation']) > 0) {
                    $doc->startElement('adminDesc');
                        $doc->writeElement('collection', trim($row['currentLocation']));
                    $doc->endElement();
                }
    				
    			/***** REFDESC *****/			
                if (strlen($row['ABC URI']) > 0 || strlen($row['Allen']) > 0 || strlen($row['Van Arsdell']) > 0 || strlen($row['Mack']) > 0  || strlen($row['Spink']) > 0  || strlen($row['BMC']) > 0){
                    $doc->startElement('refDesc');
                    	//abc references
                    	if (strlen($row['ABC URI']) > 0){        			    
                    	    $doc->startElement('reference');
                    		    $doc->writeAttribute('xlink:type', 'simple');
                    		    $doc->writeAttribute('xlink:href', $row['ABC URI']);
                    		    $doc->startElement('tei:title');
                    		         $doc->writeAttribute('key', 'http://nomisma.org/id/ancient_british_coins');
                    		         $doc->text('ABC');
                    		    $doc->endElement();
                    		    $doc->startElement('tei:idno');
                        		    if (strlen($row['ABC source']) > 0){
                        		        $doc->writeAttribute('resp', trim($row['ABC source']));
                        		    }                    		         
                    		        $doc->text(ltrim(str_replace('https://iacb.arch.ox.ac.uk/id/abc.', '', $row['ABC URI']), '0'));
                    	        $doc->endElement();
                    	    $doc->endElement();
                    	} 
                    	if (strlen($row['Allen']) > 0){
                    	    $doc->startElement('reference');
                        	    $doc->startElement('tei:title');
                            	    $doc->writeAttribute('key', 'https://zenon.dainst.org/Record/000905162');
                            	    $doc->text('Allen');
                        	    $doc->endElement();
                        	    $doc->startElement('tei:idno');
                        	       $doc->text($row['Allen']);
                        	    $doc->endElement();
                    	    $doc->endElement();
                    	} 
                    	if (strlen($row['BMC']) > 0){
                    	    $doc->startElement('reference');
                        	    $doc->startElement('tei:title');
                            	    $doc->writeAttribute('key', 'https://zenon.dainst.org/Record/000198595');
                            	    $doc->text('Hobbs');
                        	    $doc->endElement();
                        	    $doc->startElement('tei:idno');
                        	       $doc->text($row['BMC']);
                        	    $doc->endElement();
                    	    $doc->endElement();
                    	} 
                    	if (strlen($row['Spink']) > 0){
                    	    $doc->startElement('reference');
                        	    $doc->startElement('tei:title');
                            	    $doc->writeAttribute('key', 'https://zenon.dainst.org/Record/000949467');
                            	    $doc->text('Spink');
                        	    $doc->endElement();
                        	    $doc->startElement('tei:idno');
                        	       $doc->text($row['Spink']);
                        	    $doc->endElement();
                    	    $doc->endElement();
                    	} 
                    	if (strlen($row['Van Arsdell']) > 0){
                    	    $doc->startElement('reference');
                        	    $doc->startElement('tei:title');
                            	    $doc->writeAttribute('key', 'https://zenon.dainst.org/Record/000254373');
                            	    $doc->text('Van Arsdell');
                        	    $doc->endElement();
                        	    $doc->startElement('tei:idno');
                        	       $doc->text($row['Van Arsdell']);
                        	    $doc->endElement();
                    	    $doc->endElement();
                    	} 
                    	if (strlen($row['Mack']) > 0){
                    	    $doc->startElement('reference');
                        	    $doc->startElement('tei:title');
                            	    $doc->writeAttribute('key', 'https://zenon.dainst.org/Record/000151926');
                            	    $doc->text('Mack');
                        	    $doc->endElement();
                        	    $doc->startElement('tei:idno');
                        	       $doc->text($row['Mack']);
                        	    $doc->endElement();
                    	    $doc->endElement();
                    	}
                    //end refDesc
                    $doc->endElement();
                }
    		
			//end descMeta
			$doc->endElement();		
			
			/***** DIGREP *****/	
			$doc->startElement('digRep');
    			$doc->startElement('mets:fileSec');
    			//create fileGrps for each card, each which contains a fileGrp for individual files corresponding to the recto and verso of cards, if applicable
    			     
    			//parse the images spreadsheet to restructure into an object
    			$files = array();
    			foreach ($images as $image){
    			    $id = 'CCI-' . $image['id'];
    			    if ($id == $recordId){
    			        //create an array for corresponding images
    			        
    			        foreach ($image as $k=>$v){
    			            if ($k != 'id'){
    			                if (strlen($v) > 0){
    			                    $index = substr($k, 0, 1);
    			                    $side = (substr($k, 1, 1) == 'r') ? 0 : 1;
    			                    
    			                    $files[$index][$side] = $v;
    			                }
    			            }
    			        }			        
    			        break;
    			    }
    			}
    			
    			foreach ($files as $group){
    			    $doc->startElement('mets:fileGrp');
    			         $doc->writeAttribute('USE', 'card');
    			         
    			         foreach ($group as $k=>$file){
    			             $side = ($k == 0) ? 'recto' : 'verso';
    			             $folder = explode('.', $file)[0];
    			             $api = $iiif_base . urlencode($folder  . '/' . $file);
    			             
    			             $doc->startElement('mets:fileGrp');
    			                 $doc->writeAttribute('USE', $side);
    			                 
    			                 //create each file
    			                 //IIIF
    			                 $doc->startElement('mets:file');
    			                     $doc->writeAttribute('USE', 'iiif');
    			                     
    			                     $doc->startElement('mets:FLocat');
    			                         $doc->writeAttribute('LOCTYPE', 'URL');
    			                         $doc->writeAttribute('xlink:href', $api);
    			                     $doc->endElement();
    			                 $doc->endElement();
    			                 
    			                 //reference
    			                 $doc->startElement('mets:file');
    			                 $doc->writeAttribute('USE', 'reference');    			                 
    			                 $doc->writeAttribute('MIMETYPE', 'image/jpeg');
    			                 
        			                 $doc->startElement('mets:FLocat');
            			                 $doc->writeAttribute('LOCTYPE', 'URL');
            			                 $doc->writeAttribute('xlink:href', $api . '/full/1000,/0/default.jpg');
        			                 $doc->endElement();
    			                 $doc->endElement();
    			                 
    			                 //thumbnail
    			                 $doc->startElement('mets:file');
        			                 $doc->writeAttribute('USE', 'thumbnail');        			                 
        			                 $doc->writeAttribute('MIMETYPE', 'image/jpeg');
        			                 
        			                 $doc->startElement('mets:FLocat');
            			                 $doc->writeAttribute('LOCTYPE', 'URL');
            			                 $doc->writeAttribute('xlink:href', $api . '/full/240,/0/default.jpg');
        			                 $doc->endElement();
    			                 $doc->endElement();
    			                 
    			             //end fileGrp for r/v
    			             $doc->endElement();
    			         }
    			    
    			    //end fileGrp for card
    			    $doc->endElement();
    			}
    		
    			//end fileSec
    			$doc->endElement();
						
			//end digRep
			$doc->endElement();
			
		//close NUDS
		$doc->endElement();
		
		//close file
		$doc->endDocument();
		$doc->flush();
		
		unset($files);
	} else {
		//echo "No number for {$row['cciNumber']}.\n";
	}
}


 /***** FUNCTIONS *****/
//extract label and coordinates from Wikidata
function parse_findspot($uri){
        
    $query = 'PREFIX bd:  <http://www.bigdata.com/rdf#>
PREFIX wd: <http://www.wikidata.org/entity/>
PREFIX wdt: <http://www.wikidata.org/prop/direct/>
PREFIX wikibase:    <http://wikiba.se/ontology#>
SELECT ?place ?placeLabel ?type ?coord WHERE {
  BIND (<%URI%> as ?place)
  OPTIONAL {?place p:P625/ps:P625 ?coord}
  OPTIONAL {?place wdt:P31 ?type FILTER (?type = wd:Q1115575)} #civil parish
  SERVICE wikibase:label {
	bd:serviceParam wikibase:language "en"
  }
}';

    $url = "https://query.wikidata.org/sparql?query=" . urlencode(str_replace('%URI%', $uri, $query));    
    
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
    
    return parse_sparql_response($output);
}

//parse the XML response from the Wikidata SPARQL query and generate a place data object
function parse_sparql_response($xml){   
    
    $place = array();
    
    $xmlDoc = new DOMDocument();
    $xmlDoc->loadXML($xml);
    $xpath = new DOMXpath($xmlDoc);
    $xpath->registerNamespace('res', 'http://www.w3.org/2005/sparql-results#');
    
    $results = $xpath->query("//res:result");
    
    $type = null;
    
    foreach ($results as $result){
        $bindings = $result->getElementsByTagNameNS('http://www.w3.org/2005/sparql-results#', 'binding');
        
        //get the type
        foreach ($bindings as $binding){
            if ($binding->getAttribute('name') == 'type'){
                $type = $binding->getElementsByTagNameNS('http://www.w3.org/2005/sparql-results#', 'uri')->item(0)->nodeValue;
            }
        }        
        
        foreach ($bindings as $binding){
            if ($binding->getAttribute('name') == 'place'){
                $uri = $binding->getElementsByTagNameNS('http://www.w3.org/2005/sparql-results#', 'uri')->item(0)->nodeValue;
                
                $place['uri'] = $uri;
            } elseif ($binding->getAttribute('name') == 'placeLabel'){
                $place['label'] = $binding->getElementsByTagNameNS('http://www.w3.org/2005/sparql-results#', 'literal')->item(0)->nodeValue;
            } elseif ($binding->getAttribute('name') == 'coord'){
                //only parse coordinates for parish level places
                if ($type == 'http://www.wikidata.org/entity/Q1115575'){
                    $coord = $binding->getElementsByTagNameNS('http://www.w3.org/2005/sparql-results#', 'literal')->item(0)->nodeValue;
                    
                    $place['wkt'] = $coord;
                    
                    //parse WKT into lat/long
                    $pieces = explode(' ', str_replace('Point(', '', str_replace(')', '', $coord)));
                    
                    $place['lat'] = $pieces[1];
                    $place['lon'] = $pieces[0];
                }
            }
        }
    } 
    
    echo "Extracted {$place['uri']}\n";
    
    return $place;
}

//normalize integer into human-readable date
function get_date_textual($year){
    $textual_date = '';
    //display start date
    if($year < 0){
        $textual_date .= abs($year) . ' BC';
    } elseif ($year > 0) {
        if ($year <= 600){
            $textual_date .= 'AD ';
        }
        $textual_date .= $year;
    }
    return $textual_date;
}

//pad integer value from Filemaker to create a year that meets the xs:gYear specification
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