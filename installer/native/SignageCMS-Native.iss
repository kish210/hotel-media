; ============================================================================
;  SignageCMS — Native Windows Installer  (NO Docker)
;  سماع رایانه کیش | kishwifi.com
;
;  Produces a single .exe that a non-technical user just double-clicks.
;  It bundles a portable PHP + MariaDB + NSSM together with the app, and on
;  install runs provision.ps1 which:
;     • generates .env with random secrets
;     • initializes MariaDB and registers it as a Windows service
;     • imports the schema + seeds the admin user
;     • registers the web + websocket servers as auto-start services
;     • opens the firewall and launches the dashboard
;
;  Nothing here needs Docker, WSL2 or any prior setup on the target machine.
;
;  Build (done for you by scripts/build-native-installer.ps1):
;     ISCC /DMyAppVersion=1.6.2 /DPayloadDir="..\..\dist\native-payload" SignageCMS-Native.iss
; ============================================================================

#ifndef MyAppVersion
  #define MyAppVersion "1.6.2"
#endif

; Folder assembled by build-native-installer.ps1 (app + runtime\php + runtime\mariadb + runtime\nssm + runtime\scripts).
#ifndef PayloadDir
  #define PayloadDir "..\..\dist\native-payload"
#endif

#define MyAppName      "SignageCMS"
#define MyAppPublisher "Sama Rayaneh Kish - سماع رایانه کیش"
#define MyAppURL       "https://kishwifi.com"
#define WebPort        "80"
#define WsPort         "8080"

[Setup]
AppId={{4F2C8E1D-6A3B-4D9E-B71C-2E8F0A5D3C90}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppURL}
DefaultDirName=C:\SignageCMS
DefaultGroupName=SignageCMS
DisableProgramGroupPage=yes
DisableDirPage=no
PrivilegesRequired=admin
ArchitecturesAllowed=x64
ArchitecturesInstallIn64BitMode=x64
OutputDir=.\Output
OutputBaseFilename=SignageCMS-v{#MyAppVersion}-setup
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
UninstallDisplayName={#MyAppName} {#MyAppVersion}
; SetupIconFile omitted (no .ico shipped) — default icon is used.

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "Create a desktop shortcut to the dashboard"; GroupDescription: "Shortcuts:"

[Files]
; The whole assembled payload (app + bundled PHP/MariaDB/NSSM + runtime scripts).
Source: "{#PayloadDir}\*"; DestDir: "{app}"; Flags: recursesubdirs createallsubdirs ignoreversion

[Dirs]
; Writable runtime folders.
Name: "{app}\data";              Permissions: users-modify
Name: "{app}\storage\logs";      Permissions: users-modify
Name: "{app}\storage\cache";     Permissions: users-modify
Name: "{app}\storage\sessions";  Permissions: users-modify
Name: "{app}\storage\temp";      Permissions: users-modify
Name: "{app}\public\uploads";    Permissions: users-modify

[Icons]
Name: "{group}\SignageCMS Dashboard"; Filename: "http://localhost/admin"
Name: "{group}\Start SignageCMS";   Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\runtime\scripts\Manage-SignageCMS.ps1"" start";   WorkingDir: "{app}"
Name: "{group}\Stop SignageCMS";    Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\runtime\scripts\Manage-SignageCMS.ps1"" stop";    WorkingDir: "{app}"
Name: "{group}\Service status";     Filename: "powershell.exe"; Parameters: "-NoProfile -NoExit -ExecutionPolicy Bypass -File ""{app}\runtime\scripts\Manage-SignageCMS.ps1"" status"; WorkingDir: "{app}"
Name: "{group}\Open install folder"; Filename: "{app}"
Name: "{group}\Uninstall SignageCMS"; Filename: "{uninstallexe}"
Name: "{autodesktop}\SignageCMS Dashboard"; Filename: "http://localhost/admin"; Tasks: desktopicon

[Run]
; Set everything up. waituntilterminated so the wizard shows the progress window.
Filename: "powershell.exe"; \
  Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\runtime\scripts\provision.ps1"" -InstallDir ""{app}"" -Port {#WebPort} -WsPort {#WsPort}"; \
  WorkingDir: "{app}"; \
  Flags: waituntilterminated; \
  StatusMsg: "Setting up SignageCMS (database + services). This can take a few minutes..."

[UninstallRun]
; Tear down services + firewall before the files are deleted.
Filename: "powershell.exe"; \
  Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\runtime\scripts\uninstall.ps1"" -InstallDir ""{app}"""; \
  WorkingDir: "{app}"; \
  Flags: waituntilterminated runhidden; \
  RunOnceId: "SignageNativeDown"

[Messages]
WelcomeLabel2=This will install [name/ver] on your computer.%n%nSignageCMS is a digital signage management server. This installer bundles everything it needs (web server, database and realtime server) — you do NOT need Docker, a database, or any technical setup.%n%nJust click Next, and when it finishes your dashboard opens automatically.%n%nسماع رایانه کیش | kishwifi.com

[Code]
{ Friendly heads-up if something else is already using the web port. }
function InitializeSetup(): Boolean;
begin
  Result := True;
end;
