unit setup_phpldapadmin;
{$MODE DELPHI}
//{$mode objfpc}{$H+}
{$LONGSTRINGS ON}

interface

uses
  Classes, SysUtils,strutils,RegExpr in 'RegExpr.pas',unix,IniFiles,setup_libs,distridetect,setup_suse_class,install_generic;

  type
  tsetup_phpldapadmin=class


private
     libs:tlibs;
     distri:tdistriDetect;
     install:tinstall;
     packageSource:string;
public
      constructor Create();
      procedure Free;
      procedure xinstall();
      procedure xinstall_phpmyadmin();
      procedure xinstall_piwik();
      procedure APP_FILEZ_WEB();
END;

implementation

constructor tsetup_phpldapadmin.Create();
begin
distri:=tdistriDetect.Create();
libs:=tlibs.Create;
install:=tinstall.Create;
if FileExists(ParamStr(2)) then packageSource:=ParamStr(2);
end;
//#########################################################################################
procedure tsetup_phpldapadmin.Free();
begin
  libs.Free;
end;
//#########################################################################################
procedure tsetup_phpldapadmin.xinstall();
var

   source_folder,cmd:string;
   l:Tstringlist;
   CODE_NAME:string;

begin
install.INSTALL_STATUS('APP_PHPLDAPADMIN',10);
install.INSTALL_PROGRESS('APP_PHPLDAPADMIN','{downloading}');
CODE_NAME:='APP_PHPLDAPADMIN';
if FileExists(packageSource) then begin
   writeln('Extracting from local file ' +packageSource);
   source_folder:=libs.ExtractLocalPackage(packageSource);
   writeln('source folder:',source_folder);
end;


if not DirectoryExists(source_folder) then source_folder:=libs.COMPILE_GENERIC_APPS('phpldapadmin');

if length(trim(source_folder))=0 then begin
     writeln('Install phpldapadmin failed...');
     install.INSTALL_STATUS('APP_PHPLDAPADMIN',110);
     exit;
end;
    install.INSTALL_PROGRESS('APP_PHPLDAPADMIN','{installing}');
    install.INSTALL_STATUS('APP_PHPLDAPADMIN',70);
writeln('Installing phpldapadmin from "',source_folder,'"');
forceDirectories('/usr/share/phpldapadmin');
fpsystem('/bin/cp -rf '+source_folder+'/* /usr/share/phpldapadmin/');
fpsystem('/bin/ln -s --force /usr/share/phpldapadmin /usr/share/artica-postfix/ldap');
fpsystem('/bin/rm -rf '+source_folder);

if FileExists('/usr/share/phpldapadmin/index.php') then begin
        install.INSTALL_STATUS(CODE_NAME,100);
        writeln('installed');
        install.INSTALL_PROGRESS(CODE_NAME,'{installed}');
        install.INSTALL_STATUS(CODE_NAME,100);
        fpsystem('/etc/init.d/artica-postfix restart apache');
        exit;
   end;

  install.INSTALL_PROGRESS(CODE_NAME,'{failed}');
  install.INSTALL_STATUS(CODE_NAME,110);
  exit;

end;
//#########################################################################################
procedure tsetup_phpldapadmin.APP_FILEZ_WEB();
var

   source_folder,cmd:string;
   l:Tstringlist;
   CODE_NAME:string;

begin
install.INSTALL_STATUS('APP_FILEZ_WEB',10);
install.INSTALL_PROGRESS('APP_FILEZ_WEB','{downloading}');
CODE_NAME:='APP_FILEZ_WEB';
if FileExists(packageSource) then begin
   writeln('Extracting from local file ' +packageSource);
   source_folder:=libs.ExtractLocalPackage(packageSource);
   writeln('source folder:',source_folder);
end;

if not DirectoryExists(source_folder) then source_folder:=libs.COMPILE_GENERIC_APPS('filez');
if length(trim(source_folder))=0 then begin
     writeln('Install APP_FILEZ_WEB failed...');
     install.INSTALL_STATUS('APP_FILEZ_WEB',110);
     exit;
end;
    install.INSTALL_PROGRESS('APP_FILEZ_WEB','{installing}');
    install.INSTALL_STATUS('APP_FILEZ_WEB',70);

source_folder:=ExtractFilePath(source_folder);
writeln('Installing APP_FILEZ_WEB from "',source_folder,'"');

forceDirectories('/usr/share/filez');
fpsystem('/bin/cp -rf '+source_folder+'/* /usr/share/filez/');
fpsystem('/bin/rm -rf '+source_folder);

if FileExists('/usr/share/filez/index.php') then begin
 install.INSTALL_STATUS(CODE_NAME,100);
 writeln('installed');
 install.INSTALL_PROGRESS(CODE_NAME,'{installed}');
 install.INSTALL_STATUS(CODE_NAME,100);
 exit;
end;


  install.INSTALL_PROGRESS(CODE_NAME,'{failed}');
  install.INSTALL_STATUS(CODE_NAME,110);
  exit;


end;

procedure tsetup_phpldapadmin.xinstall_piwik();
var

   source_folder,cmd:string;
   l:Tstringlist;
   CODE_NAME:string;

begin
install.INSTALL_STATUS('APP_PIWIK',10);
install.INSTALL_PROGRESS('APP_PIWIK','{downloading}');
CODE_NAME:='APP_PIWIK';
if FileExists(packageSource) then begin
   writeln('Extracting from local file ' +packageSource);
   source_folder:=libs.ExtractLocalPackage(packageSource);
   writeln('source folder:',source_folder);
end;


if not DirectoryExists(source_folder) then source_folder:=libs.COMPILE_GENERIC_APPS('piwik');

if length(trim(source_folder))=0 then begin
     writeln('Install piwik failed...');
     install.INSTALL_STATUS('APP_PIWIK',110);
     exit;
end;
    install.INSTALL_PROGRESS('APP_PIWIK','{installing}');
    install.INSTALL_STATUS('APP_PIWIK',70);
writeln('Installing piwik from "',source_folder,'"');
forceDirectories('/usr/share/piwik');
fpsystem('/bin/cp -rf '+source_folder+'/* /usr/share/piwik/');
fpsystem('/bin/rm -rf '+source_folder);

if FileExists('/usr/share/piwik/index.php') then begin
        install.INSTALL_STATUS(CODE_NAME,100);
        writeln('installed');
        install.INSTALL_PROGRESS(CODE_NAME,'{installed}');
        install.INSTALL_STATUS(CODE_NAME,100);
        ForceDirectories('/usr/share/piwik/tmp/assets');
        ForceDirectories('/usr/share/piwik/tmp/templates_c');
        ForceDirectories('/usr/share/piwik/tmp/cache');
        ForceDirectories('/usr/share/piwik/tmp/assets');
        fpsystem('/bin/chmod 0777 /usr/share/piwik/tmp');
        fpsystem('/bin/chmod 0777 /usr/share/piwik/tmp/templates_c/');
        fpsystem('/bin/chmod 0777 /usr/share/piwik/tmp/cache/');
        fpsystem('/bin/chmod 0777 /usr/share/piwik/tmp/assets/');
        fpsystem('/bin/chmod a+w /usr/share/piwik/config');

        fpsystem('/etc/init.d/artica-postfix restart apachesrc');
        exit;
   end;

  install.INSTALL_PROGRESS(CODE_NAME,'{failed}');
  install.INSTALL_STATUS(CODE_NAME,110);
  exit;

end;
//#########################################################################################
procedure tsetup_phpldapadmin.xinstall_phpmyadmin();
var

   source_folder,cmd:string;
   l:Tstringlist;
   CODE_NAME:string;

begin
install.INSTALL_STATUS('APP_PHPMYADMIN',10);
install.INSTALL_PROGRESS('APP_PHPMYADMIN','{downloading}');
CODE_NAME:='APP_PHPMYADMIN';
if FileExists(packageSource) then begin
   writeln('Extracting from local file ' +packageSource);
   source_folder:=libs.ExtractLocalPackage(packageSource);
   writeln('source folder:',source_folder);
end;


if not DirectoryExists(source_folder) then source_folder:=libs.COMPILE_GENERIC_APPS('phpMyAdmin');

if length(trim(source_folder))=0 then begin
     writeln('Install phpmyadmin failed...');
     install.INSTALL_STATUS(CODE_NAME,110);
     exit;
end;
    install.INSTALL_PROGRESS(CODE_NAME,'{installing}');
    install.INSTALL_STATUS(CODE_NAME,70);
writeln('Installing phpmyadmin from "',source_folder,'"');
forceDirectories('/usr/share/phpmyadmin');
fpsystem('/bin/cp -rf '+source_folder+'/* /usr/share/phpmyadmin/');
fpsystem('/bin/ln -s --force /usr/share/phpmyadmin /usr/share/artica-postfix/mysql');
fpsystem('/bin/rm -rf '+source_folder);

if FileExists('/usr/share/phpmyadmin/index.php') then begin
        install.INSTALL_STATUS(CODE_NAME,100);
        writeln('installed');
        install.INSTALL_PROGRESS(CODE_NAME,'{installed}');
        install.INSTALL_STATUS(CODE_NAME,100);
        fpsystem('/etc/init.d/artica-postfix restart apache');
        exit;
   end;
   writeln('unable to stat /usr/share/phpmyadmin/index.php');
  install.INSTALL_PROGRESS(CODE_NAME,'{failed}');
  install.INSTALL_STATUS(CODE_NAME,110);
  fpsystem('/etc/init.d/artica-postfix restart apache');
  exit;

end;
//#########################################################################################
end.
