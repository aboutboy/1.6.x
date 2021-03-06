unit stunnel4;

{$MODE DELPHI}
{$LONGSTRINGS ON}

interface

uses
    Classes, SysUtils,variants,strutils, Process,logs,unix,RegExpr in 'RegExpr.pas',zsystem;

  type
  tstunnel=class


private
     LOGS:Tlogs;
     SYS:TSystem;
     artica_path:string;
     sTunnel4enabled:integer;

public
    procedure   Free;
    constructor Create(const zSYS:Tsystem);
    procedure   ETC_DEFAULT();
    procedure   SAVE_CERTIFICATE();
    function    READ_CONF(key:string):string;
    function    DAEMON_BIN_PATH():string;
    procedure   STUNNEL_START();
    procedure   STUNNEL_STOP();
    function    VERSION():string;
    function    STUNNEL_STATUS():string;
    function    STUNNEL_INITD():string;
    function    STUNNEL_PID():string;
    function    PID_PATH():string;
END;

implementation

constructor tstunnel.Create(const zSYS:Tsystem);
begin
       forcedirectories('/etc/artica-postfix');
       forcedirectories('/opt/artica/tmp');
       LOGS:=tlogs.Create();
       SYS:=zSYS;
       if not TryStrToInt(SYS.GET_INFO('sTunnel4enabled'),sTunnel4enabled) then sTunnel4enabled:=0;

       if not DirectoryExists('/usr/share/artica-postfix') then begin
              artica_path:=ParamStr(0);
              artica_path:=ExtractFilePath(artica_path);
              artica_path:=AnsiReplaceText(artica_path,'/bin/','');

      end else begin
          artica_path:='/usr/share/artica-postfix';
      end;
end;
//##############################################################################
procedure tstunnel.free();
begin
    logs.Free;
end;
//##############################################################################
function tstunnel.STUNNEL_INITD():string;
begin
   if FileExists('/etc/init.d/stunnel4') then exit('/etc/init.d/stunnel4');
end;
//##############################################################################
function tstunnel.DAEMON_BIN_PATH():string;
var str:string;
begin
   if FileExists(SYS.LOCATE_GENERIC_BIN('stunnel')) then exit(SYS.LOCATE_GENERIC_BIN('stunnel'));
   if FileExists(SYS.LOCATE_GENERIC_BIN('stunnel4')) then exit(SYS.LOCATE_GENERIC_BIN('stunnel4'));
   if FileExists(SYS.LOCATE_GENERIC_BIN('stunnel3')) then exit(SYS.LOCATE_GENERIC_BIN('stunnel3'));
   str:=SYS.LOCATE_GENERIC_BIN('stunnel');
   if length(str)>0 then exit(str);
   str:=SYS.LOCATE_GENERIC_BIN('stunnel4');
   if length(str)>0 then exit(str);
end;
//##############################################################################
function tstunnel.PID_PATH():string;
var
   chroot:string;
   pid:string;
   path:string;

begin
    chroot:=READ_CONF('chroot');
    pid:=READ_CONF('pid');
    path:=chroot+'/'+pid;
    path:=AnsiReplaceText(path,'//','/');
    path:=AnsiReplaceText(path,'//','/');
    result:=path;
end;
//##############################################################################
function tstunnel.STUNNEL_PID():string;
begin
result:=SYS.PIDOF_PATTERN(DAEMON_BIN_PATH()+' /etc/stunnel/stunnel.conf');
end;
//##############################################################################
procedure tstunnel.ETC_DEFAULT();
var
l:TstringList;
begin

if not FileExists('/etc/default/stunnel') then exit;
l:=TstringList.Create;

l.Add('# /etc/default/stunnel');
l.Add('# Julien LEMOINE <speedblue@debian.org>');
l.Add('# September 2003');
l.Add('');
l.Add('# Change to one to enable stunnel');
l.Add('ENABLED=1');
l.Add('FILES="/etc/stunnel/*.conf"');
l.Add('OPTIONS=""');
l.Add('');
l.Add('# Change to one to enable ppp restart scripts');
l.Add('PPP_RESTART=0');
l.SaveToFile('/etc/default/stunnel');
l.free;
end;
//##############################################################################
function tstunnel.READ_CONF(key:string):string;
var
    RegExpr:TRegExpr;
    FileDatas:TStringList;
    i:integer;
begin
 if not FileExists('/etc/stunnel/stunnel.conf') then exit;
 FileDatas:=TstringList.Create;
 FileDatas.LoadFromFile('/etc/stunnel/stunnel.conf');
 RegExpr:=TRegExpr.Create;
 RegExpr.Expression:='^'+key+'[\s=]+(.+)';
 for i:=0 to FileDatas.Count-1 do begin
     if RegExpr.Exec(FileDatas.Strings[i]) then begin
         result:=RegExpr.Match[1];
         break;
     end;

 end;
         FileDatas.Free;
         RegExpr.Free;

end;
//##############################################################################
function tstunnel.VERSION():string;
var
    RegExpr:TRegExpr;
    FileDatas:TStringList;
    i:integer;
    tmpstr:string;
begin



 if not FileExists(DAEMON_BIN_PATH()) then exit;

 tmpstr:=logs.FILE_TEMP();

result:=SYS.GET_CACHE_VERSION('APP_STUNNEL');
   if length(result)>0 then exit;


 FileDatas:=TstringList.Create;
 fpsystem(DAEMON_BIN_PATH() + ' -version >'+tmpstr+' 2>&1');
 if not FileExists(tmpstr) then exit;
 try FileDatas.LoadFromFile(tmpstr) except exit; end;
 logs.DeleteFile(tmpstr);
 RegExpr:=TRegExpr.Create;
 RegExpr.Expression:='stunnel\s+([0-9\.]+)\s+';
 for i:=0 to FileDatas.Count-1 do begin
     if RegExpr.Exec(FileDatas.Strings[i]) then begin
         result:=RegExpr.Match[1];
         break;
     end;

 end;
         FileDatas.Free;
         RegExpr.Free;
 SYS.SET_CACHE_VERSION('APP_STUNNEL',result);
end;
//##############################################################################
procedure tstunnel.SAVE_CERTIFICATE();
var
   cert:string;
   setuid:string;
   setgid:string;

begin
    cert:=READ_CONF('cert');
    setuid:=READ_CONF('setuid');
    setgid:=READ_CONF('setgid');
    logs.Debuglogs('SAVE_CERTIFICATE():: /bin/cp /opt/artica/ssl/certs/lighttpd.pem '  + cert);
    fpsystem('/bin/cp /opt/artica/ssl/certs/lighttpd.pem '  + cert + ' >/dev/null 2>&1');
    SYS.FILE_CHOWN(setuid,setgid,'/etc/stunnel');
    fpsystem('/bin/chmod 600 ' + cert + ' >/dev/null 2>&1');
end;
//##############################################################################

function tstunnel.STUNNEL_STATUS:string;
var
ini:TstringList;
pid:string;
begin
   ini:=TstringList.Create;
   ini.Add('[STUNNEL]');
   if FileExists(DAEMON_BIN_PATH()) then  begin
      pid:=STUNNEL_PID();
      if SYS.PROCESS_EXIST(pid) then ini.Add('running=1') else  ini.Add('running=0');
      ini.Add('application_installed=1');
      ini.Add('master_pid='+ pid);
      ini.Add('master_memory=' + IntToStr(SYS.PROCESS_MEMORY(pid)));
      ini.Add('master_version='+VERSION());
      ini.Add('status='+SYS.PROCESS_STATUS(pid));
      ini.Add('service_name=APP_STUNNEL');
      ini.Add('service_cmd=stunnel');
      ini.Add('service_disabled='+IntToStr(sTunnel4enabled));
      
      
   end;

   result:=ini.Text;
   ini.free;

end;
//##############################################################################
procedure tstunnel.STUNNEL_START();
begin
    fpsystem(SYS.LOCATE_PHP5_BIN()+' /usr/share/artica-postfix/exec.stunnel.php --start');
end;
//##############################################################################
procedure tstunnel.STUNNEL_STOP();
begin
    fpsystem(SYS.LOCATE_PHP5_BIN()+' /usr/share/artica-postfix/exec.stunnel.php --stop');

end;
//##############################################################################


end.
