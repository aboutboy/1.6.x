<?php
if(isset($_GET["verbose"])){ini_set('display_errors', 1);	ini_set('html_errors',0);ini_set('display_errors', 1);ini_set('error_reporting', E_ALL);}
$GLOBALS["KAV4PROXY_NOSESSION"]=true;
$GLOBALS["FORCE"]=false;
$GLOBALS["RELOAD"]=false;
$GLOBALS["TITLENAME"]="URLfilterDB daemon";
$_GET["LOGFILE"]="/var/log/artica-postfix/dansguardian.compile.log";
if(posix_getuid()<>0){
	if(isset($_GET["SquidGuardWebAllowUnblockSinglePass"])){parseTemplate_SinglePassWord();die();}
	
	if(isset($_POST["USERNAME"])){parseTemplate_LocalDB_receive();die();}
	if(isset($_POST["password"])){parseTemplate_SinglePassWord_receive();die();}
	if(isset($_GET["parseTemplate-SinglePassWord-popup"])){parseTemplate_SinglePassWord_popup();die();}
	if(isset($_GET["SquidGuardWebUseLocalDatabase"])){parseTemplate_LocalDB();die();}
	parseTemplate();die();}

if(preg_match("#--schedule-id=([0-9]+)#",implode(" ",$argv),$re)){$GLOBALS["SCHEDULE_ID"]=$re[1];}
$GLOBALS["GETPARAMS"]=@implode(" Params:",$argv);


include_once(dirname(__FILE__)."/ressources/class.user.inc");
include_once(dirname(__FILE__)."/ressources/class.groups.inc");
include_once(dirname(__FILE__)."/ressources/class.ldap.inc");
include_once(dirname(__FILE__)."/ressources/class.system.network.inc");
include_once(dirname(__FILE__)."/ressources/class.dansguardian.inc");
include_once(dirname(__FILE__)."/ressources/class.squid.inc");
include_once(dirname(__FILE__)."/ressources/class.squidguard.inc");
include_once(dirname(__FILE__)."/ressources/class.mysql.inc");
include_once(dirname(__FILE__)."/ressources/class.compile.ufdbguard.inc");
include_once(dirname(__FILE__)."/ressources/class.compile.dansguardian.inc");
include_once(dirname(__FILE__).'/framework/class.unix.inc');
include_once(dirname(__FILE__)."/framework/frame.class.inc");
include_once(dirname(__FILE__).'/ressources/class.ufdbguard-tools.inc');
include_once(dirname(__FILE__)."/ressources/class.os.system.inc");


$file=$argv[1];
if(!is_file($file)){
	echo "$file, no such file\n";
	die();
}

$sock=new sockets();
$uuid=base64_decode($sock->getFrameWork("cmd.php?system-unique-id=yes"));
$handle = @fopen($file, "r");
if (!$handle) {echo "Failed to open file\n";return;}
$q=new mysql_squid_builder();
$category="phishing";
$countstart=$q->COUNT_ROWS("categoryuris_phishing");

$prefix="INSERT IGNORE INTO categoryuris_phishing (zmd5,zDate,pattern,enabled) VALUES ";
echo "$prefix\n";
$c=0;
$CBAD=0;
$CBADIP=0;
$CBADNULL=0;
while (!feof($handle)){
$c++;
	$www =trim(fgets($handle, 4096));
	if($www==null){$CBADNULL++;continue;}
	
			$www=str_replace('"\"','',$www);
			$www=str_replace('"','',$www);
			

			$md5=md5($www);
			$www=mysql_escape_string2($www);
			$n[]="('$md5',NOW(),'$www','1')";


			if(count($n)>6000){
			$sql=$prefix.@implode(",",$n);
			$q->QUERY_SQL($sql,"artica_backup");
			if(!$q->ok){echo $q->mysql_error."\n$sql\n";$n=array();die();}
			$countend=$q->COUNT_ROWS("categoryuris_phishing");
			$final=$countend-$countstart;
			echo "".numberFormat($c,0,""," ")." items, ".numberFormat($final,0,""," ")." new entries added - $CBADNULL bad entries for null value,$CBADIP entries for IP addresses\n";
			$n=array();
				
			}

			}

			fclose($handle);