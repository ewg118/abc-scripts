<?php 
 /*****
 * Author: Ethan Gruber
 * Last modified: December 2020
 * Function: Process ABC typology into NUDS
 *****/

$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vRQKYvbc-7zJFh5SZBP2U_9arOYIvxQ9K0g9tKZTEaUz6VoZen12ebNg5V6UyWrYNHmxdObL4f7rh2V/pub?output=csv');

//$records = array();

$count = 1;

foreach($data as $row){
	//call generate_nuds twice to generate two sets of NUDS records
    
    
    //var_dump($matches);
    generate_nuds($row, $count);
    $count++;
}

//functions

function generate_nuds($row, $count){		
	$recordId = trim($row['UID']);	
	$typeNumber = str_replace('ABC ', '', $row['ABC No.']);
	$num = $typeNumber * 1;
	
	if (strlen($recordId) > 0 && $num >= 120){
		echo "Processing {$recordId}\n";
		
		preg_match('/.*_(\d{2}\.\d{4})\.(jpg|JPG)$/', $row['Image file'], $matches);
		
		$doc = new XMLWriter();
		
		//$doc->openUri('php://output');
		$doc->openUri('abc-specimens/' . $recordId . '.xml');
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
				$doc->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
				$doc->writeAttribute('xsi:schemaLocation', 'http://nomisma.org/nuds http://nomisma.org/nuds.xsd');
				$doc->writeAttribute('recordType', 'physical');
			
			//control
			$doc->startElement('control');
				$doc->writeElement('recordId', $recordId);
    			
    			$doc->writeElement('publicationStatus', 'approved');
				
				$doc->writeElement('maintenanceStatus', 'derived');
				$doc->startElement('maintenanceAgency');
					$doc->writeElement('agencyName', 'Oxford University');
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
					$doc->writeElement('copyrightHolder', 'Oxford University');
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
    			
    			if (isset($matches[1])){
    			    $title = $row['ABC No.'] . " Example (CCI {$matches[1]})";
    			} else {
    			    $title = $row['ABC No.'] . " Example";
    			}
    			
    			$doc->text($title);
			$doc->endElement();
			
			
			/***** TYPEDESC *****/
			$doc->startElement('typeDesc');
			     $doc->writeAttribute('xlink:type', 'simple');
			     $doc->writeAttribute('xlink:href', "https://abc.arch.ox.ac.uk/id/abc.{$typeNumber}");
				
				//end typeDesc
			$doc->endElement();
			
			//end descMeta
			$doc->endElement();
			
			$image_url = str_replace(' ', '%20', $row['Image file']);
			
			//start digRep
            $doc->startElement('digRep');
                $doc->startElement('mets:fileSec');
                    $doc->startElement('mets:fileGrp');
                        $doc->writeAttribute('USE', 'combined');
                        $doc->startElement('mets:file');
                            $doc->writeAttribute('USE', 'reference');
                            $doc->writeAttribute('MIMETYPE', 'image/jpeg');
                            $doc->startElement('mets:FLocat');
                                $doc->writeAttribute('LOCTYPE', 'URL');
                                $doc->writeAttribute('xlink:href', "https://abc.arch.ox.ac.uk/abc-images/{$image_url}");
                            $doc->endElement();                            
                        $doc->endElement();
                    $doc->endElement();
                $doc->endElement();
            $doc->endElement();
			
		//close NUDS
		$doc->endElement();
		
		//close file
		$doc->endDocument();
		$doc->flush();
	} else {
		echo "No number for {$row['UID']}.\n";
	}
}

 /***** FUNCTIONS *****/
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