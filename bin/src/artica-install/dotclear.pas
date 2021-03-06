unit dotclear;

{$MODE DELPHI}
{$LONGSTRINGS ON}

interface

uses
    Classes, SysUtils,variants,strutils,Process,logs,unix,RegExpr in 'RegExpr.pas',zsystem;

type
  TStringDynArray = array of string;

  type
  tdotclear=class


private
     LOGS:Tlogs;
     SYS:TSystem;
     artica_path:string;
    DotClearHttpListenPort:integer;
    EnableDotClearHTTPService:integer;
    DotClearExternalAdminUri:string;
    procedure   LIGHTTPD_DEFAULT_CONF();
    function    Explode(const Separator, S: string; Limit: Integer = 0):TStringDynArray;
    procedure   master_config();

public
    procedure   Free;
    constructor Create(const zSYS:Tsystem);
    procedure   START();
    function    LIGHTTPD_BIN_PATH():string;
    function    LIGHTTPD_LOG_PATH():string;
    function    LIGHTTPD_SOCKET_PATH():string;
    function    LIGHTTPD_PID():string;
    function    LIGHTTPD_GET_USER():string;
    function    HTTPD_CONF_PATH:string;
    function    LIGHTTPD_PID_PATH():string;
    procedure   STOP();
    FUNCTION    STATUS():string;
    function    PHP5_CGI_BIN_PATH():string;
    function    LIGHTTPD_LISTEN_PORT():string;
    function    DEFAULT_CONF():string;
    function    VERSION():string;
    procedure   CreateHome(HomeDirectory:string;uid:string);
    procedure   RELOAD();

END;

implementation

constructor tdotclear.Create(const zSYS:Tsystem);
begin
       forcedirectories('/etc/artica-postfix');
       forcedirectories('/opt/artica/tmp');
       LOGS:=tlogs.Create();
       SYS:=zSYS;
       
       if not TryStrToInt(SYS.GET_INFO('DotClearHttpListenPort'),DotClearHttpListenPort) then DotClearHttpListenPort:=82;
       if not TryStrToInt(SYS.GET_INFO('EnableDotClearHTTPService'),EnableDotClearHTTPService) then DotClearHttpListenPort:=1;
       DotClearExternalAdminUri:=SYS.GET_INFO('DotClearExternalAdminUri');
       if length(DotClearExternalAdminUri)=0 then DotClearExternalAdminUri:='http://localhost:'+IntToStr(DotClearHttpListenPort)+'/admin';
       
       

       if not DirectoryExists('/usr/share/artica-postfix') then begin
              artica_path:=ParamStr(0);
              artica_path:=ExtractFilePath(artica_path);
              artica_path:=AnsiReplaceText(artica_path,'/bin/','');

      end else begin
          artica_path:='/usr/share/artica-postfix';
      end;
end;
//##############################################################################
procedure tdotclear.free();
begin
    logs.Free;
    SYS.Free;
end;
//##############################################################################
function tdotclear.LIGHTTPD_BIN_PATH():string;
begin
exit(SYS.LOCATE_LIGHTTPD_BIN_PATH());
end;
//##############################################################################
function tdotclear.PHP5_CGI_BIN_PATH():string;
begin
   if FileExists('/usr/bin/php-cgi') then exit('/usr/bin/php-cgi');
   if FileExists('/opt/artica/lighttpd/bin/php-cgi') then exit('/opt/artica/lighttpd/bin/php-cgi');
   if FileExists('/usr/local/bin/php-cgi') then exit('/usr/local/bin/php-cgi');
end;
//##############################################################################
function tdotclear.HTTPD_CONF_PATH:string;
begin
  exit('/etc/artica-postfix/lighttpd-dotclear.conf');
end;
//##############################################################################
function tdotclear.LIGHTTPD_PID_PATH():string;
var
RegExpr:TRegExpr;
l:TStringList;
i:integer;
begin


if not FileExists(HTTPD_CONF_PATH()) then begin
   logs.Debuglogs('LIGHTTPD_PID_PATH:: unable to stat lighttpd.conf');
   exit;
end;
l:=TstringList.Create;
l.LoadFromFile(HTTPD_CONF_PATH());
RegExpr:=TRegExpr.Create;
RegExpr.Expression:='^server\.pid-file.+?"(.+?)"';
for i:=0 to l.Count-1 do begin
   if RegExpr.Exec(l.Strings[i]) then begin
    result:=RegExpr.Match[1];
    break;
   end;
end;

   l.Free;
   RegExpr.free;
end;
//##############################################################################
function tdotclear.LIGHTTPD_GET_USER();
var
     l:TstringList;
     RegExpr:TRegExpr;
     i:integer;
     user,group:string;
begin
  if not FileExists(HTTPD_CONF_PATH()) then exit;

  user:=SYS.GET_INFO('LighttpdUserAndGroup');
  if length(user)>0 then begin
     result:=user;
     exit;
  end;

  l:=TstringList.Create;
  RegExpr:=TRegExpr.Create;
  l.LoadFromFile(HTTPD_CONF_PATH());
  for i:=0 to l.Count-1 do begin
    RegExpr.Expression:='^server\.username.+?"(.+?)"';
    if RegExpr.Exec(l.Strings[i]) then user:=RegExpr.Match[1];
    RegExpr.Expression:='^server\.groupname.+?"(.+?)"';
    if RegExpr.Exec(l.Strings[i]) then group:=RegExpr.Match[1];
  end;
  if length(user)>0 then result:=user+':'+group;
  RegExpr.free;
  l.free;
end;
//##############################################################################
procedure tdotclear.START();
var
   pid:string;
   user:string;
   logs_path:string;

begin
logs.Debuglogs('###################### DOTCLEAR #####################');
if not FIleExists('/usr/share/dotclear/index.php') then begin
    logs.Debuglogs('Starting......: lighttpd daemon for dotclear dotclear is not installed..');
    exit;
end;




   if not FileExists(LIGHTTPD_BIN_PATH()) then begin
       logs.Debuglogs('tdotclear.START():: it seems that lighttpd is not installed... Aborting');
       exit;
   end;
   
   if EnableDotClearHTTPService=0 then begin
       logs.Debuglogs('Starting......: lighttpd daemon for dotclear is disabled');
       STOP();
       exit;
   end;

   if not FileExists(HTTPD_CONF_PATH()) then DEFAULT_CONF();

   pid:=LIGHTTPD_PID();
   if pid='0' then pid:=SYS.PROCESS_LIST_PID(LIGHTTPD_BIN_PATH() +' -f ' + HTTPD_CONF_PATH());

   if SYS.PROCESS_EXIST(pid) then begin
      logs.Debuglogs('Starting......: lighttpd daemon for dotclear is already running using PID ' + LIGHTTPD_PID() + '...');
      logs.Debuglogs('LIGHTTPD_START():: lighttpd daemon for dotclear already running with PID number ' + pid);
      exit();
   end;

   logs.Debuglogs('LIGHTTPD_START():: Creating user www-data if does not exists');
   logs_path:=LIGHTTPD_LOG_PATH();
   user:=LIGHTTPD_GET_USER();
   master_config();




   forcedirectories('/opt/artica/ssl/certs');
   forcedirectories('/var/lib/php/session');
   logs.OutputCmd('/bin/chown -R '+user+' /var/lib/php/session');
       if not SYS.PROCESS_EXIST(pid) then begin
          LIGHTTPD_DEFAULT_CONF();
          forcedirectories('/var/run/lighttpd');
          forcedirectories(logs_path);
          logs.Debuglogs('Starting lighttpd............: lighttpd binary '+SYS.LOCATE_LIGHTTPD_ANGEL_BIN_PATH());
          logs.OutputCmd('/bin/chown -R '+user+' /var/run/lighttpd');
          logs.OutputCmd('/bin/chown -R '+user+' '+logs_path);
          logs.OutputCmd('/bin/chown -R '+user+' /usr/share/dotclear');
          
          if not logs.IF_TABLE_EXISTS('dotclear_user','artica_backup') then begin
                logs.Debuglogs('Starting lighttpd............: Create dotclear tables...');
                logs.EXECUTE_SQL_FILE(artica_path+'/bin/install/dotclear.sql','artica_backup');
          end;
          
          logs.OutputCmd(SYS.LOCATE_LIGHTTPD_ANGEL_BIN_PATH()+ ' -f '+ HTTPD_CONF_PATH());
          logs.OutputCmd(SYS.LOCATE_PHP5_BIN()+' ' + artica_path+'/cron.dotclear.master.php');

   if not SYS.PROCESS_EXIST(LIGHTTPD_PID()) then begin
      logs.Debuglogs('Starting lighttpd............: lighttpd daemon for dotclear Failed');
      end else begin
      logs.Debuglogs('Starting lighttpd............: lighttpd daemon for dotclear Success (PID ' + LIGHTTPD_PID() + ')');
      end;
   end;
end;
//##############################################################################
procedure tdotclear.STOP();
 var
    count      :integer;
begin

     count:=0;
     
if not FIleExists('/usr/share/dotclear/index.php') then begin
    writeln('Stopping lighttpd............: lighttpd daemon for dotclear dotclear is not installed');
    exit;
end;

     if SYS.PROCESS_EXIST(LIGHTTPD_PID()) then begin
        writeln('Stopping lighttpd............: lighttpd daemon for dotclear  ' + LIGHTTPD_PID() + ' PID..');
        logs.OutputCmd('/bin/kill ' + LIGHTTPD_PID());
        while SYS.PROCESS_EXIST(LIGHTTPD_PID()) do begin
              sleep(100);
              inc(count);
              if count>100 then begin
                 writeln('Stopping lighttpd............: lighttpd daemon for dotclear Failed force kill');
                 logs.OutputCmd('/bin/kill -9 '+LIGHTTPD_PID());
                 exit;
              end;
        end;

      end else begin
        writeln('Stopping lighttpd............: lighttpd daemon for dotclear Already stopped');
     end;

end;
//##############################################################################
procedure tdotclear.RELOAD();
 var
    pid:string;
begin

if not FIleExists('/usr/share/dotclear/index.php') then begin
    logs.Debuglogs('Starting lighttpd............: lighttpd daemon for dotclear dotclear is not installed');
    exit;
end;
     pid:=LIGHTTPD_PID();
     if SYS.PROCESS_EXIST(pid) then begin
           logs.Debuglogs('Starting lighttpd............: lighttpd daemon for dotclear reload pid '+pid);
           fpsystem('/bin/kill -HUP ' + pid);
      end else begin
        logs.Debuglogs('Starting lighttpd............: lighttpd daemon for dotclear Already stopped');
     end;

end;
//##############################################################################
FUNCTION tdotclear.STATUS():string;
var
   ini:TstringList;
begin

  if not FIleExists('/usr/share/dotclear/index.php') then exit;
  ini:=TstringList.Create;
  ini.Add('[DOTCLEAR]');
  ini.Add('service_name=APP_DOTCLEAR');
  ini.Add('service_cmd=dotclear');
  ini.Add('master_version=' + VERSION());
  ini.Add('application_enabled='+IntToStr(EnableDotClearHTTPService));
  ini.Add('service_disabled='+IntToStr(EnableDotClearHTTPService));

  if EnableDotClearHTTPService=0 then begin
        result:=ini.Text;
        ini.free;

        exit;
  end;



   if SYS.PROCESS_EXIST(LIGHTTPD_PID()) then ini.Add('running=1') else  ini.Add('running=0');
      ini.Add('application_installed=1');
      ini.Add('master_pid='+LIGHTTPD_PID());
      ini.Add('master_memory=' + IntToStr(SYS.PROCESS_MEMORY(LIGHTTPD_PID())));
      ini.Add('status='+SYS.PROCESS_STATUS(LIGHTTPD_PID()));



result:=ini.Text;
ini.free
end;
//#########################################################################################
function tdotclear.VERSION();
var
RegExpr:TRegExpr;
l:TStringList;
i:integer;
begin
 if not FileExists('/usr/share/dotclear/inc/prepend.php') then exit;
 l:=TStringList.Create;
 RegExpr:=TRegExpr.Create;
 l.LoadFromFile('/usr/share/dotclear/inc/prepend.php');
 RegExpr.Expression:='DC_VERSION.+?([0-9\.]+)';
 For i:=0 to l.Count-1 do begin
     if RegExpr.Exec(l.Strings[i]) then begin
        result:=RegExpr.Match[1];
        break;
     end;

 end;
  RegExpr.Free;
  l.free;


end;
//#########################################################################################


function tdotclear.LIGHTTPD_LOG_PATH():string;
var
RegExpr:TRegExpr;
l:TStringList;
i:integer;
begin


if not FileExists(HTTPD_CONF_PATH()) then begin
   logs.Debuglogs('LIGHTTPD_LOG_PATH:: unable to stat lighttpd.conf');
   exit;
end;
l:=TstringList.Create;
l.LoadFromFile(HTTPD_CONF_PATH());
RegExpr:=TRegExpr.Create;
RegExpr.Expression:='^server\.errorlog.+?"(.+?)"';

for i:=0 to l.Count-1 do begin
   if RegExpr.Exec(l.Strings[i]) then begin
    result:=RegExpr.Match[1];
    break;
   end;
end;

   result:=ExtractFilePath(result);
   if Copy(result,length(result),1)='/' then result:=Copy(result,1,length(result)-1);
   l.Free;
   RegExpr.free;

end;
//##############################################################################
function tdotclear.LIGHTTPD_LISTEN_PORT():string;
var
RegExpr:TRegExpr;
l:TStringList;
i:integer;
begin
if not FileExists(HTTPD_CONF_PATH()) then begin
   logs.logs('LIGHTTPD_LISTEN_PORT:: unable to stat lighttpd.conf');
   exit;
end;
l:=TstringList.Create;
l.LoadFromFile(HTTPD_CONF_PATH());
RegExpr:=TRegExpr.Create;
RegExpr.Expression:='^server\.port.+?=.+?([0-9]+)';
for i:=0 to l.Count-1 do begin

   if RegExpr.Exec(l.Strings[i]) then begin
   result:=RegExpr.Match[1];
   break;
   end;
end;

   RegExpr.Free;
   l.free;

end;
//##############################################################################


procedure tdotclear.LIGHTTPD_DEFAULT_CONF();
var
socket_path:string;
session_path:string;
user:string;
begin
user:=LIGHTTPD_GET_USER();

if not FileExists(HTTPD_CONF_PATH()) then begin
   logs.logs('LIGHTTPD_DEFAULT_CONF:: unable to stat tdotclear.conf');
   exit;
end;

if not FileExists(PHP5_CGI_BIN_PATH()) then begin
   logs.logs('LIGHTTPD_DEFAULT_CONF:: unable to stat php-cgi');
   exit;
end;
session_path:=SYS.LOCATE_PHP5_SESSION_PATH();
if length(session_path)>3 then begin
   forceDirectories(session_path);
   logs.OutputCmd('/bin/chmod -R 755 ' + session_path);
   logs.OutputCmd('/bin/chown -R '+user+' ' + session_path);
end;


logs.OutputCmd('/bin/chmod -R 755 ' + LIGHTTPD_LOG_PATH());
logs.OutputCmd('/bin/chown -R '+user+' ' + LIGHTTPD_LOG_PATH());

socket_path:=LIGHTTPD_SOCKET_PATH();
if length(socket_path)>0 then begin
   forcedirectories(socket_path);
   fpsystem('/bin/chmod -R 755 ' + socket_path);
   fpsystem('/bin/chown -R '+user+' ' + socket_path);
end;


DEFAULT_CONF();



end;
//##############################################################################
function tdotclear.LIGHTTPD_PID():string;
begin
result:=SYS.GET_PID_FROM_PATH(LIGHTTPD_PID_PATH());
result:=trim(result);
if result='' then begin
   result:=trim(SYS.ExecPipe('/usr/bin/pgrep -f "'+LIGHTTPD_BIN_PATH()+ ' -f /etc/artica-postfix/lighttpd-dotclear.conf"'));
end;

if result='0' then result:='';
end;
//##############################################################################
function tdotclear.LIGHTTPD_SOCKET_PATH():string;
var

RegExpr:TRegExpr;
l:TStringList;
i:integer;

begin

if not FileExists(HTTPD_CONF_PATH()) then begin
   logs.Debuglogs('LIGHTTPD_SOCKET_PATH:: unable to stat lighttpd.conf');
   exit;
end;
l:=TstringList.Create;
l.LoadFromFile(HTTPD_CONF_PATH());
RegExpr:=TRegExpr.Create;
RegExpr.Expression:='\s+"socket".+?"(.+?)"';
for i:=0 to l.Count-1 do begin
   if RegExpr.Exec(l.Strings[i]) then begin
    result:=RegExpr.Match[1];
    break;
   end;
end;
   result:=ExtractFilePath(result);
   if Copy(result,length(result),1)='/' then result:=Copy(result,1,length(result)-1);
   l.Free;
   RegExpr.free;

end;
//##############################################################################
function tdotclear.DEFAULT_CONF():string;
var
l:TstringList;
users:TstringList;
i:integer;
user:string;
RegExpr:TRegExpr;
group,name:string;
begin

user:=LIGHTTPD_GET_USER();
if length(user)=0 then user:=SYS.GET_INFO('LighttpdUserAndGroup');
if length(user)=0 then begin
   user:='www-data:www-data';
   SYS.set_INFO('LighttpdUserAndGroup',user);
end;

RegExpr:=TRegExpr.Create;
RegExpr.Expression:='(.+?):(.+)';
RegExpr.Exec(user);
name:=RegExpr.Match[1];
group:=RegExpr.Match[2];
l:=TstringList.Create;

l.Add('#artica-postfix saved by artica lighttpd.conf');
l.Add('');
l.Add('server.modules = (');
l.Add('        "mod_alias",');
l.Add('        "mod_access",');
l.Add('        "mod_accesslog",');
l.Add('        "mod_compress",');
l.Add('        "mod_fastcgi",');
l.Add('        "mod_cgi",');
l.Add('		 "mod_status"');
l.Add(')');
l.Add('');
l.Add('server.document-root        = "/usr/share/dotclear"');
l.Add('server.username = "'+name+'"');
l.Add('server.groupname = "'+group+'"');
l.Add('server.errorlog             = "/var/log/lighttpd/dotclear-error.log"');
l.Add('index-file.names            = ( "index.php")');
l.Add('');
l.Add('mimetype.assign             = (');
l.Add('  ".pdf"          =>      "application/pdf",');
l.Add('  ".sig"          =>      "application/pgp-signature",');
l.Add('  ".spl"          =>      "application/futuresplash",');
l.Add('  ".class"        =>      "application/octet-stream",');
l.Add('  ".ps"           =>      "application/postscript",');
l.Add('  ".torrent"      =>      "application/x-bittorrent",');
l.Add('  ".dvi"          =>      "application/x-dvi",');
l.Add('  ".gz"           =>      "application/x-gzip",');
l.Add('  ".pac"          =>      "application/x-ns-proxy-autoconfig",');
l.Add('  ".swf"          =>      "application/x-shockwave-flash",');
l.Add('  ".tar.gz"       =>      "application/x-tgz",');
l.Add('  ".tgz"          =>      "application/x-tgz",');
l.Add('  ".tar"          =>      "application/x-tar",');
l.Add('  ".zip"          =>      "application/zip",');
l.Add('  ".mp3"          =>      "audio/mpeg",');
l.Add('  ".m3u"          =>      "audio/x-mpegurl",');
l.Add('  ".wma"          =>      "audio/x-ms-wma",');
l.Add('  ".wax"          =>      "audio/x-ms-wax",');
l.Add('  ".ogg"          =>      "application/ogg",');
l.Add('  ".wav"          =>      "audio/x-wav",');
l.Add('  ".gif"          =>      "image/gif",');
l.Add('  ".jar"          =>      "application/x-java-archive",');
l.Add('  ".jpg"          =>      "image/jpeg",');
l.Add('  ".jpeg"         =>      "image/jpeg",');
l.Add('  ".png"          =>      "image/png",');
l.Add('  ".xbm"          =>      "image/x-xbitmap",');
l.Add('  ".xpm"          =>      "image/x-xpixmap",');
l.Add('  ".xwd"          =>      "image/x-xwindowdump",');
l.Add('  ".css"          =>      "text/css",');
l.Add('  ".html"         =>      "text/html",');
l.Add('  ".htm"          =>      "text/html",');
l.Add('  ".js"           =>      "text/javascript",');
l.Add('  ".asc"          =>      "text/plain",');
l.Add('  ".c"            =>      "text/plain",');
l.Add('  ".cpp"          =>      "text/plain",');
l.Add('  ".log"          =>      "text/plain",');
l.Add('  ".conf"         =>      "text/plain",');
l.Add('  ".text"         =>      "text/plain",');
l.Add('  ".txt"          =>      "text/plain",');
l.Add('  ".dtd"          =>      "text/xml",');
l.Add('  ".xml"          =>      "text/xml",');
l.Add('  ".mpeg"         =>      "video/mpeg",');
l.Add('  ".mpg"          =>      "video/mpeg",');
l.Add('  ".mov"          =>      "video/quicktime",');
l.Add('  ".qt"           =>      "video/quicktime",');
l.Add('  ".avi"          =>      "video/x-msvideo",');
l.Add('  ".asf"          =>      "video/x-ms-asf",');
l.Add('  ".asx"          =>      "video/x-ms-asf",');
l.Add('  ".wmv"          =>      "video/x-ms-wmv",');
l.Add('  ".bz2"          =>      "application/x-bzip",');
l.Add('  ".tbz"          =>      "application/x-bzip-compressed-tar",');
l.Add('  ".tar.bz2"      =>      "application/x-bzip-compressed-tar",');
l.Add('  ""              =>      "application/octet-stream",');
l.Add(' )');
l.Add('');
l.Add('');
l.Add('accesslog.filename          = "/var/log/lighttpd/dotclear-access.log"');
l.Add('url.access-deny             = ( "~", ".inc" )');
l.Add('');
l.Add('static-file.exclude-extensions = ( ".php", ".pl", ".fcgi" )');
l.Add('server.port                 = '+IntToSTr(DotClearHttpListenPort));
l.Add('#server.bind                = "127.0.0.1"');
l.Add('#server.error-handler-404   = "/error-handler.html"');
l.Add('#server.error-handler-404   = "/error-handler.php"');
l.Add('server.pid-file             = "/var/run/lighttpd/lighttpd-dotclear.pid"');
l.Add('server.max-fds 		    = 2048');
l.Add('');
l.Add('fastcgi.server = ( ".php" =>((');
l.Add('                "bin-path" => "'+PHP5_CGI_BIN_PATH()+'",');
l.Add('                "socket" => "/var/run/lighttpd/php.socket",');
l.Add('		"min-procs" => 1,');
l.Add('                "max-procs" => 4,');
l.Add('		"max-load-per-proc" => 4,');
l.Add('                "idle-timeout" => 10,');
l.Add('                "bin-environment" => (');
l.Add('                        "PHP_FCGI_CHILDREN" => "4",');
l.Add('                        "PHP_FCGI_MAX_REQUESTS" => "100"');
l.Add('                ),');
l.Add('                "bin-copy-environment" => (');
l.Add('                        "PATH", "SHELL", "USER"');
l.Add('                ),');
l.Add('                "broken-scriptfilename" => "enable"');
l.Add('        ))');
l.Add(')');
l.Add('ssl.engine                 = "disable"');
l.Add('ssl.pemfile                = "/opt/artica/ssl/certs/lighttpd.pem"');
l.Add('status.status-url          = "/server-status"');
l.Add('status.config-url          = "/server-config"');
l.Add('$HTTP["url"] =~ "^/webmail" {');
l.Add('	server.follow-symlink = "enable"');
l.Add('}');

if FileExists('/etc/artica-postfix/settings/DotClear/users.conf') then begin
   users:=TstringList.Create;
   users.LoadFromFile('/etc/artica-postfix/settings/DotClear/users.conf');
   RegExpr.Expression:='(.+?);(.+)';
   for i:=0 to users.Count-1 do begin
   if not RegExpr.Exec(users.Strings[i]) then continue;
   CreateHome(RegExpr.Match[2],RegExpr.Match[1]);
   l.Add('alias.url +=("/'+RegExpr.Match[1]+'"  => "'+RegExpr.Match[2]+'/blog")');
   end;
   users.free;
end;
   
   
l.Add('alias.url += ( "/cgi-bin/" => "/usr/lib/cgi-bin/" )');
l.Add('url.rewrite-once = ( "^/index.php/(.*)$" => "/index.php?$1" )');
l.Add('');
l.Add('cgi.assign= (');
l.Add('	".pl"  => "/usr/bin/perl",');
l.Add('	".php" => "/usr/bin/php-cgi",');
l.Add('	".py"  => "/usr/bin/python",');
l.Add('	".cgi"  => "/usr/bin/perl",');
l.Add(')');
l.Add('');
l.SaveToFile(HTTPD_CONF_PATH());
result:=l.text;
l.free;
RegExpr.free;
end;
//##############################################################################

procedure tdotclear.CreateHome(HomeDirectory:string;uid:string);
var
   l:TstringList;
   user:string;
begin
  l:=TstringList.Create;
  forceDirectories(HomeDirectory+'/blog');
  forceDirectories(HomeDirectory+'/blog/public');
  user:=LIGHTTPD_GET_USER();
  logs.Debuglogs('Starting lighttpd............: lighttpd daemon for dotclear create DotClear for '+ uid);
l.Add('<?php');
l.Add('define("DC_BLOG_ID","'+uid+'"); # identifiant du blog');
l.Add('require "/usr/share/dotclear/inc/public/prepend.php";');
l.Add('?>');
l.SaveToFile(HomeDirectory+'/blog/index.php');

fpsystem('/bin/chmod 777 '+ HomeDirectory+'/blog/index.php');
fpsystem('/bin/chown -R '+user+' '+HomeDirectory+'/blog');
fpsystem('/bin/chmod 755 '+HomeDirectory+'/blog/public');


end;



procedure tdotclear.master_config();
var
   l:TstringList;
   port:string;
begin
  port:=logs.MYSQL_INFOS('port');
  if length(port)=0 then port:='3306';
l:=TstringList.Create;
l.Add('<?php');
l.Add('define("DC_DBDRIVER","mysql");');
l.Add('define("DC_DBHOST","'+logs.MYSQL_INFOS('mysql_server')+':'+port+'");');
l.Add('define("DC_DBUSER","'+logs.MYSQL_INFOS('database_admin')+'");');
l.Add('define("DC_DBPASSWORD","'+logs.MYSQL_INFOS('database_password')+'");');
l.Add('define("DC_DBNAME","artica_backup");');
l.Add('define("DC_DBPREFIX","dotclear_");');
l.Add('define("DC_DBPERSIST",false);');
l.Add('define("DC_MASTER_KEY","artica");');
l.Add('define("DC_ADMIN_URL","'+DotClearExternalAdminUri+'");');
l.Add('define("DC_SESSION_NAME","dcxd");');
l.Add('define("DC_PLUGINS_ROOT","/usr/share/dotclear/plugins");');
l.Add('define("DC_TPL_CACHE","/usr/share/dotclear/cache");');
l.Add('?>');
logs.Debuglogs('Starting lighttpd............: saving dotclear config.php');
l.SaveToFile('/usr/share/dotclear/inc/config.php');

l.Clear;
l.Add('<?php');
l.Add('if (isset($_SERVER["DC_BLOG_ID"])) {');
l.Add('	define("DC_BLOG_ID",$_SERVER["DC_BLOG_ID"]);');
l.Add('} elseif (isset($_SERVER["REDIRECT_DC_BLOG_ID"])) {');
l.Add('	define("DC_BLOG_ID",$_SERVER["REDIRECT_DC_BLOG_ID"]);');
l.Add('} elseif (isset($_SERVER["REDIRECT_REDIRECT_DC_BLOG_ID"])) {');
l.Add('	define("DC_BLOG_ID",$_SERVER["REDIRECT_REDIRECT_DC_BLOG_ID"]);');
l.Add('}else {');
l.Add('	# Define your blog here');
l.Add('	define("DC_BLOG_ID","default");');
l.Add('}');
l.Add('require dirname(__FILE__)."/inc/public/prepend.php";');
l.Add('');
l.Add('?>');
l.SaveToFile('/usr/share/dotclear/index.php');
l.free;
end;





function tdotclear.Explode(const Separator, S: string; Limit: Integer = 0):TStringDynArray;
var
  SepLen       : Integer;
  F, P         : PChar;
  ALen, Index  : Integer;
begin
  SetLength(Result, 0);
  if (S = '') or (Limit < 0) then
    Exit;
  if Separator = '' then
  begin
    SetLength(Result, 1);
    Result[0] := S;
    Exit;
  end;
  SepLen := Length(Separator);
  ALen := Limit;
  SetLength(Result, ALen);

  Index := 0;
  P := PChar(S);
  while P^ <> #0 do
  begin
    F := P;
    P := StrPos(P, PChar(Separator));
    if (P = nil) or ((Limit > 0) and (Index = Limit - 1)) then
      P := StrEnd(F);
    if Index >= ALen then
    begin
      Inc(ALen, 5); // mehrere auf einmal um schneller arbeiten zu können
      SetLength(Result, ALen);
    end;
    SetString(Result[Index], F, P - F);
    Inc(Index);
    if P^ <> #0 then
      Inc(P, SepLen);
  end;
  if Index < ALen then
    SetLength(Result, Index); // wirkliche Länge festlegen
end;

end.

