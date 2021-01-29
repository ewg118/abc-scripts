<?php 
 /*****
 * Author: Ethan Gruber
 * Last modified: January 2021
 * Function: Process ABC typology into NUDS
 *****/

$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vTHVypnlNT5CpOKfVd4BzUd2Ydpm5Yd88XqVx4i6UcBQ9VM8-c9VvdC4x9hSk-DsxLeZF_qkafcw2Zk/pub?output=csv');

$nomismaUris = array();
//$records = array();

$count = 1;

foreach($data as $row){
	//call generate_nuds twice to generate two sets of NUDS records
    generate_nuds($row, $count);
    $count++;
}

//functions

function generate_nuds($row, $count){		
	$recordId = trim($row['ID']);
	$typeNumber = ltrim(explode('.', $recordId)[1], "0");
	
	if (strlen($recordId) > 0){
		echo "Processing {$recordId}\n";
		
		$doc = new XMLWriter();
		
		//$doc->openUri('php://output');
		$doc->openUri('nuds/' . $recordId . '.xml');
		$doc->setIndent(true);
		//now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
		$doc->setIndentString("    ");
		
		$doc->startDocument('1.0','UTF-8');
		
		$doc->startElement('nuds');
			$doc->writeAttribute('xmlns', 'http://nomisma.org/nuds');
				$doc->writeAttribute('xmlns:xs', 'http://www.w3.org/2001/XMLSchema');
				$doc->writeAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
				$doc->writeAttribute('xmlns:tei', 'http://www.tei-c.org/ns/1.0');	
				$doc->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
				$doc->writeAttribute('xsi:schemaLocation', 'http://nomisma.org/nuds http://nomisma.org/nuds.xsd');
				$doc->writeAttribute('recordType', 'conceptual');
			
			//control
			$doc->startElement('control');
				$doc->writeElement('recordId', $recordId);
				
				//insert typeNumber just to capture the num.
				$doc->startElement('otherRecordId');
    				$doc->writeAttribute('localType', 'typeNumber');
    				$doc->text($typeNumber);
    			$doc->endElement();	
				
    			//insert a sortID
    			$doc->startElement('otherRecordId');
        			$doc->writeAttribute('localType', 'sortId');
        			$doc->text(number_pad(intval($count), 4));
    			$doc->endElement();
    			
    			$doc->writeElement('publicationStatus', 'approved');
				
				$doc->writeElement('maintenanceStatus', 'derived');
				$doc->startElement('maintenanceAgency');
					$doc->writeElement('agencyName', 'American Numismatic Society');
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
					$doc->writeElement('copyrightHolder', 'American Numismatic Society');
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
    			$doc->text("ABC {$typeNumber}");
			$doc->endElement();
			
			/***** NOTES *****/
			/*if (strlen(trim($row['Note'])) > 0){
				$doc->startElement('noteSet');
				if (strlen(trim($row['Note'])) > 0){
					$doc->startElement('note');
						$doc->writeAttribute('xml:lang', 'en');
						$doc->text(trim($row['Note']));
					$doc->endElement();
				}
				$doc->endElement();
			}*/
			
			/***** TYPEDESC *****/
			$doc->startElement('typeDesc');
			
				//objectType
				$doc->startElement('objectType');
					$doc->writeAttribute('xlink:type', 'simple');
					$doc->writeAttribute('xlink:href', 'http://nomisma.org/id/coin');
					$doc->text('Coin');
				$doc->endElement();
				
				//sort dates
				if (strlen($row['hasStartDate']) > 0 || strlen($row['hasEndDate']) > 0){
					if (($row['hasStartDate'] == $row['hasEndDate']) || (strlen($row['hasStartDate']) > 0 && strlen($row['hasEndDate']) == 0)){
						//ascertain whether or not the date is a range
						$fromDate = intval(trim($row['hasStartDate']));
						
						$doc->startElement('date');
							$doc->writeAttribute('standardDate', number_pad($fromDate, 4));							
							$doc->text(get_date_textual($fromDate));
						$doc->endElement();
					} else {
						$fromDate = intval(trim($row['hasStartDate']));
						$toDate= intval(trim($row['hasEndDate']));
						
						//only write date if both are integers
						if (is_int($fromDate) && is_int($toDate)){
							$doc->startElement('dateRange');
								$doc->startElement('fromDate');
									$doc->writeAttribute('standardDate', number_pad($fromDate, 4));
									$doc->text(get_date_textual($fromDate));
								$doc->endElement();
								$doc->startElement('toDate');
									$doc->writeAttribute('standardDate', number_pad($toDate, 4));
									$doc->text(get_date_textual($toDate));
								$doc->endElement();
							$doc->endElement();
						}
					}
				}
				
				if (strlen($row['Denomination1 URI']) > 0){
				    $uri =  trim($row['Denomination1 URI']);
				    $content = processUri($uri);
				    
				    $doc->startElement('denomination');
				    $doc->writeAttribute('xlink:type', 'simple');
				    $doc->writeAttribute('xlink:href', $uri);
				    if($row['Denomination Uncertain'] == "TRUE"){
				        $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
				    }
				    $doc->text($content['label']);
				    $doc->endElement();
				}
				if (strlen($row['Denomination2 URI']) > 0){
				    $uri =  trim($row['Denomination2 URI']);
				    $content = processUri($uri);
				    
				    $doc->startElement('denomination');
				    $doc->writeAttribute('xlink:type', 'simple');
				    $doc->writeAttribute('xlink:href', $uri);
				    if($row['Denomination Uncertain'] == "TRUE"){
				        $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
				    }
				    $doc->text($content['label']);
				    $doc->endElement();
				}
				
				if (strlen($row['Manufacture URI']) > 0){
				    $uri = trim($row['Manufacture URI']);
				    $content = processUri($uri);
				    
				    $doc->startElement('manufacture');
    				    $doc->writeAttribute('xlink:type', 'simple');
    				    $doc->writeAttribute('xlink:href', $uri);
    				    $doc->text($content['label']);
				    $doc->endElement();	
				}
				
				if (strlen($row['Material URI']) > 0){
				    $uri = trim($row['Material URI']);
				    $content = processUri($uri);
				    
				    $doc->startElement('material');
    				    $doc->writeAttribute('xlink:type', 'simple');
    				    $doc->writeAttribute('xlink:href', $uri);
    				    $doc->text($content['label']);
				    $doc->endElement();
				}
				
				//authority
				if (strlen($row['Org1 URI']) > 0 || strlen($row['Org2 URI']) > 0 || strlen($row['Ruler URI']) > 0){
					$doc->startElement('authority');
					if (strlen($row['Ruler URI']) > 0){
					    $uri = trim($row['Ruler URI']);
					    $content = processUri($uri);
					    
					    $doc->startElement($content['element']);
					    $doc->writeAttribute('xlink:type', 'simple');
					    $doc->writeAttribute('xlink:role', 'ruler');
					    $doc->writeAttribute('xlink:href', $uri);
					    if($row['Authority Uncertain'] == "TRUE"){
					        $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
					    }
					    $doc->text($content['label']);
					    $doc->endElement();
					}
					if (strlen($row['Org1 URI']) > 0){	
					    $uri = trim($row['Org1 URI']);
					    $content = processUri($uri);
					    
					    $doc->startElement($content['element']);
    					    $doc->writeAttribute('xlink:type', 'simple');
    					    $doc->writeAttribute('xlink:role', 'authority');
    					    $doc->writeAttribute('xlink:href', $uri);
    					    if($row['Authority Uncertain'] == "TRUE"){
    					        $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
    					    }
    					    $doc->text($content['label']);
					    $doc->endElement();
					}
					if (strlen($row['Org2 URI']) > 0){
					    $uri = trim($row['Org2 URI']);
					    $content = processUri($uri);
					    
					    $doc->startElement($content['element']);
					    $doc->writeAttribute('xlink:type', 'simple');
					    $doc->writeAttribute('xlink:role', 'authority');
					    $doc->writeAttribute('xlink:href', $uri);
					    if($row['Authority Uncertain'] == "TRUE"){
					        $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
					    }
					    $doc->text($content['label']);
					    $doc->endElement();
					}
					
						
					$doc->endElement();
				}
				
				//geography
				//mint
				if (strlen($row['Region URI']) > 0){
				    $uri = trim($row['Region URI']);
				    $content = processUri($uri);
				    
					$doc->startElement('geographic');
    					$doc->startElement('geogname');
        					$doc->writeAttribute('xlink:type', 'simple');
        					$doc->writeAttribute('xlink:role', 'region');
        					$doc->writeAttribute('xlink:href', $uri);
    					   $doc->text($content['label']);
    					$doc->endElement();					
					$doc->endElement();
				}
				
				if (strlen(trim($row['Obverse Legend'])) > 0 || strlen(trim($row['Obverse Type'])) > 0){
				    $doc->startElement('obverse');
				    //legend
				    if (strlen(trim($row['Obverse Legend'])) > 0){
				        $legend = trim($row['Obverse Legend']);				        
				        $doc->startElement('legend');
    				        $doc->writeAttribute('scriptCode', 'Latn');
    				        $doc->text($legend);
				        $doc->endElement();
				    }
				    
				    if (strlen(trim($row['Obverse Type'])) > 0) {
				        $doc->startElement('type');
    				        $doc->startElement('description');
        				        $doc->writeAttribute('xml:lang', 'en');
        				        $doc->text(trim($row['Obverse Type']));
    				        $doc->endElement();
				        $doc->endElement();
				    }
				    $doc->endElement();
				}
				
				if (strlen(trim($row['Reverse Legend'])) > 0 || strlen(trim($row['Reverse Type'])) > 0){
				    $doc->startElement('reverse');
				    //legend
				    if (strlen(trim($row['Reverse Legend'])) > 0){
				        $legend = trim($row['Reverse Legend']);
				        $doc->startElement('legend');
				            $doc->writeAttribute('scriptCode', 'Latn');
				            $doc->text($legend);
				        $doc->endElement();
				    }
				    
				    if (strlen(trim($row['Reverse Type'])) > 0) {
				        $doc->startElement('type');
    				        $doc->startElement('description');
        				        $doc->writeAttribute('xml:lang', 'en');
        				        $doc->text(trim($row['Reverse Type']));
    				        $doc->endElement();
				        $doc->endElement();
				    }
				    $doc->endElement();
				}
								
				//Type Series should be explicit
				$doc->startElement('typeSeries');
					$doc->writeAttribute('xlink:type', 'simple');
					$doc->writeAttribute('xlink:href', $row['TypeSeries']);
					$doc->text('Ancient British Coins');
				$doc->endElement();
				
				//end typeDesc
				$doc->endElement();
				
				/***** REFDESC *****/			
				/*if (strlen($row['Pella']) > 0){
				    $doc->startElement('refDesc');
    				//Price references
    				if (strlen($row['Pella']) > 0){
    				    $priceURIs = explode('|', $row['Pella']);
    				    
    				    foreach ($priceURIs as $uri){
    				        $doc->startElement('reference');
        				        $doc->writeAttribute('xlink:type', 'simple');
        				        $doc->writeAttribute('xlink:href', $uri);
        				        $doc->startElement('tei:title');
            				        $doc->writeAttribute('key', 'http://nomisma.org/id/price1991');
            				        $doc->text('Price (1991)');
        				        $doc->endElement();
        				        $doc->startElement('tei:idno');
        				            $doc->text(str_replace('http://numismatics.org/pella/id/price.', '', $uri));
        				        $doc->endElement();
    				        $doc->endElement();
    				    }
    				}
    				//end refDesc
    				$doc->endElement();
				}*/
			//end descMeta
			$doc->endElement();		
		//close NUDS
		$doc->endElement();
		
		//close file
		$doc->endDocument();
		$doc->flush();
	} else {
		echo "No number for {$row['ID']}.\n";
	}
}


 /***** FUNCTIONS *****/
function processUri($uri){
	GLOBAL $nomismaUris;
	$content = array();
	$uri = trim($uri);
	$type = '';
	$label = '';
	$node = '';
	
	//if the key exists, then formulate the XML response
	if (array_key_exists($uri, $nomismaUris)){
		$type = $nomismaUris[$uri]['type'];
		$label = $nomismaUris[$uri]['label'];
		if (isset($nomismaUris[$uri]['parent'])){
			$parent = $nomismaUris[$uri]['parent'];
		}
	} else {
		//if the key does not exist, look the URI up in Nomisma
		$pieces = explode('/', $uri);
		
		if (isset($pieces[4])){
		    $id = trim($pieces[4]);
		    if (strlen($id) > 0){
		        $uri = 'http://nomisma.org/id/' . $id;
		        $file_headers = @get_headers($uri);
		        
		        //only get RDF if the ID exists
		        if ($file_headers[0] == 'HTTP/1.1 200 OK'){
		            $xmlDoc = new DOMDocument();
		            $xmlDoc->load('http://nomisma.org/id/' . $id . '.rdf');
		            $xpath = new DOMXpath($xmlDoc);
		            $xpath->registerNamespace('skos', 'http://www.w3.org/2004/02/skos/core#');
		            $xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
		            $type = $xpath->query("/rdf:RDF/*")->item(0)->nodeName;
		            $label = $xpath->query("descendant::skos:prefLabel[@xml:lang='en']")->item(0)->nodeValue;
		            
		            if (!isset($label)){
		                echo "Error with {$id}\n";
		            }
		            
		            //get the parent, if applicable
		            $parents = $xpath->query("descendant::org:organization");
		            if ($parents->length > 0){
		                $nomismaUris[$uri] = array('label'=>$label,'type'=>$type, 'parent'=>$parents->item(0)->getAttribute('rdf:resource'));
		                $parent = $parents->item(0)->getAttribute('rdf:resource');
		            } else {
		                $nomismaUris[$uri] = array('label'=>$label,'type'=>$type);
		            }
		        } else {
		            //otherwise output the error
		            echo "Error: {$uri} not found.\n";
		            $nomismaUris[$uri] = array('label'=>$uri,'type'=>'nmo:Mint');
		        }
		    }
		} else {
		    echo "ERROR: {$uri}\n";
		}
		
		
	}
	switch($type){
		case 'nmo:Mint':
		case 'nmo:Region':
			$content['element'] = 'geogname';
			$content['label'] = $label;
			if (isset($parent)){
				$content['parent'] = $parent;
			}
			break;
		case 'nmo:Material':
			$content['element'] = 'material';
			$content['label'] = $label;
			break;
		case 'nmo:Denomination':
			$content['element'] = 'denomination';
			$content['label'] = $label;
			break;
		case 'nmo:Manufacture':
			$content['element'] = 'manufacture';
			$content['label'] = $label;
			break;
		case 'nmo:ObjectType':
			$content['element'] = 'objectType';
			$content['label'] = $label;
			break;
		case 'rdac:Family':
			$content['element'] = 'famname';
			$content['label'] = $label;
			break;
		case 'foaf:Organization':
		case 'foaf:Group':
		case 'nmo:Ethnic':
			$content['element'] = 'corpname';
			$content['label'] = $label;
			break;
		case 'foaf:Person':
			$content['element'] = 'persname';
			$content['label'] = $label;
			$content['role'] = 'portrait';
			if (isset($parent)){
				$content['parent'] = $parent;
			}
			break;
		case 'wordnet:Deity':
		    $content['element'] = 'persname';
		    $content['role'] = 'deity';
		    $content['label'] = $label;
		    break;
		case 'crm:E4_Period':
			$content['element'] = 'periodname';
			$content['label'] = $label;
			break;
		default:
			$content['element'] = 'ERR';
			$content['label'] = $label;
	}
	return $content;
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