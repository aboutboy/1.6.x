<?php
if(posix_getuid()<>0){die("Cannot be used in web server mode\n\n");}
include_once(dirname(__FILE__)."/ressources/class.user.inc");
include_once(dirname(__FILE__)."/ressources/class.mysql.squid.builder.php");
include_once(dirname(__FILE__)."/ressources/class.mysql.dump.inc");
include_once(dirname(__FILE__)."/ressources/class.mysql.inc");
include_once(dirname(__FILE__)."/framework/class.unix.inc");
include_once(dirname(__FILE__)."/framework/frame.class.inc");
cpulimit();
$_GET["LOGFILE"]="/var/log/artica-postfix/dansguardian-logger.debug";
if(preg_match("#schedule-id=([0-9]+)#",implode(" ",$argv),$re)){$GLOBALS["SCHEDULE_ID"]=$re[1];}
if(preg_match("#--verbose#",implode(" ",$argv))){$GLOBALS["debug"]=true;$GLOBALS["VERBOSE"]=true;}
if($GLOBALS["VERBOSE"]){ini_set('display_errors', 1);	ini_set('html_errors',0);ini_set('display_errors', 1);ini_set('error_reporting', E_ALL);}
if(posix_getuid()<>0){die("Cannot be used in web server mode\n\n");}

ScanMainDirs();
function ScanMainDirs(){
	$unix=new unix();
	$clamscan=$unix->find_program("clamscan");
	if(!is_file($clamscan)){
		system_admin_mysql(1, "Unable to find ClamScan for periodic scan!", null,__FILE__,__LINE__);
		die();
		
	}
	
	$php=$unix->LOCATE_PHP5_BIN();
	echo "Running updates...\n";
	system("$php /usr/share/artica-postfix/exec.freshclam.php --exec --force");
	
	
	$dirs[]="/bin";
	$dirs[]="/sbin";
	$dirs[]="/lib";
	$dirs[]="/usr/sbin";
	$dirs[]="/usr/bin";
	$dirs[]="/usr/local/bin";
	$dirs[]="/usr/local/sbin";
	$dirs[]="/lib/x86_64-linux-gnu";
	$dirs[]="/usr/local/lib";
	$dirs[]="/etc/init.d";
	$dirs[]="/etc/rc0.d";
	$dirs[]="/etc/rc1.d";
	$dirs[]="/etc/rc2.d";
	$dirs[]="/etc/rc3.d";
	$dirs[]="/etc/rc4.d";
	$dirs[]="/etc/rc5.d";
	$dirs[]="/lib";
	$dirs[]="/lib32";
	$dirs[]="/lib64";
	$dirs[]="/tmp";
	
	$WARNS=array();
	while (list ($num, $directory) = each ($dirs) ){
		if(!is_dir($directory)){continue;}
		$results=array();
		echo "Scanning $directory\n";
		exec("$clamscan --infected --suppress-ok-results --stdout $directory 2>&1",$results);
		
		while (list ($a, $lin) = each ($results) ){
			$lin=trim($lin);
			if($lin==null){continue;}
			if(!preg_match("#(.+?):\s+(.+?)\s+FOUND#", $lin,$re)){continue;}
			$filepath=$re[1];	
			$next_file=str_replace("/", "-", $filepath);
			
			@mkdir("/home/artica/.infected",0755,true);
			$nextfile="/home/artica/.infected/$next_file";
			if(is_file($nextfile)){$nextfile="$nextfile.".time();}
			
			@copy($filepath, $nextfile);
			@unlink($filepath);
			$lineErr="Found Virus {$re[2]} in $filepath ( moved to $nextfile)";
			echo "$lineErr\n";
			$WARNS[]=$lineErr;
			
		}
		
		
	}
	
	if(count($WARNS)>0){
		system_admin_mysql(0, "Warning found ".count($WARNS)." malwares on system", @implode("\n", $WARNS),__FILE__,__LINE__);
		squid_admin_mysql(0,  "Warning found ".count($WARNS)." malwares on system", @implode("\n", $WARNS),__FILE__,__LINE__);
	}
	
	
}






