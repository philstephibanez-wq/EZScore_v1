$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot

$obsolete = @(
    (Join-Path $root "scripts\update_recette_r15.ps1")
)

foreach ($path in $obsolete) {
    if (Test-Path $path) {
        Remove-Item -Force $path
        Write-Host "Removed obsolete file: $path"
    }
}

Write-Host "R16 ACL cleanup complete."
