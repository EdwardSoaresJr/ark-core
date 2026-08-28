; ARK Forge Setup — bundles Forge Core + ARK Bridge (+ Workbench when built)
#define MyAppName "ARK Forge"
#define MyAppVersion "0.1.0"
#define MyAppPublisher "Auto Repair Keeper"
#define MyAppURL "https://autorepairkeeper.com"
#define MyDistDir "..\dist\ARK Forge"

[Setup]
AppId={{A7F3C2E1-9B4D-4F8A-8E2C-1D5F6A7B8C9D}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
DefaultDirName={autopf}\ARK Forge
DefaultGroupName=ARK Forge
OutputDir=..\dist
OutputBaseFilename=ARK Forge Setup
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=admin
ArchitecturesInstallIn64BitMode=x64

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "Create a &desktop shortcut for ARK Bridge"; GroupDescription: "Additional icons:"
Name: "autostart"; Description: "Start ARK Bridge when Windows starts"; GroupDescription: "Startup:"; Flags: unchecked

[Files]
Source: "{#MyDistDir}\Forge Core.exe"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#MyDistDir}\ARK Bridge.exe"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#MyDistDir}\bridge.ico"; DestDir: "{app}"; Flags: ignoreversion skipifsourcedoesntexist
Source: "{#MyDistDir}\ARK Forge Workbench.exe"; DestDir: "{app}"; Flags: ignoreversion skipifsourcedoesntexist

[Icons]
Name: "{group}\ARK Bridge"; Filename: "{app}\ARK Bridge.exe"; WorkingDir: "{app}"
Name: "{group}\Forge Core (headless)"; Filename: "{app}\Forge Core.exe"; WorkingDir: "{app}"
Name: "{group}\ARK Forge Workbench"; Filename: "{app}\ARK Forge Workbench.exe"; WorkingDir: "{app}"; Check: FileExists(ExpandConstant('{app}\ARK Forge Workbench.exe'))
Name: "{group}\Uninstall ARK Forge"; Filename: "{uninstallexe}"
Name: "{autodesktop}\ARK Bridge"; Filename: "{app}\ARK Bridge.exe"; Tasks: desktopicon; WorkingDir: "{app}"

[Run]
Filename: "{app}\ARK Bridge.exe"; Description: "Launch ARK Bridge now"; Flags: nowait postinstall skipifsilent

[Registry]
Root: HKCU; Subkey: "Software\Microsoft\Windows\CurrentVersion\Run"; ValueType: string; ValueName: "ARK Bridge"; ValueData: """{app}\ARK Bridge.exe"""; Tasks: autostart
