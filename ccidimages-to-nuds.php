<?php 
 /*****
 * Author: Ethan Gruber
 * Last modified: February 2022
 * Function: Process CCID spreadsheet into NUDS
 *****/

//CCID spreadsheet with metadata
$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vSNbNjFpUz5DS1a2u8sa7g9e_dKDlkawMfDIqgtieAdxtbjMjgLeichvidEwrdl9oJsKehNCyBH49zK/pub?output=csv');
//full list of images per CCI number
$images = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vSpSU905tlJab-PL_McGYY6S4tP0veMPOEOLvdH22Jngtju_H9GYqR4mLT4-5mElJIiZKLaoblaG9RU/pub?output=csv');

//$records = array();

//avoid creating NUDS for ids from the other sheet
$matches = array();
foreach($images as $row){
    //avoid creating NUDS for ids from the other sheet
    $recordId = 'CCI-' . trim($row['id']);
    foreach ($data as $record){
        if ($record['cciNumber'] == $recordId){
            echo "Found {$recordId}\n";
            $matches[] = $recordId;
            break;
        }
    }
}

$count = 1;
foreach($images as $row){	
	$recordId = 'CCI-' . trim($row['id']);
	
    //if exists remains false after the check process, then generate the NUDS
    if (!in_array($recordId, $matches)){
        generate_nuds($row, $count);
        $count++;
    }
}

//functions

function generate_nuds($row, $count){	
    
    $iiif_base = 'https://cci.arch.ox.ac.uk/images/';
    
	$recordId = 'CCI-' . trim($row['id']);	
	
	if (strlen($recordId) > 0){
		echo "Processing {$recordId}\n";
			
		$doc = new XMLWriter();
		
		//$doc->openUri('php://output');
		$doc->openUri('cci-nuds2/' . $recordId . '.xml');
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
    			
    			/***** TYPEDESC *****/
    			$doc->startElement('typeDesc');
    			
    			$doc->startElement('objectType');
        			$doc->writeAttribute('xlink:type', 'simple');
        			$doc->writeAttribute('xlink:href', 'http://nomisma.org/id/coin');
        			$doc->text('Coin');
    			$doc->endElement();
    				
    			//end typeDesc
                $doc->endElement();
    		
			//end descMeta
			$doc->endElement();		
			
			/***** DIGREP *****/	
			$doc->startElement('digRep');
    			$doc->startElement('mets:fileSec');
    			//create fileGrps for each card, each which contains a fileGrp for individual files corresponding to the recto and verso of cards, if applicable
    			     
    			//parse the images spreadsheet to restructure into an object
    			$files = array();
    			foreach ($row as $k=>$v){
    			    if ($k != 'id'){
    			        if (strlen($v) > 0){
    			            $index = substr($k, 0, 1);
    			            $side = (substr($k, 1, 1) == 'r') ? 0 : 1;
    			            
    			            $files[$index][$side] = $v;
    			        }
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