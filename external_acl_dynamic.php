#!/usr/bin/php -q
<?php
$GLOBALS["HERLPER_LOADED_BY_SQUID"]=true;
$GLOBALS["LOG_FILE_NAME"]="/var/log/squid/external-acl-dynamic.log";
//ini_set('display_errors', 1);	ini_set('html_errors',0);ini_set('display_errors', 1);ini_set('error_reporting', E_ALL);
  error_reporting(0);
 
  $GLOBALS["PID"]=getmypid();
  $GLOBALS["STARTIME"]=time();
  $GLOBALS["uriToHost"]=array();
  $GLOBALS["ITCHART"]=false;
  $GLOBALS["SESSION_TIME"]=array();
  $GLOBALS["VERBOSE"]=false;
  $GLOBALS["XVFERTSZ"]=XVFERTSZ();
  $GLOBALS["CATZ-EXTRN"]=0;
  $GLOBALS["DEBUG_LEVEL"]=4;
  $GLOBALS["UNBLOCK"]=false;

  if(preg_match("#--itchart#", @implode("", $argv))){
  	WLOG("Starting ACLs dynamic with itchart feature...");
  	include_once(dirname(__FILE__)."/ressources/class.mysql.squid.builder.php");
  	$GLOBALS["ITCHART"]=true;
  }
  if(preg_match("#--ufdbunlock#", @implode("", $argv))){
  	WLOG("Starting ACLs dynamic with Dynamic Unblock feature...");
  	include_once(dirname(__FILE__)."/ressources/class.mysql.squid.builder.php");
  	include_once(dirname(__FILE__)."/ressources/class.tcpip.inc");
  	$GLOBALS["UNBLOCK"]=true;
  }  
  
  
  if($argv[1]=="--test-itchart"){
  	include_once(dirname(__FILE__)."/ressources/class.mysql.squid.builder.php");
  	ini_set('display_errors', 1);	ini_set('html_errors',0);ini_set('display_errors', 1);ini_set('error_reporting', E_ALL);
  	echo CheckITChart($argv[2],$argv[3],$argv[4]);
  	die();
  }
  
  if(preg_match("#--tests-categories\s+([0-9]+)#", @implode(" ", $argv),$re)){
  	WLOG("Starting ACLs dynamic with Categories features Group {$re[1]}...");
  	ini_set('display_errors', 1);	ini_set('html_errors',0);ini_set('display_errors', 1);ini_set('error_reporting', E_ALL);
  	include_once(dirname(__FILE__)."/ressources/class.mysql.squid.builder.php");
  	$GLOBALS["QZ"]=new mysql_catz();
  	$GLOBALS["CATZ-EXTRN"]=$re[1];
  
  	$categories_match=categories_match($GLOBALS["CATZ-EXTRN"],$argv[3]);
  	echo "RESULTS: $categories_match\n";
  	if($categories_match<>null){
  		fwrite(STDOUT, "ERR message=$categories_match\n");
  		
  	}
  	return;
  }  
  

  if(preg_match("#--categories\s+([0-9]+)#", @implode(" ", $argv),$re)){
  	WLOG("Starting ACLs dynamic with Categories features Group {$re[1]}...");
  	include_once(dirname(__FILE__)."/ressources/class.mysql.squid.builder.php");
  	$GLOBALS["QZ"]=new mysql_catz();
  	$GLOBALS["CATZ-EXTRN"]=$re[1];
  	
  }
  
  
  
  
  if(!is_numeric($GLOBALS["DEBUG_LEVEL"])){$GLOBALS["DEBUG_LEVEL"]=1;}
  $GLOBALS["RULE_ID"]=0;
  WLOG("Starting ACLs dynamic with debug level:{$GLOBALS["DEBUG_LEVEL"]} - License: {$GLOBALS["XVFERTSZ"]}");
  openLogs();
  
 
  
  if($GLOBALS["DEBUG_LEVEL"]>0){
  	if($GLOBALS["DEBUG_LEVEL"]>1){error_reporting(1);}
  	WLOG("Starting ACLs dynamic with debug level:{$GLOBALS["DEBUG_LEVEL"]}");
  	
  	
  }

//----------------- LOOP ------------------------------------  
  
	while (!feof(STDIN)) {
	 $url = trim(fgets(STDIN));
	 if($url==null){
	 	if($GLOBALS["DEBUG_LEVEL"]>3){WLOG("LOOP::URL is NULL");}
	 	continue;
	 }
	 
	 if($GLOBALS["UNBLOCK"]){	
	 		WLOG("UNBLOCK::$url");
	 		
	 		if(UFDGUARD_UNLOCKED($url)){
	 			WLOG("UNBLOCK::OK");
	 			fwrite(STDOUT, "OK\n"); 
	 			continue; 
	 		}
	 		
	 		WLOG("UNBLOCK::ERR");
	 		fwrite(STDOUT, "ERR\n");
	 		continue;
	 	}
	 
	 
		if($url<>null){
			$array=parseURL($url);
			if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("LOOP()::str ".$url ." [".__LINE__."]");}
			$ID=0;
			if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("LOOP()::str:".strlen($url)." Array(".count($array).") Rule ID:{$GLOBALS["RULE_ID"]}; LOGIN:{$array["LOGIN"]}; IPADDR:{$array["IPADDR"]}; MAC:{$array["MAC"]}; HOST:{$array["HOST"]}; RHOST:{$array["RHOST"]}; URI:{$array["URI"]}");}
			 	
			if($GLOBALS["ITCHART"]){
				$ChartID=CheckITChart($array["LOGIN"],$array["IPADDR"],$array["MAC"]);
				if($ChartID>0){
					$error=base64_encode(serialize(array("ChartID"=>$ChartID,"LOGIN"=>$array["LOGIN"],"IPADDR"=>$array["IPADDR"],"MAC"=>$array["MAC"])));
					fwrite(STDOUT, "ERR message=$error\n");
					continue;
				}
				fwrite(STDOUT, "OK\n");
			 	continue;
			}
			 	
			if($GLOBALS["CATZ-EXTRN"]>0){
				if(!$GLOBALS["XVFERTSZ"]){
			 		$error=urlencode("License Error, please remove Artica categories objects in ACL");
			 		WLOG("LOOP():: License Error ! [".__LINE__."]");
			 		fwrite(STDOUT, "BH message=$error\n");
			 		continue;
			 	}
			 		
			 	$categories_match=categories_match($GLOBALS["CATZ-EXTRN"],$array["RHOST"]);
			 	if($categories_match<>null){
			 		fwrite(STDOUT, "OK message=$categories_match\n");
			 		continue;
			 	}
			 	fwrite(STDOUT, "ERR\n");
			 	continue;
			 }
			 	
			 	
			 	if(isset($array["LOGIN"])){
			 		if($GLOBALS["DEBUG_LEVEL"]>3){WLOG("LOOP()::{$GLOBALS["RULE_ID"]} ->{$array["LOGIN"]} type 2");}
			 		$ID=CheckPattern($array["LOGIN"],$GLOBALS["RULE_ID"],2);
			 	}
			 	if($ID==0){
				 	if(isset($array["HOST"])){
				 		if(preg_match("#^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$#", $array["HOST"])){$array["HOST"]=gethostbyaddr($array["HOST"]);}
				 		if($GLOBALS["DEBUG_LEVEL"]>3){WLOG("LOOP()::{$GLOBALS["RULE_ID"]} ->{$array["HOST"]} type 3");}
				 		$ID=CheckPattern($array["HOST"],$GLOBALS["RULE_ID"],3);
				 	}
			 	}	
			
			 		
			 	if($ID==0){
			 		if(isset($array["IPADDR"])){
			 			if($GLOBALS["DEBUG_LEVEL"]>3){WLOG("LOOP()::{$GLOBALS["RULE_ID"]} ->{$array["HOST"]} type 1");}
			 			$ID=CheckPattern($array["IPADDR"],$GLOBALS["RULE_ID"],1);
			 		}
			 	}
			 	if($ID==0){
			 		if(isset($array["RHOST"])){
			 			if($GLOBALS["DEBUG_LEVEL"]>3){WLOG("LOOP()::{$GLOBALS["RULE_ID"]} ->{$array["RHOST"]} type 4");}
			 			$ID=CheckPattern($array["RHOST"],$GLOBALS["RULE_ID"],4);
			 		}
			 	}
			 	
			 		
			 	if($ID==0){
			 		if(isset($array["MAC"])){
			 			if($GLOBALS["DEBUG_LEVEL"]>3){WLOG("LOOP()::{$GLOBALS["RULE_ID"]} ->{$array["MAC"]} type 0");}
			 			$ID=CheckPattern($array["MAC"],$GLOBALS["RULE_ID"],0);
			 		}
			 	} 		
			 		
			 		
			 	if($ID>0){
			 		WLOG("LOOP()::Rule:{$GLOBALS["RULE_ID"]} ({$array["MAC"]}/{$array["LOGIN"]}/{$array["MAC"]}/{$array["IPADDR"]}/{$array["HOST"]}/{$array["RHOST"]}) MATCH;");
			 		fwrite(STDOUT, "OK message=\"Group:{$GLOBALS["RULE_ID"]}: Rule:$ID\"\n");
			 		continue;
			 	} 		
			 	if($GLOBALS["DEBUG_LEVEL"]>3){WLOG("LOOP()::Rule:{$GLOBALS["RULE_ID"]} ({$array["LOGIN"]}/{$array["MAC"]}/{$array["IPADDR"]}/{$array["HOST"]}/{$array["RHOST"]}) nothing match");}
				fwrite(STDOUT, "ERR\n");
			 	continue;
		 	}
	}

//-----------------------------------------------------
CleanSessions();
$distanceInSeconds = round(abs(time() - $GLOBALS["STARTIME"]));
$distanceInMinutes = round($distanceInSeconds / 60);
WLOG("Dynamic ACL: v1.0a: die after ({$distanceInSeconds}s/about {$distanceInMinutes}mn)");
if(isset($GLOBALS["F"])){@fclose($GLOBALS["F"]);}


function CleanSessions(){
	if(!isset($GLOBALS["SESSIONS"])){return;}
	if(!is_array($GLOBALS["SESSIONS"])){return;}
	$cachesSessions=unserialize(@file_get_contents("/etc/squid3/session.cache"));
	if(isset($cachesSessions)){
		if(is_array($cachesSessions)){
			while (list ($md5, $array) = each ($cachesSessions)){$GLOBALS["SESSIONS"][$md5]=$array;}
		}
	}
	@file_put_contents("/etc/squid3/session.cache", serialize($GLOBALS["SESSIONS"]));
}

function categories_get_memory($gpid,$sitname){
	if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("categories_get_memory: $gpid,$sitname ->". strlen($GLOBALS["categories_memory"])." bytes");}
	
	$data=unserialize($GLOBALS["categories_memory"]);
	if(count($data[$sitname])>64000){$GLOBALS["categories_memory"]=null;}
	if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("MEMORY: Group: $gpid `$sitname` -> {$data[$sitname][$gpid]}");}
	return $data[$sitname][$gpid];
}

function categories_set_memory($gpid,$sitname,$result){
	
	$data=unserialize($GLOBALS["categories_memory"]);
	$data[$sitname][$gpid]=$result;
	$GLOBALS["categories_memory"]=serialize($data);
}

function categories_match($gpid,$sitname){
	if(preg_match("#^www\.(.+)#", $sitname,$re)){$sitname=$re[1];}
	if(preg_match("#^(.+):[0-9]+]#", $sitname,$re)){$sitname=$re[1];}
	if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("Analyze: Group: $gpid `$sitname`");}
	
	$categories_get_memory=categories_get_memory($gpid,$sitname);
	if($categories_get_memory<>null){
		if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("Group: $gpid `$sitname` -> MEMORY: `$categories_get_memory` ");}
		if($categories_get_memory=="UNKNOWN"){return null;}
		return $categories_get_memory;
	}
	
	$q=new mysql_catz();
	if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("Group: $gpid `$sitname` -> CATEGORY ?? [".__LINE__."]");}
	$categoriF=$q->GET_CATEGORIES($sitname);
	
	$trans=$q->TransArray();
	if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("Group: $gpid `$sitname` -> category: `$categoriF` ");}
	
	if($categoriF==null){
		if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("squid_familysite()");}
		if(!class_exists("squid_familysite")){include_once(dirname(__FILE__)."/ressources/class.squid.familysites.inc");}
		$qF=new squid_familysite();
		$familysite=$qF->GetFamilySites($sitname);
		if($familysite<>$sitname){
			
			$categoriF=$q->GET_CATEGORIES($familysite);
			if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("Group: $gpid `$sitname` -> $familysite -> category: `$categoriF` ");}
		}
	}
	
	if($categoriF==null){
		categories_set_memory($gpid,$sitname,"UNKNOWN");
		return null;
	}
	
	if(strpos($categoriF, ",")>0){
		$categoriT=explode(",",$categoriF);
	}else{
		$categoriT[]=$categoriF;
	}
	
	while (list ($a, $b) = each ($categoriT)){
		$MAIN[$b]=true;
	}
	
	$filename="/etc/squid3/acls/catz_gpid{$gpid}.acl";
	$categories=unserialize(@file_get_contents($filename));
	
	while (list ($category_table, $category_rule) = each ($categories)){
		$category_rule=urlencode($category_rule);
		$categoryname=$trans[$category_table];
		
		if(isset($MAIN[$categoryname])){
			if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("FOUND `$categoryname` -> `$category_rule` ");}
			categories_set_memory($gpid,$sitname,$category_rule);
			return $category_rule;
		}
		
	}
	
	categories_set_memory($gpid,$sitname,"UNKNOWN");
	
}

function parseURL($url,$return_rhost=false){
	$uri=null;
	$md5=md5($url);
	$MAIN_ARRAY=array();
	if(isset($GLOBALS["CACHE_URI"][$md5])){
		if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("MEMORY $md5 ". strlen($GLOBALS["CACHE_URI"][$md5])." [".__LINE__."]");}
		if($return_rhost){
			$a=unserialize($GLOBALS["CACHE_URI"][$md5]);
			if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("RETURN MEMORY $md5 [".__LINE__."]");}
			return $a["RHOST"];
		}
		if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("RETURN MEMORY $md5 [".__LINE__."]");}
		return unserialize($GLOBALS["CACHE_URI"][$md5]);
	}
	
	if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("\n -----------------------------------------------------\n");}
	if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("parseURL():: Analyze $url /CATZ = {$GLOBALS["CATZ-EXTRN"]} [".__LINE__."]");}
	
	if( $GLOBALS["CATZ-EXTRN"]>0){
		$tr=explode(" ", $url);
		
		$MAIN_ARRAY["LOGIN"]=null;
		$MAIN_ARRAY["IPADDR"]=$tr[0];
		$MAIN_ARRAY["MAC"]=$tr[1];
		$MAIN_ARRAY["HOST"]=GetComputerName($tr[0]);
		$MAIN_ARRAY["URI"]=$tr[3];
		
		if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("parseURL():: Analyze RHOST = {$tr[3]} [".__LINE__."]");}
		
		if(preg_match("#^(.*?):([0-9]+)$#i", $tr[3],$re)){
			$MAIN_ARRAY["RHOST"]=$re[1];
			if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("parseURL():: FOUND RHOST = {$MAIN_ARRAY["RHOST"]} [".__LINE__."]");}
			if($return_rhost){return $re[1];}
			$GLOBALS["CACHE_URI"][$md5]=serialize($MAIN_ARRAY);
			return $MAIN_ARRAY;
		}
		
		if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("parseURL():: {$tr[3]} != ^([a-z0-9\.]+):([0-9]+) [".__LINE__."]");}
		
		if(preg_match("#^http.*?:#", $tr[3])){
			$URLAR=parse_url($tr[3]);
			if(isset($URLAR["host"])){
				$MAIN_ARRAY["RHOST"]=$URLAR["host"];
				if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("parseURL():: FOUND RHOST = {$MAIN_ARRAY["RHOST"]} [".__LINE__."]");}
				if($return_rhost){return $re[1];}
				$GLOBALS["CACHE_URI"][$md5]=serialize($MAIN_ARRAY);
				return $MAIN_ARRAY;
			}
			
		}
		
		
		
	}
	if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("parseURL():: Analyze $url [".__LINE__."]");}
	
	if(preg_match("#-\s+(.+?)\s+ID([0-9]+)#", $url,$re)){
		$GLOBALS["RULE_ID"]=$re[2];
		$url=str_replace($re[0], "", $url);
		
		if(preg_match("#(.+?):([0-9]+)#", $re[1],$ri)){$re[1]=$ri[1];}
		$MAIN_ARRAY["RHOST"]=$re[1];
		$MAIN_ARRAY["RULE_ID"]=$re[2];
		if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("parseURL()::found ID:{$GLOBALS["RULE_ID"]} remote host={$re[1]}  [".__LINE__."]");}
	}
	
	
	if(preg_match("#-\s+ID([0-9]+)#", $url,$re)){
		$GLOBALS["RULE_ID"]=$re[1];
		$MAIN_ARRAY["RULE_ID"]=$re[1];
		if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("parseURL()::found ID:{$GLOBALS["RULE_ID"]}  [".__LINE__."]");}
		if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("parseURL()::Analyze {$re[0]}  [".__LINE__."]");}
		$url=str_replace($re[0], "", $url);
	}
	
	
	
	if(preg_match("#(http|ftp|https|ftps):\/\/(.*)#i", $url,$re)){
		$uri=$re[1]."://".$re[2];
		if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("parseURL()::found uri $uri  [".__LINE__."]");}
		$url=trim(str_replace($uri, "", $url));
		if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("parseURL()::Analyze $url  [".__LINE__."]");}
		
	}
	if($uri==null){
		if(preg_match("#^(.*?):([0-9]+)$#i", $url,$re)){
			$uri="http://".$re[1];
			if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("parseURL()::found uri $uri  [".__LINE__."]");}
			$url=trim(str_replace($re[1].":".$re[2], "", $url));
			if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("parseURL()::Analyze \"$url\"  [".__LINE__."]");}
		}
	}
	if($uri<>null){
		$URLAR=parse_url($uri);
		if(isset($URLAR["host"])){$rhost=$URLAR["host"];}
	}
	
	
	
	
	
	$tr=explode(" ", $url);
	if($GLOBALS["DEBUG_LEVEL"]>1){
		while (list ($index, $line) = each ($tr)){
			WLOG("parseURL()::tr[$index] = $line");	
		}
	}
	
	
	//max auth=4
	if(count($tr)==4){
		
		$login=$tr[0];
		$ipaddr=$tr[1];
		$mac=$tr[2];
		$forwarded=$tr[3];
		if(isset($tr[4])){$uri=$tr[4];}
		if($mac=="00:00:00:00:00:00"){$mac=null;}
		if(preg_match("#^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$#", $forwarded)){$ipaddr=$forwarded;}
		if($mac==null){$mac=GetMacFromIP($ipaddr);}
		
		if($mac=="00:00:00:00:00:00"){$mac=null;}
		$MAIN_ARRAY["LOGIN"]=$login;
		$MAIN_ARRAY["IPADDR"]=$ipaddr;
		$MAIN_ARRAY["MAC"]=$mac;
		$MAIN_ARRAY["HOST"]=GetComputerName($ipaddr);
		$MAIN_ARRAY["URI"]=$uri;
		$MAIN_ARRAY["RHOST"]=$rhost;
		$GLOBALS["CACHE_URI"][$md5]=serialize($MAIN_ARRAY);
		return $MAIN_ARRAY;
	}
	
	
	
	if(count($tr)==3){
		if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("parseURL()::count --> 3");}
		if(preg_match("#^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$#", $tr[0])){
			//ip en premier donc mac=ok, pas de login
			$login=null;	
			$ipaddr=$tr[0];
			$mac=$tr[1];
			$forwarded=$tr[2];
			if(isset($tr[3])){$uri=$tr[3];}	
		}else{
			//login en premier donc mac=bad
			$login=$tr[0];
			$ipaddr=$tr[1];
			
			$forwarded=$tr[2];
			if(isset($tr[3])){$uri=$tr[3];}	
		}
		if($mac=="00:00:00:00:00:00"){$mac=null;}
		if(preg_match("#[0-9]+\[0-9]+\.[0-9]+\.[0-9]+#", $forwarded)){$ipaddr=$forwarded;}
		if($mac==null){$mac=GetMacFromIP($ipaddr);}
		if($mac=="00:00:00:00:00:00"){$mac=null;}
		$MAIN_ARRAY["LOGIN"]=$login;
		$MAIN_ARRAY["IPADDR"]=$ipaddr;
		$MAIN_ARRAY["MAC"]=$mac;
		$MAIN_ARRAY["HOST"]=GetComputerName($ipaddr);
		$MAIN_ARRAY["URI"]=$uri;	
		$MAIN_ARRAY["RHOST"]=$rhost;		
		$GLOBALS["CACHE_URI"][$md5]=serialize($MAIN_ARRAY);
		return $MAIN_ARRAY;
		
	}
	
	
	
	if(count($tr)==2){
		if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("parseURL()::count --> 2");}
		//pas de login et pas de MAC;
		$login=null;	
		$ipaddr=$tr[0];
		$mac=null;
		$forwarded=$tr[1];
		if(isset($tr[2])){$uri=$tr[2];}	
		if(preg_match("#[0-9]+\[0-9]+\.[0-9]+\.[0-9]+#", $forwarded)){$ipaddr=$forwarded;}
		
	}
	if($mac==null){$mac=GetMacFromIP($ipaddr);}
	else{		
		if($mac=="00:00:00:00:00:00"){$mac=null;$mac=GetMacFromIP($ipaddr);}
	}
	if($mac=="00:00:00:00:00:00"){$mac=null;}
	$MAIN_ARRAY["LOGIN"]=$login;
	$MAIN_ARRAY["IPADDR"]=$ipaddr;
	$MAIN_ARRAY["MAC"]=$mac;
	$MAIN_ARRAY["HOST"]=GetComputerName($ipaddr);
	$MAIN_ARRAY["URI"]=$uri;	
	$MAIN_ARRAY["RHOST"]=$rhost;
	$GLOBALS["CACHE_URI"][$md5]=serialize($MAIN_ARRAY);
	return $MAIN_ARRAY;
	
	
}

function CheckPattern($name,$gpid,$type){
	if(!is_numeric($gpid)){return;}
	if(!is_numeric($type)){return;}
	if(!isset($GLOBALS["CACHE_CLEAN_MYSQL"])){$GLOBALS["CACHE_CLEAN_MYSQL"]=time();}
	
	if($type==3){
		if(preg_match("#^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$#", $name)){
			return 0;
		}
	}
	
	try{
		
	
	if(!class_exists("mysql_squid_builder")){include_once(dirname(__FILE__)."/ressources/class.mysql.squid.builder.php");}
	$q=new mysql_squid_builder();
	$sql="SELECT ID,`value`  FROM webfilter_aclsdynamic WHERE gpid=$gpid AND `type`=$type";
	$results = $q->QUERY_SQL($sql);
	if(!$q->ok){
		WLOG("CheckPattern()::ERROR SQL: $sql");
		WLOG("CheckPattern()::ERROR SQL: `$q->mysql_error`");
		return 0;
	}
	
	if(mysql_num_rows($results)==0){return 0;}
	
	while ($ligne = mysql_fetch_assoc($results)) {
		$value=$ligne["value"];
		if(preg_match("#re:(.+)#", $value,$re)){
			if(preg_match("#{$re[1]}#i", $name)){return $ligne["ID"];}
			continue;
		}
		
		$value=string_to_regex($value);
		if($GLOBALS["DEBUG_LEVEL"]>3){WLOG("CheckPattern()::Checks `$value` with `$name`");}
		if(preg_match("#$value#i", $name)){
			if($GLOBALS["DEBUG_LEVEL"]>3){WLOG("CheckPattern()::Match `$value` with `$name` = {$ligne["ID"]}");}
			return $ligne["ID"];
		}
	}
	
	if(DistanceInMns($GLOBALS["CACHE_CLEAN_MYSQL"])>5){
		$GLOBALS["CACHE_CLEAN_MYSQL"]=time();
		if($GLOBALS["DEBUG_LEVEL"]>3){WLOG("CheckPattern():: Clean old rules...");}
		$sql="DELETE FROM `webfilter_aclsdynamic` WHERE `duration`>0 AND `maxtime`>".time();
		$q->QUERY_SQL($sql);
		if(!$q->ok){WLOG("CheckPattern()::$q->mysql_error,$sql");}
	}
	
	
	}
	catch (Exception $e) {
		WLOG($e->getMessage());
		return 0;
	}
	
	return 0;
}


function GetMacToUid($mac){
	if($mac==null){return;}
	if(isset($GLOBALS["GetMacToUidMD5"])){
			$md5file=md5_file("/etc/squid3/MacToUid.ini");
			if($md5file<>$GLOBALS["GetMacToUidMD5"]){
				unset($GLOBALS["GetMacToUid"]);
			}
	}
	if(isset($GLOBALS["GetMacToUid"])){
		if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("MEM: $mac =`{$GLOBALS["GetMacToUid"][$mac]}`");}
		if(isset($GLOBALS["GetMacToUid"][$mac])){
				return $GLOBALS["GetMacToUid"][$mac];
			}
		return;
	}
	
	$GLOBALS["GetMacToUid"]=unserialize(@file_get_contents("/etc/squid3/MacToUid.ini"));
	$GLOBALS["GetMacToUidMD5"]=md5_file("/etc/squid3/MacToUid.ini");
	if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("DISK: $mac =`{$GLOBALS["GetMacToUid"][$mac]}`");}
	if(isset($GLOBALS["GetMacToUid"][$mac])){return $GLOBALS["GetMacToUid"][$mac];}
}




function CheckQuota($CPINFOS){
	$RULES=unserialize(@file_get_contents("/etc/squid3/squid.durations.ini"));
	if(!is_array($RULES)){return true;}
	if(count($RULES)==0){return true;}
	
	while (list ($duration, $array_duration) = each ($RULES)){
		while (list ($xtype, $array_type) = each ($array_duration)){
			while (list ($pattern, $quotaBytes) = each ($array_type)){
				WLOG("Check rule for duration:$duration type:$xtype ($pattern) $quotaBytes bytes");
				
				if($duration==1){
					if(CheckQuota_day($CPINFOS,$xtype,$pattern,$quotaBytes)){return false;}
					continue;
				}
				if($duration==2){
					if(CheckQuota_hour($CPINFOS,$xtype,$pattern,$quotaBytes)){return false;}
					continue;
				}
			}
		}
		
	}

return true;
	
	
}
function CheckQuota_day($infos,$xtype,$pattern,$quotaBytes){
	$IPADDR=$infos["IPADDR"];
	$MAC=$infos["MAC"];
	$HOST=$infos["HOST"];
	$LOGIN=$infos["LOGIN"];
	
	$array=unserialize(@file_get_contents("/etc/squid3/squid.quotasD.ini"));
	$pattern=str_replace(".", "\.", $pattern);
	$pattern=str_replace("*", ".*?", $pattern);	
	
	if($xtype=="ipaddr"){
		if($IPADDR==null){WLOG("$IPADDR is null");return false;}
		if(!preg_match("#$pattern#i", $IPADDR)){WLOG("$IPADDR did nor match rule $pattern");return false;}
		if(count($array["ipaddr"])==0){WLOG("ipaddr: not an array...");return false;}
		if(!isset($array["ipaddr"][$IPADDR])){WLOG("ipaddr[$IPADDR]: !isset");return false;}
		$CurrentQuota=$array["ipaddr"][$IPADDR];
		$CurrentQuotaM=($CurrentQuota/1024)/1000;
		$quotaBytesM=($quotaBytes/1024)/1000;
		if($CurrentQuota<$quotaBytes){WLOG("Current $CurrentQuota - $CurrentQuotaM MB did not match rule of $quotaBytes - $quotaBytesM MB");return false;}
		if($CurrentQuota>=$quotaBytes){WLOG("Current $CurrentQuota - $CurrentQuotaM MB match rule of $quotaBytesM MB");return true;}
	}

	if($xtype=="uid"){
		if($LOGIN==null){WLOG("LOGIN is null");return false;}
		if(!preg_match("#$pattern#i", $LOGIN)){WLOG("$LOGIN did nor match rule $pattern");return false;}
		if(count($array["uid"])==0){WLOG("uid: not an array...");return false;}
		if(!isset($array["uid"][$LOGIN])){WLOG("uid[$LOGIN]: !isset");return false;}
		$CurrentQuota=$array["uid"][$LOGIN];
		$CurrentQuotaM=($CurrentQuota/1024)/1000;
		$quotaBytesM=($quotaBytes/1024)/1000;
		if($CurrentQuota<$quotaBytes){WLOG("Current $CurrentQuota - $CurrentQuotaM MB did not match rule of $quotaBytes - $quotaBytesM MB");return false;}
		if($CurrentQuota>=$quotaBytes){WLOG("Current $CurrentQuota - $CurrentQuotaM MB match rule of $quotaBytesM MB");return true;}
	}	
	
	if($xtype=="hostname"){
		if($HOST==null){WLOG("HOST is null");return false;}
		if(!preg_match("#$pattern#i", $HOST)){WLOG("$HOST did nor match rule $pattern");return false;}
		if(count($array["hostname"])==0){WLOG("hostname: not an array...");return false;}
		if(!isset($array["hostname"][$HOST])){WLOG("hostname[$LOGIN]: !isset");return false;}
		$CurrentQuota=$array["hostname"][$HOST];
		$CurrentQuotaM=($CurrentQuota/1024)/1000;
		$quotaBytesM=($quotaBytes/1024)/1000;
		if($CurrentQuota<$quotaBytes){WLOG("Current $CurrentQuota - $CurrentQuotaM MB did not match rule of $quotaBytes - $quotaBytesM MB");return false;}
		if($CurrentQuota>=$quotaBytes){WLOG("Current $CurrentQuota - $CurrentQuotaM MB match rule of $quotaBytesM MB");return true;}
	}	
	
	if($xtype=="MAC"){
		if($MAC==null){WLOG("MAC is null");return false;}
		if(!preg_match("#$pattern#i", $MAC)){WLOG("$MAC did nor match rule $pattern");return false;}
		if(count($array["MAC"])==0){WLOG("MAC: not an array...");return false;}
		if(!isset($array["MAC"][$MAC])){WLOG("MAC[$MAC]: !isset");return false;}
		$CurrentQuota=$array["MAC"][$MAC];
		$CurrentQuotaM=($CurrentQuota/1024)/1000;
		$quotaBytesM=($quotaBytes/1024)/1000;
		if($CurrentQuota<$quotaBytes){WLOG("Current $CurrentQuota - $CurrentQuotaM MB did not match rule of $quotaBytes - $quotaBytesM MB");return false;}
		if($CurrentQuota>=$quotaBytes){WLOG("Current $CurrentQuota - $CurrentQuotaM MB match rule of $quotaBytesM MB");return true;}
	}		
	
	
}

function CheckQuota_hour($infos,$xtype,$pattern,$quotaBytes){
	
	$IPADDR=$infos["IPADDR"];
	$MAC=$infos["MAC"];
	$HOST=$infos["HOST"];
	$LOGIN=$infos["LOGIN"];
	
	
	$array=unserialize(@file_get_contents("/etc/squid3/squid.quotasH.ini"));
	$pattern=str_replace(".", "\.", $pattern);
	$pattern=str_replace("*", ".*?", $pattern);

	if($xtype=="ipaddr"){
		if($IPADDR==null){WLOG("IPADDR is null");return false;}
		if(!preg_match("#$pattern#i", $IPADDR)){WLOG("$IPADDR did nor match rule $pattern");return false;}
		if(count($array["ipaddr"])==0){WLOG("ipaddr: not an array...");return false;}
		if(!isset($array["ipaddr"][$IPADDR])){WLOG("ipaddr[$IPADDR]: !isset");return false;}
		$CurrentQuota=$array["ipaddr"][$IPADDR];
		$CurrentQuotaM=($CurrentQuota/1024)/1000;
		$quotaBytesM=($quotaBytes/1024)/1000;
		if($CurrentQuota<$quotaBytes){WLOG("Current $CurrentQuota - $CurrentQuotaM MB did not match rule of $quotaBytes - $quotaBytesM MB");return false;}
		if($CurrentQuota>=$quotaBytes){WLOG("Current $CurrentQuota - $CurrentQuotaM MB match rule of $quotaBytesM MB");return true;}
	}

	if($xtype=="uid"){
		if($LOGIN==null){WLOG("LOGIN is null");return false;}
		if(!preg_match("#$pattern#i", $LOGIN)){WLOG("$LOGIN did nor match rule $pattern");return false;}
		if(count($array["uid"])==0){WLOG("uid: not an array...");return false;}
		if(!isset($array["uid"][$LOGIN])){WLOG("uid[$LOGIN]: !isset");return false;}
		$CurrentQuota=$array["uid"][$LOGIN];
		$CurrentQuotaM=($CurrentQuota/1024)/1000;
		$quotaBytesM=($quotaBytes/1024)/1000;
		if($CurrentQuota<$quotaBytes){WLOG("Current $CurrentQuota - $CurrentQuotaM MB did not match rule of $quotaBytes - $quotaBytesM MB");return false;}
		if($CurrentQuota>=$quotaBytes){WLOG("Current $CurrentQuota - $CurrentQuotaM MB match rule of $quotaBytesM MB");return true;}
	}	
	
	if($xtype=="hostname"){
		if($HOST==null){WLOG("HOST is null");return false;}
		if(!preg_match("#$pattern#i", $HOST)){WLOG("$HOST did nor match rule $pattern");return false;}
		if(count($array["hostname"])==0){WLOG("hostname: not an array...");return false;}
		if(!isset($array["hostname"][$HOST])){WLOG("hostname[$LOGIN]: !isset");return false;}
		$CurrentQuota=$array["hostname"][$HOST];
		$CurrentQuotaM=($CurrentQuota/1024)/1000;
		$quotaBytesM=($quotaBytes/1024)/1000;
		if($CurrentQuota<$quotaBytes){WLOG("Current $CurrentQuota - $CurrentQuotaM MB did not match rule of $quotaBytes - $quotaBytesM MB");return false;}
		if($CurrentQuota>=$quotaBytes){WLOG("Current $CurrentQuota - $CurrentQuotaM MB match rule of $quotaBytesM MB");return true;}
	}	
	
	if($xtype=="MAC"){
		if($MAC==null){WLOG("MAC is null");return false;}
		if(!preg_match("#$pattern#i", $MAC)){WLOG("$MAC did nor match rule $pattern");return false;}
		if(count($array["MAC"])==0){WLOG("MAC: not an array...");return false;}
		if(!isset($array["MAC"][$MAC])){WLOG("MAC[$MAC]: !isset");return false;}
		$CurrentQuota=$array["MAC"][$MAC];
		$CurrentQuotaM=($CurrentQuota/1024)/1000;
		$quotaBytesM=($quotaBytes/1024)/1000;
		if($CurrentQuota<$quotaBytes){WLOG("Current $CurrentQuota - $CurrentQuotaM MB did not match rule of $quotaBytes - $quotaBytesM MB");return false;}
		if($CurrentQuota>=$quotaBytes){WLOG("Current $CurrentQuota - $CurrentQuotaM MB match rule of $quotaBytesM MB");return true;}
	}	

	
	
}

function SplasHCheckAuth($array){
	if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("curl_init()");}
	$ch = curl_init();
	
	$LOGIN=$array["LOGIN"];
	$IPADDR=$array["IPADDR"];
	$MAC=$array["MAC"];
	$HOST=$array["HOST"];
	$md5key=md5("$LOGIN$IPADDR$MAC$HOST");	
	
	$params="?checks=".base64_encode(serialize($array));
	curl_setopt($ch, CURLOPT_INTERFACE,"127.0.0.1");
	curl_setopt($ch, CURLOPT_URL, $GLOBALS["SplashScreenURI"].$params);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_TIMEOUT, 5);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, 0);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:10.0) Gecko/20100101 Firefox/10.0");
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Pragma: no-cache", "Cache-Control: no-cache",'Expect:'));
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	
	
	if($GLOBALS["DEBUG_LEVEL"]>2){WLOG("curl_exec()-> ".$GLOBALS["SplashScreenURI"].$params);}
	$data=curl_exec($ch);
	$errno=curl_errno($ch);
	
	if($errno>0){
		if($GLOBALS["DEBUG_LEVEL"]>0){WLOG("Error $errno");}
		return;
	}
	
	if($GLOBALS["DEBUG_LEVEL"]>0){WLOG("{$GLOBALS["SplashScreenURI"]} Error:$errno $data");}
	
	if(preg_match("#<OK>uid=(.*?)</OK>#is", $data,$re)){
		$time=time();
		WLOG("{$re[1]}: $md5key return {$re[1]} -> Cache time:$time");
		$GLOBALS["SESSIONS"][$md5key]["TIME"]=$time;
		$GLOBALS["SESSIONS"][$md5key]["uid"]=$re[1];
		return $re[1];
	}
	
	
	curl_close($ch);
}

function SessionActive($array){
	
	$LOGIN=$array["LOGIN"];
	$IPADDR=$array["IPADDR"];
	$MAC=$array["MAC"];
	$HOST=$array["HOST"];
	$md5key=md5("$LOGIN$IPADDR$MAC$HOST");
	if(!isset($GLOBALS["SESSIONS"][$md5key])){return;}
	$distanceInSeconds = round(abs(time() - $GLOBALS["SESSIONS"][$md5key]["TIME"]));
	
	
	if($distanceInSeconds>$GLOBALS["SESSION_TIME"]){
		WLOG("{$GLOBALS["SESSIONS"][$md5key]["uid"]}: Key: $md5key {$GLOBALS["SESSIONS"][$md5key]["TIME"]} = $distanceInSeconds seconds <> {$GLOBALS["SESSION_TIME"]} seconds");
		unset($GLOBALS["SESSIONS"][$md5key]);return; }
	return $GLOBALS["SESSIONS"][$md5key]["uid"];

}
function DistanceInMns($time){
	$data1 = $time;
	$data2 = time();
	$difference = ($data2 - $data1);
	return round($difference/60);
}

function openLogs($force=false){
	$ACLS_OPTIONS=unserialize(base64_decode(@file_get_contents("/etc/artica-postfix/settings/Daemons/AclsOptions")));
	if(!is_numeric($ACLS_OPTIONS["DYN_LOG_LEVEL"])){$ACLS_OPTIONS["DYN_LOG_LEVEL"]=0;}
	if($ACLS_OPTIONS["DYN_LOG_LEVEL"]>$GLOBALS["DEBUG_LEVEL"]){
		$GLOBALS["DEBUG_LEVEL"]=$ACLS_OPTIONS["DYN_LOG_LEVEL"];	
	}
	if(!isset($GLOBALS["PID"])){$GLOBALS["PID"]=getmypid();}
}
function _get_memory_usage_158() {
	$mem_usage = memory_get_usage(true);
	if ($mem_usage < 1024){return $mem_usage." bytes";}
	if ($mem_usage < 1048576){return round($mem_usage/1024,2)." kilobytes";}
	return round($mem_usage/1048576,2)." megabytes";
}

function WLOG($text=null){
	$trace=@debug_backtrace();
	$filename=$GLOBALS["LOG_FILE_NAME"];
	if(isset($trace[1])){$called=" called by ". basename($trace[1]["file"])." {$trace[1]["function"]}() line {$trace[1]["line"]}";}
	$date=@date("Y-m-d H:i:s");
	$mem=_get_memory_usage_158();
	$f = @fopen($GLOBALS["LOG_FILE_NAME"], 'a'); 
	
	
   	if (is_file($GLOBALS["LOG_FILE_NAME"])) { 
   		$size=@filesize($GLOBALS["LOG_FILE_NAME"]);
   		if($size>1000000){
   			@fclose($f);
   			unlink($GLOBALS["LOG_FILE_NAME"]);
   			openLogs(true);
   		}
	}
	
	
	if($GLOBALS["VERBOSE"]){echo "$date [{$GLOBALS["PID"]}]: $text $called\n";}
	@fwrite($f, "$date [{$GLOBALS["PID"]}]: $text $called - process Memory:$mem\n");
	@fclose($f);
}

function uriToHost($uri){
	if(count($GLOBALS["uriToHost"])>20000){$GLOBALS["uriToHost"]=array();}
	if(isset($GLOBALS["uriToHost"][$uri])){return $GLOBALS["uriToHost"][$uri];}
	$URLAR=parse_url($uri);
	if(isset($URLAR["host"])){$sitename=$URLAR["host"];}
	if(preg_match("#^www\.(.*?)#", $sitename,$re)){$sitename=$re[1];}
	if(preg_match("#(.*?):[0-9]+#", $sitename)){$sitename=$re[1];}
	$GLOBALS["uriToHost"][$uri]=$sitename;
	return $sitename;
	
}
function GetComputerName($ip){
		$time=time("Ymh");
		if(count($GLOBALS["resvip"])>5){unset($GLOBALS["resvip"]);}
		if(isset($GLOBALS["resvip"][$time][$ip])){return $GLOBALS["resvip"][$time][$ip];}
		$name=gethostbyaddr($ip);
		$GLOBALS["resvip"][$time]=$name;
		return $name;
		}
function GetMacFromIP($ipaddr){
		$ipaddr=trim($ipaddr);
		$ttl=date('YmdH');
		if(count($GLOBALS["CACHEARP"])>3){unset($GLOBALS["CACHEARP"]);}
		if(isset($GLOBALS["CACHEARP"][$ttl][$ipaddr])){return $GLOBALS["CACHEARP"][$ttl][$ipaddr];}
		if(!isset($GLOBALS["SBIN_ARP"])){$GLOBALS["SBIN_ARP"]=find_program("arp");}
		if(!isset($GLOBALS["SBIN_ARPING"])){$GLOBALS["SBIN_ARPING"]=find_program("arping");}
		
		if(strlen($GLOBALS["SBIN_ARPING"])>3){
			$cmd="{$GLOBALS["SBIN_ARPING"]} $ipaddr -c 1 -r 2>&1";
			exec($cmd,$results);
			while (list ($num, $line) = each ($results)){
				if(preg_match("#^([0-9a-zA-Z\:]+)#", $line,$re)){
					$GLOBALS["CACHEARP"][$ttl][$ipaddr]=$re[1];
					return $GLOBALS["CACHEARP"][$ttl][$ipaddr];
				}
			}
		}
		
		
		$results=array();
			
		if(strlen($GLOBALS["SBIN_ARP"])<4){return;}
		if(!isset($GLOBALS["SBIN_PING"])){$GLOBALS["SBIN_PING"]=find_program("ping");}
		if(!isset($GLOBALS["SBIN_NOHUP"])){$GLOBALS["SBIN_NOHUP"]=find_program("nohup");}
		
		$cmd="{$GLOBALS["SBIN_ARP"]} -n \"$ipaddr\" 2>&1";
		$this->events($cmd);
		exec($cmd,$results);
		while (list ($num, $line) = each ($results)){
			if(preg_match("#^[0-9\.]+\s+.+?\s+([0-9a-z\:]+)#", $line,$re)){
				if($re[1]=="no"){continue;}
				$GLOBALS["CACHEARP"][$ttl][$ipaddr]=$re[1];
				return $GLOBALS["CACHEARP"][$ttl][$ipaddr];
			}
			
		}
		
		if(!isset($GLOBALS["PINGEDHOSTS"][$ipaddr])){
			shell_exec("{$GLOBALS["SBIN_NOHUP"]} {$GLOBALS["SBIN_PING"]} $ipaddr -c 3 >/dev/null 2>&1 &");
			$GLOBALS["PINGEDHOSTS"][$ipaddr]=true;
		}
			
		
	}
	
function CheckITChart($login,$ipaddr,$mac){
	$q=new mysql_squid_builder();
	
	if(!isset($GLOBALS["ITCHARTSIDS"])){
		$sql="SELECT ID FROM itcharters WHERE enabled=1";
		$results = $q->QUERY_SQL($sql);
		while ($ligne = mysql_fetch_assoc($results)) {
			$GLOBALS["ITCHARTSIDS"][$ligne["ID"]]=$ligne["ID"];
			WLOG("CheckITChart():: ChartID {$ligne["ID"]} to check..");
		}
	}
	
	if(count($GLOBALS["ITCHARTSIDS"])==0){
		WLOG("CheckITChart():: $login,$ipaddr,$mac no chart id, aborting, you should disable this feature.");
		return 0;}
	
	$itCharts=$GLOBALS["ITCHARTSIDS"];
	reset($itCharts);
	if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("CheckITChart():: login=$login,ipaddr=$ipaddr,mac=$mac ". count($itCharts)." to check");}
	if(trim($login<>null)){
		$login=trim(strtolower($login));
		while (list ($ID, $line) = each ($itCharts)){
			$ligne=mysql_fetch_array($q->QUERY_SQL("SELECT ID FROM itchartlog WHERE uid='$login' AND chartid='$ID'"));
			$EvID=$ligne["ID"];
			
			if(!is_numeric($EvID)){$EvID=0;}
			if($EvID==0){
				WLOG("CheckITChart():: LOGIN:$login ChartID $ID not read..");
				return $ID;}
		}
		return 0;
	}
	
	if(trim($mac<>null)){
		$mac=trim(strtolower($mac));
		reset($itCharts);
		while (list ($ID, $line) = each ($itCharts)){
			$ligne=mysql_fetch_array($q->QUERY_SQL("SELECT ID FROM itchartlog WHERE MAC='$mac' AND chartid='$ID'"));
			$EvID=$ligne["ID"];
			if(!is_numeric($EvID)){$EvID=0;}
			if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("CheckITChart()::MAC:$mac ChartID $ID = $EvID");}
			if($EvID==0){
				WLOG("CheckITChart():: MAC:$mac ChartID $ID not read..");
				return $ID;}
		}
		return 0;
	}	
	if(trim($ipaddr<>null)){
		$ipaddr=trim(strtolower($ipaddr));
		reset($itCharts);
		while (list ($ID, $line) = each ($itCharts)){
			$ligne=mysql_fetch_array($q->QUERY_SQL("SELECT ID FROM itchartlog WHERE ipaddr='$ipaddr' AND chartid='$ID'"));
			$EvID=$ligne["ID"];
			if($GLOBALS["DEBUG_LEVEL"]>1){WLOG("CheckITChart()::IPADDR:$ipaddr ChartID $ID = $EvID");}
			
			if(!is_numeric($EvID)){$EvID=0;}
			if($EvID==0){
				WLOG("CheckITChart():: IPADDR:$ipaddr ChartID $ID not read..");
				return $ID;}
		}
		return 0;
	}

	WLOG("CheckITChart():: ??!!");
	
}	

function UFDGUARD_UNLOCKED($url){
	
	if(trim($url)==null){
		
		return false;}
	$q=new mysql_squid_builder();
	if($q->COUNT_ROWS("ufdbunlock")==0){
		
		return false;}
	$values=explode(" ",$url);
	$LOGIN=$values[0];
	$IPADDR=$values[1];
	$MAC=$values[2];
	$XFORWARD=$values[3];
	$WWW=$values[4];
	$LOGIN=str_replace("%25", "-", $LOGIN);
	$IPADDR=str_replace("%25", "-", $IPADDR);
	$MAC=str_replace("%25", "-", $MAC);
	$XFORWARD=str_replace("%25", "-", $XFORWARD);
	if($XFORWARD=="-"){$XFORWARD=null;}
	if($MAC=="00:00:00:00:00:00"){$MAC=null;}
	if($MAC=="-"){$MAC=null;}
	if($LOGIN=="-"){$LOGIN=null;}
	$IPCalls=new IP();
	if($IPCalls->isIPAddress($XFORWARD)){$IPADDR=$XFORWARD;}
	
	if(preg_match("#(.+?):[0-9]+#", $WWW,$re)){$WWW=$re[1];}
	if(preg_match("#^www\.(.+)#", $WWW,$re)){$WWW=$re[1];}
	
	
	$WWW=$q->GetFamilySites($WWW);
	if(!isset($GLOBALS["ufdbunlock_c"])){$GLOBALS["ufdbunlock_c"]=0;}
	$GLOBALS["ufdbunlock_c"]++;
	
	
	if($GLOBALS["ufdbunlock_c"]>90){
		$q->QUERY_SQL("DELETE FROM ufdbunlock WHERE `finaltime` <". time());
		if(!$q->ok){WLOG("$q->mysql_error");}
		$GLOBALS["ufdbunlock_c"]=0;
	}
	
	if($LOGIN<>null){
		$ligne=mysql_fetch_array($q->QUERY_SQL("SELECT md5,finaltime FROM ufdbunlock WHERE `www`='$WWW' AND uid='$LOGIN'"));
		if(!$q->ok){WLOG("$q->mysql_error");}
		
		
		
		if($ligne["md5"]<>null){
			if($ligne["finaltime"]<time()){
				return false;
			}
			if($MAC<>null){
				$q->QUERY_SQL("UPDATE ufdbunlock SET MAC='$MAC' WHERE uid='$LOGIN'");
			}
			
			return true;
		}
	}
		

	if($MAC<>null){
			$ligne=mysql_fetch_array($q->QUERY_SQL("SELECT md5,finaltime FROM ufdbunlock WHERE `www`='$WWW' AND MAC='$MAC'"));
			if(!$q->ok){WLOG("$q->mysql_error");}
			
			
			
			if($ligne["md5"]<>null){
				if($ligne["finaltime"]<time()){return false;}
				if($IPADDR<>null){
					$q->QUERY_SQL("UPDATE ufdbunlock SET ipaddr='$IPADDR' WHERE MAC='$MAC'");
				}
				
				return true;
			}	
	}
			
	
	if($IPADDR<>null){
		$ligne=mysql_fetch_array($q->QUERY_SQL("SELECT md5,finaltime FROM ufdbunlock WHERE `www`='$WWW' AND ipaddr='$IPADDR'"));
		if(!$q->ok){WLOG("$q->mysql_error");}
		$time=time();
		
		
		if($ligne["md5"]<>null){
			if($ligne["finaltime"]<time()){
				WLOG("{$ligne["finaltime"]} < $time -> FALSE");
				return false;}
		
		if($MAC<>null){
			$q->QUERY_SQL("UPDATE ufdbunlock SET MAC='$MAC' WHERE ipaddr='$IPADDR'");
		}
		
		return true;
		}
	}	
	
	
	
	
	
	
}
	
	
function find_program($strProgram) {
	  $key=md5($strProgram);
	  if(isset($GLOBALS["find_program"][$key])){return $GLOBALS["find_program"][$key];}
	  $value=trim(internal_find_program($strProgram));
	  $GLOBALS["find_program"][$key]=$value;
      return $value;
}

function XVFERTSZ(){
	$F=base64_decode("L3Vzci9sb2NhbC9zaGFyZS9hcnRpY2EvLmxpYw==");
	
	if(!is_file($F)){
		WLOG("License check no such license");
		return false;}
	$D=trim(@file_get_contents($F));
	if(trim($D)=="TRUE"){return true;}
	WLOG("License check no such license content");
	return false;
	
}

function internal_find_program($strProgram){
	  global $addpaths;	
	  $arrPath = array('/bin', '/sbin', '/usr/bin', '/usr/sbin', '/usr/local/bin', 
	  '/usr/local/sbin',
	  '/usr/kerberos/bin',
	  
	  );
	  
	  if (function_exists("is_executable")) {
	    foreach($arrPath as $strPath) {
	      $strProgrammpath = $strPath . "/" . $strProgram;
	      if (is_executable($strProgrammpath)) {
	      	  return $strProgrammpath;
	      }
	    }
	  } else {
	   	return strpos($strProgram, '.exe');
	  }
	}	
?>
