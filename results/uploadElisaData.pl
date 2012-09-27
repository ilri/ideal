#!/usr/bin/perl -w
use warnings;
#use strict;
use DBI;
use dbModules;


#Open database connection
my $db = dbModules::connect_azizi();
#$db->do("delete from $elisaSetUp");
#$db->do("delete from $elisaControls");
#$db->do("delete from $elisaTest");

#my @infiles = ("Plate1.txt", "Plate2.txt", "Plate3.txt", "Plate4.txt", "Plate5.txt", "Plate6.txt", "Plate7.txt", "Plate8.txt");
my $elisaSetUp = "elisaSetUpTest";
my $elisaControls = "elisaControlsTest";
my $elisaTest = "elisaTestTest";
my $samples = "testSamples";
my $outfile = "uploadedFiles/homelessSamples.txt";
my $duplicatesFile = "uploadedFiles/DuplicateSamples.txt";
my $logfile = "uploadedFiles/elisaUploadLog.txt";
my $errors = "uploadedFiles/MySQLerrors.txt";
my %results = ("POS" => "POS", "N" => "NEG", "retest" => "RETEST");
my %field;
my @testNames = ("B.bigemina", "T.mutans", "A.marginale", "T.parva");
my $i = 0;
my ($controlCount, $testCount);
my (@controlRow, @testRow, @testheaders, @controlHeaders);
my @elisaSetUp = ("testID", "testType", "plateStatus", "plateName", "testDateTime", "createBy", "filter", 
		"technician", "blankingValue", "kitBatch", "AcceptableODrange", "PPthreshold", 'meanControlOD');

#die;

my %rows;
$row{0} = "Plate status:";
$row{1} = "Plate name:";
$row{2} = "Created by:";
$row{3} = "Filter:";
$row{4} = "Blanking value:";
$row{6} = "CONTROLS:";
$row{7} = "control limits:";
$row{9} = 'ID\sSTATUS';
$row{10} = '^C\+\+\s\w';
$row{11} = '^C\+\s\w';
$row{12} = '^C-\s\w';
$row{13} = '^Cc\s\w';
$row{15} = "average of the two intermediate";
$row{17} = "TEST SAMPLES:";
$row{19} = 'ID\sWELLS';
my %function = (0 => 'status', 1 => 'name', 2 => 'created', 3 => 'filter', 4=> 'blanking',
		6 => 'controls', 7=> 'ignore', 9=>'controlHeader', 10 => 'controlValue',
		11 => 'controlValue',12 => 'controlValue',13 => 'controlValue',
		15 => 'controlMean', 17 => 'ignore', 19 => 'testHeader'); 
foreach my $r (20..60){
	$row{$r} = '\d*\s\w\d\/\w\d\s\w+';
	$function{$r} = "testValue";
}
#Get ids of Projects
my %projects;
$querystr = "select val_id, value from modules_custom_values";
$query = run_query($querystr, $db);
while(my ($val_id, $value) = $query->fetchrow_array()){
	$projects{$val_id} = $value;
}

my $filename = $ARGV[0];

my @good;

#Open data file
#foreach my $infile (0..$#infiles){
$controlCount = 0;
$testCount = 0;
@controlRow = ();
@testRow = ();
%field = ();
$i=0;
#print "Opening $infiles[$infile]\n";
open (OUT_FH, ">$outfile" ) || die "Cannot open $outfile $!:";
open (DUP_FH, ">$duplicatesFile" ) || die "Cannot open $duplicatesFile $!:";
open (LOG_FH, ">$logfile" ) || die "Cannot open $logfile $!:";
open (ERR_FH, ">$errors" ) || die "Cannot open $errors $!:";

open (IN_FH, "<$filename" ) || die "Cannot open  $filename$!:";
while(<IN_FH>){
	if($row{$i}){
		my $line = substr($_,0,-1);
		if($line =~ m/$row{$i}/){
			$function{$i}->($line);
		}
		#else{ print ("Line $i is $line; Does not contain expected value $row{$i}\n");}
	}
	$i++;
}
close(IN_FH);
unlink($filename);
$filename = substr($filename, rindex($filename,"\/") + 1);

my ($id, $datetime) = $db->selectrow_array("select testID, testDateTime from $elisaSetUp where plateName = '$field{'plateName'}'
	and testDateTime = '$field{'testDateTime'}'");
if($id){
	#print "$filename has been uploaded before test ID = $id, datetime = $field{'testDateTime'}\n";
	push (@good,"$filename has been uploaded before: test ID = $id, datetime = $field{'testDateTime'}");
	print (DUP_FH "$filename has been uploaded before test ID = $id, Species = $field{'testType'}, datetime = $field{'testDateTime'}\\n");
}
elsif($#good  == -1){
	writeToDb();
	push (@good, "$filename was successfully uploaded");
}
my $message =join(", ", @good);	
print "$message\n";
close(OUT_FH);
close(DUP_FH);
close(LOG_FH);
close(ERR_FH);

###################################################
sub status{
	my $row = shift;
	($field{'testType'}, $field{'plateStatus'}) = split(/\s{5,30}/,$row);
	$field{'testType'} =~ s/\"//g;
	if($field{'testType'} =~ m/^(\w)[\.|,]\s*(\w+)\s(.+)/){
		my $sp = $2;
		$sp =~ tr/[A-Z]/[a-z]/;
		$field{'testType'} = $1 . "." . $sp . " " . $3;
	}
	else{
		#print "Species name not in format G.species. species not recognised. Upload aborted\n";
		push(@good, "Species name not in format G.species. species not recognised. Upload aborted");
	}
		
	if($field{'plateStatus'} =~ m/OUTSIDE/){
		$field{'plateStatus'} = "OUTSIDE_LIMITS";
	}
	elsif($field{'plateStatus'} =~ m/WITHIN/){
		$field{'plateStatus'} = "WITHIN_LIMITS";
	}
}
sub name{
	my $row = shift;
	($field{'plateName'}, $field{'testDateTime'}) = split(/\s{4,30}/,$row);
	$field{'plateName'} =~ s/Plate name: //;
	$field{'testDateTime'} =~ s/Test date: //;
	$field{'testDateTime'} = reformatDate($field{'testDateTime'});
}
sub created{
	my $row = shift;
	($field{'createBy'}, $field{'time'}) = split(/\s{5,30}/,$row);
	$field{'createBy'} =~ s/Created by: //;
	$field{'time'} =~ s/Test time: //;
	$field{'testDateTime'} .= " " . $field{'time'};
}
sub filter{
	my $row = shift;
	($field{'filter'}, $field{'technician'}) = split(/\s{5,30}/,$row);
	$field{'filter'} =~ s/\s*Filter: //;
	$field{'technician'} =~ s/Technician: //;
}
sub blanking{
	my $row = shift;
	($field{'blankingValue'}, $field{'kitBatch'}) = split(/\s{5,30}/,$row);
	$field{'blankingValue'} =~ s/\s*Blanking value: //;
	$field{'kitBatch'} =~ s/Kit Batch#: //;
}
sub controls{
	my $row = shift;
	if($row =~ m /\s*(CONTROLS:  Acceptable OD range C\+\+:)\s+(\d\.\d+\s+-\s+\d\.\d+)\s+\w+:\s(.{10})\s+$/){
		$field{'AcceptableODrange'} = $2;
		$field{'PPthreshold'} = $3;
	}
}
sub controlHeader{
	my $row = shift;
	$row =~ s/-/_/g;	
 	@controlHeader = split(/\s+/,$row);	
}
sub controlValue{
	my $row = shift;	
	@{$controlRow[$controlCount]} = split(/\s+/,$row);
	my $lastValue = pop(@{$controlRow[$controlCount]});
	$controlRow[$controlCount][$#{$controlRow[$controlCount]}] .= " $lastValue";
	$controlCount++;
}	
sub controlMean{
	my $row = shift;
	if($row =~ m /\s(\d\.\d\d\d)\s*/){
		$field{'meanControlOD'} = $1;
	}
}
sub testHeader{
	my $row = shift;	
 	@testHeader = split(/\s+/,$row);
	push(@testHeader, ("SSID", "AnimalID", "Project")); 
}
sub testValue{
	my $row = shift;	
	@{$testRow[$testCount]} = split(/\s+/,$row);
	$testRow[$testCount][2] =~ tr/[a-z]/[A-Z]/;
	(my ($SSID, $AnimalID, $Project), $Elisa[$testCount]) = $db->selectrow_array("select SSID, AnimalID, Project, Elisa_Results from $samples 
						where label = '$testRow[$testCount][2]'");	
	push(@{$testRow[$testCount]}, ($SSID, $AnimalID, $projects{$Project}));
	$testCount++;
}
sub ignore{
	my $row = shift;		
}
sub writeToDb{
	$field{'testID'} = $db->selectrow_array("select max(testID) from $elisaSetUp");
	if ($field{'testID'}){$field{'testID'}++;}
	else{$field{'testID'} = 1}
	# Write to elisaSetUp
	my $querystr = "insert into $elisaSetUp (" . join(", ", @elisaSetUp) . ") values ($field{'testID'}, ";
	foreach my $esu (1..$#elisaSetUp){
		$querystr .= "'" .$field{$elisaSetUp[$esu]}. "', ";
	}
	$querystr = substr($querystr, 0, -2);
	$querystr .= ")";
	$db->do($querystr);
	#write to elisaControls
	foreach my $cr (0..$#controlRow){		
		$querystr = "insert into $elisaControls (testID, " . join(", ", @controlHeader) . 
		") values ($field{'testID'}, '" . join("', '",@{$controlRow[$cr]}) . "')";
		#print "$querystr\n";
		$db->do($querystr);
	}
	#write to elisatest
	foreach $cr (0..$#testRow){		
		$querystr = "insert into $elisaTest (testID, " . join(", ", @testHeader) . 
			") values ($field{'testID'}, '". join("', '",@{$testRow[$cr]}) . "')";
		#print "$querystr\n";
		$db->do($querystr);
		#INsert hyperlink into samples table
		my $href;
		my $testType = substr($field{'testType'}, 0, index($field{'testType'},"(")-1);
		if($Elisa[$cr]){
			$href = $Elisa[$cr] . "<br>$field{'testDateTime'}: <a href=\"http://localhost/results?page=results&project=ideal&test=$field{'testID'}\"> $testType</a>; " .
			"<a href=\"http://localhost/results?page=results&project=ideal&sample=$testRow[$cr][2]\">$results{$testRow[$cr][3]}  </a>ODav: $testRow[$cr][6]; PPav: $testRow[$cr][10]";
		}
		else{
			$href = $Elisa[$cr] . "$field{'testDateTime'}: <a href=\"http://localhost/results?page=results&project=ideal&test=$field{'testID'}\">  $testType</a>; " .
					      "<a href=\"http://localhost/results?page=results&project=ideal&sample=$testRow[$cr][2]\">$results{$testRow[$cr][3]} </a>ODav: $testRow[$cr][6]; PPav: $testRow[$cr][10]";
		}	
		$db->do("update $samples set Elisa_Results = '$href' where SSID = '$testRow[$cr][11]'");
	}

}
	
#####################################################################
sub reformatDate{
	#Convert day-month-year to Mysql year-month-day
	my $date = shift;
	my ($day, $month, $year) = split(/\//,$date);
	#if($day < 10){
	#	$day = 0 . $day;
	#}
	$year = 20 . $year;
	$date = $year . "-" . $month . "-" . $day;
	unless($date =~ m/^\d{4}-\d{2}-\d{2}$/){
		print (ERR_FH "Wrong date fomat on line $i; $date\n");
		return "0000-00-00";
	}
	return $date;
}
#####################################################################
#subroutine to run queries
sub run_query{
	my $querystr =shift;
	my $db = shift;
 
	# Prepare the query
	my  $query = $db->prepare($querystr);
	# Run the query, checking that it ran correctly
	unless($query->execute()) {
		my $error = "Error in executing database query $DBI::errstr";
	}
	return $query;
}
#####################################################################

