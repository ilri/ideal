<?php

/**
 * Creates an interface to be used in assigning the trays to a particular location
 *
 * @global array $uploadedFiles   Parameters of the uploaded file
 * @global string $data           Global variable where the generated string will be stored
 * @global array $config1         Configuration parameters for temp_dbase dbase
 * @global array $config          Configuration parameters for azizi dbase
 * @param string $addinfo         Additional info to be displayed at the top of the page
 * @return mixed   Returns nothing on errors, else it sets the generated page to the global variable $data
 */
function Assigning($addinfo=''){
global $uploadedFiles, $data, $config1, $config;
   //get the file name and make sure its there and is accesible
   $file=$uploadedFiles['location'].$_GET['file'];
   if(!file_exists($file)){
      UnassignedBoxesList('Error! The file specified does not exist. Please contact the system administrator.');
      return;
   }
   $res=Connect2DB($config1);
   if(is_string($res)){
      UnassignedBoxesList($res); return;
   }
   //Get the boxes which are already assigned
   $res=GetColumnValues('boxes', array('name','location'), "", MYSQL_ASSOC);
   if(is_string($res)){
      UnassignedBoxesList('There was an error while fetching data from the database. Please contact the system administrator.'); return;
   }
   $assigned=array();
   //get where each tray is stored
   foreach($res as $t) $assigned[$t['name']]=$t['location'];

   $res=Connect2DB($config);
   if(is_string($res)){
      UnassignedBoxesList($res); return;
   }
   //get all the locations
   $allLocations=GetColumnValues('boxes_local_def', array('id','facility','notes'), 'order by facility');
   if(is_string($allLocations)){
      UnassignedBoxesList('There was an error while fetching data from the database. Please contact the system administrator.'); return;
   }
   $allTrays=GetAllUniqueSamples($file);
   if(is_string($allTrays)){
      UnassignedBoxesList($allTrays); return;
   }
   //extract the location id and name
   $locIds=array(); $locNames=array(); $locations=array();
   foreach($allLocations as $t){
      $locIds[]=$t['id']; $locNames[]=$t['facility'];
      $locations[$t['id']]=$t['facility'];
   }
   //now create the assigned boxes interface
   $assContent='<table>'; $assContent1='';
   foreach($locations as $key=>$value){
      $i=0;
      foreach($assigned as $key1 => $value1){
         if($value1==$key){
            if(($i%4)==0) $assContent1.="<tr>";
            if(($i%4)==3) $assContent1.="<td>$key1</td></tr>";
            else $assContent1.="<td>$key1</td>";
            $i++;
         }
      }
      if($assContent1!=''){
         $assContent.="<tr><td colspan=='4'><hr /></td></tr><tr><td colspan=='4'>
            <b>Trays in $value</b></td></tr>$assContent1";
      }
      $assContent1='';
   }
   $assContent.="</table>";

   //LogError(print_r($allLocations, true));
   $combo=Populate_Combo($locNames, $locIds, 'Select Location', 'locations', 0, true, null);
   $combo="<div id='assignCombo' style='height: auto;'>$combo</div>";
   $i=0; $curSample=''; $contents='';
   sort($allTrays);
   //The select all and none links uses the cursample id which has the sample id of the prev collection of trays to select
   foreach($allTrays as $t){
      if(array_key_exists($t, $assigned)) continue;
      $sample=substr($t, 0, 4);
      if($i==0) $curSample=$sample;
      if(($i%4)==0) $contents.="<tr>";
      $tray="<input type='checkbox' name='$sample' value='$t' />$t";
      $sublinks="<tr><td colspan=4 style='text-align:center; font-size;1.2em;'>Select:
            <a href='javascript:;' onClick='SamplesUpload.selectClearAllCheckBoxes(\"$curSample\",true)'>All</a>
            <a href='javascript:;' onClick='SamplesUpload.selectClearAllCheckBoxes(\"$curSample\",false);'>None</a></td></tr>";
      //do the grouping
      if($sample===$curSample){
         if(($i%4)==3) $contents.="<td>$tray</td></tr>\n";
         else $contents.="<td>$tray</td>\n";
      }
      else{
         $contents.="</tr>$sublinks\n<tr><td colspan=4><hr /></td></tr><tr><td>$tray</td>";   //close the row and start a new ros
         $curSample=$sample;
         $i=0;
      }
      $i++;
   }
   if(($i%4)==3) $contents.="</tr>$sublinks";
   else $contents.="$sublinks";

   $links="<div id='sublinks' style='height: auto;'><a href='javascript:;' onClick='SamplesUpload.saveChanges();'>Save Changes</a></div>";
$data=<<<DATA
   <div id='assigning'>
      <div id='assigned' style='max-height:560px; height:560px;'>
         <span style='margin: 5px 0px;'>Assigned Trays</span>
         <div id='in_assigned'>
            $assContent
         </div>
      </div>
      <div style='max-height:580px; height:580px; width: 58%;'>
         Trays Pending Assignment
         $combo
         <div id='to_assign'>
            <table border=0>
               $contents
            </table>
         </div>
         $links
      </div>
   </div>
DATA;
}
//=============================================================================================================================================

/**
 * Generates an interface with all the unassigned/new boxes
 *
 * @global <type> $data
 * @global <type> $pageref
 * @global <type> $uploadedFiles The location where the samples file is loaded to
 * @param string $addinfo        Additional information to be displayed
 */
function UnassignedBoxesList($addinfo=''){
global $data, $pageref, $uploadedFiles;
if(isset($addinfo) && $addinfo!='') $addinfo="<div id='addinfo'>$addinfo</div>";

$files=GetBatches($uploadedFiles['location']);
$contents='';
for($i=0, $j=1; $i<count($files); $i++, $j++){
   $file=$files[$i];
   $actions="<a href='$pageref?page=assign&file=".$file['name']."' >assign</a>";
   $contents.="<tr><td>$j</td><td>".$file['name']."</td><td>".$file['size']."</td><td>".$file['time']."</td><td>$actions</td></tr>";
}

$data=<<<DATA
   <div id='unass_boxes'>
      $addinfo
      <table>
         <tr><th>#</th><th>File Name</th><th>Size</th><th>Date of upload</th><th>actions</th></tr>
         $contents
      </table>
   </div>
DATA;
}
//=============================================================================================================================================

/**
 * Given a location, it gets all files and returns an array with data to this file
 *
 * @param <string> $location  The location to search for files
 * @return <array>   Returns the information about the files
 */
function GetBatches($location){
   $contents=scandir($location);
   $files=array();
   //after getting all the data, skip the folders and get the file data
   for($i=0; $i<count($contents); $i++){
      if($contents[$i]=='.' || $contents[$i]=='..') continue;
      if(!preg_match('/SamplesFile/i', $contents[$i])) continue;
      $path=$location.$contents[$i];
      $t=array('name'=>$contents[$i], 'size'=>filesize($path), 'time'=>date('d/m/Y',filemtime($path)));
      $files[]=$t;
   }
   return $files;
}
//=============================================================================================================================================

/**
 * Given a samples file, it extracts all the unique trays
 *
 * @param <string> $filePath  The path to the file to use
 * @return <mixed>  Return an array with the tray numbers on success, else a string with the error that occured on failure
 */
function GetAllUniqueSamples($filePath){
   $fd=fopen($filePath,'rt');
   if(!$fd) return 'Error! Cannot open the samples file. Contact the sytem administrator.';
   rewind($fd);

   $i=-1; $allTrays=array();
   while(!feof($fd)){
      $i++;
      $string=fgets($fd);
      if($string=='') continue;
      if($string===FALSE){
         fclose($fd);
         var_dump($string);
         LogError($i.$string.'Error! There was an error while reading the samples file '.$filePath);
         return 'Error! There was an error while reading the samples file. Contact the system administrator.';
      }
      //LogError("$string");
      //get the tray number
      $trayNo=explode("\t",$string);   //split e string wit delimiters
      $trayNo=$trayNo[0];  //get the first item as this is whea the tray number is stored
      if($trayNo=='TrayNumber') continue;
      //LogError($trayNo);
      $allTrays[]=$trayNo;
   }
   fclose($fd);
   return array_unique($allTrays);
}
//=============================================================================================================================================

/**
 * Generates an interface for assigning the trays to specific racks
 *
 * @global HTML $data        Place holder for storing the generated code
 * @global array $config       configuration data for logging to azizi db
 * @global array $config1      configuration data for logging to temp db
 * @global string $footerLinks place holder for appending footer links
 * @return mixed Returns nothing, but sets the HTML code in the global variable data
 */
function UnassignedTraysList(){
global $data, $query, $config, $config1, $footerLinks;
   $footerLinks.="<a href='javascript:;' onClick='SamplesUpload.saveAssignedTrays();'>Save Changes</a>";
   //get all the freezers
   $res=Connect2DB($config);
   if(is_string($res)){
      UnassignedBoxesList($res); return;
   }
   //get all the locations
   $allLocations=GetColumnValues('boxes_local_def', array('id','facility','notes'), 'order by facility');
   if(is_string($allLocations)){
      UnassignedBoxesList('There was an error while fetching data from the database. Please contact the system administrator.'); return;
   }
   //extract the location id and name
   $locIds=array(); $locNames=array(); $locations=array();
   foreach($allLocations as $t){
      $locIds[]=$t['id']; $locNames[]=$t['facility'];
      $locations[$t['id']]=$t['facility'];
   }
   
   //manual db connection
   $res=Connect2DB($config1);
   if(is_string($res)){
      Home($res); return;
   }
   //get all the freezers with unassigned trays
   $query="select distinct location from boxes where rack is null and rack_position is null";
   $res=GetQueryValues($query, MYSQL_ASSOC);
   if(is_string($res)){
      Home($res); return;
   }
//   LogError(print_r($locations, true));
   //get all the types in each freezer
   $i=0;
   $contents="<table border=0>";
   foreach($res as $t){
      //get the unique sample types from this location
      $query="select substring(name, 1, 4) as name from boxes where rack is null and location=".$t['location'].' group by substring(name, 1, 4)';
      $res2=GetQueryValues($query, MYSQL_ASSOC);
      if(is_string($res2)){
         Home($res2); return;
      }
//      LogError($query);
//      LogError(print_r($res2, true));
      
      $query="select * from boxes where rack is null and location=".$t['location'].' order by name';
      $res1=GetQueryValues($query, MYSQL_ASSOC);
//      LogError(print_r($res1, true));
      if(is_string($res1)){
         Home($res1); return;
      }
      $freezerName=$locations[$t['location']];   //freezer name
      if($contents!="<table border=0>") $contents.="<tr><td colspan=7><hr /></td></tr>";
      $color=($i%2==0)?'dark_brown':'light_brown';
      $contents.="<tr class='$color'><td colspan=7><b>Trays in $freezerName</b></td></tr>";
      foreach($res2 as $types){
         $prefix=substr($types['name'], 0, 4);
         //show the trays in qst
         $thisBoxes = array();
         $thisBoxes=GetColumnValues('boxes', array('name'), "where name like '$prefix%' and rack is null", MYSQL_ASSOC);
         if(is_string($thisBoxes)){Home($thisBoxes); return;}
//         LogError($query);
//         LogError(print_r($thisBoxes, true));
         $contents.="<tr style='font-size:8.5pt;'><td colspan=7 style='border-top:1px dotted black;'><i>";
         foreach($thisBoxes as $t3) $contents.=' '.$t3['name'];
         $contents.="</i></td></tr>";
         $contents.="<tr id='rw_{$prefix}' colspan=5><td>&nbsp;</td><td>$prefix Racks&nbsp;<input type='text'
            id='$prefix' name='racks' value='' onBlur='SamplesUpload.generateTrays(this, \"$prefix\");' /></td><td>&nbsp;</td></tr><tr id='trays_{$prefix}Id' class='collapsed'><td>&nbsp;</td></tr>";
      }
      $i++;
   }
   $contents.="</table>";

   //now get all the assigned trays
   $query="select distinct location from boxes where rack is not null";
   $res=GetQueryValues($query, MYSQL_ASSOC);
   if(is_string($res)){
      Home($res); return;
   }
   $contents1="<table border=0>";
   foreach($res as $t){
      $query="select * from boxes where rack is not null and location=".$t['location'];
      $res1=GetQueryValues($query, MYSQL_ASSOC);
      //LogError('Debugging:');
      if(is_string($res1)){
         Home($res1); return;
      }
      $freezerName=$locations[$t['location']];   //freezer name
      if(count($res1)==0) continue;
      if($contents1!="<table border=0 cellspacing=0>") $contents1.="<tr><td colspan=7><hr /></td></tr>";
      $contents1.="<tr><td colspan=7><b>Trays in $freezerName</b></td></tr>";
      $oldPrefix='';
      foreach($res1 as $types){
         $prefix=substr($types['name'], 0, 4);
         if($oldPrefix=='' || $oldPrefix!=$prefix){
            if($oldPrefix!='') $contents1.="</td></tr>";
            $contents1.="<tr><td style='border-top:1px dotted black;'>"; $oldPrefix=$prefix;
         }
         $contents1.=$types['name']." ";
      }
      $contents1.="</td></tr>";
   }
   $contents1.="</table>";

$data=<<<DATA
   <div id='assigningTrays'>
      <span style='margin-left:19%;'>Unassigned Trays</span> <span style='margin-left:33%;'>Assigned Trays</span>
      <div id='unassigned_trays'>
         $contents
      </div>
      <div id='assigned_trays'>
         $contents1
      </div>
   </div>
DATA;
}
//=============================================================================================================================================

/**
 * Creates an error page. The generated code is usually a full HTML page and doesnt neet to be displayed via the index.php as most other generated code must be
 *
 * @param <string> $addinfo  Any additional information that might be displayed on the home page
 */
function ErrorPage($addinfo=''){
global $footerLinks;

$content=<<<CONTENT
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Update Lab Collector</title>
        <link rel='stylesheet' type='text/css' href='basic.css'>
        <link rel='stylesheet' type='text/css' href='../../common/common.css'>
        <link rel='stylesheet' type='text/css' href='../../common/mssg_box.css'>
    </head>
    <body>
        <table id='maintable'><tr><td>
            <div id="header"><img src="images/ilri.jpg" align="left" alt="Lab Collector Updates"/></div>
            <div id='contents'><div id='error_page'>$addinfo</div></div>
            <div id='footer_links'>$footerLinks</div>
            <div id='footer'>&copy; ILRI - LIMS Updater</div>
        </td></tr></table>
        <script type='text/javascript' src='custom_modules.js'></script>
        <script type='text/javascript' src='../../common/jquery.js'></script>
        <script type='text/javascript' src='../../common/jquery.json.js'></script>
        <script type='text/javascript' src='../../common/common.js'></script>
    </body>
</html>
CONTENT;
   return $content;
}
//=========================================================================================================================================================
?>
