param(
    [int]$Port = 8501
)

$ErrorActionPreference = "Stop"
$Project = Split-Path -Parent $PSScriptRoot
Set-Location $Project

$LogDir = Join-Path $Project "var\log"
$RuntimeDir = Join-Path $Project "var\runtime"
New-Item -ItemType Directory -Force -Path $LogDir,$RuntimeDir | Out-Null

$PidFile = Join-Path $RuntimeDir "ezscore-web.pid"
$OutLog = Join-Path $LogDir "ezscore-web.out.log"
$ErrLog = Join-Path $LogDir "ezscore-web.err.log"
$Php = (Get-Command php -ErrorAction Stop).Source
$PublicDir = Join-Path $Project "public"

function Test-EZScorePhpServer {
    param([Parameter(Mandatory=$true)][int]$ProcessId)

    try {
        $Proc = Get-CimInstance Win32_Process -Filter "ProcessId = $ProcessId" -ErrorAction Stop
        if ($null -eq $Proc) { return $false }

        $Cmd = [string]$Proc.CommandLine
        if ([string]::IsNullOrWhiteSpace($Cmd)) { return $false }

        $Normalized = $Cmd.ToLowerInvariant().Replace('"','')
        $ExpectedBind = "-s 127.0.0.1:$Port"
        $ExpectedDocRoot = ("-t " + $PublicDir).ToLowerInvariant()

        return (
            $Normalized.Contains($ExpectedBind) -and
            $Normalized.Contains($ExpectedDocRoot)
        )
    }
    catch {
        return $false
    }
}

if (Test-Path $PidFile) {
    $StoredPid = 0
    [void][int]::TryParse((Get-Content $PidFile -Raw -ErrorAction SilentlyContinue).Trim(), [ref]$StoredPid)

    if ($StoredPid -gt 0) {
        $StoredListener = Get-NetTCPConnection `
            -LocalPort $Port `
            -State Listen `
            -OwningProcess $StoredPid `
            -ErrorAction SilentlyContinue

        if ($null -ne $StoredListener -and (Test-EZScorePhpServer -ProcessId $StoredPid)) {
            Write-Output "[OK] Serveur EZScore deja actif (PID $StoredPid)."
            Write-Output "[CMD] php -S 127.0.0.1:$Port -t $PublicDir"
            exit 0
        }
    }

    Remove-Item $PidFile -Force -ErrorAction SilentlyContinue
}

$Listener = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue |
    Select-Object -First 1

if ($null -ne $Listener) {
    $ExistingPid = [int]$Listener.OwningProcess

    if (Test-EZScorePhpServer -ProcessId $ExistingPid) {
        $ExistingPid | Set-Content -Path $PidFile -Encoding ASCII
        Write-Output "[OK] Serveur EZScore existant reconnu et reutilise (PID $ExistingPid)."
        Write-Output "[CMD] php -S 127.0.0.1:$Port -t $PublicDir"
        exit 0
    }

    $ForeignProcess = Get-Process -Id $ExistingPid -ErrorAction SilentlyContinue
    $Name = if ($null -ne $ForeignProcess) { $ForeignProcess.ProcessName } else { "inconnu" }

    $ForeignCmd = ""
    try {
        $ForeignCmd = [string](Get-CimInstance Win32_Process -Filter "ProcessId = $ExistingPid").CommandLine
    } catch {}

    throw "Le port $Port est utilise par PID $ExistingPid ($Name), mais ce n'est pas le serveur EZScore attendu. Commande detectee : $ForeignCmd"
}

$Arguments = @(
    "-S",
    "127.0.0.1:$Port",
    "-t",
    $PublicDir
)

$Process = Start-Process `
    -FilePath $Php `
    -ArgumentList $Arguments `
    -WorkingDirectory $Project `
    -WindowStyle Hidden `
    -PassThru `
    -RedirectStandardOutput $OutLog `
    -RedirectStandardError $ErrLog

$Process.Id | Set-Content -Path $PidFile -Encoding ASCII

Start-Sleep -Milliseconds 450

if ($Process.HasExited) {
    Remove-Item $PidFile -Force -ErrorAction SilentlyContinue
    $Err = if (Test-Path $ErrLog) { Get-Content $ErrLog -Raw -ErrorAction SilentlyContinue } else { "" }
    throw "Le serveur PHP s'est arrete immediatement. $Err"
}

Write-Output "[OK] Serveur EZScore demarre (PID $($Process.Id))."
Write-Output "[CMD] php -S 127.0.0.1:$Port -t $PublicDir"
