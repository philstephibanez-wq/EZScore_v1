@echo off
cd /d "%~dp0"
start "" powershell.exe -NoProfile -STA -ExecutionPolicy Bypass -WindowStyle Hidden -File "%~dp0scripts\launch_ezscore_splash.ps1" -Port 8501
exit /b 0
