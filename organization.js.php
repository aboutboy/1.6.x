<?php
	session_start();
	if(isset($_GET["verbose"])){$GLOBALS["VERBOSE"]=true;ini_set('display_errors', 1);ini_set('error_reporting', E_ALL);}
	if($_SESSION["uid"]==null){echo "window.location.href ='logoff.php';";die();}
	include_once('ressources/class.templates.inc');
	include_once('ressources/class.ldap.inc');
	include_once('ressources/class.users.menus.inc');
	include_once('ressources/class.artica.inc');
	include_once('ressources/class.pure-ftpd.inc');
	include_once('ressources/class.user.inc');
	include_once('ressources/charts.php');
	include_once('ressources/class.mimedefang.inc');
	include_once('ressources/class.computers.inc');
	include_once('ressources/class.ini.inc');	
	
	
if(isset($_GET["add-ou"])){add_ou();exit;}

function add_ou(){
	$tpl=new templates();
	$add_new_organisation_text=$tpl->javascript_parse_text("{add_new_organisation_text}");
	$t=time();
	header("content-type: application/x-javascript");
$html="

var xTreeAddNewOrganisation$t = function (obj) {
	var response=obj.responseText;
	if(response){alert(response);}

	if(document.getElementById('TEMPLATE_LEFT_MENUS')){LoadAjax('TEMPLATE_LEFT_MENUS','admin.tabs.php?left-menus=yes',true);}
	if(document.getElementById('admin_perso_tabs')){RefreshTab('admin_perso_tabs');}
	YahooWinHide();
	 


	if(document.getElementById('orgs')){
		if(document.getElementById('ajaxmenu')){
			YahooWin5(750,'domains.index.php?ajaxmenu=yes');
			return;
		}
		LoadAjax('orgs','domains.index.php?ShowOrganizations=yes');
	}
}

function TreeAddNewOrganisation$t(){
	var texte=document.getElementById('$add_new_organisation_text').value
	var org=prompt(texte,'');
	if(org){
		var XHR = new XHRConnection();
		XHR.appendData('TreeAddNewOrganisation',org);
		XHR.sendAndLoad('domains.php', 'GET',xTreeAddNewOrganisation$t);
	}

}

TreeAddNewOrganisation$t();
";

echo $html;
}