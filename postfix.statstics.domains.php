<?php
//postfix.statstics.domains.php
include_once('ressources/class.artica.graphs.inc');
include_once('ressources/class.templates.inc');
include_once('ressources/class.users.menus.inc');
include_once('ressources/class.templates.inc');
include_once('ressources/class.ldap.inc');
include_once('ressources/class.mysql.inc');

$user=new usersMenus();
if($user->AsPostfixAdministrator==false){
	echo FATAL_ERROR_SHOW_128("{ERROR_NO_PRIVS}");
	die();exit();
}

if(isset($_GET["list"])){search();exit;}

table();



function table(){
	$t=time();
	$page=CurrentPageName();
	$tpl=new templates();
	$users=new usersMenus();
	$sock=new sockets();
	$t=time();
	$apply=$tpl->javascript_parse_text("{apply}");
	$domain=$tpl->javascript_parse_text("{domains}");
	$connections=$tpl->javascript_parse_text("{connections}");
	$greylisted=$tpl->javascript_parse_text("{greylisted}");
	$blacklisted=$tpl->javascript_parse_text("{blacklisted}");
	$title=$tpl->javascript_parse_text("{domains_found_in_smtp_connections}");
		$LOCAL_MDA=false;
		if($users->cyrus_imapd_installed){$LOCAL_MDA=true;}
		if($users->ZARAFA_INSTALLED){$LOCAL_MDA=true;}
		if(!$LOCAL_MDA){$localdomainButton=null;}
		$buttons="
		buttons : [
		$localdomainButton
		{name: '<strong style=font-size:18px>$add_relay_domain</strong>', bclass: 'add', onpress : add_relay_domain$t},$parametersButton$applybutton$aboutButton
		],";
		
		
		$buttons=null;
	
		$explain=$tpl->javascript_parse_text("{postfix_transport_table_explain}");
	
		$html="
<table class='flexRT$t' style='display: none' id='flexRT$t' style='width:100%'></table>
<script>
$(document).ready(function(){
	$('#flexRT$t').flexigrid({
		url: '$page?list=yes',
		dataType: 'json',
		colModel : [
		{display: '<span style=font-size:22px>$domain</span>', name : 'domain', width : 858, sortable : true, align: 'left'},
		{display: '<span style=font-size:22px>$connections</span>', name : 'CNX', width :162, sortable : true, align: 'right'},
		{display: '<span style=font-size:22px>$blacklisted</span>', name : 'BLACK', width : 162, sortable : true, align: 'right'},
		{display: '<span style=font-size:22px>$greylisted</span>', name : 'GREY', width : 162, sortable : true, align: 'right'},
		],
		$buttons
		searchitems : [
		{display: '$domain', name : 'domain'},
		],
		sortname: 'CNX',
		sortorder: 'desc',
		usepager: true,
		title: '<span style=font-size:26px>$title</span>',
		useRp: true,
		rp: 50,
		showTableToggleBtn: false,
		width: '99%',
		height: 550,
		singleSelect: true,
		rpOptions: [10, 20, 30, 50,100,200]
	
	});
	});
	
	function About$t(){
	alert('$explain');
	}
	function Apply$t(){
	Loadjs('postfix.transport.progress.php');
	}
	
	function add_relay_domain$t(){
	Loadjs('$page?relaydomain-js=yes&domain=&t=$t');
	}
	
	function add_local_domain$t(){
	Loadjs('$page?localdomain-js=yes&domain=&t=$t');
	}
	
	function DeleteLocalDomain$t(domain){
	Loadjs('$page?localdomain-delete-js=yes&domain='+domain+'&t=$t');
	}
	
	function DeleteTransportDomain$t(domain){
	Loadjs('$page?relaydomain-delete-js=yes&domain='+domain+'&t=$t');
	}
	
	function parameters$t(){
	YahooWin4('550','$page?all-domains-parameters=yes&t=$t','$parameters');
	}
	
	
	</script>
	";
	
	echo $html;
}

function search(){
	$tpl=new templates();
	$MyPage=CurrentPageName();
	$q=new mysql();
	$t=$_GET["t"];
	$search='%';
	$table="smtpstats_day";
	$page=1;
	if($q->COUNT_ROWS("smtpstats_day","artica_events")==0){json_error_show("smtpstats_day: no item,1");}
	
	$table="(SELECT SUM(GREY) as GREY, SUM(BLACK) AS BLACK, SUM(CNX) as CNX,domain FROM smtpstats_day GROUP BY domain) as t";

	if(isset($_POST["sortname"])){
		if($_POST["sortname"]<>null){
			if($_POST["sortname"]=="servername"){$_POST["sortname"]="value";}
			$ORDER="ORDER BY {$_POST["sortname"]} {$_POST["sortorder"]}";
		}
	}

	if (isset($_POST['page'])) {$page = $_POST['page'];}

	$searchstring=string_to_flexquery();
	if($searchstring<>null){
		$sql="SELECT COUNT(*) as TCOUNT FROM $table WHERE 1 $searchstring $FORCE";
		$ligne=mysql_fetch_array($q->QUERY_SQL($sql,"artica_events"));
		$total = $ligne["TCOUNT"];

	}else{
		$sql="SELECT COUNT(*) as TCOUNT FROM $table WHERE 1 $FORCE";
		$ligne=mysql_fetch_array($q->QUERY_SQL($sql,"artica_events"));
		$total = $ligne["TCOUNT"];
	}

	if (isset($_POST['rp'])) {$rp = $_POST['rp'];}



	$pageStart = ($page-1)*$rp;
	$limitSql = "LIMIT $pageStart, $rp";
	$sql="SELECT *  FROM $table WHERE 1 $searchstring $FORCE $ORDER $limitSql";
	writelogs($sql,__FUNCTION__,__FILE__,__LINE__);

	$data = array();
	$data['page'] = $page;
	$data['total'] = $total;
	$data['rows'] = array();
	$results = $q->QUERY_SQL($sql,"artica_events");
	if(!$q->ok){json_error_show($q->mysql_error,0);}

	$divstart="<span style='font-size:12px;font-weight:normal'>";
	$divstop="</div>";
	if((mysql_num_rows($results)==0)){json_error_show("no data");}


	while ($ligne = mysql_fetch_assoc($results)) {
		$id=md5(serialize($ligne));
		$color="black";
		$color_black="black";
		$fontweight="normal";
		if($ligne["BLACK"]>0){$color_black="#d32d2d";}
		if($ligne["GREY"]>0){$fontweight="bold";}
		$BLACK=FormatNumber($ligne["BLACK"]);
		$GREY=FormatNumber($ligne["GREY"]);
		$CNX=FormatNumber($ligne["CNX"]);
		$domain=$ligne["domain"];
		$data['rows'][] = array(
			'id' => $ligne['ID'],
			'cell' => array(
				"<span style='font-size:18px;color:$color'>$domain</strong>",
				"<strong  style='font-size:18px;color:$color'>$CNX</strong><a>",
				"<span  style='font-size:18px;color:$color_black'>$BLACK</span>",
				"<span style='font-size:18px;color:$color;font-weight:$fontweight'>$GREY</strong></a>")
				);
			}


			echo json_encode($data);



}
function FormatNumber($number, $decimals = 0, $thousand_separator = '&nbsp;', $decimal_point = '.'){$tmp1 = round((float) $number, $decimals); while (($tmp2 = preg_replace('/(\d+)(\d\d\d)/', '\1 \2', $tmp1)) != $tmp1)$tmp1 = $tmp2; return strtr($tmp1, array(' ' => $thousand_separator, '.' => $decimal_point));}