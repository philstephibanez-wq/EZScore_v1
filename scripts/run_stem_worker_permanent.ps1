$ErrorActionPreference = "Continue"

$Project = Split-Path -Parent $PSScriptRoot
Set-Location $Project

$LogDir = Join-Path $Project "var\log"
if (-not (Test-Path $LogDir)) {
    New-Item -ItemType Directory -Force -Path $LogDir | Out-Null
}
$SupervisorLog = Join-Path $LogDir "stem-worker-permanent.log"

function Write-SupervisorLog([string]$Message) {
    $Line = "[{0}] {1}" -f (Get-Date -Format "yyyy-MM-dd HH:mm:ss"), $Message
    Add-Content -Path $SupervisorLog -Value $Line -Encoding UTF8
}

if (-not $env:EZSCORE_STEM_PYTHON) {
    $Candidates = @(
        "H:\EZScore_v1\.venv-py313\Scripts\python.exe",
        "H:\EZScore\.venv-py313\Scripts\python.exe"
    )

    foreach ($Candidate in $Candidates) {
        if (Test-Path $Candidate) {
            $env:EZSCORE_STEM_PYTHON = $Candidate
            break
        }
    }

    if (-not $env:EZSCORE_STEM_PYTHON) {
        $Python = Get-Command python -ErrorAction SilentlyContinue
        if ($Python) {
            $env:EZSCORE_STEM_PYTHON = $Python.Source
        }
    }
}

if (-not $env:BS_ROFORMER_MODELS_PATH -and (Test-Path "H:\EZScoreModels\bs-roformer")) {
    $env:BS_ROFORMER_MODELS_PATH = "H:\EZScoreModels\bs-roformer"
}

if (-not $env:MELBAND_ROFORMER_MODELS_PATH -and (Test-Path "H:\EZScoreModels\melband-roformer")) {
    $env:MELBAND_ROFORMER_MODELS_PATH = "H:\EZScoreModels\melband-roformer"
}

if (-not $env:EZSCORE_RUNTIME_TMP) {
    $env:EZSCORE_RUNTIME_TMP = "H:\Temp\EZScore"
}

if (-not $env:EZSCORE_STEM_DEVICE) {
    $env:EZSCORE_STEM_DEVICE = "cuda:0"
}

if (-not $env:EZSCORE_STEM_PYTHON) {
    Write-SupervisorLog "ERROR: EZSCORE_STEM_PYTHON introuvable."
    exit 2
}

Write-SupervisorLog "Permanent STEM worker supervisor started."
Write-SupervisorLog "Python=$env:EZSCORE_STEM_PYTHON Device=$env:EZSCORE_STEM_DEVICE"

while ($true) {
    try {
        Write-SupervisorLog "Launching Symfony STEM worker."
        & php bin\console app:stems:worker --sleep=2 *>> $SupervisorLog
        $ExitCode = $LASTEXITCODE
        Write-SupervisorLog "Symfony STEM worker exited with code $ExitCode. Restarting in 5 seconds."
    }
    catch {
        Write-SupervisorLog ("Worker exception: " + $_.Exception.Message)
    }

    Start-Sleep -Seconds 5
}
