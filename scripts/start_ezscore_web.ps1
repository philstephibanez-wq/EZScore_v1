param(
    [int]$Port = 8501
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

$Php = (Get-Command php -ErrorAction Stop).Source
$OutLog = Join-Path $LogDir "ezscore-web.out.log"
$ErrLog = Join-Path $LogDir "ezscore-web.err.log"

$Arguments = @(
    "-S",
    "127.0.0.1:$Port",
    "-t",
    (Join-Path $Project "public")
)

$Process = Start-Process `
    -FilePath $Php `
    -ArgumentList $Arguments `
    -WorkingDirectory $Project `
    -WindowStyle Hidden `
    -PassThru `
    -RedirectStandardOutput $OutLog `
    -RedirectStandardError $ErrLog

Start-Sleep -Milliseconds 700

if ($Process.HasExited) {
    $Err = if (Test-Path $ErrLog) { Get-Content $ErrLog -Raw -ErrorAction SilentlyContinue } else { "" }
    throw "EZScore PHP server exited immediately. $Err"
}

Write-Host "[OK] EZScore web server PID $($Process.Id): php -S 127.0.0.1:$Port -t $Project\public"
