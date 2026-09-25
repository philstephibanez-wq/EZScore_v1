param(
    [int]$Sleep = 2
)

$ErrorActionPreference = "Stop"

Set-Location (Split-Path -Parent $PSScriptRoot)

Write-Host "EZScore publication-mail worker"
Write-Host "Stop with Ctrl+C."
Write-Host ""

php bin\console app:mailing:worker --sleep=$Sleep
