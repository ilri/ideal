<?php
require_once 'uploading_samples_config';
require_once '../../common/dbase_functions.php';
require_once '../../common/general.php';
require_once 'uploading_samples_functions.php';
session_save_path($config['dbase']);
session_name('sessions');
StartingSession();

//ensure that this user has logged in and is allowed to use the system
if(isset($_SESSION['username']) && isset($_SESSION['psswd'])){
   $res=ConfirmUser($_SESSION['username'], $_SESSION['psswd']);
   if($res==-1) die('-1There was an error while accessing the system resources.'.$contact);
}
else{
   $footerLinks='';
   die("-1Error! The system does not recognise you. Please log in through to access AVID system resources.
         <br />Please contact the system administrator if you have any problems.");
}


$pageref = $_SERVER['PHP_SELF'];
$queryString=$_SERVER['QUERY_STRING'];
if(isset($_REQUEST['page']) && $_REQUEST['page']!=''){
	$paging=$_REQUEST['page'];
	//if(Checks($paging, 7)) exit(ErrorPage("There is an Error in your data."));
}
else $paging='';
if(isset($_POST['flag']) && $_POST['flag']!=''){
	$action=$_POST['flag'];
	//if(Checks($action, 13)) exit(ErrorPage("There is an Error in your data."));
}
else $action='';

if($action=='saveRacks') SaveRacks();
elseif($action=='saveTrayAllocation') SaveTrayAllocation();

   $trayDescriptions=array(
       'TBCI' => 'Bacteriology isolates from miscellaneous clinical samples',
       'TBFI' => "Bacteriology fecal isolates",
       'TBGX' => 'Serum decanted from Heparinised blood collected at the final visit',
       'TBKS' => 'Thick blood smears from jugular blood',
       'TBMI' => 'Rack TBMI1, bacterial isolates from mastitic milk',
       'TBNS' => 'Thin blood smears from jugular blood',
       'TCHS' => 'Samples that are tissues for histopathology in formaldehyde',
       'TCSS' => 'Miscellaneous stained/fixed Impression smears',
       'TCTF' => 'Tissue flaps for virology',
       'TCYT' => 'Plasma for cytokines',
       'THSV' => 'Daughter samples from Heparinised blood collected in all clinical episodes',
       'TMBE' => 'Blood in magic buffer for genetics',
       'TMKS' => 'Thick blood smears from marginal ear vein blood',
       'TMLK' => 'Milk samples',
       'TMNS' => 'Thin blood smears from marginal ear vein blood',
       'TNMB' => 'New methylene blue slides',
       'TRED' => 'EDTA blood collected during routine visits',
       'TRLE' => 'Plasma in RNALater buffer collected during a final visit',
       'TSER' => 'Serum samples from routine visits'
   );

/**
 * From the users definitions it assigns trays into storage locations
 *
 * @global <string> $data    Place holder for storing the generated code
 * @global <array> $config   configuration data for logging to azizi db
 * @global <array> $config1  configuration data for logging to temp db
 */
function SaveRacks(){
global $config, $config1, $data, $uploadedFiles, $trayDescriptions;
   $loc=$_POST['location'];
   if(!is_numeric($loc) || $loc==0){
      die('-1There is an error in your data. Check the location');
   }
   $fileName=$_GET['file'];
   if(!file_exists($uploadedFiles['location'].$fileName)) die('The file specified does not exist. Please contact the system administrator.');

   $traySizes=Tray_Sizes($fileName);
   $racks=stripslashes($_POST['racks']);
   $racks=explode(',',$racks);
   //LogError($racks);
   $res=Connect2DB($config1);
   if(is_string($res)){
      die('-1Error while connecting to the database.');
   }
   StartTrans();
   foreach($racks as $t){
      $cols=array('name', 'location','type', 'features','size');
      $prefix=substr($t, 0, 4);
      $colvals=array(mysql_real_escape_string($t), $loc, 'box', mysql_real_escape_string($trayDescriptions[strtoupper($prefix)]), $traySizes[strtolower($t)]);
      $res=InsertValues('boxes', $cols, $colvals);
      //LogError('Debugging');
      if(is_string($res)){
         RollBackTrans();
         LogError('Error while saving the data to the database');
         die('-1Error while saving data to the database.');
      }
   }
   Assigning('The data was successfully saved.');
   CommitTrans(); die($data);
   //RollBackTrans(); die('-1Data successfully saved but we are doing a test run.');
}
//=============================================================================================================================================

/**
 * Given the file name currently being worked on, it extracts the tray sizes
 *
 * @param <string> $file_name The file with the tray definitions. This file is expected to be located in the uploaded folder with the prefix Trays_
 * @return <type> Returns an array with the trays data on success, else the script ends with an error message
 */
function Tray_Sizes($file_name){
global $uploadedFiles;
   $fd=fopen($uploadedFiles['location']."TrayFile_0.txt",'rt');
   if(!$fd){
      LogError($i.$string.'Error! There was an error while reading the tray definition file '.$file_name);
      die('-1There was an error while reading the tray definition information.');
   }
   //LogError('reading the xls file.'.file_get_contents("misc/$file_name"));
   rewind($fd);

   $i=-1; $allTrays=array();
   while(!feof($fd)){
      $i++;
      $string=fgets($fd);
      if($string=='') continue;
//echo "$string <br />";
      //LogError($string);
      if($string===FALSE){
         fclose($fd);
         LogError($i.$string.'Error! There was an error while reading the tray definition file '.$file_name);
         die('-1Error! There was an error while reading the tray definition file. Contact the system administrator.');
      }
      //get the tray number
      $trayNo=explode("\t",$string);   //split e string wit delimiters
      $tray=strtolower($trayNo[0]);  //get the first item as this is whea the tray number is stored
      $traySize=$trayNo[1];
      if($tray=='traynumber') continue;
      //LogError("{$i}--{$string}");
      if(isset($traySize) && trim($traySize)!=''){
//print_r($trayNo);
         $s=explode('x',$traySize);
         //LogError(print_r($s, true));
         //LogError(var_dump($trayNo[2],true));
         if(count($s)!=2) die("-1Error! Wrong definition of tray size for $tray $traySize");
         $size='A:1.'.chr(64+trim($s[0])).':'.trim($s[1]);     //create the box size in form of A:1.J:10
      }
      else $size="A:0.A:0";
      //LogError($trayNo);
      $allTrays[strtolower($tray)]=$size;   //the tray name is the key of the array whereas the value is the description
   }
   fclose($fd);
   return $allTrays;
}
//===========================================================================================================================

/**
 * Given a file name it gets the description of all the trays from a description file located in misc/
 * 
 * @param <string> $file_name The file containing the tray description
 * @return <mixed> It returns all the trays, their number and description on success and dies on error!!
 * @deprecated
 */
function DeprecatedTrayDescription($file_name){
global $uploadedFiles;
   $fd=fopen($uploadedFiles['location']."$file_name",'rt');
   if(!$fd){
      LogError($i.$string.'Error! There was an error while reading the tray definition file '.$uploadedFiles['location'].$file_name);
      die('-1There was an error while reading the tray type information.');
   }
   //LogError('reading the xls file.'.file_get_contents("misc/$file_name"));
   rewind($fd);

   $i=-1; $allTrays=array();
   while(!feof($fd)){
      $i++;
      $string=fgets($fd);
      if($string=='') continue;
      //LogError($string);
      if($string===FALSE){
         fclose($fd);
         LogError($i.$string.'Error! There was an error while reading the tray definition file '.$file_name);
         die('-1Error! There was an error while reading the tray definition file. Contact the system administrator.');
      }
      //LogError("{$i}--{$string}");
      //get the tray number
      $trayNo=explode("\t",$string);   //split e string wit delimiters
      $tray=strtolower($trayNo[0]);  //get the first item as this is whea the sample type is stored
      $descr=$trayNo[1];   //the tray description
      if($descr=='Description') continue;
      $allTrays[$tray]=substr($descr, 0, -1);   //the tray name is the key of the array whereas the value is the description
   }
   fclose($fd);
   return $allTrays;
   $allTrays=array(
       'TBCI' => 'Bacteriology isolates from miscellaneous clinical samples',
       'TBFI' => "Bacteriology fecal isolates",
       'TBGX' => 'Serum decanted from Heparinised blood collected at the final visit',
       'TBKS' => 'Thick blood smears from jugular blood',
       'TBMI' => 'Rack TBMI1, bacterial isolates from mastitic milk',
       'TBNS' => 'Thin blood smears from jugular blood',
       'TCHS' => 'Samples that are tissues for histopathology in formaldehyde',
       'TCSS' => 'Miscellaneous stained/fixed Impression smears',
       'TCTF' => 'Tissue flaps for virology',
       'TCYT' => 'Plasma for cytokines',
       'THSV' => 'Daughter samples from Heparinised blood collected in all clinical episodes',
       'TMBE' => 'Blood in magic buffer for genetics',
       'TMKS' => 'Thick blood smears from marginal ear vein blood',
       'TMLK' => 'Milk samples',
       'TMNS' => 'Thin blood smears from marginal ear vein blood',
       'TNMB' => 'New methylene blue slides',
       'TRED' => 'EDTA blood collected during routine visits',
       'TRLE' => 'Plasma in RNALater buffer collected during a final visit',
       'TSER' => 'Serum samples from routine visits'
   );
   return $allTrays;
}
//===========================================================================================================================

function SaveTrayAllocation(){
global $data, $config, $config1;
   $dat=stripslashes($_POST['data']);
   $dat=json_decode($dat, true);
   if($dat==NULL) die('-1Error! There was an error in the data saved.');

   //LogError(print_r($dat, true));
   $res=Connect2DB($config1);
   if(is_string($res)){
      die('-1Error while connecting to the database.');
   }
   StartTrans();
   foreach($dat as $d){
      //we have the rack information and the tray information
      $rack=$d['rack'];
      //LogError(print_r($d, true));
      //we have the trays, so recreate the tray numbers and use em to update the dbase
      $trays=explode(',',$d['trays']);
      foreach($trays as $t){
         //ensure that we have a rack of 4 letters and a numeric rack  number or floating
         if(!is_numeric($t)){ RollBackTrans(); die('-1There was an error in the tray numbers provided.'); }
         if(Checks(substr($rack,0,4),'','^[tT][a-zA-Z]{3}$')){RollBackTrans(); die('-1There was an error in the rack number provided.');}
         //LogError(substr($rack,4));
         if(strtolower(substr($rack,4))=='floating'){   //we have a floating rack
            $colvals=array('Floating');
         }
         else $colvals=array($rack);
         $tray=substr($rack,0,4).implode('',array_fill(0,6-strlen($t),'0')).$t;
         $res=UpdateTable('boxes', array('rack'), $colvals, 'name', $tray);
         //LogError();
         if(is_string($res)){RollBackTrans(); die('-1There was an error while saving the trays');}
      }
   }
   UnassignedTraysList();
   //RollBackTrans(); die($data); //die('-1Testing');
   CommitTrans(); die($data);
}
//=============================================================================================================================================
?>
