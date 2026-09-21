; ============================================================================
;  Hotel Media — Windows Server Installer (Inno Setup / ISCC)
;  سماع رایانه کیش | kishwifi.com
;
;  This produces a native Windows installer (.exe) that:
;    1. Copies the full Hotel Media server into the install folder.
;    2. Optionally runs the universal installer (install.ps1) right after,
;       which auto-detects the Windows edition and sets up Docker/WSL2.
;
;  Build:
;    ISCC.exe /DMyAppVersion=1.6.2 /DSourceDir="..\dist\server-files" HotelMedia.iss
; ============================================================================

#ifndef MyAppVersion
  #define MyAppVersion "1.6.2"
#endif

; Folder whose contents get packaged into the installer.
; In CI this is the cleaned server payload produced by build-release.ps1.
#ifndef SourceDir
  #define SourceDir "..\dist\server-files"
#endif

#define MyAppName     "Hotel Media Server"
#define MyAppPublisher "Sama Rayaneh Kish — سماع رایانه کیش"
#define MyAppURL      "https://kishwifi.com"

[Setup]
AppId={{B7E4F2A1-9C3D-4E6B-8A21-5F0D7C1E9A42}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppURL}
AppUpdatesURL={#MyAppURL}
DefaultDirName=C:\HotelMedia
DefaultGroupName=Hotel Media
DisableProgramGroupPage=yes
DisableDirPage=no
; Server install needs admin (WSL2, Firewall, Scheduled Task, Docker).
PrivilegesRequired=admin
ArchitecturesAllowed=x64
ArchitecturesInstallIn64BitMode=x64
OutputDir=.\Output
OutputBaseFilename=HotelMedia-v{#MyAppVersion}-server-setup
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
UninstallDisplayName={#MyAppName}
; SetupIconFile is intentionally omitted (no .ico shipped) — default icon is used.

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "runsetup"; Description: "Run the Hotel Media installer now (installs Docker / WSL2 and starts the stack)"; GroupDescription: "Post-install:"
Name: "desktopicon"; Description: "Create a desktop shortcut to the dashboard"; GroupDescription: "Shortcuts:"

[Files]
; Whole cleaned server payload (no .git / node_modules / vendor / .env).
Source: "{#SourceDir}\*"; DestDir: "{app}"; Flags: recursesubdirs createallsubdirs ignoreversion

[Icons]
Name: "{group}\Hotel Media Dashboard"; Filename: "http://localhost/admin"
Name: "{group}\Open install folder"; Filename: "{app}"
Name: "{group}\Re-run installer"; Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\install.ps1"""; WorkingDir: "{app}"
Name: "{group}\Uninstall Hotel Media"; Filename: "{uninstallexe}"
Name: "{autodesktop}\Hotel Media Dashboard"; Filename: "http://localhost/admin"; Tasks: desktopicon

[Run]
; Kick off the universal installer after files are copied (only if the user opted in).
Filename: "powershell.exe"; \
  Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\install.ps1"""; \
  WorkingDir: "{app}"; \
  Flags: shellexec waituntilterminated; \
  Description: "Set up Hotel Media (Docker / WSL2)"; \
  StatusMsg: "Running Hotel Media installer…"; \
  Tasks: runsetup

[UninstallRun]
; Best-effort teardown of the Docker stack on uninstall (non-fatal).
Filename: "powershell.exe"; \
  Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\install.ps1"" -Uninstall -Silent"; \
  WorkingDir: "{app}"; \
  Flags: shellexec waituntilterminated runhidden; \
  RunOnceId: "SignageStackDown"

[Messages]
WelcomeLabel2=This will install [name/ver] on your computer.%n%nHotel Media is a digital signage management server (PHP + MySQL + Redis + WebSocket) that runs in Docker. The installer can set up Docker / WSL2 for you automatically.%n%nسماع رایانه کیش | kishwifi.com
