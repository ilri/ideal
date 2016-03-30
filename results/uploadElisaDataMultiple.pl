#!/usr/bin/perl -w
use warnings;
#use strict;
use DBI;
use dbModules;


#Open database connection
my $db = dbModules::connect_azizi();

#my @infiles = ("Plate1.txt", "Plate2.txt", "Plate3.txt", "Plate4.txt", "Plate5.txt", "Plate6.txt", "Plate7.txt", "Plate8.txt");
my $elisaSetUp = "elisaSetUpTest";
my $elisaControls = "elisaControlsTest";
my $elisaTest = "elisaTestTest";
my $samples = "samples";
my $outfile = "uploadedFiles/homelessSamples.txt";
my $duplicatesFile = "uploadedFiles/DuplicateSamples.txt";
my $logfile = "uploadedFiles/elisaUploadLog.txt";
my $errors = "uploadedFiles/MySQLerrors.txt";
my %results = ("POS" => "POS", "N" => "NEG", "retest" => "RETEST");
my %field;
my $linesUploaded = 0;
my $Project = "IDEAL";
my @testNames = ("B.bigemina", "T.mutans", "A.marginale", "T.parva");
my $i = 0;
my $j = 0;
my ($controlCount, $testCount);
my (@controlRow, @testRow, @testheaders, @controlHeaders);
my @elisaSetUp = ("testID", "testType", "plateStatus", "plateName", "testDateTime", "createBy", "filter", 
		"technician", "blankingValue", "kitBatch", "AcceptableODrange", "PPthreshold", 'meanControlOD');

#die;
#$db->do("delete from $elisaSetUp");
#$db->do("delete from $elisaControls");
#$db->do("delete from $elisaTest");

my %rows;
$row{1} = "Plate status:";
$row{2} = "Plate name:";
$row{3} = "Created by:";
$row{4} = "Filter:";
$row{5} = "Blanking value:";
$row{7} = "CONTROLS:";
$row{8} = "control limits:";
$row{10} = 'ID\s+STATUS';
$row{11} = '^C\+\+\s\w';
$row{12} = '^C\+\s+\w';
$row{13} = '^C-\s+\w';
$row{14} = '^Cc\s+\w';
$row{16} = "average of the two intermediate";
$row{18} = "TEST SAMPLES:";
$row{20} = 'ID\sWELLS';
my %function = (1 => 'status', 2 => 'name', 3 => 'created', 4 => 'filter', 5=> 'blanking',
		7 => 'controls', 8=> 'ignore', 10=>'controlHeader', 11 => 'controlValue',
		12 => 'controlValue',13 => 'controlValue',14 => 'controlValue',
		16 => 'controlMean', 18 => 'ignore', 20 => 'testHeader'); 
foreach my $r (21..60){
	$row{$r} = '\d\s\w\d+\/\w\d+\s+\w+';
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
my $message = "Results:";
#Open data file
#foreach my $infile (0..$#infiles){
$controlCount = 0;
$testCount = 0;
@controlRow = ();
@testRow = ();
%field = ();
$i=0;
#print "Opening $infiles[$infile]\n";
#open (OUT_FH, ">$outfile" ) || die "Cannot open $outfile $!:";
#open (DUP_FH, ">$duplicatesFile" ) || die "Cannot open $duplicatesFile $!:";
#open (LOG_FH, ">$logfile" ) || die "Cannot open $logfile $!:";
#open (ERR_FH, ">$errors" ) || die "Cannot open $errors $!:";

open (IN_FH, "<$filename" ) || die "Cannot open  $filename$!:";
while(<IN_FH>){
	$j++;
	if(m/Plate status:/ && $i> 10){
			writeOutput();
	}
	if($row{$i}){
		my $line = substr($_,0,-1);
		$line =~ s/\s*$//;
		#print ("$i; $line\n  $row{$i}\n");
		if($line =~ m/$row{$i}/){
			$function{$i}->($line);
			$linesUploaded++;
		}
		else{
			push (@good, "Line $j is $line<br> Does not contain expected value $row{$i}");
		}
	}

	$i++;
	
}
if($i> 10){
	writeOutput();
}

close(IN_FH);
#unlink($filename);
$filename = substr($filename, rindex($filename,"\/") + 1);
if($linesUploaded == 0){
	$message .= "<br>There was a probem with your file no data was uploaded";
}
	
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
	if($row =~ m /\s*(CONTROLS:  Acceptable OD range C\+\+:)\s+(\d\.\d+\s+-\s+\d\.\d+)\s+\w+:\s(.{10})\s*$/){
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
	#remove any leading spaces from line
	$row =~ s/^\s+//;	
	@{$testRow[$testCount]} = split(/\s+/,$row);
	$testRow[$testCount][2] =~ tr/[a-z]/[A-Z]/;
	(my ($SSID, $AnimalID, $Project), $Elisa[$testCount]) = $db->selectrow_array("select SSID, AnimalID, Project, Elisa_Results from $samples where StoreLabel = '$testRow[$testCount][2]'");
	#print("$SSID, $AnimalID, $Project; $projects{$Project}; $Elisa[$testCount]; $testRow[$testCount][2];\n");
	#print("select SSID, AnimalID, Project, Elisa_Results from $samples where StoreLabel = '$testRow[$testCount][2]'\n");
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
	#print "$querystr\n";
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
					      "<a href=\"http://azizi.ilri.cgiar.org/avid/results?page=results&project=ideal&sample=$testRow[$cr][2]\">$results{$testRow[$cr][3]} </a>ODav: $testRow[$cr][6]; PPav: $testRow[$cr][10]";
		}	
		#$db->do("update $samples set Elisa_Results = '$href' where SSID = '$testRow[$cr][11]'");
	}

}
	
#####################################################################
sub reformatDate{
	#Convert day-month-year to Mysql year-month-day
	my $date = shift;
	my ($month, $day, $year) = split(/\//,$date);
	
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
sub writeOutput{
		my ($id, $datetime) = $db->selectrow_array("select testID, testDateTime from $elisaSetUp where plateName = '$field{'plateName'}'
			and testDateTime = '$field{'testDateTime'}'");
			#print "select testID, testDateTime from $elisaSetUp where plateName = '$field{'plateName'}' and testDateTime = '$field{'testDateTime'}'\n";
		if($id){
			#print "$filename has been uploaded before test ID = $id, datetime = $field{'testDateTime'}\n";
			push (@good,"Plate $field{'plateName'} has been uploaded before: test ID = $id, datetime = $field{'testDateTime'}");
			print (DUP_FH "$field{'plateName'} has been uploaded before test ID = $id, Species = $field{'testType'}, datetime = $field{'testDateTime'}\\n");
		}
		elsif($#good  == -1){
			writeToDb();
			push (@good, "$field{'plateName'} was successfully uploaded");
			#print "$field{'plateName'} was successfully uploaded\n";
			
		}
		else{
			#print join("\n", @good) . "\n";
		}
		$message .= "<br>" . join("<br>", @good);
		$controlCount = 0;
		$testCount = 0;
		@controlRow = ();
		@testRow = ();
		%field = ();
		@good = ();
		$i = 1;
		return 1;
}
