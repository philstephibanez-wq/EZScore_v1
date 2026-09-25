Option Explicit

Dim shell, fso, root, scriptPath, cmd
Set shell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

root = fso.GetParentFolderName(WScript.ScriptFullName)
scriptPath = fso.BuildPath(root, "scripts\launch_ezscore_ui.ps1")

cmd = "powershell.exe -NoProfile -STA -ExecutionPolicy Bypass -File """ & scriptPath & """ -Port 8501"
shell.Run cmd, 0, False
