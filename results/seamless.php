<?php
require_once('results_config');
include_once('../../common/dbase_functions.php');
require_once('../../common/general.php');

//get the variables as passed by the user
$pageref = $_SERVER['PHP_SELF'];
$queryString=$_SERVER['QUERY_STRING'];
if(isset($_GET['page']) && $_GET['page']!='')	$paging=$_GET['page'];
else $paging='';
if(isset($_GET['browse']) && $_GET['browse']!='') $browseBy=$_GET['browse'];
else $browseBy='';

if(isset($_POST['flag']) && $_POST['flag']!='') $action=$_POST['flag'];
else $action='';
$content='';
$footerLinks.="<a href='$pageref'>Home</a>";

//echo 'we gud'.$paging;
//a hash table of the different look up values
$lookUp=array(
   'projects'=>array(
      'avid'=>'AVID',
      'ideal'=>'IDEAL'
   )
);

//echo "$paging $browseBy -- ";
//LogError(print_r($_POST, true));
//LogError(print_r($_GET, true));

if($paging=='') die('');
elseif($paging=='results'){
    if($browseBy=='animal'){
       //check if we have the animal id
       if(!isset($_POST['animalId'])){
          die('-1Please select an animal to display its data.');
       }
       else{
          //connect to the db
          $res=Connect2DB($config);
          if(is_string($res)) die("-1There was an error while connecting to the database.$contact");
          //get the animal results
          $animalId = $_POST['animalId'];
          $query="select b.label, c.testType, a.STATUS, a.PP1, a.PP2, a.PPav, b.VisitID, b.VisitDate from elisaTest as a
             inner join samples as b on a.sampleId=b.count inner join elisaSetUp as c on a.testID=c.testID
             where b.AnimalId='".mysql_real_escape_string($animalId)."'";
//          LogError($query);
//          die($query);
          $res=GetQueryValues($query, MYSQL_ASSOC);
          if(is_string($res)){
             die('-1There was an error while fetching data from the database.'.$contact);
          }
          $content="<div id='results'><div>Results for the animal: <b>$animalId</b></div><table>";
          $content.="<tr><th>#</th><th>SampleId</th><th>Visit Id</th><th>Visit Date</th><th>Disease</th><th>Result</th><th>PP1</th><th>PP2</th><th>PPav</th></tr>";
          $i=1;
          foreach($res as $t){
             if($t['STATUS']=='N') $status='NEG';
             elseif($t['STATUS']=='POS') $status='POS';
             $content.="<tr><td>$i</td><td>".$t['label']."</td><td>".$t['VisitID']."</td><td>".$t['VisitDate']."</td><td>".$t['testType'].
             "</td><td>$status</td><td>".$t['PP1']."</td><td>".$t['PP2']."</td><td>".$t['PPav']."</td></tr>";
             $i++;
          }
          $content.="</div>";
          die($content);
       }
    }
    elseif($browseBy=='search') {
       //we wanna create a filter(s) for the search criteria

       //connect to the db
       $res=Connect2DB($config);
       if(is_string($res)) die("-1There was an error while connecting to the database.$contact");

       $lookUp=array( 'projects'=>array('avid'=>'AVID','ideal'=>'IDEAL') );
       $project=trim($_GET['project']); //we are expecting them to be strings
       if(!Checks($project, 19)) die("-1There is an error in the options provided.$contact");

       if(is_numeric($_POST['animalType']) && $_POST['animalType']!=0){
          if($_POST['animalType']==1) $type="and b.AnimalId like 'CA%'";
          elseif($_POST['animalType']==2) $type="and b.AnimalId like 'DM%'";
       }
       else $type = '';

       //visit date
       if(is_numeric($_POST['visitDate']) && $_POST['visitDate']!=0){
          $query="select b.VisitDate from elisaTest as a inner join samples as b on a.sampleId=b.count
             where a.Project='".$lookUp['projects'][$project]."' group by b.VisitDate";
          $res=GetQueryValues($query, MYSQL_ASSOC);
          if(is_string($res)) return -1;
          $visitDates=array();
          foreach($res as $i => $t) $visitDates[]=$t['VisitDate'];
          $visitDate="and b.VisitDate='".$visitDates[$_POST['visitDate']-1]."'";
       }
       else $visitDate='';

       //visit id
       if(is_numeric($_POST['visitId']) && $_POST['visitId']!=0){
          $query="select substr(b.VisitID, 1, 5) as VisitID from elisaTest as a inner join samples as b on a.sampleId=b.count
             where a.Project='".$lookUp['projects'][$project]."' group by substr(b.VisitID, 1, 5)";
          $res=GetQueryValues($query, MYSQL_ASSOC);
          if(is_string($res)) return -1;
          $visitIds=array();
          foreach($res as $i => $t) $visitIds[]=$t['VisitID'];
          $visitId="and b.VisitID like '".$visitIds[$_POST['visitId']-1]."%'";
       }
       else $visitId='';

       //test types
       if(is_numeric($_POST['testType']) && $_POST['testType']!=0){
          $query="SELECT testType FROM elisaSetUp where a.Project='".$lookUp['projects'][$project]."' group by testType";
          $res=GetQueryValues($query, MYSQL_ASSOC);
          $testTypes=array();
          foreach($res as $i => $t) $testTypes[]=$t['testType'];
//          LogError(print_r($testTypes, true));
          $testTypesCriteria = " and c.testType='".$testTypes[$_POST['testType']-1]."'";
       }

       //result
       if(is_numeric($_POST['result']) && $_POST['result']!=0){
          if($_POST['result']==1) $result = "and a.STATUS='POS'";
          elseif($_POST['result']==2) $result = "and a.STATUS='N'";
          elseif($_POST['result']==3) $result = "and a.STATUS='retest'";
       }
       else $result='';

       //test results
       if(is_numeric($_POST['comparison']) && $_POST['comparison']!=0 && is_numeric($_POST['indicators']) && $_POST['indicators']!=0
               && is_numeric($_POST['compareValue'])){
          $searchCriteria = "";
          //indicators
          if($_POST['indicators']==1) $searchCriteria = "and a.PP1";
          elseif($_POST['indicators']==2) $searchCriteria = "and a.PP2";
          elseif($_POST['indicators']==2) $searchCriteria = "and a.PPav";
          //comparison
          if($_POST['comparison']==1) $searchCriteria .= " = ";
          elseif($_POST['comparison']==2) $searchCriteria .= " < ";
          elseif($_POST['comparison']==3) $searchCriteria .= " > ";
          //comparison value
          $searchCriteria .= $_POST['compareValue'];
       }
       else $searchCriteria='';

       //global search
       if(isset($_POST['global']) && $_POST['global']!='Global Search' && $_POST['global']!=''){
          $globalFields=array('a.WELLS', 'a.DESCRIPTION', 'a.STATUS', 'b.SSID', 'b.AnimalID', 'b.label', 'b.comments', 'b.sampleID', 'b.VisitID',
             'b.VisitDate', 'b.TrayID');
          $globalSearch=array();
          foreach($globalFields as $t){
             $globalSearch[] = "$t like '%".$_POST['global']."%'";
          }
          $globalSearch = "and (".implode(' or ', $globalSearch).")";
       }

       //the diff parts of the query are now set, so now create the query
       $query="select b.AnimalID from elisaTest as a inner join samples as b on a.sampleId=b.count inner join elisaSetUp as c on a.testID=c.testID
             where a.Project='".$lookUp['projects'][$project]."' $type $visitDate $visitId $result $searchCriteria $testTypesCriteria
             $globalSearch group by b.AnimalID";
       $res=GetQueryValues($query, MYSQL_ASSOC);
       if(is_string($res)) {
          die('-1There was an error while fetching data from the database.'.$contact);
       }
       if($_POST['queryType']=='export' || $_POST['queryType']=='export_master') {
          //create the query that will fetch all the data based on the search criteria
          $query="select b.label, b.AnimalID, b.SSID, b.comments, b.VisitID, b.VisitDate, b.TrayID, a.WELLS, a.STATUS, a.PP1, a.PP2,
             a.PPav, a.DESCRIPTION, c.testType, a.ODAv, a.OD1, a.OD2, a.Var
             from elisaTest as a inner join samples as b on a.sampleId=b.count inner join elisaSetUp as c on a.testID=c.testID
             where a.Project='".$lookUp['projects'][$project]."' $type $visitDate $visitId $result $searchCriteria $testTypesCriteria
                  $globalSearch order by b.AnimalID";
//          LogError($query);
          $res=GetQueryValues($query, MYSQL_ASSOC);
          if(is_string($res)) die();
          //try and open the file for writing
          $fd=fopen('errors/output.csv', 'wt');
          if(!$fd) $addinfo="There was an error while opening the output file.";

          if($_POST['queryType']=='export' && $fd) {
             fputs($fd, "Sample,SampleId,AnimalID,Status,Test Type,PP1,PP2,PPav,Visit Id,Visit Date,Tray,Comments\n");
             foreach($res as $t) {
                fputs($fd, $t['label'].",".$t['DESCRIPTION'].",".$t['AnimalID'].",".$t['STATUS'].",".$t['testType'].",".$t['PP1'].",".
                        $t['PP2'].",".$t['PPav'].",".$t['VisitID'].",".$t['VisitDate'].",".$t['TrayID'].",".$t['comments']."\n");
             }
             fclose($fd);

             if(isset($_SERVER['HTTP_USER_AGENT']) and strpos($_SERVER['HTTP_USER_AGENT'],'MSIE')) Header('Content-Type: application/vnd.ms-excel');
//          Header('Content-Type: application/force-download');
             Header('Content-Disposition: attachment; filename="Query_Results_'.date("d-m-Y His").'.csv"');
             if(headers_sent())  return $content;
             readfile('errors/output.csv');
             die();
          }
          elseif($_POST['queryType']=='export_master' && $fd) {
             GenerateMaster($project, $res, $fd);
          }
       }
       elseif($_POST['queryType']=='query'){
          $content="<ul>";
          if(count($res)==0) $content.="The search returned 0 results";
          else{
             foreach ($res as $temp) {
                $temp=$temp['AnimalID'];
                $content.="<li><a href='javascript:;' onClick='Results.fetchAnimalData(\"$temp\");'>$temp</a></li>";
             }
          }
          $content.="</ul>";
          die($content);
       }
    }
}

function GenerateMaster($project, $allData, $fd) {
global $query, $contact, $lookUp;
//import the file with the class defs
   require_once 'classes.php';
   //get all the animals with the test on and then add the results data from the executed results query
   $query="SELECT AnimalID FROM `elisaTest` where Project='".$lookUp['projects'][$project]."' group by AnimalID";
   $res1 = GetQueryValues($query, MYSQL_ASSOC);
   if(is_string($res1)) die('-1There was an error while fetching data from the database.'.$contact);
   $animalResults = new AnimalElisaResults();
   foreach($res1 as $tmp) {
      $tmpAnimal = new Animal($tmp['AnimalID']);  //create a new instance of an animal and add it to the objects collection
      $animalResults->addAnimal($tmpAnimal, $tmp['AnimalID']);   //add the created animal instance into the animal collection
   }
   //after creating all the animals, now iterate through all the results and add each result to the corresponding animal
//   $uniqueTests = array();
   foreach($allData as $temp) {
      $tempAnimal = $animalResults->getAnimalById($temp['AnimalID']);
      //create a new instance of the sample
      $sampleExists = $tempAnimal->getSampleByName($temp['DESCRIPTION']);
//      LogError(print_r($sampleExists, true));
      if($sampleExists == -1) $tempSample = new Sample($temp['DESCRIPTION'], substr($temp['VisitID'], 0, 5));
      //remove the INDIRECT
      if(preg_match('/\(INDIRECT\)/', $temp['testType'])==1){
         $tmp = preg_split('/\(INDIRECT\)/', $temp['testType']);
         $temp['testType'] = trim($tmp[0]);
      }
//      if(!in_array($temp['testType'], $uniqueTests)) $uniqueTests[] = $temp['testType'];
//LogError(print_r($temp, true));
      $res1 = $tempSample->addResult($temp['testType'], $temp['STATUS'], $temp['OD1'], $temp['OD2'],
              $temp['ODAv'], $temp['PP1'], $temp['PP2'], $temp['Var'], $temp['PPav'], $temp['VisitID']);
      //check that if it is a clinical visit and check the flag for clinical visits
      if(preg_match('/VCC/', $temp['VisitID'])!=0){
         if($tempAnimal->ifAnimalHasClinicalSample()==0)  $tempAnimal->animalHasClinicalSample();
      }
      if($res1) {
         //There was an error while adding these results to the sample. They might already have been added
      }
      else {
         if($sampleExists == -1) $tempAnimal->addSample($tempSample, $temp['DESCRIPTION']);
      }
   }
//echo 'Am here';
//   LogError(print_r($animalResults, true));
//   LogError(print_r($uniqueTests, true));
   //get the unique visits that have been carried out so far
   $query="SELECT substr(b.VisitID, 1, 5) as VisitID FROM `elisaTest` as a inner join samples as b on a.sampleId=b.count where a.Project='"
      .$lookUp['projects'][$project]."' group by substr(b.VisitID, 1, 5)";
   $res2 = GetQueryValues($query, MYSQL_ASSOC);
   if(is_string($res2)) {
      //There was an error while fetching data from the database, please contact the syst admin
   }
   $uniqueVisits = array(); $clinicalVisits = array();
   foreach($res2 as $temp){
      if(preg_match('/VRC/', $temp['VisitID'])) $uniqueVisits[] = $temp['VisitID'];
      elseif(preg_match('/VCC/', $temp['VisitID'])) $clinicalVisits[] = $temp['VisitID'];
   }
//   LogError(print_r($clinicalVisits, true));
//             foreach($res2 as $temp) $uniqueVisits[] = $temp['VisitID'];
   //get the unique tests that are being carried out
   $query="SELECT b.testType FROM `elisaTest` as a inner join elisaSetUp as b on a.testID=b.testID where a.Project='".
      $lookUp['projects'][$project]."' group by b.testType";
             LogError($query);
   $res2 = GetQueryValues($query, MYSQL_ASSOC);
   if(is_string($res2)) {
      //There was an error while fetching data from the database, please contact the syst admin
   }
   $uniqueTests = array();
   foreach($res2 as $temp){
      //remove the INDIRECT
      if(preg_match('/\(INDIRECT\)/', $temp['testType'])==1){
         $temp = preg_split('/\(INDIRECT\)/', $temp['testType']);
         $temp['testType'] = trim($temp[0]);
      }
      if($temp['testType'] != '' && !in_array($temp['testType'], $uniqueTests)) $uniqueTests[] = $temp['testType'];
   }

//   LogError(print_r($uniqueTests, true));
//   LogError(print_r($uniqueVisits, true));
   //now all is set now lets start creating the excel spreadsheet
   fputs($fd, ",,,,This excel spreadsheet bears a format close to the expected master spreadsheet and not the exact format.,,,,\n");
   fputs($fd, "\n\n"); //give a 2 line formatting space
   $allTests = implode(str_repeat(",", count($uniqueVisits)+1), $uniqueTests).str_repeat(",", count($uniqueVisits)+1).implode(",", $uniqueTests);
   fputs($fd, ",,,$allTests,,,\n"); //the header of all the tests that we are doing
   fputs($fd, ",,");
   $allVisits = implode("W,", $uniqueVisits);
   foreach($uniqueTests as $temp) fputs($fd, "Dam,{$allVisits}W,");    //add all routine visits under each test
   foreach($uniqueTests as $temp) fputs($fd, ",".implode(",", $clinicalVisits));
   fputs($fd, "\n");
   //end of the headers, now the real stuff, get all the animals
   $allAnimals = $animalResults->getAnimals();
//   LogError(print_r($allAnimals, true));
   //loop thru all the animals and start spitting out the data
   foreach($allAnimals as $tempAnimal) {
      $tempId = $tempAnimal->getAnimalId();
      if(preg_match('/CA/', $tempId)==0){
//         LogError(print_r($tempAnimal, true));
         continue;
      }
      //get the dam of this animal
      $dam = DamOfCalf($allAnimals, $tempId);
      $allSamples = $tempAnimal->getAllSamples('VRC');   //get only the routine visits
      fputs($fd, ",".$tempAnimal->getAnimalId());
      foreach($uniqueTests as $tempTest) {   //iterate thru all the unique tests
         //get the single sample ***BIG ASSUMPTION, CONFIRM WITH HENRY**** and get the results
         if($dam === 1) fputs($fd, ",No Dam");
         else{
            $damSamples = $dam->getAllSamples(); //get only the routine visits
            $res = "";
            foreach ($damSamples as $damSample){
               $tempResult = $damSample->getResult($tempTest);
               $res = $tempResult['ppav'];
               break;   //since we are assuming that the dam has only one sample, get this sample and continue with what you have been doing
            }
            if($res=="") fputs($fd, ",No Result");
            else fputs($fd, ",$res");
         }
         foreach($uniqueVisits as $tempVisit) {    //iterate thru all the unique visits
            $res ="";
            foreach($allSamples as $tempSample) {  //now get the result of this visit of this test
               if($tempSample->getVisitId() == $tempVisit) {  //the visits are ok
                  $tempResult = $tempSample->getResult($tempTest);
                  $res = $tempResult['ppav'];//." ($tempTest -- $tempVisit)";
                  break;   //we have got wat we want, lets stop wasting sys resources
               }
               if($res=="") $res = "No Result";
            }
            if($res=="") $res = "No Result";
            fputs($fd, ",$res");
         }
      }
//      if($tempId == 'CA051910541') LogError(print_r($allSamples, true));
      //check if this animals has some clinical visits
      if($tempAnimal->ifAnimalHasClinicalSample()==1) {
         fputs($fd, ",");  //leave the extra column
//         LogError(print_r($tempAnimal, true));
         $allSamples = $tempAnimal->getAllSamples('VCC');   //now get only the clinical visits
         foreach($clinicalVisits as $tempVisit) {
            foreach($uniqueTests as $tempTest) {
               $res = "";
               foreach($allSamples as $tempSample) {
                  $visitId = $tempSample->getVisitId();
                  if(preg_match('/VCC/', $visitId)==1 && $tempVisit == $visitId) {
                     $tempResult = $tempSample->getResult($tempTest);
                     $res = $tempResult['ppav'];
//                     LogError(print_r($tempResult, true));
//                     break; //stop wasting sys resources
                  }
               }
               if($res=="") $res = "No Result";
               fputs($fd, ",$res");
            }
//            if($res=="") $res = "No Result";
         }
      }
      fputs($fd, "\n");   //we have finished with one animal, lets go to the next
   }
   //end of all the data, now return the file
   fclose($fd);
   if(isset($_SERVER['HTTP_USER_AGENT']) and strpos($_SERVER['HTTP_USER_AGENT'],'MSIE')) Header('Content-Type: application/vnd.ms-excel');
   Header('Content-Disposition: attachment; filename="Master-SpreadSheet'.date("d-m-Y His").'.csv"');
   if(headers_sent())  return $content;
   readfile('errors/output.csv');
   die();
}

function DamOfCalf($allAnimals, $calfId){
   //the last 4 digits of the calf and the dam are equal, so get it
   $subCalfId = substr($calfId, 7);
   foreach($allAnimals as $tempAnimal){
      $tempId = $tempAnimal->getAnimalId();
      if(preg_match("/$subCalfId$/", $tempId)==1 && preg_match("/DM/", $tempId)==1){
//         LogError("Dam ID: $tempId, Calf ID: $calfId, Criteria1: $subCalfId");
         return $tempAnimal;
      }
   }
   return 1;
}
?>
