<?php
if(posix_getuid()<>0){die("Cannot be used in web server mode\n\n");}
$GLOBALS["FORCE"]=false;
$GLOBALS["RECONFIGURE"]=false;
$GLOBALS["WORKDIR"]="/home/shorewall-db";
$GLOBALS["SERV_NAME"]="shorewall-db";
$GLOBALS["MYSQL_PID"]="/var/run/shorewall-db.pid";
$GLOBALS["MYSQL_SOCKET"]="/var/run/mysqld/shorewall-db.sock";
$GLOBALS["MYPID"]="/etc/artica-postfix/pids/{$GLOBALS["SERV_NAME"]}.pid";
$GLOBALS["mysql_install_db"]=true;
if(preg_match("#--verbose#",implode(" ",$argv))){$GLOBALS["VERBOSE"]=true;$GLOBALS["OUTPUT"]=true;
$GLOBALS["debug"]=true;ini_set('display_errors', 1);ini_set('error_reporting', E_ALL);ini_set('error_prepend_string',null);ini_set('error_append_string',null);}
if(preg_match("#--output#",implode(" ",$argv))){$GLOBALS["OUTPUT"]=true;}
if(preg_match("#schedule-id=([0-9]+)#",implode(" ",$argv),$re)){$GLOBALS["SCHEDULE_ID"]=$re[1];}
if(preg_match("#--force#",implode(" ",$argv),$re)){$GLOBALS["FORCE"]=true;}
if(preg_match("#--reconfigure#",implode(" ",$argv),$re)){$GLOBALS["RECONFIGURE"]=true;}
$GLOBALS["AS_ROOT"]=true;
include_once(dirname(__FILE__).'/ressources/class.templates.inc');
include_once(dirname(__FILE__).'/ressources/class.ccurl.inc');
include_once(dirname(__FILE__).'/ressources/class.mysql.squid.builder.php');
include_once(dirname(__FILE__).'/framework/class.unix.inc');
include_once(dirname(__FILE__).'/framework/frame.class.inc');
include_once(dirname(__FILE__).'/ressources/class.mysql.services.inc');
include_once(dirname(__FILE__).'/ressources/class.amavidb.inc');

if($argv[1]=="--stop"){$GLOBALS["OUTPUT"]=true;stop();die();}
if($argv[1]=="--start"){$GLOBALS["OUTPUT"]=true;start();die();}
if($argv[1]=="--restart"){$GLOBALS["OUTPUT"]=true;restart();die();}
if($argv[1]=="--changemysqldir"){changemysqldir($argv[2]);die();}
if($argv[1]=="--databasesize"){databasesize($GLOBALS["FORCE"]);die();}
if($argv[1]=="--restorefrom"){RestoreFromBackup($argv[2]);die();}






function start(){
	$unix=new unix();
		
	if(!isset($GLOBALS["MYSQL_BIN_PATH"])){$GLOBALS["MYSQL_BIN_PATH"]=null;}
	if(!isset($GLOBALS["mysql_install_db"])){$GLOBALS["mysql_install_db"]=true;}
	$pidfile=$GLOBALS["MYPID"];
	$WORKDIR=$GLOBALS["WORKDIR"];
	$SERV_NAME=$GLOBALS["SERV_NAME"];
	$mysql_pid_file=$GLOBALS["MYSQL_PID"];
	$MYSQL_SOCKET=$GLOBALS["MYSQL_SOCKET"];
	$pid=$unix->get_pid_from_file($pidfile);
	$sock=new sockets();
	$MySQLShorewallType=$sock->GET_INFO("MySQLShorewallType");
	if(!is_numeric($MySQLShorewallType)){$MySQLShorewallType=1;}
	$MySQLWorkDir=$sock->GET_INFO("MySQLShorewallWorkDir");
	if($MySQLWorkDir==null){$MySQLWorkDir=$GLOBALS["WORKDIR"];}	
	$shorewall=$unix->find_program("shorewall");
	
	if(!is_file($shorewall)){
		if($GLOBALS["OUTPUT"]){echo "Starting......: ".date("H:i:s")." [INIT]: $SERV_NAME main application not installed...\n";}
		return;
	}
	
	
	if($unix->process_exists($pid,basename(__FILE__))){
		$time=$unix->PROCCESS_TIME_MIN($pid);
		if($GLOBALS["OUTPUT"]){echo "Starting......: ".date("H:i:s")." [INIT]: Starting Task Already running PID $pid since {$time}mn\n";}
		return;
	}
		
	@file_put_contents($pidfile, getmypid());
	
	$mysql_install_db=$unix->find_program("mysql_install_db");

	
	if($MySQLShorewallType<>1){
		if($GLOBALS["OUTPUT"]){echo "Starting......: ".date("H:i:s")." [INIT]: $SERV_NAME is not a server...\n";}
		stop();
		die(0);		
	}
		
	if($GLOBALS["MYSQL_BIN_PATH"]<>null){$mysqld=$GLOBALS["MYSQL_BIN_PATH"];}else{$mysqld=$unix->find_program("mysqld");}
	if(!is_file($mysqld)){
		if($GLOBALS["OUTPUT"]){echo "Starting......: ".date("H:i:s")." [INIT]: $SERV_NAME is not installed (mysqld, no such binary)...\n";}
		return;
	}	
	
	if($GLOBALS["mysql_install_db"]){
		if(!is_file($mysql_install_db)){
			if($GLOBALS["OUTPUT"]){echo "Starting......: ".date("H:i:s")." [INIT]: $SERV_NAME mysql_install_db no such binary...\n";}
			return;
		}	
	}
	
	
	$pid=DBPID();
	
	if($unix->process_exists($pid)){
		$time=$unix->PROCCESS_TIME_MIN($pid);
		if($GLOBALS["OUTPUT"]){echo "Starting......: ".date("H:i:s")." [INIT]: $SERV_NAME MySQL Database Engine already running pid $pid since {$time}mn\n";}
		return;
	}	
	$TMP=$unix->FILE_TEMP();

	@mkdir($MySQLWorkDir,0755,true);
	$mysqlserv=new mysql_services();
	$mysqlserv->WORKDIR=$MySQLWorkDir;
	$mysqlserv->MYSQL_PID_FILE=$mysql_pid_file;
	$mysqlserv->MYSQL_SOCKET=$MYSQL_SOCKET;
	$mysqlserv->SERV_NAME=$SERV_NAME;
	$mysqlserv->TokenParams="MySQLShoreWallParams";
	$mysqlserv->INSTALL_DATABASE=$GLOBALS["mysql_install_db"];
	$mysqlserv->MYSQL_BIN_DAEMON_PATH=$GLOBALS["MYSQL_BIN_PATH"];
	$mysqlserv->MYSQL_ERRMSG=$GLOBALS["MYSQL_ERRMSG"];
	$mysqlserv->InnoDB=false;	
	
	
	$cmdline=$mysqlserv->BuildParams();
	
	$nohup=$unix->find_program("nohup");
	if($GLOBALS["VERBOSE"]){echo $cmdline."\n";}	

	if($GLOBALS["OUTPUT"]){echo "Starting......: ".date("H:i:s")." [INIT]: $SERV_NAME Starting MySQL daemon ($SERV_NAME)\n";}
	if($GLOBALS["OUTPUT"]){echo "Starting......: ".date("H:i:s")." [INIT]: $SERV_NAME Starting MySQL daemon Output in $TMP\n";}
	$ExecutLine="$nohup $cmdline >$TMP 2>&1 &";
	shell_exec($ExecutLine);
	sleep(1);
	for($i=0;$i<10;$i++){
		$pid=DBPID();
		if($unix->process_exists($pid)){if($GLOBALS["OUTPUT"]){echo "Starting......: ".date("H:i:s")." [INIT]: $SERV_NAME MySQL daemon ($SERV_NAME) started pid .$pid..\n";}break;}
		if($GLOBALS["OUTPUT"]){echo "Starting......: ".date("H:i:s")." [INIT]: $SERV_NAME MySQL daemon wait $i/10\n";}
		sleep(1);
	}	
	sleep(1);
	
	
	
	
	$pid=DBPID();
	if(!$unix->process_exists($pid)){
		if($GLOBALS["OUTPUT"]){echo "Starting......: ".date("H:i:s")." [INIT]: $SERV_NAME MySQL daemon ($SERV_NAME) failed to start\n";}
		if($GLOBALS["OUTPUT"]){echo "$ExecutLine\n";}
		$f=explode("\n",@file_get_contents($TMP));
		while (list ($num, $ligne) = each ($f) ){
			if(trim($ligne)==null){continue;}
			if($GLOBALS["OUTPUT"]){echo "Starting......: ".date("H:i:s")." [INIT]: $SERV_NAME $ligne\n";}
		}
	
	}else{
		if($GLOBALS["OUTPUT"]){echo "Starting......: ".date("H:i:s")." [INIT]: $SERV_NAME MySQL daemon ($SERV_NAME) success\n";}
	}
	
	$mysqlserv->CheckOutputErrors($TMP);
	
	if(!$unix->process_exists($pid)){if($GLOBALS["OUTPUT"]){echo "Starting......: ".date("H:i:s")." [INIT]: $SERV_NAME $cmdline\n";}}
	$unix->THREAD_COMMAND_SET($unix->LOCATE_PHP5_BIN()." ".__FILE__." --databasesize");
}


function stop(){
	$SERV_NAME=$GLOBALS["SERV_NAME"];
	$MYSQL_SOCKET=$GLOBALS["MYSQL_SOCKET"];
	$unix=new unix();
	$pidfile="/etc/artica-postfix/pids/".basename(__FILE__).".".__FUNCTION__.".pid";
	$pid=$unix->get_pid_from_file($pidfile);
	if($unix->process_exists($pid,basename(__FILE__))){
		$time=$unix->PROCCESS_TIME_MIN($pid);
		if($GLOBALS["OUTPUT"]){echo "Stopping......: ".date("H:i:s")." [INIT]: $SERV_NAME Already task running PID $pid since {$time}mn\n";}
		return;
	}

	$pid=DBPID();
	
	if(!$unix->process_exists($pid)){
		if($GLOBALS["OUTPUT"]){echo "Stopping......: ".date("H:i:s")." [INIT]: MySQL daemon ($SERV_NAME) already stopped...\n";}
		return;
	}	
	
	
	$time=$unix->PROCCESS_TIME_MIN($pid);
	if($GLOBALS["OUTPUT"]){echo "Stopping......: ".date("H:i:s")." [INIT]: Stopping MySQL Daemon ($SERV_NAME) with a ttl of {$time}mn\n";}
	$mysqladmin=$unix->find_program("mysqladmin");
	if($GLOBALS["OUTPUT"]){echo "Stopping......: ".date("H:i:s")." [INIT]: Stopping MySQL Daemon ($SERV_NAME) smoothly...\n";}
	$cmd="$mysqladmin --socket=$MYSQL_SOCKET  --protocol=socket --user=root shutdown >/dev/null";
	shell_exec($cmd);

	$pid=DBPID();
	
	if(!$unix->process_exists($pid)){
		if($GLOBALS["OUTPUT"]){echo "Stopping......: ".date("H:i:s")." [INIT]: MySQL daemon ($SERV_NAME) success...\n";}
		return;
	}	
	
	$kill=$unix->find_program("kill");
	for($i=0;$i<10;$i++){
		$pid=DBPID();
		if($unix->process_exists($pid)){
			if($GLOBALS["OUTPUT"]){echo "Stopping......: ".date("H:i:s")." [INIT]: MySQL daemon ($SERV_NAME) kill pid $pid..\n";}
			unix_system_kill_force($pid);
		}else{
			break;
		}
		if($GLOBALS["OUTPUT"]){echo "Starting......: ".date("H:i:s")." [INIT]: MySQL daemon ($SERV_NAME) wait $i/10\n";}
		sleep(1);
	}	
	$pid=DBPID();
	
	if(!$unix->process_exists($pid)){
		if($GLOBALS["OUTPUT"]){echo "Stopping......: ".date("H:i:s")." [INIT]: MySQL daemon ($SERV_NAME) success...\n";}
		@unlink($MYSQL_SOCKET);
		return;
	}	
	if($GLOBALS["OUTPUT"]){echo "Stopping......: ".date("H:i:s")." [INIT]: MySQL daemon ($SERV_NAME) Failed...\n";}
}

function DBPID(){
	$unix=new unix();
	$pid=$unix->get_pid_from_file($GLOBALS["MYSQL_PID"]);
	if($unix->process_exists($pid)){return $pid;}

	if(!isset($GLOBALS["MYSQL_BIN_PATH"])){return;}
	if($GLOBALS["MYSQL_BIN_PATH"]==null){return;}
	$pgrep=$unix->find_program("pgrep");
	
	exec("$pgrep -l -f \"{$GLOBALS["MYSQL_BIN_PATH"]}\" 2>&1",$results);
	while (list ($num, $ligne) = each ($results) ){
		if(preg_match("#pgrep#", $ligne)){continue;}
		if(!preg_match("#^([0-9]+)\s+#", $ligne,$re)){continue;}
		return $re[1];
	}
	
}


function changemysqldir($dir){
	
	
	$unix=new unix();
	$pidfile=$GLOBALS["MYPID"];
	$pid=$unix->get_pid_from_file($pidfile);
	if($unix->process_exists($pid,basename(__FILE__))){
		$time=$unix->PROCCESS_TIME_MIN($pid);
		if($GLOBALS["OUTPUT"]){echo "Starting......: ".date("H:i:s")." [INIT]: Already task running PID $pid since {$time}mn\n";}
		return;
	}
	
	@file_put_contents($pidfile, getmypid());	
	
	initd();
	$dirCMD=$unix->shellEscapeChars($dir);
	if($dir=="{$GLOBALS["WORKDIR"]}/data"){return;}
	@mkdir($dir,0755,true);
	echo "Stopping {$GLOBALS["SERV_NAME"]}";
	shell_exec("/etc/init.d/{$GLOBALS["SERV_NAME"]} stop");
	$Size=$unix->DIRSIZE_BYTES("{$GLOBALS["WORKDIR"]}");
	echo "Copy {$GLOBALS["WORKDIR"]}/data content to next dir size=$Size";
	$cp=$unix->find_program("cp");
	$rm=$unix->find_program("rm");
	$ln=$unix->find_program("ln");
	shell_exec("$cp -rf {$GLOBALS["WORKDIR"]}/data/* $dirCMD/");
	$Size2=$unix->DIRSIZE_BYTES($dir);
	if($Size2<$Size){
		echo "Copy error $Size2 is less than original size ($Size)\n";
	}
	echo "Removing old data\n";
	shell_exec("$rm -rf {$GLOBALS["WORKDIR"]}/data");
	echo "Create a new symbolic link...\n";
	shell_exec("$ln -s $dirCMD {$GLOBALS["WORKDIR"]}/data");
	echo "Starting MySQL database engine...\n";
	shell_exec("/etc/init.d/{$GLOBALS["SERV_NAME"]} start");
	$unix->THREAD_COMMAND_SET($unix->LOCATE_PHP5_BIN()." ".__FILE__." --databasesize");
}
function restart(){
	$unix=new unix();
	$php5=$unix->LOCATE_PHP5_BIN();
	shell_exec("$php5 ".__FILE__." --stop");
	shell_exec("$php5 ".__FILE__." --start");
	
}

function databasesize($force=false){
	$unix=new unix();
	$arrayfile="/usr/share/artica-postfix/ressources/logs/web/{$GLOBALS["SERV_NAME"]}.size.db";
	if($GLOBALS["VERBOSE"]){echo "arrayfile=$arrayfile\n";}
	
	
	if(!$force){
		$pidfile="/etc/artica-postfix/pids/{$GLOBALS["SERV_NAME"]}-databasesize.pid";
		$pid=$unix->get_pid_from_file($pidfile);
		if($unix->process_exists($pid,basename(__FILE__))){
			$time=$unix->PROCCESS_TIME_MIN($pid);
			if($GLOBALS["VERBOSE"]){echo "$pid already exists since {$time}Mn\n";}
			return;
		}
	
		@file_put_contents($pidfile, getmypid());
		$time=$unix->file_time_min($arrayfile);
		if($time<20){
			if($GLOBALS["VERBOSE"]){echo "{$time}Mn require 20mn\n";}
			return;}
	}
	
	$sock=new sockets();
	$MySQLSyslogWorkDir=$sock->GET_INFO("MySQLSyslogWorkDir");
	if($MySQLSyslogWorkDir==null){$MySQLSyslogWorkDir="/home/syslogsdb";}
	$dir=$MySQLSyslogWorkDir."/data";
	if(is_link($dir)){$dir=readlink($dir);}
	$unix=new unix();
	$sizbytes=$unix->DIRSIZE_BYTES($dir);
	if($GLOBALS["VERBOSE"]){echo "sizbytes=$sizbytes\n";}
	$dir=$unix->shellEscapeChars($dir);
	$df=$unix->find_program("df");
	$array["DBSIZE"]=$sizbytes/1024;
	exec("$df -B K $dir 2>&1",$results);
	while (list ($num, $ligne) = each ($results) ){
		if(preg_match("#^.*?\s+([0-9A-Z\.]+)K\s+([0-9A-Z\.]+)K\s+([0-9A-Z\.]+)K\s+([0-9\.]+)%\s+(.+)#", $ligne,$re)){
			$array["SIZE"]=$re[1];
			$array["USED"]=$re[2];
			$array["AIVA"]=$re[3];
			$array["POURC"]=$re[4];
			$array["MOUNTED"]=$re[5];
			break;
		}
	}
	$results=array();
	exec("$df -i $dir 2>&1",$results);
	while (list ($num, $ligne) = each ($results) ){
		if(preg_match("#^.*?\s+([0-9A-Z\.]+)\s+([0-9A-Z\.]+)\s+([0-9A-Z\.]+)\s+([0-9\.]+)%\s+(.+)#", $ligne,$re)){
			$array["ISIZE"]=$re[1];
			$array["IUSED"]=$re[2];
			$array["IAIVA"]=$re[3];
			$array["IPOURC"]=$re[4];
			break;
		}
	}	

	if($GLOBALS["VERBOSE"]) {print_r($array);}
	
	@unlink($arrayfile);
	@file_put_contents($arrayfile, serialize($array));
	if($GLOBALS["VERBOSE"]) {echo "Saving $arrayfile...\n";}
	@chmod($arrayfile, 0755);
	
}
?>