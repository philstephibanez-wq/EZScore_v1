$ErrorActionPreference = "Stop"

$Project = Split-Path -Parent $PSScriptRoot
Set-Location $Project

$VenvDir = Join-Path $Project ".venv-py313"
$Python = Join-Path $VenvDir "Scripts\python.exe"
$Requirements = Join-Path $Project "analysis\requirements-stems.txt"
$TorchIndex = if ($env:EZSCORE_TORCH_INDEX_URL) {
    $env:EZSCORE_TORCH_INDEX_URL
} else {
    "https://download.pytorch.org/whl/cu132"
}

function Invoke-PythonFileProbe {
    param(
        [Parameter(Mandatory=$true)][string]$Code,
        [switch]$Quiet
    )

    $Probe = Join-Path $env:TEMP ("ezscore-probe-" + [guid]::NewGuid().ToString("N") + ".py")
    $StdOut = Join-Path $env:TEMP ("ezscore-out-" + [guid]::NewGuid().ToString("N") + ".txt")
    $StdErr = Join-Path $env:TEMP ("ezscore-err-" + [guid]::NewGuid().ToString("N") + ".txt")

    try {
        [System.IO.File]::WriteAllText($Probe, $Code, [System.Text.UTF8Encoding]::new($false))

        $Process = Start-Process `
            -FilePath $Python `
            -ArgumentList @($Probe) `
            -WorkingDirectory $Project `
            -Wait `
            -PassThru `
            -NoNewWindow `
            -RedirectStandardOutput $StdOut `
            -RedirectStandardError $StdErr

        $Out = if (Test-Path $StdOut) { Get-Content $StdOut -Raw -ErrorAction SilentlyContinue } else { "" }
        $Err = if (Test-Path $StdErr) { Get-Content $StdErr -Raw -ErrorAction SilentlyContinue } else { "" }

        if (-not $Quiet) {
            if ($Out) { Write-Host $Out.TrimEnd() }
            if ($Err) { Write-Host $Err.TrimEnd() -ForegroundColor Yellow }
        }

        return [pscustomobject]@{
            ExitCode = $Process.ExitCode
            StdOut = $Out
            StdErr = $Err
        }
    }
    finally {
        Remove-Item $Probe,$StdOut,$StdErr -Force -ErrorAction SilentlyContinue
    }
}

function Install-CudaTorch {
    Write-Host "[INFO] Replacing CPU-only Torch with CUDA Torch in EZScore_v1..."
    Write-Host "[INFO] PyTorch index: $TorchIndex"
    Write-Host "[INFO] Installing torch only; torchaudio is not required by the STEM pipeline."

    $Out = Join-Path $env:TEMP ("ezscore-torch-out-" + [guid]::NewGuid().ToString("N") + ".txt")
    $Err = Join-Path $env:TEMP ("ezscore-torch-err-" + [guid]::NewGuid().ToString("N") + ".txt")

    try {
        $Install = Start-Process `
            -FilePath $Python `
            -ArgumentList @(
                "-m", "pip", "install",
                "--upgrade",
                "--force-reinstall",
                "torch",
                "--index-url", $TorchIndex
            ) `
            -WorkingDirectory $Project `
            -Wait `
            -PassThru `
            -NoNewWindow `
            -RedirectStandardOutput $Out `
            -RedirectStandardError $Err

        if (Test-Path $Out) { Get-Content $Out }
        if (Test-Path $Err) { Get-Content $Err }

        if ($Install.ExitCode -ne 0) {
            throw "CUDA Torch installation failed with exit code $($Install.ExitCode)."
        }
    }
    finally {
        Remove-Item $Out,$Err -Force -ErrorAction SilentlyContinue
    }
}

if (-not (Test-Path $Python)) {
    Write-Host "[INFO] Creating H:\EZScore_v1\.venv-py313..."
    $Py = Get-Command py -ErrorAction SilentlyContinue
    if (-not $Py) {
        throw "Python launcher 'py' is missing. Python 3.13 is required."
    }

    & py -3.13 -m venv $VenvDir
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path $Python)) {
        throw "Unable to create H:\EZScore_v1\.venv-py313."
    }
}

Write-Host "[INFO] EZScore_v1 Python: $Python"

$ImportCode = @'
import sys
import bs_roformer
import mel_band_roformer
print("PY=", sys.executable)
print("STEM_IMPORTS_OK")
'@

$ImportProbe = Invoke-PythonFileProbe -Code $ImportCode -Quiet

if ($ImportProbe.ExitCode -ne 0) {
    Write-Host "[INFO] STEM packages missing/broken in EZScore_v1; installing requirements..."

    if (-not (Test-Path $Requirements)) {
        if ($ImportProbe.StdErr) { Write-Host $ImportProbe.StdErr.TrimEnd() -ForegroundColor Yellow }
        throw "Missing analysis\requirements-stems.txt"
    }

    $InstallOut = Join-Path $env:TEMP ("ezscore-pip-out-" + [guid]::NewGuid().ToString("N") + ".txt")
    $InstallErr = Join-Path $env:TEMP ("ezscore-pip-err-" + [guid]::NewGuid().ToString("N") + ".txt")

    try {
        $Install = Start-Process `
            -FilePath $Python `
            -ArgumentList @("-m", "pip", "install", "-r", $Requirements) `
            -WorkingDirectory $Project `
            -Wait `
            -PassThru `
            -NoNewWindow `
            -RedirectStandardOutput $InstallOut `
            -RedirectStandardError $InstallErr

        if (Test-Path $InstallOut) { Get-Content $InstallOut }
        if (Test-Path $InstallErr) { Get-Content $InstallErr }

        if ($Install.ExitCode -ne 0) {
            throw "STEM dependency installation failed with exit code $($Install.ExitCode)."
        }
    }
    finally {
        Remove-Item $InstallOut,$InstallErr -Force -ErrorAction SilentlyContinue
    }

    $ImportProbe = Invoke-PythonFileProbe -Code $ImportCode
    if ($ImportProbe.ExitCode -ne 0) {
        throw "STEM imports still fail after pip install."
    }
}
else {
    Write-Host "[OK] bs_roformer + mel_band_roformer imports."
}

$CudaCode = @'
import sys
import torch
print("PY=", sys.executable)
print("TORCH=", torch.__version__)
print("TORCH CUDA=", torch.version.cuda)
print("CUDA=", torch.cuda.is_available())
print("GPU=", torch.cuda.get_device_name(0) if torch.cuda.is_available() else "NONE")
raise SystemExit(0 if torch.cuda.is_available() else 2)
'@

Write-Host "[INFO] Checking Torch / CUDA in EZScore_v1..."
$CudaProbe = Invoke-PythonFileProbe -Code $CudaCode

if ($CudaProbe.ExitCode -eq 2) {
    Write-Host "[INFO] CPU-only Torch detected."
    Install-CudaTorch

    Write-Host "[INFO] Rechecking Torch / CUDA..."
    $CudaProbe = Invoke-PythonFileProbe -Code $CudaCode
}

if ($CudaProbe.ExitCode -eq 2) {
    throw "CUDA remains unavailable after CUDA Torch installation."
}
if ($CudaProbe.ExitCode -ne 0) {
    throw "EZScore_v1 STEM runtime validation failed with exit code $($CudaProbe.ExitCode)."
}

$FinalProbe = Invoke-PythonFileProbe -Code @'
import sys
import torch
import bs_roformer
import mel_band_roformer

assert torch.cuda.is_available()
print("PY=", sys.executable)
print("TORCH=", torch.__version__)
print("TORCH CUDA=", torch.version.cuda)
print("GPU=", torch.cuda.get_device_name(0))
print("STEM_RUNTIME_OK")
'@

if ($FinalProbe.ExitCode -ne 0) {
    throw "Final STEM runtime validation failed after CUDA Torch installation."
}

Write-Host "[OK] EZScore_v1 STEM runtime ready."
