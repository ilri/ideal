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

if($paging=='') MainPage();
elseif($paging=='browse') BrowseResults();
elseif($paging=='upload') UploadResults();
elseif($paging=='results'){
    if($browseBy=='animal') BrowseByAnimal();
    else FetchResults();
} 
elseif($paging=='save_file') SaveUpload();

//==============================================================================================================================================
function MainPage($addinfo=''){
global $data, $pageref, $content, $footerLinks;
if(isset($addinfo) && $addinfo!='') $addinfo="<div id='addinfo'>$addinfo</div>";
else $addinfo="<div id='addinfo'>Results Module</div>";
$footerLinks="<a href='/ideal/?page=home'>Home</a>";

$content=<<<DATA
   <div id='home'>
      $addinfo
      <ol>
         <li><a href='$pageref?page=upload'>Upload file with results.</a></li>
         <li><a href='$pageref?page=browse'>Browse Results</a></li>
      </ol>
   </div>
DATA;
}
//==============================================================================================================================================

function BrowseResults($addinfo=''){
global $query, $pageref, $content;
if(isset($addinfo) && $addinfo!='') $addinfo="<div id='addinfo'>$addinfo</div>";
else $addinfo="<div id='addinfo'>Select a Module</div>";

$content=<<<DATA
   <div id='results'>
        $addinfo
        <ol class='results_lists'>
           <li><a href='$pageref?page=results&project=ideal&results=elisa'>Elisa Results</a></li>
           <li><a href='$pageref?page=results&project=ideal&browse=animal'>Browse by Animals</a></li>
        </ol>
   </div>
DATA;
}
//==============================================================================================================================================

function FetchResults(){
global $query, $pageref, $content, $lookUp, $contact, $config, $footerLinks;
   $project=trim($_GET['project']); $results=trim($_GET['results']); //we are expecting them to be strings
   $test=isset($_GET['test'])?$_GET['test']:NULL; $sample=isset($_GET['sample'])?$_GET['sample']:NULL;

   if(!Checks($project, 19)){MainPage("There is an error in the options provided.$contact"); return;}
   if(!Checks($results, 19)){MainPage("There is an error in the options provided.$contact"); return;}
   if(isset($test) && !is_numeric($test)){MainPage("There is an error in the options provided.$contact"); return;}
   if(isset($sample) && Checks($sample, '', '^[a-zA-Z]{3,4}[0-9]{5,6}$')){MainPage("There is an error in the options provided.$contact"); return;}

   //things are now ok, so fetch the data
   //LogError(print_r($lookUp, true));
   $res=Connect2DB($config);
   if(is_string($res)){MainPage("There was an error while connecting to the database.$contact"); return;}
   $query="select * from elisaTest where Project='".$lookUp['projects'][$project]."' order by testID";
   $res=GetQueryValues($query, MYSQL_ASSOC);
   if(is_string($res)){
      MainPage('There was an error while fetching data from the database.'); return;
   }
   elseif(count($res)==0){
      $content='<div id="plate_results">There are no results.</div>';
      return;
   }
   $curTestId='';
   $content='<div id="plate_results">';
   //LogError(print_r($res, true));
   foreach($res as $t){
      if(isset($test) && $test!=$t['testID']) continue;
      if($curTestId=='' || $t['testID']!=$curTestId){
         //fetch the metadata of this test id
         if($curTestId!='') $content.="</table><br /><hr /></div>"; //we have finished with that plate so close it
         $curTestId=$t['testID'];
         $query="select * from elisaSetUp where testID=$curTestId";
         $setup=GetQueryValues($query, MYSQL_ASSOC);
         if(is_string($setup)){
            MainPage("There was an error while fetching data from the database.$contact"); return;
         }
         elseif(count($setup)!=1){
            MainPage("There was an error in the data from the database. An elisa plate can have only one setup.$contact"); return;
         }

         $setup=$setup[0];
         $content.="<div class='plate_results'>";
         //start by creating the plate setup info
         $content.="<table border=0 cellspacing='0px' class='elisa_setup'><tr><th colspan='4'>".$setup['testType']."   <a href=''>Export to Excel Spreadsheet</a></th></tr>\n";
         $content.='<tr class="top_row"><td class="left_cell">Plate Name</td><td>'.$setup['plateName'].'</td><td>Status</td><td>'.$setup['plateStatus']."</td></tr>\n";
         $content.='<tr><td class="left_cell">Test Date and Time</td><td>'.$setup['testDateTime'].'</td><td>Filter</td><td>'.$setup['filter']."</td></tr>\n";
         $content.='<tr><td class="left_cell">Created By.</td><td>'.$setup['createBy'].'</td><td>Kit Batch No.</td><td>'.$setup['kitBatch']."</td></tr>\n";
         $content.='<tr><td class="left_cell">Technician</td><td>'.$setup['technician'].'</td><td>Blanking Value</td><td><b>'.$setup['blankingValue']."</b></td></tr>\n";
         $content.="<tr><td class='left_cell' colspan='4'>Acceptable OD Range: <b>".$setup['AcceptableODrange']."</b>, Threshold PP: <b>".$setup['PPthreshold']."</b>, Intermediate OD mean: <b>".$setup['meanControlOD']."</b></td></tr>";
         $content.="</table></div>";
         //create the table for the actual results
         $content.="<div class='actual_results'><table border=0 cellspacing='1px'>";
         $content.="<tr><th>ID</th><th>WELLS</th><th>Sample Id</th><th>AnimalID</th><th>Status</th><th>OD1</th><th>OD2</th><th>ODav</th>
                  <th>PP1</th><th>PP2</th><th>Var</th><th>PPav</th></tr>\n";  //create the headers
      }
      //get the actual results
      if(eregi('p',$t['STATUS'])){ $status='Positive'; $class='pos_elisa_res'; }
      else{ $status='Negative'; $class='neg_elisa_res'; }

      //check if we are looking for it
      if(isset($sample) && strtolower($sample)==strtolower($t['DESCRIPTION'])){
         $class.=" selected_result";
      }
      $content.="<tr class='$class'><td class='left'>".$t['ID']."</td><td>".$t['WELLS']."</td><td>".$t['DESCRIPTION']."</td><td>".$t['AnimalID']."</td><td>$status</td><td>".$t['OD1']."</td>
         <td>".$t['OD2']."</td><td>".$t['ODAv']."</td><td>".$t['PP1']."</td><td>".$t['PP2']."</td><td class='elisa_var'>".$t['Var']."</td><td class='right'>".$t['PPav']."</td></tr>\n";
   }
   $content.="</table></div></div>"; //we have finished, close the last table
}
//==============================================================================================================================================

function UploadResults($addinfo=''){
global $content, $pageref;
if(isset($addinfo) && $addinfo!='') $addinfo="<div id='addinfo'>$addinfo</div>";
else $addinfo="<div id='addinfo'>Select the file with the results to upload</div>";

$content=<<<DATA
   <div id='page_header'>Results Upload</div>
   $addinfo
   <form enctype="multipart/form-data" name="upload" action="$pageref?page=save_file" method="POST">
      <input type="hidden" value="10240000" name="MAX_FILE_SIZE"/>
      <div id='uploads'>
         Results's File: <input type="file" name="results[]" value="" width="50"/><br />
      </div>
      <div id='links'>
         <input type="submit" value="Upload" name="upload" /><input type="reset" value="Cancel" name="cancel" />
      </div>
   </form>
DATA;
}
//==============================================================================================================================================

function SaveUpload(){
global $content, $uploadedFiles, $message;
   $res=CustomSaveUploads(array('location'=>'../results/'.$uploadedFiles['location'], 'max_size'=>10485760), 'results', array('text/plain'), array('uploadedElisa.txt'));
   if(is_string($res)){ UploadResults($res); return; }
   
//print_r($res);
   //exec('perl /Library/WebServer/CGI-Executables/FileUploadTest.cgi'.' '.EscapeShellArg($res[0]));
$file=pathinfo($res[0]);
   $filePath='./uploadElisaDataMultiple.V3.pl uploadedFiles/' . $file['basename'];
//echo $filePath;
//print_r(error_get_last());
   //virtual('../../CGI-Executables/FileUploadTest.cgi'.' '.EscapeShellArg($filePath));
	exec($filePath, $message, $value);

/*echo "messages from the perl script <br />";*/
//print_r($message);
/*echo "<br /> returned value == $value";*/

//exec('/Library/WebServer/CGI-Executables/FileUploadTest.cgi');
   UploadResults($message[0]);
}
//==============================================================================================================================================

function BrowseByAnimal(){
global $query, $pageref, $content, $contact, $lookUp;

   $project=trim($_GET['project']); //we are expecting them to be strings
   if(!Checks($project, 19)){MainPage("There is an error in the options provided.$contact"); return;}

//connect to the db
$res=Connect2DB($config);
if(is_string($res)){MainPage("There was an error while connecting to the database.$contact"); return;}

//create the various combo boxes that will be used
   //visit id
   $query="select substr(b.VisitID, 1, 5) as Visitid from elisaTest as a inner join samples as b on a.sampleId=b.count
      where a.Project='".$lookUp['projects'][$project]."' group by substr(b.VisitID, 1, 5)";
   $res=GetQueryValues($query, MYSQL_ASSOC);
//   LogError($query);
   if(is_string($res)) return -1;
   $keys=array(); $values=array();
   foreach($res as $i => $t) $values[]=$t['Visitid'];
   $visitId=Populate_Combo($values, '', 'Select VisitId', 'visitId', 0, true, 'Results.updateData()');
   //visit date
   $query="select b.VisitDate from elisaTest as a inner join samples as b on a.sampleId=b.count where a.Project='".$lookUp['projects'][$project]."' group by b.VisitDate";
   $res=GetQueryValues($query, MYSQL_ASSOC);
   if(is_string($res)) return -1;
   $values=array();
   foreach($res as $i => $t) $values[]=$t['VisitDate'];
   $visitDate=Populate_Combo($values, '', 'Select VisitDate', 'visitDate', 0, true, 'Results.updateData()');
   //results, pos or negative
   $values=array('Positive', 'Negative', 'Retest');
   $result=Populate_Combo($values, '', 'Select Result', 'result', 0, true, 'Results.updateData()');
//   //results, pos or negative
//   $values=array('Positive', 'Negative');
//   $result=Populate_Combo($values, '', 'Select Result', 'result', 0, true, 'Results.updateData()');
//   //test types
   $query="SELECT testType FROM elisaSetUp group by testType";
   $res=GetQueryValues($query, MYSQL_ASSOC);
   if(is_string($res)) return -1;
   $values=array();
   foreach($res as $i => $t) $values[]=$t['testType'];
   $testType=Populate_Combo($values, '', 'Test Type', 'testType', 0, true, 'Results.updateData()');
   //animal type, calves or dams
   $values=array('Calves', 'Dams');
   $animalType=Populate_Combo($values, '', 'Select', 'animalType', NULL, true, 'Results.updateData()');
   //animal type, calves or dams
   $values=array('PP1', 'PP2', 'PPav');
   $indicators=Populate_Combo($values, '', 'Select', 'indicators', 0, true, '');
   //animal type, calves or dams
   $values=array('Equal To', 'Less than', 'Greater than');
   $comparison=Populate_Combo($values, '', 'Select', 'comparison', 0, true, '');

//create a page that the user will use to browse the results
$content=<<<CONTENT
<style>
#contents{
   max-height:350px;
}
</style>
    <div id='animal_browsing'>
        <div id='search_panel'>
            <div><form name='search' id='searchId' method='POST' action='seamless.php?page=results&project=ideal&browse=search'>
               <input type='text' name='global' value='Global Search' onEnter='this.value="";' size='13' onBlur='if(this.value==''){this.value="Global Search";}' />
               $indicators $comparison <input type='text' name='compareValue' size='13' id='compareId' value='' />
               <input type='button' value='Search' onClick='Results.updateData("query");' />
               <input type='button' value='Export' onClick='Results.updateData("export");' />
               <input type='button' value='Gen. Master' onClick='Results.updateData("export_master");' />
               <input type='hidden' name='queryType' id='queryId' value='' />
            </div>
            <div>{$animalType}{$testType}{$result}{$visitId}{$visitDate}</div>
            <iframe id='seamless_upload' name='seamless_upload' src='' style='width:0;height:0;border:0px solid #ffffff;'></iframe>
            </form>
        </div>
        <div id='left_panel'><div id='animals'>
CONTENT;
    $query="SELECT AnimalID FROM elisaTest where Project='".$lookUp['projects'][$project]."' group by AnimalID";
    $res=GetQueryValues($query, MYSQL_ASSOC);
    if(is_string($res)){
        MainPage("There was an error while fetching data from the database.$contact"); return;
    }
    $content.="<ul>";
    foreach ($res as $temp) {
        $temp=$temp['AnimalID'];
        $content.="<li><a href='javascript:;' onClick='Results.fetchAnimalData(\"$temp\");'>$temp</a></li>";
    }
    $content.="</ul>";
$content.=<<<CONTENT
        </div></div>
        <div id='right_panel'>
            Please click on an animal on the left to show its results here.
        </div>
    </div>
CONTENT;
}
//=============================================================================================================================================
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Results Module</title>
        <link rel='stylesheet' type='text/css' href='../../common/common.css'>
        <link rel='stylesheet' type='text/css' href='../basic.css'>
        <link rel='stylesheet' type='text/css' href='plate_results.css'>
        <link rel='stylesheet' type='text/css' href='../../common/mssg_box.css'>
    </head>
    <body>
        <table id='maintable'><tr><td>
        <?php
            echo "<div id='header'></div>\n";
            echo "<div id='contents'>$content</div>\n";
            echo "<div id='footer_links'>$footerLinks</div>\n";
            echo "<div id='footer'>IDEAL - Results Page</div>\n";
        ?>
        </td></tr></table>
        <script type='text/javascript' src='../../common/jquery.js'></script>
        <script type='text/javascript' src='../../common/jquery.json.js'></script>
        <script type='text/javascript' src='../../common/jquery.form.js'></script>
        <script type='text/javascript' src='../../common/common.js'></script>
        <script type='text/javascript' src='results.js'></script>
    </body>
</html>
