$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$obsolete = @(
    (Join-Path $root "scripts\update_recette_r15.ps1")
)

foreach ($path in $obsolete) {
    if (Test-Path $path) {
        Remove-Item -Force $path
        Write-Host "Removed obsolete R15 script: $path"
    }
}

Write-Host "R16.2 files installed."
Write-Host "Existing database is preserved."
Write-Host "Run Doctrine migration/validation commands from readme.md."
