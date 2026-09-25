param(
    [int]$Port = 8000
)

$ErrorActionPreference = "Stop"
$Project = Split-Path -Parent $PSScriptRoot
Set-Location $Project

$LogDir = Join-Path $Project "var\log"
New-Item -ItemType Directory -Force -Path $LogDir | Out-Null

$Connection = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
if ($Connection) {
    Write-Host "[OK] EZScore web already listening on port $Port."
    exit 0
}

$OutLog = Join-Path $LogDir "ezscore-web.out.log"
$ErrLog = Join-Path $LogDir "ezscore-web.err.log"

$Symfony = Get-Command symfony -ErrorAction SilentlyContinue
if ($Symfony) {
    Start-Process `
        -FilePath $Symfony.Source `
        -ArgumentList @("server:start", "--no-tls", "--port=$Port") `
        -WorkingDirectory $Project `
        -WindowStyle Hidden `
        -RedirectStandardOutput $OutLog `
        -RedirectStandardError $ErrLog

    Write-Host "[OK] Symfony web server launched on http://127.0.0.1:$Port"
    exit 0
}

$Php = (Get-Command php -ErrorAction Stop).Source

Start-Process `
    -FilePath $Php `
    -ArgumentList @("-S", "127.0.0.1:$Port", "-t", "public") `
    -WorkingDirectory $Project `
    -WindowStyle Hidden `
    -RedirectStandardOutput $OutLog `
    -RedirectStandardError $ErrLog

Write-Host "[OK] PHP web server launched on http://127.0.0.1:$Port"
