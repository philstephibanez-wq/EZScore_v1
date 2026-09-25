$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

Write-Host 'R14 hardening: rotating local APP_SECRET...'

$envLocal = Join-Path $root '.env.local'
if (-not (Test-Path $envLocal)) {
    Copy-Item (Join-Path $root '.env.example') $envLocal
}

$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$bytes = New-Object byte[] 32
$rng.GetBytes($bytes)
$rng.Dispose()
$secret = -join ($bytes | ForEach-Object { $_.ToString('x2') })

$content = [System.IO.File]::ReadAllText($envLocal)
if ($content -match '(?m)^APP_SECRET=.*$') {
    $content = [System.Text.RegularExpressions.Regex]::Replace(
        $content,
        '(?m)^APP_SECRET=.*$',
        ('APP_SECRET=' + $secret)
    )
} else {
    $content = $content.TrimEnd() + [Environment]::NewLine + 'APP_SECRET=' + $secret + [Environment]::NewLine
}
[System.IO.File]::WriteAllText($envLocal, $content, (New-Object System.Text.UTF8Encoding($false)))

Write-Host 'R14 hardening: removing obsolete tracked source files...'
$obsolete = @(
    'assets\css\app.css',
    'assets\js\app.js',
    'public\assets\js\interaction-feedback.js',
    'translations\messages.r741.fr.yaml',
    'translations\messages.r741.en.yaml',
    'src\Controller\.gitignore',
    'src\Entity\.gitignore',
    'src\Repository\.gitignore',
    'translations\.gitignore',
    'migrations\.gitignore'
)

foreach ($relative in $obsolete) {
    $path = Join-Path $root $relative
    if (Test-Path $path) {
        Remove-Item $path -Force
        Write-Host "  removed $relative"
    }
}

foreach ($dir in @('assets\css', 'assets\js', 'assets', 'src\Entity', 'src\Repository')) {
    $path = Join-Path $root $dir
    if ((Test-Path $path) -and -not (Get-ChildItem $path -Force | Select-Object -First 1)) {
        Remove-Item $path -Force
    }
}

Write-Host 'R14 hardening: untracking runtime uploads while keeping local files...'
$trackedUploads = @(git ls-files -- 'public/uploads/*')
foreach ($tracked in $trackedUploads) {
    if ($tracked) {
        git rm --cached -- $tracked | Out-Host
    }
}

Write-Host ''
Write-Host 'APP_SECRET rotated in .env.local.'
Write-Host 'Old public secret is intentionally invalidated; existing remember-me cookies may need a new login.'
Write-Host 'No commit and no push were performed.'
