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
   if($res==-1) die(ErrorPage());
}
else{
   $footerLinks='';
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
$data='';
$footerLinks="<a href='$pageref'>Home</a>";

if($paging=='') Home();
elseif($paging=='upload') Uploads();
elseif($paging=='save_file') SaveFiles();
elseif($paging=='unassign_boxes') UnassignedBoxesList();
elseif($paging=='unassign_trays') UnassignedTraysList();
elseif($paging=='assign') Assigning();
elseif($paging=='process_samples') ProcessSamples();

//=============================================================================================================================================
function Uploads($addinfo=''){
global $data, $pageref;
if(isset($addinfo) && $addinfo!='') $addinfo="<div id='addinfo'>$addinfo</div>";

$data=<<<DATA
   <div id='page_header'>Select files to upload</div>
   $addinfo
   <form enctype="multipart/form-data" name="upload" action="$pageref?page=save_file" method="POST">
      <input type="hidden" value="10240000" name="MAX_FILE_SIZE"/>
      <div id='uploads'>
         Tray's File: <input type="file" name="trays_batch[]" value="" width="50"/><br />
         Sample's File: <input type="file" name="samples_batch[]" value="" width="50"/>
      </div>
      <div id='links'>
         <input type="submit" value="Upload" name="upload" /><input type="reset" value="Cancel" name="cancel" />
      </div>
   </form>
DATA;
}
//=============================================================================================================================================

function Home($addinfo=''){
global $data, $pageref, $footerLinks;
if(isset($addinfo) && $addinfo!='') $addinfo="<div id='addinfo'>$addinfo</div>";
else $addinfo="<div id='addinfo'>Batch uploading of IDEAL samples from Busia</div>";
$footerLinks="<a href='/ideal/index.php?page=home'>Home</a>";

$data=<<<DATA
   <div id='home'>
      $addinfo
      <ol>
         <li><a href='$pageref?page=upload'>Upload file with samples.</a></li>
         <li><a href='$pageref?page=unassign_boxes'>Assign trays to locations</a></li>
         <li><a href='$pageref?page=unassign_trays'>Assign trays to racks</a></li>
         <li><a href='$pageref?page=process_samples'>Process uploaded samples</a></li>
      </ol>
   </div>
DATA;
}
//=============================================================================================================================================

function ProcessSamples(){
global $data, $query, $config, $config1, $dbcon, $uploadedFiles, $duplicateTrays, $duplicateEmptyTrays, $insertsFile, $updatesFile, $resultsFolder;
global $cgiPath;

   $res=Connect2DB($config1);
   if(is_string($res)) {
      Home("There was an error while connecting to the database. Please contact the system administrator."); return;
   }
   //check that all the samples in the database have been allocated and we are ready to call Harry's script
   $query="select * from boxes";
   $res=GetQueryValues($query, MYSQL_ASSOC);
   if(is_string($res)) {
      Home("There was an error while fetching data from the database. Please contact the system administrator."); return;
   }
   foreach($res as $t) {
      if($t['location']==NULL || $t['rack']==NULL){
         Home("There are some trays which have not been allocated their respective positions. Please allocate all trays first before finalizing the process.");
         return;
      }
      elseif($t['size']=='' || $t['size']==NULL || Checks($t['size'], '', '^[a-zA-Z]:[0-9]{1,2}\.[a-zA-Z]:[0-9]{1,2}$')){ //check the format of the size
         Home("The box ".$t['name']." have  an invalid size(".$t['size']."). Please check the box size or contact the system administrator.");
         return;
      }
   }
   //all is ok, so transfer the data from the misc dbase to the permanent dbase
//   rewind($res);
//   $query="select * from boxes";
//   $res=GetQueryValues($query, MYSQL_ASSOC);
//   if(is_string($res)){
//      Home("There was an error while fetching data from the database. Please contact the system administrator."); return;
//   }

   //now pasting the data
   $res1=Connect2DB($config);
   if(is_string($res1)){
      Home("There was an error while connecting to the database. Please contact the system administrator."); return;
   }
   StartTrans();
   //for the keeper get the value from the login details of the person currently logged in
   $cols=array('box_name','box_features','size','box_type','location','rack','rack_position','keeper');
   foreach($res as $t) {
      if($t['rack']=='Floating') {
         $t['rack']=''; $t['rack_position']='';
      }
$tray_descr=$t['rack']==''?'Floating, '.$t['features']:$t['rack'].', '.$t['features'];
      $colvals=array($t['name'],$tray_descr,$t['size'],$t['type'],$t['location'],$t['rack'],$t['rack_position'],2);
      //check if the box is already uploaded
      $boxId=GetSingleRowValue('boxes_def', 'box_id', 'box_name', $t['name']);
      if($boxId==-2) {
         Home("There was an error while fetching data from the database. Please contact the system administrator.");
         RollBackTrans();
         return;
      }
      else if(is_numeric($boxId)) {
         //check if it has samples associated to it
            $samples=GetColumnValues('samples', array('count'), "where box_id=$boxId");
            if(is_string($samples)) {   //error while fetching data
               Home("There was an error while fetching data from the database. Please contact the system administrator.");
               RollBackTrans(); return;
            }
            elseif(count($samples)>0) { //there are samples associated with this tray
               LogError(implode(", ", $colvals), $duplicateTrays); continue;
            }
            elseif(count($samples)==0) {   //the tray is there but it is empty
               LogError(implode(", ", $colvals), $duplicateEmptyTrays);
               //update it
               $updates=UpdateTable('boxes_def', $cols, $colvals, 'box_id', $boxId);
               LogError('',$updatesFile);
               if(is_string($updates)) {
                  Home("There was an error while fetching data from the database. Please contact the system administrator.");
                  RollBackTrans(); return;
               }
            }
         }
         else { //the box aint there, so add it
            $insert=InsertValues('boxes_def', $cols, $colvals);
            LogError('',$insertsFile);
            if(is_string($insert)) {
               Home("There was an error while saving data to the database. Please contact the system administrator.");
               RollBackTrans(); return;
            }
         }
   }

   CommitTrans(); //close this trans and in case there is an error deleting the data from old db wika
   
   /**
    * zip the files used to a temp folder and then combine all the results file and zip them to a backup folder for archival purposes
    */

   //so get rid of all the files, ie we rename the samples file to IdealAllSamples.txt and delete the trays file
   $files=scandir($uploadedFiles['location']);
   //if(count($files)!=4){ Home("There was an error while processing the samples file. Please contact the system administrator."); return; }
   foreach($files as $t){
      if($t=='.' || $t=='..') continue;
      if(preg_match('/SamplesFile/i', $t)) { //rename this
         $success=rename($uploadedFiles['location'].$t, $uploadedFiles['location'].'IdealAllSamples.txt');
         if(!$success) {
            Home("There was an error while renaming the processed file. Please contact the system administrator."); return;
         }
      }
   }

   $message=array();
   exec('/var/www/ideal/uploading_samples/addSamples.V2.pl', $message, $result);
   if($result!=0) {
      Home("There was an error while uploading the samples file.".implode("; ",$message).print_r($message, true)." Please contact the system administrator."); return;
   }
$addmessage='';
   foreach($files as $t){
      if($t=='.' || $t=='..') continue;
      if(preg_match('/TrayFile/i', $t)){   //delete this
         $success=unlink($uploadedFiles['location'].$t);
         if(!$success){
            $addmessage.="\nThere was an error while deleting the tray's file($t). Please contact the system administrator.";
         }
      }
   }
   //we now ok, so delete all the data from the old db
   //now pasting the data
   $res=Connect2DB($config1);
   if(is_string($res)){
      $addmessage.="\nThere was an error while connecting to the database to delete the trays from the boxes table.";
   }
   else {
      StartTrans();
      $query="DELETE FROM boxes WHERE 1";
      $result=mysql_query($query, $dbcon);
      if(!$result) {
         LogError(); RollBackTrans();
         $addmessage.="\nThere was an error while deleting the used trays.";
      }
      CommitTrans();
   }

   //get any files that Harry's script might have created, zip them and download them
   //$zip=new ZipArchive();
   $files=scandir($resultsFolder);
   //echo $resultsFolder;
   $searchFiles=array('homelessSamples.txt', 'DuplicateSamples.txt', 'aziziUploadLog.txt', 'MySQLerrors.txt');
   $toDownload=''; $time='';
   $fd=fopen($uploadedFiles['location'].'results.txt', 'wt');
   if($fd) {
      fputs($fd, "{$addmessage}The data has been successfully processed and has been updated to the database.
               Log in to Lab Collector and ensure that all the data has been successfully saved.\n\n");
      //if($zip->open($uploadedFiles['location'].'results.zip', ZIPARCHIVE::CREATE)===TRUE){
      foreach($files as $t) {
         if($t=='.' || $t=='..') continue;
         $stats=pathinfo($t);
         if($stats['extension']!='txt') continue;
         if(in_array($stats['basename'], $searchFiles)) {
         //$zip->addFile($resultsFolder.$t);
            fputs($fd, $t."\n");
            fputs($fd, file_get_contents($resultsFolder.$t));
            fputs($fd, "\n\n\n");
         }
      }
      // $zip->close();
      fclose($fd);
      Header('Content-Disposition: attachment; filename="results.txt"');	
      readfile($uploadedFiles['location'].'results.txt');
      //clean the uploadedFiles folder for next uploads
      $success=unlink($uploadedFiles['location'].'results.txt');
      $success=unlink($uploadedFiles['location'].'IdealAllSamples.txt');
     die();
   }
   else {
   //we now gud, so return the dude to the home page
      Home("The data has been successfully processed and has been updated to the database. Log in to Lab Collector and ensure that all the data has been successfully saved.<br /> Cannot generate the results file.");
   }
}
//=============================================================================================================================================

function SaveFiles(){
global $uploadedFiles, $data;
   $err_occ=0;
//   print_r($uploadedFiles);
   if(is_dir($uploadedFiles['location'])){        //the dir exists//check if its writable; if not make it writable
 		if(!is_writable($uploadedFiles['location'])) {chmod($uploadedFiles['location'],0766); /*echo 'made it writable';*/}
   }
   else{
   	if(!mkdir($uploadedFiles['location'],0766)){
   		$err_occ=1;
	print_r($uploadedFiles);
         LogError('Error while creating the destination folder for uploaded files.');
   		die("Cannot create the destination folder.</div>");
   	}
   }

   //save the samples file
   $err_msg='';
   for($i=0;$i<count($_FILES['samples_batch']['name']);$i++){
      $err_code=$_FILES['samples_batch']['error'][$i];
      //LogError("Error Code: $err_code".count($_FILES['samples_batch']));
      if($err_code==4) continue;        //no file selected
      //check for the errors that might have occurred
      if($err_code!=0 && $err_code!=4){
      	if($err_code<3){
            $err_msg.=$_FILES['samples_batch']['name'][$i].' Max file size allowed was exceeded.<br>';
            LogError('The max file size was exceeded while trying to upload '.$_FILES['samples_batch']['name'][$i]);
         }
         if($err_code==3){
            $err_msg.=$_FILES['samples_batch']['name'][$i].' The file was partially uploaded.<br>';
            LogError('The '.$_FILES['samples_batch']['name'][$i].' was partially uploaded and discarded.');
         }
         $err_occ=1; continue;
      }

      //only allow xml files to be uploaded
      //LogError($_FILES['samples_batch']['type'][$i]);
      if($_FILES['samples_batch']['type'][$i]!='application/vnd.ms-excel' && $_FILES['samples_batch']['type'][$i]!='text/plain'){
      	$err_msg.=$_FILES['samples_batch']['name'][$i].' Not an allowed file type.<br>';
         LogError('Attempt to upload a wrong file: '.$_FILES['samples_batch']['name'][$i].' is not an allowed file type.'.$_FILES['samples_batch']['type'][$i]);
       	$err_occ=1; continue;
      }

      //Dont allow importation of files larger than 10Mb
      if($_FILES['samples_batch']['size'][$i] > $uploadedFiles['max_size']){
      	$err_msg.=$_FILES['samples_batch']['name'][$i].' The SD is bigger than 10Mb. You are only allowed to import files less than 10Mb.<br>';
         LogError('The uploaded file '.$_FILES['samples_batch']['name'][$i].'exceeds the limit of 10Mb.');
         $err_occ=1;  continue;
      }
      //create the destination folder name
      $destfolder=$uploadedFiles['location']."SamplesFile_$i.txt";
      //move the uploaded file to the final destination
      if(!move_uploaded_file($_FILES['samples_batch']['tmp_name'][$i],$destfolder)){
         $err_msg.=$_FILES['samples_batch']['name'][$i].'. There was an error while uploading the files.';
         LogError('There was an error while uploading the files.');
         $err_occ=1;  continue;
      }
   }

   if($err_occ==1){ Uploads($err_msg); return;}

   //save the trays files
   for($i=0;$i<count($_FILES['trays_batch']['name']);$i++){
      $err_code=$_FILES['trays_batch']['error'][$i];
      //LogError("Error Code: $err_code".count($_FILES['trays_batch']));
      if($err_code==4) continue;        //no file selected
      //check for the errors that might have occurred
      if($err_code!=0 && $err_code!=4){
      	if($err_code<3){
            $err_msg.=$_FILES['trays_batch']['name'][$i].' Max file size allowed was exceeded.<br>';
            LogError('The max file size was exceeded while trying to upload '.$_FILES['trays_batch']['name'][$i]);
         }
         if($err_code==3){
            $err_msg.=$_FILES['trays_batch']['name'][$i].' The file was partially uploaded.<br>';
            LogError('The '.$_FILES['trays_batch']['name'][$i].' was partially uploaded and discarded.');
         }
         $err_occ=1; continue;
      }

      //only allow xml files to be uploaded
      //LogError($_FILES['trays_batch']['type'][$i]);
      if($_FILES['trays_batch']['type'][$i]!='application/vnd.ms-excel' && $_FILES['trays_batch']['type'][$i]!='text/plain'){
      	$err_msg.=$_FILES['trays_batch']['name'][$i].' Not an allowed file type.<br>';
         LogError('Attempt to upload a wrong file: '.$_FILES['trays_batch']['name'][$i].' is not an allowed file type.'.$_FILES['trays_batch']['type'][$i]);
       	$err_occ=1; continue;
      }

      //Dont allow importation of files larger than 10Mb
      if($_FILES['trays_batch']['size'][$i] > $uploadedFiles['max_size']){
      	$err_msg.=$_FILES['trays_batch']['name'][$i].' The SD is bigger than 10Mb. You are only allowed to import files less than 10Mb.<br>';
         LogError('The uploaded file '.$_FILES['trays_batch']['name'][$i].'exceeds the limit of 10Mb.');
         $err_occ=1;  continue;
      }
      //create the destination folder name
      $destfolder=$uploadedFiles['location']."TrayFile_$i.txt";
      //move the uploaded file to the final destination
      if(!move_uploaded_file($_FILES['trays_batch']['tmp_name'][$i],$destfolder)){
         $err_msg.=$_FILES['trays_batch']['name'][$i].'. There was an error while uploading the files.';
         LogError('There was an error while uploading the files.');
         $err_occ=1;  continue;
      }
   }

   if($err_occ==1){ Uploads($err_msg); return;}
   else{
      $addinfo='The files have been successfully uploaded.';
      Home($addinfo);
   }
}
//=============================================================================================================================================
//=============================================================================================================================================
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Update Lab Collector</title>
        <link rel='stylesheet' type='text/css' href='../../common/common.css'>
        <link rel='stylesheet' type='text/css' href='../basic.css'>
        <link rel='stylesheet' type='text/css' href='../../common/mssg_box.css'>
    </head>
    <body>
        <table id='maintable'><tr><td>
        <?php
            echo '<div id="header"></div>';
            echo "<div id='contents'>$data</div>";
            echo "<div id='footer_links'>$footerLinks</div>";
            echo "<div id='footer'>IDEAL - LIMS Updater</div>";
        ?>
        </td></tr></table>
        <script type='text/javascript' src='custom_modules.js'></script>
        <script type='text/javascript' src='../../common/jquery.js'></script>
        <script type='text/javascript' src='../../common/jquery.json.js'></script>
        <script type='text/javascript' src='../../common/common.js'></script>
    </body>
</html>
