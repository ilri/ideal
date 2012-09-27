<?php

/**
 * Performs user authentication depending on the username and password received from the form. Calls the necessary functions to generate the necessary page
 *
 * @return <HTML content>   Returns the generated HTML content for the 'necessary page'.
 */
function LogIn(){
global $query, $content, $footerLinks, $contact, $config;
   //print_r($_POST);
   $username=$_POST['user']; $pass=md5($_POST['password']);
   if(Checks($username, 16)){
      $content=MainPage('Error. You have specified an invalid username. Please specify a correct username.'); return $content;
   }
   //since the password is encoded, just encode it and go on
   $res=ConfirmUser($username, $pass);
   if($res==-1){
      $content=MainPage('There was an error while fetching data from the database.'.$contact); return $content;
   }
   elseif(is_array($res)){
      //get the user level
      $user_level=GetSingleRowValue($config['session_dbase'].'.user_levels', 'name', 'id', $res['user_level']);
      if($user_level==-2){
         $content=MainPage('There was an error while fetching data from the database.'.$contact); return $content;
      }
      $_SESSION['username']=$res['login']; $_SESSION['psswd']=$pass;
      $_SESSION['project']=$res['project']; $_SESSION['user_level']=$user_level;
      //we now ok, so determine the home page to display
      $content=HomePage();
   }

return $content;
}
//=========================================================================================================================================================

/**
 * Determines the home page of the user depending on the user rights, and calls the respective home page generator
 *
 * @param <string> $addinfo Any additional info to be displayed on the page
 * @return <HTML content>   Returns the generated HTML content.
 */
function HomePage($addinfo=''){
   //print_r($_SESSION);
   //if($_SESSION['user_level']=='Super Administrator') $content=SuperAdminHomePage($addinfo);
   //elseif($_SESSION['user_level']=='Ideal Users') $content=IdealCollaboratorsHomePage($addinfo);
$content=SuperAdminHomePage($addinfo);
   return $content;
}
//=========================================================================================================================================================

/**
 * Generates an interface for the admin home page with the necessary links
 *
 * @param <string> $addinfo Any additional info to be displayed on the generated page
 * @return <HTML Content>   Returns the generated HTML content for the page
 */
function SuperAdminHomePage($addinfo=''){
global $pageref;
if($addinfo=='') $addinfo = 'IDEAL System Resources';

$content=<<<CONTENT
   <div id='home'>
      <div id='addinfo'>$addinfo</div>
      <ol>
         <li><a href='uploading_samples/'>Uploading Samples</a></li>
         <li><a href='results/'>Results Module</a></li>
         <li><a href='$pageref?page=download_all_samples'>Download all IDEAL samples(csv file)</a></li>
         <li><a href='$pageref?page=download_elisa_results'>Download all ELISA results(csv file)</a></li>
         <li><a href='$pageref?page=users'>Users Settings</a></li>
         <li><a href='$pageref?page=change_credits'>Change username and/or password</a></li>
      </ol>
   </div>
CONTENT;

return $content;
}
//============================================================================================================================================

/**
 * Generates the home page for label printers.
 *
 * @global <string> $pageref  The current executing script
 * @return <HTML>  Returns a formatted HTML output of the label printing home page
 */
function LabelPrintingHomePage(){
global $pageref;

$content=<<<CONTENT
   <div id='home'>
      <div id="page_header">Label Printing</div>
      <ol>
         <li><a href='../../avid/LabelPrinting/'>Label Printing</a></li>
         <li><a href='$pageref?page=change_credits'>Change username and/or password</a></li>
      </ol>
   </div>
CONTENT;

return $content;
}
//=========================================================================================================================================================

/**
 * Generates the error page content
 *
 * @param <string> $addinfo Any additional info to be displayed on the page. This is usually the cause of the error that has occured.
 */
function ErrorPage($addinfo=''){
global $content;
   if($addinfo=='') $addinfo="There was an unspecified error. Please contact the system administrator";
   $content=MainPage($addinfo);

$content=<<<CONTENT
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>IDEAL Repositories</title>
        <link rel='stylesheet' type='text/css' href='../common/common.css'>
        <link rel='stylesheet' type='text/css' href='basic.css'>
        <link rel='stylesheet' type='text/css' href='ideal_system.css'>
        <link rel='stylesheet' type='text/css' href='../common/mssg_box.css'>
    </head>
    <body>
        <table id='maintable'><tbody><tr><td>
        <?php
            echo "<div id='header'>&nbsp;</div>\n";
            echo "<div id='contents'>$content</div>\n";
            echo "<div id='footer_links'>$footerLinks</div>\n";
            echo "<div id='footer'>IDEAL Repositories</div>\n";
        ?>
        </td></tr>
        <tr><td></td></tr>
           </tbody></table>
        <script type='text/javascript' src='ideal_system.js'></script>
        <script type='text/javascript' src='../common/jquery.js'></script>
        <script type='text/javascript' src='../common/jquery.form.js'></script>
        <script type='text/javascript' src='../common/jquery.json.js'></script>
        <script type='text/javascript' src='../common/common.js'></script>
    </body>
</html>
CONTENT;

return $content;
}
//=========================================================================================================================================================

/**
 * Generates the main page. this is usually the login page
 *
* @param string $addinfo Any additional info to be displayed on the page
 * @return <HTML content>   Returns the generated HTML content.
 */
function MainPage($addinfo=''){
global $footerLinks, $pageref;
   $footerLinks='';
   if($addinfo=='') $addinfo="Please enter your username and password to access IDEAL system resources.";
   $content = "<script type='text/javascript' src='". OPTIONS_COMMON_FOLDER_PATH ."jquery.md5.js'></script>";

$content .= <<<CONTENT
<div id='main'>
   <table><form action='$pageref?page=login' name='avid' method='POST' >
      <tr><td colspan='2'>$addinfo</td></tr>
      <tr><td class='right_align'>Username</td><td class='left_align'><input type='text' size='15' value='' name='user' /></td></tr>
      <tr><td class='right_align'>Password</td><td class='left_align'><input type='password' size='15' value='' name='password' /></td></tr>
      <tr><td colspan='2'><input type='submit' value='Log In' name='login' /><input type='button' value='Cancel' name='cancel' /></td></tr>
      <input type="hidden" name="md5_pass" />
   </form></table>
</div>
<script type='text/javascript'>
   function submitLogin(){
       var userName = $('[name=user]').val(), password = $('[name=password]').val();
       if(userName == ''){
          alert('Please enter your username!');
          return false;
       }
       if(password == ''){
          alert('Please enter your password!');
          return false;
       }

       //we have all that we need, lets submit this data to the server
       $('[name=md5_pass]').val($.md5(password));
       $('[name=password]').val('');
       return true;
   }

   $('[name=login]').bind('click', submitLogin);
   $('[name=username]').focus();
</script>
CONTENT;

return $content;
}
//=========================================================================================================================================================

function AllUsers(){
global $query, $config;
   //get all the users as defined in the dbase and display them on the interface

}
//=============================================================================================================================================

function DownloadElisaResults(){
   global $contact;
   $start = 0; $batch = 100;
      $fileName = 'IDEAL_Elisa_Results_'.date('Ymd_His').'.csv';
      $outputFile = fopen("uploads/$fileName", 'wt');
      if(!$outputFile) die('There was an error while opening the output file for writing.');
   while(1){
//      echo "$start - $batch<br />";
      $query="select
         a.count, a.label, a.SSID, a.SampleID, a.StoreLabel, a.AnimalID, a.VisitID, a.date_created, b.sample_type_name, c.org_name, a.TrayId, a.box_details, a.ShippedDate, a.DateRecorded, a.Latitude, a.Longitude, a.comments
         from samples as a inner join sample_types_def as b on a.sample_type=b.count inner join organisms as c on a.org=c.org_id where Project=4 limit $start, $batch";
//      echo $query; die();
      $res1 = GetQueryValues($query, MYSQL_ASSOC);
      if(is_string($res1)) die('-1There was an error while fetching data from the database.'.$contact);
//      echo '<pre>'.print_r($res1, true).'</pre>'; die();
      if(!count($res1)) break;
      if($start == 0){
         $colHeaders = array_keys($res1[0]);
         fwrite($outputFile, '"' . implode('","', $colHeaders) . "\"\n");
      }
      foreach($res1 as $t){
         fwrite($outputFile, '"' . implode('","', $t) . "\"\n");
      }
      $start += $batch;
   }
   fclose($outputFile);
   if(isset($_SERVER['HTTP_USER_AGENT']) and strpos($_SERVER['HTTP_USER_AGENT'],'MSIE')) Header('Content-Type: application/vnd.ms-excel');
   Header('Content-Disposition: attachment; filename="IDEAL_Elisa_Results_'.date("Ymd_His").'.csv"');
   if(headers_sent())  return $content;
   readfile("uploads/$fileName");
   unlink("uploads/$fileName");
   die();
}
//=============================================================================================================================================

function DownloadSamples(){
   global $contact;
   $start = 0; $batch = 100;
      $fileName = 'allSamples_'.date('Ymd_His').'.csv';
      $outputFile = fopen("uploads/$fileName", 'wt');
      if(!$outputFile) die('There was an error while opening the output file for writing.');
   while(1){
//      echo "$start - $batch<br />";
      $query="select
         a.count, a.label, a.SSID, a.SampleID, a.StoreLabel, a.AnimalID, a.VisitID, a.date_created, b.sample_type_name, c.org_name, a.TrayId, a.box_details, a.ShippedDate, a.DateRecorded, a.Latitude, a.Longitude, a.comments
         from samples as a inner join sample_types_def as b on a.sample_type=b.count inner join organisms as c on a.org=c.org_id where Project=4 limit $start, $batch";
//      echo $query; die();
      $res1 = GetQueryValues($query, MYSQL_ASSOC);
      if(is_string($res1)) die('-1There was an error while fetching data from the database.'.$contact);
//      echo '<pre>'.print_r($res1, true).'</pre>'; die();
      if(!count($res1)) break;
      if($start == 0){
         $colHeaders = array_keys($res1[0]);
         fwrite($outputFile, '"' . implode('","', $colHeaders) . "\"\n");
      }
      foreach($res1 as $t){
         fwrite($outputFile, '"' . implode('","', $t) . "\"\n");
      }
      $start += $batch;
   }
   fclose($outputFile);
   if(isset($_SERVER['HTTP_USER_AGENT']) and strpos($_SERVER['HTTP_USER_AGENT'],'MSIE')) Header('Content-Type: application/vnd.ms-excel');
   Header('Content-Disposition: attachment; filename="All_IDEAL_Samples_'.date("Ymd_His").'.csv"');
   if(headers_sent())  return $content;
   readfile("uploads/$fileName");
   unlink("uploads/$fileName");
   die();
}
//=============================================================================================================================================
?>
