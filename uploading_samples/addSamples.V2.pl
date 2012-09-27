#!/usr/bin/perl -w
use warnings;
#use strict;
use DBI;
use CGI qw/:standard/;
use CGI::Carp qw(fatalsToBrowser);

#use dbModules;
#PopulateSampleTypes

#Open database connection
my $db = connect_azizi();

my $infile = "errors/IdealAllSamples.txt";
my $outfile = "errors/homelessSamples.txt";
my $duplicatesFile = "errors/DuplicateSamples.txt";
my $logfile = "errors/aziziUploadLog.txt";
my $errors = "errors/MySQLerrors.txt";
my $positionLog = "errors/positionLog.txt";

#Get ids and descriptions of samples types 
my (%sampleTypes, %sampleDesc, %trays, %organisms, %keepers, %projects, @boxMap);
my $previousTray = "XX";
my $querystr = "select sample_type_name, count, description from sample_types_def";
my $query = run_query($querystr, $db);
while(my ($Stype, $count, $description) = $query->fetchrow_array()){
	$sampleTypes{$Stype} = $count;
	$sampleDesc{substr($Stype,0,3)} = $description;
}

#Get ids of trays
$querystr = "select box_name, box_id, keeper from boxes_def";
$query = run_query($querystr, $db);
while(my ($tray, $id, $keeper) = $query->fetchrow_array()){
	$trays{$tray} = $id;
	$keepers{$tray} = $keeper;
}

#Get ids of organsisms
$querystr = "select org_name, org_id from organisms";
$query = run_query($querystr, $db);
while(my ($org, $id) = $query->fetchrow_array()){
	$organisms{$org} = $id;
}

#Get ids of Projects
$querystr = "select val_id, value from modules_custom_values";
$query = run_query($querystr, $db);
while(my ($val_id, $value) = $query->fetchrow_array()){
	$projects{$value} = $val_id;
}


#Open data file

open (IN_FH, "<$infile" ) || die "Cannot open $infile $!:";
open (OUT_FH, ">$outfile" ) || die "Cannot open $outfile $!:";
open (DUP_FH, ">$duplicatesFile" ) || die "Cannot open $duplicatesFile $!:";
open (LOG_FH, ">$logfile" ) || die "Cannot open $logfile $!:";
open (ERR_FH, ">$errors" ) || die "Cannot open $errors $!:";
open (POS_FH, ">$positionLog" ) || die "Cannot open $positionLog $!:";

#$db->do("SET autocommit=0");
#$db->do("START TRANSACTION");
my $i = 0;
while(<IN_FH>){
	$i++;
	#if ($i > 25){last}
	if ($i % 1000 == 0){print "Entered line $i\n"}
	$line = substr($_,0,-2);
#	html_message("Read line $i: $line\n");
	$line =~ s/'//g;
	if($i == 1){
		$head = getColumnHeaders($line);
		%headers = %{$head};
		print(OUT_FH "$line\n");
		print(DUP_FH "Dup. SSID count\t$line\n");
		next;
	}
	my @data = split(/\t/, $line);
	
	#if tray not already set up in database then write sample to outfile for upload later
	unless($trays{$data[$headers{'TrayNumber'}]}){
		print(OUT_FH "$line\n");
		next;
	}
	#If box is not the same as last one get a the array that converts Busia 1A:10J positions to ILRI A1:J10 positions 
	if($trays{$data[$headers{'TrayNumber'}]} ne $previousTray){
		my $boxMap = boxMap($trays{$data[$headers{'TrayNumber'}]});
		@boxMap = @{$boxMap};	
		$previousTray = $trays{$data[$headers{'TrayNumber'}]};
	}
	print(POS_FH "Box $trays{$data[$headers{'TrayNumber'}]}; BoxMap  $boxMap[$data[$headers{'SlotNumber'}]]; File $data[$headers{'SlotNumber'}];\n");
	#If sample already in database skip and write to file
	my $count = $db-> selectrow_array("select count from samples where SSID = '$data[$headers{'SSID'}]'");
	if($count){
		print(DUP_FH "Duplicate SSID= $data[$headers{'SSID'}]: $count^t$line\n");
		#print("Duplicate SSID= $data[$headers{'SSID'}]: at line $i<br>");
		next;
	}
		
	#LabCollector uses 'A1' fomat IDEAL uses 'A01' format;
#		print "\nSlot Number: ".$data[$headers{'SlotNumber'}]."\n\n";
		if(substr($data[$headers{'SlotName'}],1,1) == 0){
			$data[$headers{'SlotName'}] = substr($data[$headers{'SlotName'}],0,1) . substr($data[$headers{'SlotName'}],2);
		}
		#The last character of sample type sometimes refers to an aliquot;
		my $sampleType = substr($data[$headers{'Sampletype'}],0,3);
		#kihara's additions
		#$db->do("set autocommit=0"); $db->do("start transaction");
		#end of kihara's additions
		my $queryStr = "insert ignore into samples 
		(label,comments, date_created, sample_type, org, main_operator, 
		box_id, volume_unit, box_details, Project, SSID, StoreLabel, SampleID, VisitID,
		Visitdate, AnimalID, Visit, ShippedDate, TrayID)
		values ('$data[$headers{'StoreLabel'}]', '$sampleDesc{$sampleType}',  NOW(), $sampleTypes{$data[$headers{'Sampletype'}]}, $organisms{'Cow'},
		$keepers{$data[$headers{'TrayNumber'}]}, $trays{$data[$headers{'TrayNumber'}]},$data[$headers{'SlotNumber'}],'$boxMap[$data[$headers{'SlotNumber'}]]',
		'$projects{'IDEAL'}','$data[$headers{'SSID'}]','$data[$headers{'StoreLabel'}]','$data[$headers{'SampleID'}]'
		,'$data[$headers{'VSID'}]','$data[$headers{'Visit_date'}]','$data[$headers{'animid'}]','$data[$headers{'Visit'}]',
		'$data[$headers{'ShippedDate'}]','$data[$headers{'TrayID'}]')";
		print(LOG_FH "$queryStr\n");
		my $dbMessage = $db->do($queryStr);
		unless($dbMessage ==1){
			print (DUP_FH "Duplicate SSID $data[$headers{'SSID'}] at line $i; $dbMessage\n");
			#print ( "Duplicate SSID $data[$headers{'SSID'}] at line $i; $dbMessage<br>");
		}
	}
	$db->disconnect();
	close(IN_FH);
	close(OUT_FH);
	close(DUP_FH);
	close(LOG_FH);
	close(ERR_FH);
	close(POS_FH);
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
	sub getColumnHeaders{
		#Get column headers into a hash with keys headers and values column numbers
		#Assume windows format with ctrl lf line endings
		my $line = shift;
		my @headers = split(/\t/, $line);
		my %headers;
		foreach my $h (0..$#headers){
			$headers[$h] =~ s/\s//g;
			$headers{$headers[$h]} = $h;
		}
		my $res = checkHeaders(@headers);
		if($res ne 0){
			die("The header $res is missing. Regenerate the file and try again.");
		}
		return \%headers;
	}
	#####################################################################

	#####################################################################
	#				COPIED BY KIHARA ABSOLOMON FROM THE FILE dbModules
	#####################################################################
	sub connect_azizi{
		#Connects to azizi database;
		######################MODIFICATIONS BY KIHARA#################
		#This script was reading a file to get the file with the dbase parameters. I have modified it to just read a file and get the parameters.
		
		my $dbParams = getConfig();

		#print $dbParams->{'config_file'};
		my $db = connect_db('/etc/php5/include/add_samples_config');


		return $db;


	}
	#####################################################################


	#####################################################################################


	sub connect_db{
	# Import database module
	use DBI;
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
	# If AutoCommit is not set, this allows us to rollback transactions, but we have to explicitly commit our changes to the database (this also requires a database with tables that support transactions - SNPLAD supports these)

	my $dsn = "DBI:mysql:database=$name;host=$host";
	my $database = DBI->connect     ($dsn,
											  $user,
											  $pass,
											  {
											  RaiseError => 1,
											  AutoCommit => 1
											  }
									) || die ("Error in connecting to database $DBI::errstr\n");

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

	#####################################################
	sub html_message{
		my $q = new CGI;
		my $message = shift;	#create graph
		print $q->header(-type =>"text/html"),
		$q->start_html(-title=>'Elisa Test Upload');#
		print h2("$message",hr);
		print $q->end_html;
	}
	######################################################
	# Create map of alphanumeric box positions to numeric box positions
	sub boxMap{
		my $boxId = shift;	
	#print "\nThe box position is $boxId\n";
		my $size = $db-> selectrow_array("select size from boxes_def where box_id = $boxId");
		my ($discard, $boxSize) = split(/\./,$size);
		my ($letter, $number) = split(/:/,$boxSize);
		my (@letters, @numbers);
		my @boxMap;
		@letters = (A..$letter);
		@numbers = (1..$number);
		$number = 1;
		foreach my $l (0..$#letters){
			foreach my $n (0..$#numbers){
				$boxMap[$number] = '';	#Added by Kihara to avoid the uninitialized string warnings
				print(POS_FH "($boxSize, $letter, $number) $boxMap[$number] = $letters[$l] . $numbers[$n];\n");
				$boxMap[$number] = $letters[$l] . $numbers[$n];
				$number++;
			}
		}
		return \@boxMap;
	}
	#####################################################################################

	##
	# @author Kihara <a.kihara@cgiar.org>
	# Confirm that we have all the right headers, hence meaning the right data
   ##
sub checkHeaders{
	my @headers =  @_;	#get the passed headers
	my @headerCheckList = ('TrayNumber','ShippedDate','SlotNumber','SlotName','StoreLabel','SSID','SampleID','VSID','Visit_date','animid','Visit');
	#iterate and check that all headers are in the headers checklist
	foreach my $header(@headerCheckList){
#		$res = false;
#		print $_;
#      $res = grep {$header eq $_} @$headerCheckList;
#		print "yahoo\n" if($res eq false);
#		print " checking: $header\n";
		#if(grep @headers ne $header, @headerCheckList){
		if(!(grep /$header/, @headers)){
#				print "$header is apparently not in @headerCheckList";
				return $header; 	#the specified header is not in the column headers
		}
	}
	return 0;
}
#####################################################################################

#####################################################################
#				END OF COPYING BY KIHARA
#####################################################################
