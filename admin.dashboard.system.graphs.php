<?php
if(isset($_GET["verbose"])){$GLOBALS["VERBOSE"]=true;$GLOBALS["DEBUG_MEM"]=true;ini_set('display_errors', 1);ini_set('error_reporting', E_ALL);ini_set('error_prepend_string',null);ini_set('error_append_string',null);}
$GLOBALS["BASEDIR"]="/usr/share/artica-postfix/ressources/interface-cache";
include_once(dirname(__FILE__).'/ressources/class.html.pages.inc');
include_once(dirname(__FILE__).'/ressources/class.cyrus.inc');
include_once(dirname(__FILE__).'/ressources/class.main_cf.inc');
include_once(dirname(__FILE__).'/ressources/charts.php');
include_once(dirname(__FILE__).'/ressources/class.syslogs.inc');
include_once(dirname(__FILE__).'/ressources/class.system.network.inc');
include_once(dirname(__FILE__).'/ressources/class.os.system.inc');
include_once(dirname(__FILE__).'/ressources/class.stats-appliance.inc');
include_once(dirname(__FILE__).'/ressources/class.templates.inc');
include_once(dirname(__FILE__).'/ressources/class.os.system.tools.inc');
include_once(dirname(__FILE__).'/ressources/class.mysql.catz.inc');



if(!IsRights()){
	echo FATAL_ERROR_SHOW_128("{ERROR_NO_PRIVS}");
	die();
}

if(isset($_GET["cpu-day"])){cpu_day();exit;}
if(isset($_GET["load-day"])){load_day();exit;}
if(isset($_GET["memory-day"])){memory_day();exit;}



if(isset($_GET["cpu-week"])){cpu_week();exit;}
if(isset($_GET["load-week"])){load_week();exit;}
if(isset($_GET["memory-week"])){memory_week();exit;}

page();

function IsRights(){


	$users=new usersMenus();
	if($users->AsSquidAdministrator){return true;}
	if($users->AsSystemAdministrator){return true;}
	if($users->AsWebMaster){return true;}

}

function page(){
	$page=CurrentPageName();
	$tpl=new templates();
	$html[]="<div style='font-size:30px;margin-bottom:20px'>{your_server_performance}</div>";
	
	
	$filecache=dirname(__FILE__)."/ressources/logs/web/cpustatsH.db";
	$filecache_load=dirname(__FILE__)."/ressources/logs/web/INTERFACE_LOAD_AVGH.db";
	$filecache_mem=dirname(__FILE__)."/ressources/logs/web/INTERFACE_LOAD_AVG2H.db";
	
	
	
	$filecache_array=unserialize(@file_get_contents($filecache));
	
	if(count($filecache_array[0])>1){
	
		$html[]="<div id='cpu-day' style='with:1450;height:350px'></div>";
		$js[]="Loadjs('$page?cpu-day=yes');";
	
	}
	
	
	$filecache_array=unserialize(@file_get_contents($filecache_load));
	
	if(count($filecache_array[0])>1){
	
		$html[]="<div id='load-day' style='with:1450;height:350px'></div>";
		$js[]="Loadjs('$page?load-day=yes');";
	
	}
	
	$filecache_array=unserialize(@file_get_contents($filecache_mem));
	
	if(count($filecache_array[0])>1){
	
		$html[]="<div id='memory-day' style='with:1450;height:350px'></div>";
		$js[]="Loadjs('$page?memory-day=yes');";
	
	}	
	
	
	
// -----------------------------------------------------------------------------------------------------------	
	
	
	
	$filecache=dirname(__FILE__)."/ressources/logs/web/cpustats.db";
	$filecache_load=dirname(__FILE__)."/ressources/logs/web/INTERFACE_LOAD_AVG.db";
	$filecache_mem=dirname(__FILE__)."/ressources/logs/web/INTERFACE_LOAD_AVG2.db";
	
	
	
	$filecache_array=unserialize(@file_get_contents($filecache));
	
	if(count($filecache_array[0])>1){
		
		$html[]="<div id='cpu-week' style='with:1450;height:350px'></div>";
		$js[]="Loadjs('$page?cpu-week=yes');";
		
	}
	
	
	$filecache_array=unserialize(@file_get_contents($filecache_load));
	
	if(count($filecache_array[0])>1){
	
		$html[]="<div id='load-week' style='with:1450;height:350px'></div>";
		$js[]="Loadjs('$page?load-week=yes');";
	
	}
	
	$filecache_array=unserialize(@file_get_contents($filecache_mem));
	
	if(count($filecache_array[0])>1){
	
		$html[]="<div id='memory-week' style='with:1450;height:350px'></div>";
		$js[]="Loadjs('$page?memory-week=yes');";
	
	}	
	
	
	echo $tpl->_ENGINE_parse_body(@implode("\n", $html)."<script>".@implode("\n" ,$js)."</script>");
	
}

function cpu_week(){
	$tpl=new templates();
	$filecache=dirname(__FILE__)."/ressources/logs/web/cpustats.db";
	$filecache_array=unserialize(@file_get_contents($filecache));

	$title="{cpu_average_last7days}";
	$timetext="{hours}";
	$highcharts=new highcharts();
	$highcharts->container="cpu-week";
	$highcharts->xAxis=$filecache_array[0];
	$highcharts->Title=$title;
	$highcharts->TitleFontSize="22px";
	$highcharts->AxisFontsize="12px";
	$highcharts->yAxisTtitle="% CPU (AVERAGE)";
	$highcharts->xAxis_labels=false;
	$highcharts->LegendPrefix=$tpl->javascript_parse_text('{date}: ');
	$highcharts->LegendSuffix="%";
	$highcharts->xAxisTtitle=$timetext;

	$highcharts->datas=array("{percent}"=>$filecache_array[1]);
	echo $highcharts->BuildChart();
}
function load_day(){
	$tpl=new templates();
	$filecache=dirname(__FILE__)."/ressources/logs/web/INTERFACE_LOAD_AVGH.db";
	$filecache_array=unserialize(@file_get_contents($filecache));

	$title="{load_average_last24h}";
	$timetext="{hours}";
	$highcharts=new highcharts();
	$highcharts->container="load-day";
	$highcharts->xAxis=$filecache_array[0];
	$highcharts->Title=$title;
	$highcharts->TitleFontSize="22px";
	$highcharts->AxisFontsize="12px";
	$highcharts->yAxisTtitle="LOAD";
	$highcharts->xAxis_labels=false;
	$highcharts->LegendPrefix=$tpl->javascript_parse_text('{date}: ');
	$highcharts->LegendSuffix=" (LOAD)";
	$highcharts->xAxisTtitle=$timetext;

	$highcharts->datas=array("{load2}"=>$filecache_array[1]);
	echo $highcharts->BuildChart();
}
function load_week(){
	$tpl=new templates();
	$filecache=dirname(__FILE__)."/ressources/logs/web/INTERFACE_LOAD_AVG.db";
	$filecache_array=unserialize(@file_get_contents($filecache));

	$title="{load_average_last7days}";
	$timetext="{hours}";
	$highcharts=new highcharts();
	$highcharts->container="load-week";
	$highcharts->xAxis=$filecache_array[0];
	$highcharts->Title=$title;
	$highcharts->TitleFontSize="22px";
	$highcharts->AxisFontsize="12px";
	$highcharts->yAxisTtitle="LOAD";
	$highcharts->xAxis_labels=false;
	$highcharts->LegendPrefix=$tpl->javascript_parse_text('{date}: ');
	$highcharts->LegendSuffix=" (LOAD)";
	$highcharts->xAxisTtitle=$timetext;

	$highcharts->datas=array("{load2}"=>$filecache_array[1]);
	echo $highcharts->BuildChart();
}
function cpu_day(){
	$tpl=new templates();
	$filecache=dirname(__FILE__)."/ressources/logs/web/cpustatsH.db";
	$filecache_array=unserialize(@file_get_contents($filecache));

	$title="{cpu_average_last24h}";
	$timetext="{time}";
	$highcharts=new highcharts();
	$highcharts->container="cpu-day";
	$highcharts->xAxis=$filecache_array[0];
	$highcharts->Title=$title;
	$highcharts->TitleFontSize="22px";
	$highcharts->AxisFontsize="12px";
	$highcharts->yAxisTtitle="% CPU";
	$highcharts->xAxis_labels=false;
	$highcharts->LegendPrefix=$tpl->javascript_parse_text('{date}: ');
	$highcharts->LegendSuffix=" %";
	$highcharts->xAxisTtitle=$timetext;

	$highcharts->datas=array("{percent}"=>$filecache_array[1]);
	echo $highcharts->BuildChart();
}

function memory_day(){
	$tpl=new templates();
	$filecache=dirname(__FILE__)."/ressources/logs/web/INTERFACE_LOAD_AVG2H.db";
	$filecache_array=unserialize(@file_get_contents($filecache));

	$title="{memory_average_last24h}";
	$timetext="{hours}";
	$highcharts=new highcharts();
	$highcharts->container="memory-day";
	$highcharts->xAxis=$filecache_array[0];
	$highcharts->Title=$title;
	$highcharts->TitleFontSize="22px";
	$highcharts->AxisFontsize="12px";
	$highcharts->yAxisTtitle="MEM (MB)";
	$highcharts->xAxis_labels=false;
	$highcharts->LegendPrefix=$tpl->javascript_parse_text('{date}: ');
	$highcharts->LegendSuffix=" (MB)";
	$highcharts->xAxisTtitle=$timetext;

	$highcharts->datas=array("{memory}"=>$filecache_array[1]);
	echo $highcharts->BuildChart();
}

function memory_week(){
	$tpl=new templates();
	$filecache=dirname(__FILE__)."/ressources/logs/web/INTERFACE_LOAD_AVG2.db";
	$filecache_array=unserialize(@file_get_contents($filecache));
	
	$title="{memory_average_last7days}";
	$timetext="{hours}";
	$highcharts=new highcharts();
	$highcharts->container="memory-week";
	$highcharts->xAxis=$filecache_array[0];
	$highcharts->Title=$title;
	$highcharts->TitleFontSize="22px";
	$highcharts->AxisFontsize="12px";
	$highcharts->yAxisTtitle="MEM (GB)";
	$highcharts->xAxis_labels=false;
	$highcharts->LegendPrefix=$tpl->javascript_parse_text('{date}: ');
	$highcharts->LegendSuffix=" (GB)";
	$highcharts->xAxisTtitle=$timetext;
	
	$highcharts->datas=array("{memory}"=>$filecache_array[1]);
	echo $highcharts->BuildChart();
}