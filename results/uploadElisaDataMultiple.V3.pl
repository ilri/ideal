#!/usr/bin/perl -w
use warnings;
#use strict;
use DBI;


#Open database connection
my $db = connect_azizi();

#my @infiles = ("Plate1.txt", "Plate2.txt", "Plate3.txt", "Plate4.txt", "Plate5.txt", "Plate6.txt", "Plate7.txt", "Plate8.txt");
my $elisaSetUp = "elisaSetUp";
my $elisaControls = "elisaControls";
my $elisaTest = "elisaTest";
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
open (OUT_FH, ">$outfile" ) || die "Cannot open $outfile $!:";
open (DUP_FH, ">$duplicatesFile" ) || die "Cannot open $duplicatesFile $!:";
open (LOG_FH, ">$logfile" ) || die "Cannot open $logfile $!:";
open (ERR_FH, ">$errors" ) || die "Cannot open $errors $!:";

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
			#This condition causes a tray with less than 40 samples not to be uploaded.
			#push (@good, "Line $j is $line<br> Does not contain expected value at line $i $row{$i}");
			#If a plate has less than 40 samples, then the next row should have a format of begining a new tray
			$curPos = tell(IN_FH);	 #get the pointer to the current row
			if($curPos == -1){
				#Cannot determine the current position of the file for the purpose of later rewinding
				push(@good, "Error 3.1 has occurred. Contact the system administrator.");
			}
			$nxt_line = <IN_FH>;
			if(($nxt_line =~ m/Plate status:/ || !$nxt_line) && $i> 10){
				#push (@good, "Totally we are having the next plate!! $nxt_line ");
				writeOutput();
				if(!seek(IN_FH, $curPos, 0)){  #Rewind the file to the position just before this read
					#Cannot rewind the file so as to get the plate meta-data
					push(@good, "Error 3.2 has occurred. Contact the system administrator.");
				}
				else{
					$i--;	#Rewind also the pointer
				}
			}
			else{
				#we are not having a next line and neither are we having a good variable
				push (@good, "Line $j is $line<br> Does not contain expected value at line $i $row{$i}");
			}
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
	#trim the white spaces
	$field{'testType'} =~ s/^\s+//;
	$field{'testType'} =~ s/\s+$//;
	if($field{'testType'} =~ m/^(\w)[\.|,]\s*(\w+)\s(.+)/){
		my $sp = $2;
		$sp =~ tr/[A-Z]/[a-z]/;
		$field{'testType'} = $1 . "." . $sp . " " . $3;
	}
	else{
		#print "Species name not in format G.species. species not recognised. Upload aborted\n";
		push(@good, "Test type not in format G.species. Test type not recognised. Plate not uploaded.");
	}
		
	if($field{'plateStatus'} =~ m/OUTSIDE/){
		$field{'plateStatus'} = "OUTSIDE_LIMITS";
	}
	elsif($field{'plateStatus'} =~ m/WITHIN/){
		$field{'plateStatus'} = "WITHIN_LIMITS";
	}
	else{
		push(@good, "Unrecognised plate status. Plate not uploaded.");
	}
}
sub name{
	my $row = shift;
	($field{'plateName'}, $field{'testDateTime'}) = split(/\s{4,30}/,$row);
	$field{'plateName'} =~ s/Plate name: //;
	$field{'plateName'} =~ s/\s//;
	unless($field{'plateName'} =~ m/^IDEAL-\d+\.\d+$/){
		push (@good, "Plate name $field{'plateName'} not in format 'IDEAL-N.N' where N are numbers. Plate not uploaded");
	}
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
	push(@testHeader, ("SSID", "AnimalID", "Project", "sampleId")); 
}
sub testValue{
	my $row = shift;
	#remove any leading spaces from line
	$row =~ s/^\s+//;	
	@{$testRow[$testCount]} = split(/\s+/,$row);
	$testRow[$testCount][2] =~ tr/[a-z]/[A-Z]/;
	#Added the Count variable, ie select the count variable from the samples to be stored in the elisaTest table
	(my ($SSID, $AnimalID, $Project), $Elisa[$testCount], $Count) = $db->selectrow_array("select SSID, AnimalID, Project, Elisa_Results, count from samples where StoreLabel = '$testRow[$testCount][2]'");
	#print("$SSID, $AnimalID, $Project; $projects{$Project};  $testRow[$testCount][2];\n");
	#print("select SSID, AnimalID, Project, Elisa_Results from $samples where StoreLabel = '$testRow[$testCount][2]'\n");
	$projects{$Project} =~ s/A-Z/a-z/g;
	push(@{$testRow[$testCount]}, ($SSID, $AnimalID, $projects{$Project}, $Count));
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
			$href =  $Elisa[$cr] . "<br>$field{'testDateTime'}: <a href=\"/avid/results?page=results&project=$testRow[$cr][13]&test=$field{'testID'}\"> $testType</a>; "
			. "<a href=\"/avid/results?page=results&project=$testRow[$cr][13]&sample=$testRow[$cr][2]\">$results{$testRow[$cr][3]}  </a>ODav: $testRow[$cr][6]; PPav: $testRow[$cr][10]";
		}
		else{
			$href = $Elisa[$cr] . "$field{'testDateTime'}: <a href=\"/avid/results?page=results&project=$testRow[$cr][13]&test=$field{'testID'}\"> $testType</a>; " .
					      "<a href=\"/avid/results?page=results&project=$testRow[$cr][13]&sample=$testRow[$cr][2]\">$results{$testRow[$cr][3]} </a>ODav: $testRow[$cr][6]; PPav: $testRow[$cr][10]";
		}	
		$db->do("update $samples set Elisa_Results = '$href' where SSID = '$testRow[$cr][11]'");
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
		############ KLUDGE TO ENSURE THAT THE PLATE HAS A PLATE NAME ###############################
		if(!$field{'testType'}){
			push(@good, "Plate $field{'plateName'} test type cannot be recognized. Ensure that the plate's metadata is correctly formatted and try to upload again. The plate was not uploaded.");
		}
		############ END OF KLUDGE ############################
		my ($id, $datetime) = $db->selectrow_array("select testID, testDateTime from $elisaSetUp where testDateTime = '$field{'testDateTime'}'");
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

#####################################################################
   #                               COPIED BY KIHARA ABSOLOMON FROM THE FILE dbModules
   #####################################################################
   sub connect_azizi{
           #Connects to azizi database;
           ######################MODIFICATIONS BY KIHARA#################
           #This script was reading a file to get the file with the dbase parameters. I have modified it to just read a file and get the pa    rameters.
           
           my $dbParams = getConfig();
 
           #print $dbParams->{'config_file'};
           my $db = connect_db('/etc/php5/include/add_samples_config');
 
 
           return $db;
 
 
   }
   #####################################################################################
 
 
   sub connect_db{
              # Import database module
              my $configFile = shift;
              open (CONFIG_FH,  $configFile) || die "Cannot open $configFile $!:";
 
              my @parameters;
              while(<CONFIG_FH>){
                         my $line = $_;
                         unless ($line =~ m/=/){next}
                         my ($key, $value) = split(/=/, $line);
                         $value =~ s/\'//g;
                         $value =~ s/\s//g;
                         $value = substr($value, 0, index($value,";"));
                          push (@parameters, $value);
              }
              close(CONFIG_FH);
              # Declare and initialise database variables
              my $host = "$parameters[0]";
              my $user = "$parameters[1]";
              my $pass = "$parameters[2]";
              my $name = "$parameters[3]";
 
              # Connect to the database
              # If RaiseError is set, any error message from accessing the database is stored in the variable $DBI::errstr
              # If AutoCommit is not set, this allows us to rollback transactions, but we have to explicitly commit our changes to the data    base (this also requires a database with tables that support transactions - SNPLAD supports these)
 
              my $dsn = "DBI:mysql:database=$name;host=$host";
              my $database = DBI->connect($dsn, $user, $pass,{RaiseError => 1, AutoCommit => 1}) || die("Error in connecting to database $D    BI::errstr\n");
              return $database;
   }
 
 

  #####################################################################
  sub getConfig{
          my %barcodeParams;
          my $barcodeConfig = "/etc/php5/include/add_samples_config";
          open (CONFIG_FH, $barcodeConfig ) || die "Cannot open $barcodeConfig $!:";
          while(<CONFIG_FH>){
                  if(m/^#/){next}
                  s/\s//g;
                  my ($key, $value) = split(/=/, $_);
                  if($key && $value){
                          $barcodeParams{$key} = $value;
                          #print "$key -- $value\n";
                  }
          }
          close(CONFIG_FH);
          return(\%barcodeParams);
  }

