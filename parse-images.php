<?php 

/*****
 * Author: Ethan Gruber
 * Date: February 2022
 * Function: To read the directories of CCI images and create an array and CSV of concordances
 * between CCI numbers and all associated images
 */

const BASEPATH = '/f/CCI-converted/';

$handle = opendir(BASEPATH);
$dirs = array();
$images = array();
$rows = array();


if ($handle) {
    while (($entry = readdir($handle)) !== FALSE) {
        if ($entry != '.' && $entry != '..'){
            $dirs[] = $entry;
        }
    }
}

sort($dirs);

foreach ($dirs as $dir){
    $handle = opendir(BASEPATH . $dir);
    while (($file = readdir($handle)) !== FALSE) {
        if ($file != '.' && $file != '..'){
            $id = substr($file, 0, 7);
            
            //parse the filename to place it
            if (strpos($file, '_') !== FALSE){
                //detect verso
                if (strpos($file, 'b') !== FALSE) {
                    preg_match("/.*_(\d)/", $file, $matches);
                    $key = $matches[1] . 'v';
                } else {
                    preg_match("/.*_(\d)/", $file, $matches);
                    $key = $matches[1] . 'r';
                }
            } else {
                //detect verso
                if (strpos($file, 'b') !== FALSE) {
                   $key = '1v';
                } else {
                    $key = '1r';
                }
            }
            
            $images[$id][$key] = $file;
        }
    }
}

//perform sorting
ksort($images);

foreach ($images as $id=>$array){
    ksort($array);
    
    $row = array();
    $row['id'] = $id;
    
    if (array_key_exists('1r', $array)){
        $row['1r'] = $array['1r'];
    } else {
        $row['1r'] = '';
    }    
    if (array_key_exists('1v', $array)){
        $row['1v'] = $array['1v'];
    } else {
        $row['1v'] = '';
    }
    
    if (array_key_exists('2r', $array)){
        $row['2r'] = $array['2r'];
    } else {
        $row['2r'] = '';
    }
    if (array_key_exists('2v', $array)){
        $row['2v'] = $array['2v'];
    } else {
        $row['2v'] = '';
    }
    
    if (array_key_exists('3r', $array)){
        $row['3r'] = $array['3r'];
    } else {
        $row['3r'] = '';
    }
    if (array_key_exists('3v', $array)){
        $row['3v'] = $array['3v'];
    } else {
        $row['3v'] = '';
    }
    
    if (array_key_exists('4r', $array)){
        $row['4r'] = $array['4r'];
    } else {
        $row['4r'] = '';
    }
    if (array_key_exists('4v', $array)){
        $row['4v'] = $array['4v'];
    } else {
        $row['4v'] = '';
    }
    
    if (array_key_exists('5r', $array)){
        $row['5r'] = $array['5r'];
    } else {
        $row['5r'] = '';
    }
    if (array_key_exists('5v', $array)){
        $row['5v'] = $array['5v'];
    } else {
        $row['5v'] = '';
    }
    if (array_key_exists('6r', $array)){
        $row['6r'] = $array['6r'];
    } else {
        $row['6r'] = '';
    }
    if (array_key_exists('6v', $array)){
        $row['6v'] = $array['6v'];
    } else {
        $row['6v'] = '';
    }
    
    if (array_key_exists('7r', $array)){
        $row['7r'] = $array['7r'];
    } else {
        $row['7r'] = '';
    }
    if (array_key_exists('7v', $array)){
        $row['7v'] = $array['7v'];
    } else {
        $row['7v'] = '';
    }
    
    $rows[] = $row;
    unset($row);
}

$header = array('id', '1r', '1v','2r','2v','3r','3v','4r','4v','5r','5v','6r', '6v', '7r', '7v');

$fp = fopen('cci-images.csv', 'w');
fputcsv($fp, $header);
foreach ($rows as $row) {
    fputcsv($fp, $row);
}

fclose($fp);

//echo $maxCount;

//var_dump($images);

?>