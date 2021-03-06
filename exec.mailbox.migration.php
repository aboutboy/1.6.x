<?php
if(!function_exists("posix_getuid")){echo "posix_getuid !! not exists\n";}
if(posix_getuid()<>0){die("Cannot be used in web server mode\n\n");}
if(preg_match("#schedule-id=([0-9]+)#",implode(" ",$argv),$re)){$GLOBALS["SCHEDULE_ID"]=$re[1];}
include_once(dirname(__FILE__).'/ressources/class.templates.inc');
include_once(dirname(__FILE__).'/ressources/class.mysql.inc');
include_once(dirname(__FILE__).'/framework/class.unix.inc');
include_once(dirname(__FILE__)."/framework/frame.class.inc");
include_once(dirname(__FILE__)."/ressources/class.user.inc");

if(preg_match("#--verbose#",implode(" ",$argv))){$GLOBALS["VERBOSE"]=true;$GLOBALS["VERBOSE"]=true;ini_set('display_errors', 1);ini_set('error_reporting', E_ALL);ini_set('error_prepend_string',null);ini_set('error_append_string',null);}
if($argv[1]=="--files"){import_files();die();}
if($argv[1]=="--accounts"){accounts();die();}
if($argv[1]=="--member"){member($argv[2]);die();}
if($argv[1]=="--check"){checkTask($argv[2]);die();}
if($argv[1]=="--folders"){mailboxes_folders($argv[2]);die();}
if($argv[1]=="--schedules"){mailboxes_schedules();die();}


function mailboxes_schedules(){
	$unix=new unix();
	$pidfile="/etc/artica-postfix/pids/".basename(__FILE__).".".__FUNCTION__.".pid";
	$cachetime="/etc/artica-postfix/pids/".basename(__FILE__).".".__FUNCTION__.".time";
	$unix=new unix();
	$myFile=basename(__FILE__);	
	$pid=@file_get_contents($pidfile);
	if($unix->process_exists($pid,$myFile)){system_admin_events("Already executed PID:$pid, die()",__FUNCTION__,__FILE__,__LINE__,"mbximport");die();}
		
	@file_put_contents($pidfile, getmypid());
	$sql="SELECT zmd5 FROM mbx_migr_users WHERE scheduled=1";
	$q=new mysql();
	$results=$q->QUERY_SQL($sql,"artica_backup");
	$nohup=$unix->find_program("nohup");
	$php5=$unix->LOCATE_PHP5_BIN();
	$myscript=__FILE__;
	
	
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
		$c++;
		if($ligne["zmd5"]==null){continue;}
		$cmd="$nohup $php5 $myscript --member {$ligne["zmd5"]} --schedule-id={$GLOBALS["SCHEDULE_ID"]} >/dev/null 2>&1 &";
		shell_exec($cmd);
	}
	
	if($c>0){system_admin_events("$c launched tasks",__FUNCTION__,__FILE__,__LINE__,"mbximport");}
	
}



function import_files(){
	system_admin_events("Starting importing files to migrate",__FUNCTION__,__FILE__,__LINE__,"mbximport");
	$sql="SELECT ID,filepath,local_domain,ou FROM mbx_migr WHERE imported=0 and finish=0";
	$q=new mysql();
	$results=$q->QUERY_SQL($sql,"artica_backup");
	if(!$q->ok){system_admin_events("$sql $q->mysql_error",__FUNCTION__,__FILE__,__LINE__,"mbximport");}
	if($GLOBALS["VERBOSE"]){echo mysql_num_rows($results)." items...\n";}
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
		if(!is_file($ligne["filepath"])){
			if($GLOBALS["VERBOSE"]){echo "{$ligne["filepath"]} no such file, stamp finish\n";}
			$sql="UPDATE mbx_migr SET imported=1,finish=1 WHERE ID={$ligne["ID"]}";
			$q->QUERY_SQL($sql,"artica_backup");
			continue;
		}
		
		import_single_file($ligne["filepath"],$ligne["ID"],$ligne["ou"],$ligne["local_domain"]);
		
	}
	
}

function import_single_file($filepath,$ID,$ou,$localdomain){
	system_admin_events("$ID:: importing $filepath for $ou",__FUNCTION__,__FILE__,__LINE__,"mbximport");
	if($ou==null){system_admin_events("$ID:: OU IS NULL !!! ",__FUNCTION__,__FILE__,__LINE__,"mbximport");return;}

	$GLOBALS["OU"]=$ou;
	$f=explode("\n",@file_get_contents($filepath));
	$ldap=new clladp();
	$count=0;
	while (list ($num, $line) = each ($f) ){
		if($line==null){continue;}
		$tbl=explode(";",$line);
		$account=$tbl[0];
		$password=$tbl[1];
		$imap_server=$tbl[2];
		$new_uid=$tbl[3];
		$uid=null;
		$usessl=0;
		$zmd5=md5("$account$imap_server$new_uid");
		
		
		system_admin_events("$ID:: Scanning account=$account ou={$GLOBALS["OU"]} imap_server=$imap_server new_uid=$new_uid",
		__FUNCTION__,__FILE__,__LINE__,"mbximport");		
		
		if($new_uid==null){
			if(preg_match("#(.+?)@(.+?)$#",trim($account),$re)){$new_uid=$re[1];}else{$new_uid=$account;}
			system_admin_events("$ID:: local user=$new_uid@$localdomain",__FUNCTION__,__FILE__,__LINE__,"mbximport");
		}else{
			$uid=$new_uid;
			
		}
		
		
		if($uid==null){
			$uid=$ldap->uid_from_email("$new_uid@$localdomain");
		}
		
		if($uid==null){
			if(preg_match("#(.+?)@(.+?)$#",trim($new_uid),$re)){$new_uid=$re[1];}
			system_admin_events("$ID:: Add uid=\"$new_uid\" ou={$GLOBALS["OU"]} mail=$new_uid@$localdomain",
			__FUNCTION__,__FILE__,__LINE__,"mbximport");	
			$user_uid=new user();
			$user_uid->uid=$new_uid;
			$user_uid->ou=$GLOBALS["OU"];
			$user_uid->password=$password;
			$user_uid->mail="$new_uid@$localdomain";
			$user_uid->domainname=$localdomain;
			if(!$user_uid->add_user()){system_admin_events("$ID:: failed to add $new_uid in LDAP database width error `$user_uid->error`",
			__FUNCTION__,__FILE__,__LINE__,"mbximport");continue;}
			else{$new_uid=$user_uid->uid;}
		}else{
			
			$new_uid=$uid;
		}
		$count++;
		writelogs("$ID:: local uid:$uid",__FUNCTION__,__FILE__,__LINE__);
		
		if(preg_match("#ssl:(.+?)$#",$imap_server,$re)){$usessl=1;$imap_server=$re[1];}
		
		
		
		$sql="INSERT INTO mbx_migr_users (`zmd5`,`mbx_migr_id`,`ou`, `imap_server`,`usessl`,`username`,`password`,`uid`)
		VALUES('$zmd5','$ID','{$GLOBALS["OU"]}','$imap_server','$usessl','$account','$password','$new_uid')";
		writelogs("$ID:: \"$sql\"",__FUNCTION__,__FILE__,__LINE__);
		
		
		$q=new mysql();
		$q->QUERY_SQL($sql,"artica_backup");
		if(!$q->ok){system_admin_events("$ID:: Mysql error: $q->mysql_error \"$sql\"",__FUNCTION__,__FILE__,__LINE__,"mbximport");;}
	}
	
	
	$sql="UPDATE mbx_migr SET imported=1,members_count=$count WHERE ID=$ID";
	$q=new mysql();
	$q->QUERY_SQL($sql,"artica_backup");
	if(!$q->ok){system_admin_events("$ID:: $q->mysql_error \"$sql\"",__FUNCTION__,__FILE__,__LINE__,"mbximport");return;}
	
	$users=new usersMenus();
	if(!$users->offlineimap_installed){
		system_admin_events("$ID:: Offline Imap tool is not installed",__FUNCTION__,__FILE__,__LINE__,"mbximport");
		die();
	}
	
	sys_THREAD_COMMAND_SET(LOCATE_PHP5_BIN2()." ".__FILE__." --accounts");

}

function accounts(){
	$sql="SELECT * FROM mbx_migr_users WHERE imported=0";
	$unix=new unix();
	$nohup=$unix->find_program("nohup");
	$q=new mysql();
	$results=$q->QUERY_SQL($sql,"artica_backup");
	if(!$q->ok){writelogs("$sql $q->mysql_error",__FUNCTION__,__FILE__,__LINE__);}
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
		$PID=$ligne["PID"];
		if($PID<>null){
			if($unix->process_exists($PID)){continue;}
		}
		$cmd="$nohup ".LOCATE_PHP5_BIN2()." ".__FILE__." --member {$ligne["zmd5"]} >/dev/null 2>&1 &";
		shell_exec("$cmd");
	}	
}

function mailboxes_folders($ID){
	mailbox_settings($ID);
	$unix=new unix();
	$offlineimap=$unix->find_program("offlineimap");
	$NICE=EXEC_NICE();	
	$cmd="$NICE$offlineimap -u basic --info -c /etc/artica-postfix/offline-imap/$ID.cfg 2>&1";
	if($GLOBALS["VERBOSE"]){echo $cmd."\n";}
	
	$sql="SELECT mbxfolders FROM mbx_migr_users WHERE zmd5='$ID'";
	$q=new mysql();
	$ligne=@mysql_fetch_array($q->QUERY_SQL($sql,'artica_backup'));	
	$array=unserialize(base64_decode($ligne["mbxfolders"]));

	unset($array["SourceServer"]);
	unset($array["TargetServer"]);
	
	exec($cmd,$results);
	$scan=false;
	while (list ($num, $ligne) = each ($results) ){
		if(trim($ligne)==null){continue;}
		if(preg_match("#Remote repository '(.+?)'#", $ligne,$re)){
			$serv=$re[1];
			$scan=false;
			continue;
		}
		if(preg_match("#Folderlist:#", $ligne)){
			$scan=true;
			continue;
		}
		
		if(preg_match("#Local repository '(.+?)'#", $ligne,$re)){
			$serv=$re[1];
			$scan=false;
			continue;		
		}
		
		if($scan){
			$array[$serv][trim($ligne)]["NAME"]=trim($ligne);
		}
		
	}
	
	if(count($array)==0){
		if($GLOBALS["VERBOSE"]){echo "Not an array....\n";}
		return;
	}
	
	$final=base64_encode(serialize($array));
	$q=new mysql();
	$sql="UPDATE mbx_migr_users SET `mbxfolders`='$final' WHERE zmd5='$ID'";
	
	$q->QUERY_SQL($sql,"artica_backup");
	if(!$q->ok){system_admin_events("$ID:: $q->mysql_error \"$sql\"",__FUNCTION__,__FILE__,__LINE__,"mbximport");return;}
	
	
	
	
	
}

function mailbox_settings($ID,$tolog=array()){
	$sql="SELECT * FROM mbx_migr_users WHERE zmd5='$ID'";
	$q=new mysql();
	$ligne=@mysql_fetch_array($q->QUERY_SQL($sql,'artica_backup'));
	$folderfilter=null;
	$imap_server=$ligne["imap_server"];
	$cert_fingerprint=$ligne["cert_fingerprint"];
	$uid=$ligne["uid"];

	$usessl=$ligne["usessl"];
	$remote_username=$ligne["username"];
	$username=$ligne["username"];
	$remote_password=$ligne["password"];
	$ct=new user($uid);	
	$maxage=$ligne["maxage"];
	$maxsize=$ligne["maxsize"];
	$createfolders=$ligne["createfolders"];
	$readonly=$ligne["readonly"];
	
	
	$AsGateway=$ligne["AsGateway"];
	$imapr_server=$ligne["imapr_server"];
	$usesslr=$ligne["usesslr"];
	$usernamer=$ligne["usernamer"];
	$passwordr=$ligne["passwordr"];
	$cert_fingerprintr=$ligne["cert_fingerprintr"];
	if($remote_username==null){$remote_username=md5(time());}
	
	if($uid==null){
		$tolog[]="$ID: uid is null, aborting";
		if($usernamer<>null){
			$tolog[]="$ID: assume `$usernamer` as uid...";
		}
	}
	
	$Folders=unserialize(base64_decode($ligne["mbxfolders"]));
	
	
	if(isset($Folders["FoldersSelectedSourceServer"])){
		$ArraySelected=$Folders["FoldersSelectedSourceServer"];	
		if(count($ArraySelected)>0){
			while (list ($folder, $ligne) = each ($ArraySelected) ){
				if(trim($folder)==null){continue;}
				$folder=str_replace("'", "\'", $folder);
				$foldersInclude[]="'$folder'";
			}
			
			if(count($foldersInclude)>0){$folderfilter="folderfilter = lambda foldername: foldername in [".@implode(",", $foldersInclude)."]";}
		}
	}else{
		if($GLOBALS["VERBOSE"]){echo "FoldersSelectedSourceServer no such array\n";}
	}
	
	if($folderfilter==null){if($GLOBALS["VERBOSE"]){echo "folderfilter no such array\n";}}
	$offlineImapConf[]="[general]";
	$offlineImapConf[]="accounts = $remote_username";
	$offlineImapConf[]="maxsyncaccounts = 1";
	$offlineImapConf[]="ui =Noninteractive.Basic, Noninteractive.Quiet";
	$offlineImapConf[]="ignore-readonly = no";
	$offlineImapConf[]="socktimeout = 60";
	$offlineImapConf[]="fsync = true";
	$offlineImapConf[]="";
	$offlineImapConf[]="";
	$offlineImapConf[]="[ui.Curses.Blinkenlights]";
	$offlineImapConf[]="statuschar = .";
	$offlineImapConf[]="";
	$offlineImapConf[]="[Account $remote_username]";
	$offlineImapConf[]="localrepository = TargetServer";
	$offlineImapConf[]="remoterepository = SourceServer";
	$offlineImapConf[]="status_backend = sqlite";
	if($maxsize>0){
		$maxsize=($maxsize*1024)*1000;
		$offlineImapConf[]="maxsize = $maxsize";
	}
	if($maxage>0){
		$offlineImapConf[]="maxage = $maxage";
	}
	
	if($ct->password==null){
		$tolog[]="$ID: warning, Target server: no password is set for `$uid`";
		if($passwordr<>null){
			$tolog[]="$ID: Target server: assume `passwordr (****)` as password...";
			$ct->password=$passwordr;
		}
	}
	
	$offlineImapConf[]="[Repository TargetServer]";
	$offlineImapConf[]="";
	if($AsGateway==0){
		$tolog[]="$ID: using local mode 127.0.0.1:143 for `$uid`";
		$offlineImapConf[]="type = IMAP";
		$offlineImapConf[]="remotehost = 127.0.0.1";
		$offlineImapConf[]="ssl = 0";
		$offlineImapConf[]="remoteport = 143";
		$offlineImapConf[]="remoteuser = $uid";
		$offlineImapConf[]="remotepass = $ct->password";
	}else{
		
		$offlineImapConf[]="type = IMAP";
		$offlineImapConf[]="remotehost = $imapr_server";
		if($usesslr==1){$offlineImapConf[]="ssl = 1";$offlineImapConf[]="remoteport = 993";}else{$offlineImapConf[]="ssl = 0";$offlineImapConf[]="remoteport = 143";}
		if(strlen($cert_fingerprintr)>5){$offlineImapConf[]="cert_fingerprint = $cert_fingerprintr";}
		$tolog[]="$ID: Target server: using gateway mode $imapr_server for `$usernamer`";
		$offlineImapConf[]="remoteuser = $usernamer";
		$offlineImapConf[]="remotepass = $passwordr";		
	}
	
	$offlineImapConf[]="maxconnections = 2";
	$offlineImapConf[]="holdconnectionopen = yes";
	$offlineImapConf[]="expunge = no";
	$offlineImapConf[]="subscribedonly = no";

	$offlineImapConf[]="";
	$offlineImapConf[]="";
	$offlineImapConf[]="[Repository SourceServer]";
	$offlineImapConf[]="type = IMAP";
	$offlineImapConf[]="remotehost = $imap_server";
	if($folderfilter<>null){$offlineImapConf[]=$folderfilter;}		
		
	$tolog[]="$ID: source server (where orginal messages are stored) : $remote_username@$imap_server password=".strlen($remote_password)." bytes";
	
	
	if($usessl==1){
			$offlineImapConf[]="ssl = 1";
			$offlineImapConf[]="remoteport = 993";
			if(strlen($cert_fingerprint)>5){$offlineImapConf[]="cert_fingerprint = $cert_fingerprint";}
		}else{
			$offlineImapConf[]="ssl = 0";
			$offlineImapConf[]="remoteport = 143";	
		}
		$offlineImapConf[]="remoteuser = $remote_username";
		$offlineImapConf[]="remotepass = $remote_password";
		$offlineImapConf[]="maxconnections = 2";
		$offlineImapConf[]="holdconnectionopen = no";
		$offlineImapConf[]="expunge = no";
		$offlineImapConf[]="subscribedonly = no";
		
		if($createfolders==0){
			$offlineImapConf[]="createfolders = False";
		}else{
			$offlineImapConf[]="createfolders = True";
		}
		
		if($readonly==0){
			$offlineImapConf[]="readonly = False";
		}else{
			$offlineImapConf[]="readonly = True";
		}		
		
		
	
		@mkdir("/etc/artica-postfix/offline-imap",666,true);
		@file_put_contents("/etc/artica-postfix/offline-imap/$ID.cfg",@implode("\n",$offlineImapConf));
		return $tolog;
	
}


function member($ID){
	$sql="SELECT * FROM mbx_migr_users WHERE zmd5='$ID'";
	if($GLOBALS["VERBOSE"]){echo "ID:$ID\n";}
	if($GLOBALS["VERBOSE"]){echo "$sql\n";}
	$q=new mysql();
	$ligne=@mysql_fetch_array($q->QUERY_SQL($sql,'artica_backup'));
	$pid=$ligne["PID"];
	$TASKID=$ligne["mbx_migr_id"];
	$unix=new unix();
	$t1=time();
	$NICE=EXEC_NICE();
	if($pid<>null){if($unix->process_exists($pid)){system_admin_events("$ID:: $pid already executed",__FUNCTION__,__FILE__,__LINE__,"mbximport");die();}}
	$pid=getmypid();
	$offlineimap=$unix->find_program("offlineimap");	
	$verbosed=$ligne["verbosed"];
	$debug=null;
	if(!is_numeric($verbosed)){$verbosed=0;}
	
	$sql="UPDATE mbx_migr_users SET `PID`='$pid',`events`='' WHERE zmd5='$ID'";
	$q->QUERY_SQL($sql,"artica_backup");
	if(!$q->ok){system_admin_events("$ID:: $q->mysql_error \"$sql\"",__FUNCTION__,__FILE__,__LINE__,"mbximport");return;}
	mailboxes_folders($ID);
	$imap_server=$ligne["imap_server"];
	$cert_fingerprint=$ligne["cert_fingerprint"];
	$uid=$ligne["uid"];
	$usessl=$ligne["usessl"];
	$remote_username=$ligne["username"];
	$ct=new user($uid);
	$tolog[]="{$ligne["username"]}@$imap_server SSL:$usessl Port:993/143 fingerprint:$cert_fingerprint Using $offlineimap";
	
	$tolog=mailbox_settings($ID,$tolog);
	exec("$offlineimap --version 2>&1",$tolog);
	$t=time();
	if(is_file($offlineimap)){
		if($verbosed==1){$debug=" -d ALL";}
		$cmd="$NICE$offlineimap -u basic -l /etc/artica-postfix/offline-imap/$ID.log -c /etc/artica-postfix/offline-imap/$ID.cfg$debug 2>&1";
		$tolog[]=$cmd;
		if(is_file("/etc/artica-postfix/offline-imap/$ID.log")){@unlink("/etc/artica-postfix/offline-imap/$ID.log");}
		shell_exec("$cmd");
		$t2=time();
				
		$messages_count=0;
		if(!is_file("/etc/artica-postfix/offline-imap/$ID.log")){$tolog[]="$ID.log no such file";}
		$f=file("/etc/artica-postfix/offline-imap/$ID.log");
		while (list ($num, $pp) = each ($f) ){
			$tolog[]=$pp;
			if(preg_match("#Copy message#",$pp)){$messages_count++;}
		}
		$tolog[]="Messages replicated.: $messages_count";
		$tolog[]="Execution time......: ".distanceOfTimeInWords($t1,$t2);

	}else{
		$tolog[]="UNABLE TO STAT OFFLINEIMAP TOOL !!!";
		}
		
	$took=$unix->distanceOfTimeInWords($t,time(),true);	
	system_admin_events("$uid from $imap_server done took\n".@implode("\n",$tolog)."\n",__FUNCTION__,__FILE__,__LINE__,"mbximport");	
	$sql="UPDATE mbx_migr_users SET events='".addslashes(@implode("\n",$tolog))."',
	imported=1
	WHERE zmd5='$ID'";
	if($GLOBALS["VERBOSE"]){echo "$sql\n";}
	$q->QUERY_SQL($sql,"artica_backup");
	if(!$q->ok){writelogs("$ID:: $q->mysql_error \"$sql\"",__FUNCTION__,__FILE__,__LINE__);return;}
	checkTask($TASKID);
	
}

function checkTask($ID){
	$sql="SELECT COUNT(*) AS tcount FROM mbx_migr_users WHERE mbx_migr_id=$ID AND imported=0";
	$q=new mysql();
	$ligne=@mysql_fetch_array($q->QUERY_SQL($sql,'artica_backup'));
	if($ligne["tcount"]==null){$ligne["tcount"]=0;}
	if($ligne["tcount"]==0){
		if($GLOBALS["VERBOSE"]){echo "Task $ID ->  0 items...\n";}
		$sql="UPDATE mbx_migr SET finish=1 WHERE ID=$ID";
		$q->QUERY_SQL($sql,'artica_backup');
	}
	
}

