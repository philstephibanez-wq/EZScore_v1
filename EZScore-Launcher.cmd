@echo off
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File ".\scripts\launch_ezscore.ps1"
if errorlevel 1 pause
