<?php
define('OPTIONS_COMMON_FOLDER_PATH', '../common/');

require_once 'ideal_config';
require_once 'ideal_system.php';
include_once  OPTIONS_COMMON_FOLDER_PATH . 'dbase_functions.php';
require_once  OPTIONS_COMMON_FOLDER_PATH . 'general.php';
session_save_path($config['session_dbase']);
session_name('sessions');
StartingSession();

$pageref = $_SERVER['PHP_SELF'];
$server_name=$_SERVER['SERVER_NAME'];
$queryString=$_SERVER['QUERY_STRING'];
if(isset($_GET['page']) && $_GET['page']!='')	$paging=$_GET['page'];
else $paging='';
if(isset($_POST['flag']) && $_POST['flag']!='') $action=$_POST['flag'];
else $action='';
$content='';
$footerLinks.="<a href='$pageref?page=home'>[Home]</a>";


//confirm that we are dealing with the right user
if($paging=='login') $content=LogIn();
elseif(isset($_SESSION['username']) && isset($_SESSION['psswd'])){
   $res=ConfirmUser($_SESSION['username'], $_SESSION['psswd']);
   if($res==-1) $paging='';
}
else{
   LogOut();
   $paging='';
   $content=MainPage();
}

if($paging==''){
   //check if something has been passed to us from the main page
//   print_r($_SERVER);
   if(isset($_GET['addinfo'])) $addinfo = $_GET['addinfo'];
   $content=MainPage($addinfo);
}
elseif($paging=='home') $content=HomePage();
elseif($paging=='logout'){
   LogOut();
   $content=MainPage();
}
elseif($paging=='change_credits') $content=ChangePasswordInterface('Please enter the new credentials.');
elseif($paging=='change_password') $content=ChangePassword();
elseif($paging=='download_all_samples') $content=DownloadSamples();
elseif($paging=='download_elisa_results') $content=DownloadElisaResults();

if(!($paging=='' || $paging=='logout')){
   $footerLinks.="<a href='$pageref?page=logout'>[Log Out]</a>";
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>IDEAL Repositories</title>
        <link rel='stylesheet' type='text/css' href='<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>common.css'>
        <link rel='stylesheet' type='text/css' href='basic.css'>
        <link rel='stylesheet' type='text/css' href='<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>mssg_box.css'>
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
        <script type='text/javascript' src='<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery.js'></script>
        <script type='text/javascript' src='<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery.form.js'></script>
        <script type='text/javascript' src='<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>jquery.json.js'></script>
        <script type='text/javascript' src='<?php echo OPTIONS_COMMON_FOLDER_PATH; ?>common.js'></script>
    </body>
</html>