Option Explicit

Dim shell, fso, root, splash, cmd
Set shell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

root = fso.GetParentFolderName(WScript.ScriptFullName)
splash = fso.BuildPath(root, "scripts\launch_ezscore_splash.ps1")

cmd = "powershell.exe -NoProfile -STA -ExecutionPolicy Bypass -File """ & splash & """ -Port 8501"
shell.Run cmd, 0, False
