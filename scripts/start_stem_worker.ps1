param(
    [switch]$Once,
    [int]$Sleep = 2
)

$ErrorActionPreference = "Stop"
$Project = Split-Path -Parent $PSScriptRoot
Set-Location $Project

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

if (-not $env:EZSCORE_STEM_PYTHON) {
    throw "Python STEM introuvable. Définir EZSCORE_STEM_PYTHON."
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

Write-Host "EZScore STEM-only worker"
Write-Host "Python : $env:EZSCORE_STEM_PYTHON"
Write-Host "BS models : $env:BS_ROFORMER_MODELS_PATH"
Write-Host "MelBand models : $env:MELBAND_ROFORMER_MODELS_PATH"
Write-Host "Device : $env:EZSCORE_STEM_DEVICE"
Write-Host ""

if ($Once) {
    php bin\console app:stems:worker --once
} else {
    php bin\console app:stems:worker --sleep=$Sleep
}
