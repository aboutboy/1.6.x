<?php
	include_once('ressources/class.templates.inc');
	include_once('ressources/class.ldap.inc');
	include_once('ressources/class.users.menus.inc');
	include_once('ressources/class.artica.inc');
	include_once('ressources/class.ini.inc');
	include_once('ressources/class.squid.inc');
	include_once('ressources/class.tcpip.inc');
	
	$user=new usersMenus();
	
	if($user->AsSquidAdministrator==false){
		$tpl=new templates();
		echo "alert('". $tpl->javascript_parse_text("{ERROR_NO_PRIVS}")."');";
		die();exit();
	}	
	
	

	if(isset($_GET["popup"])){popup();exit;}
	if(isset($_POST["CacheReplacementPolicy"])){save();exit;}
js();


function js(){
	$t=time();
	$tpl=new templates();
	$page=CurrentPageName();
	$title=$tpl->_ENGINE_parse_body("{caches_options}");
	$html="YahooWin4(687,'$page?popup=yes','$title')";
	echo $html;
}


function popup(){
	$tpl=new templates();
	$page=CurrentPageName();
	$sock=new sockets();
	$CacheReplacementPolicy=$sock->GET_INFO("CacheReplacementPolicy");
	
	if($CacheReplacementPolicy==null){$CacheReplacementPolicy="heap_LFUDA";}
	$SquidDebugCacheProc=$sock->GET_INFO("SquidDebugCacheProc");
	$ForceWindowsUpdateCaching=$sock->GET_INFO("ForceWindowsUpdateCaching");
	$ProxyDedicateMicrosoftRules=$sock->GET_INFO("ProxyDedicateMicrosoftRules");
	if(!is_numeric($SquidDebugCacheProc)){$SquidDebugCacheProc=0;}
	if(!is_numeric($ForceWindowsUpdateCaching)){$ForceWindowsUpdateCaching=0;}
	if(!is_numeric($ProxyDedicateMicrosoftRules)){$ProxyDedicateMicrosoftRules=0;}
	
	
	
	$refresh_pattern_def_min=$sock->GET_INFO("refresh_pattern_def_min");
	$refresh_pattern_def_max=$sock->GET_INFO("refresh_pattern_def_max");
	$refresh_pattern_def_perc=$sock->GET_INFO("refresh_pattern_def_perc");
	
	if(!is_numeric($refresh_pattern_def_min)){$refresh_pattern_def_min=0;}
	if(!is_numeric($refresh_pattern_def_max)){$refresh_pattern_def_max=43200;}
	if(!is_numeric($refresh_pattern_def_perc)){$refresh_pattern_def_perc=80;}
	
	
	$squid=new squidbee();
	$t=time();
	$array["lru"]="{cache_lru}";
	$array["heap_GDSF"]="{heap_GDSF}";
	$array["heap_LFUDA"]="{heap_LFUDA}";
	$array["heap_LRU"]="{heap_LRU}";
	

	


	$html="
	<div id='$t'></div>
	<table style='width:99%' class=form>
		<tr>
			<td class=legend style='font-size:14px'>{ForceWindowsUpdateCaching}:</td>
			<td>". Field_checkbox("ForceWindowsUpdateCaching-$t",1,$ForceWindowsUpdateCaching)."</td>
		</tr>
		<tr>
			<td class=legend style='font-size:14px'>{ProxyDedicateMicrosoftRules}:</td>
			<td>". Field_checkbox("ProxyDedicateMicrosoftRules-$t",1,$ProxyDedicateMicrosoftRules)."</td>
		</tr>			
					
					
				
	<tr>
		<td class=legend style='font-size:14px'>{cache_replacement_policy}:</td>
		<td>". Field_array_Hash($array, "CacheReplacementPolicy-$t",$CacheReplacementPolicy,null,null,0,"font-size:14px")."</td>
		<td width=1%>" . help_icon('{cache_replacement_policy_explain}',true)."</td>
	</tr>
	<tr>
			<td style='font-size:14px' class=legend>{maximum_object_size}:</td>
			<td align='left' style='font-size:14px'>" . Field_text("maximum_object_size-$t",$maximum_object_size,'width:90px;font-size:14px')."&nbsp;MB</td>
			<td width=1%>" . help_icon('{maximum_object_size_text}',true)."</td>
	</tr>
				
	<tr>
			<td style='font-size:14px' class=legend>{debug_cache_processing}:</td>
			<td align='left' style='font-size:14px'>" . Field_checkbox("SquidDebugCacheProc-$t",1,$SquidDebugCacheProc)."</td>
			<td width=1%></td>
	</tr>					
	<tr>
	<td colspan=3 align='right'><hr>". button("{apply}","Save$t()","18px")."</td>
	</tr>			
	</table>	
<script>
		var x_Save$t= function (obj) {
			var results=obj.responseText;
			if(results.length>3){alert(results);}
			YahooWin4Hide();
		}		

		function Save$t(){
			var SquidDebugCacheProc=0;
			
			var ForceWindowsUpdateCaching=0;
			var ProxyDedicateMicrosoftRules=0;
			var XHR = new XHRConnection();
			if(document.getElementById('SquidDebugCacheProc-$t').checked){SquidDebugCacheProc=1;}
			if(document.getElementById('ForceWindowsUpdateCaching-$t').checked){ForceWindowsUpdateCaching=1;}
			if(document.getElementById('ProxyDedicateMicrosoftRules-$t').checked){ProxyDedicateMicrosoftRules=1;}
			
			
			XHR.appendData('CacheReplacementPolicy',document.getElementById('CacheReplacementPolicy-$t').value);
			XHR.appendData('maximum_object_size',document.getElementById('maximum_object_size-$t').value);
			XHR.appendData('SquidDebugCacheProc',SquidDebugCacheProc);
			XHR.appendData('ForceWindowsUpdateCaching',ForceWindowsUpdateCaching);
			XHR.appendData('ProxyDedicateMicrosoftRules',ProxyDedicateMicrosoftRules);
			AnimateDiv('$t');
			XHR.sendAndLoad('$page', 'POST',x_Save$t);
		}
		
		
		
</script>				
	";
	
	echo $tpl->_ENGINE_parse_body($html);
	
	
}

function save(){
	$sock=new sockets();
	$sock->SET_INFO("CacheReplacementPolicy", $_POST["CacheReplacementPolicy"]);
	$sock->SET_INFO("SquidDebugCacheProc", $_POST["SquidDebugCacheProc"]);
	
	$sock->SET_INFO("ForceWindowsUpdateCaching", $_POST["ForceWindowsUpdateCaching"]);
	$sock->SET_INFO("ProxyDedicateMicrosoftRules", $_POST["ProxyDedicateMicrosoftRules"]);
	$squid=new squidbee();
	$squid->global_conf_array["maximum_object_size"]=$_POST["maximum_object_size"]." MB";
	$squid->SaveToLdap(true);
	$tpl=new templates();
	echo $tpl->javascript_parse_text("{must_restart_proxy_settings}");
	
}

?>
	