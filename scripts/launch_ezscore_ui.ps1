param(
    [int]$Port = 8501
)

$ErrorActionPreference = "Stop"
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing

$Project = Split-Path -Parent $PSScriptRoot
Set-Location $Project

$form = New-Object System.Windows.Forms.Form
$form.Text = "EZScore Launcher"
$form.StartPosition = "CenterScreen"
$form.Size = New-Object System.Drawing.Size(660, 420)
$form.MinimumSize = New-Object System.Drawing.Size(660, 420)
$form.MaximizeBox = $false
$form.BackColor = [System.Drawing.Color]::FromArgb(18, 26, 32)
$form.ForeColor = [System.Drawing.Color]::White
$form.Font = New-Object System.Drawing.Font("Segoe UI", 10)

$title = New-Object System.Windows.Forms.Label
$title.Text = "EZScore_v1"
$title.Font = New-Object System.Drawing.Font("Segoe UI", 20, [System.Drawing.FontStyle]::Bold)
$title.Location = New-Object System.Drawing.Point(24, 20)
$title.AutoSize = $true
$form.Controls.Add($title)

$subtitle = New-Object System.Windows.Forms.Label
$subtitle.Text = "Démarrage de l'environnement local"
$subtitle.ForeColor = [System.Drawing.Color]::FromArgb(160, 180, 190)
$subtitle.Location = New-Object System.Drawing.Point(28, 62)
$subtitle.AutoSize = $true
$form.Controls.Add($subtitle)

$status = New-Object System.Windows.Forms.Label
$status.Text = "Initialisation…"
$status.Font = New-Object System.Drawing.Font("Segoe UI", 11, [System.Drawing.FontStyle]::Bold)
$status.Location = New-Object System.Drawing.Point(28, 98)
$status.Size = New-Object System.Drawing.Size(590, 26)
$form.Controls.Add($status)

$progress = New-Object System.Windows.Forms.ProgressBar
$progress.Location = New-Object System.Drawing.Point(28, 132)
$progress.Size = New-Object System.Drawing.Size(590, 20)
$progress.Minimum = 0
$progress.Maximum = 100
$progress.Value = 0
$form.Controls.Add($progress)

$log = New-Object System.Windows.Forms.TextBox
$log.Location = New-Object System.Drawing.Point(28, 170)
$log.Size = New-Object System.Drawing.Size(590, 150)
$log.Multiline = $true
$log.ReadOnly = $true
$log.ScrollBars = "Vertical"
$log.BackColor = [System.Drawing.Color]::FromArgb(7, 11, 15)
$log.ForeColor = [System.Drawing.Color]::FromArgb(200, 247, 210)
$log.Font = New-Object System.Drawing.Font("Consolas", 9)
$form.Controls.Add($log)

$closeButton = New-Object System.Windows.Forms.Button
$closeButton.Text = "Fermer"
$closeButton.Location = New-Object System.Drawing.Point(518, 334)
$closeButton.Size = New-Object System.Drawing.Size(100, 30)
$closeButton.Enabled = $false
$closeButton.Add_Click({ $form.Close() })
$form.Controls.Add($closeButton)

function Add-Step {
    param(
        [Parameter(Mandatory=$true)][string]$Message,
        [int]$Percent
    )

    $status.Text = $Message
    if ($Percent -ge 0 -and $Percent -le 100) {
        $progress.Value = $Percent
    }

    $timestamp = Get-Date -Format "HH:mm:ss"
    $log.AppendText("[$timestamp] $Message`r`n")
    $log.SelectionStart = $log.TextLength
    $log.ScrollToCaret()

    [System.Windows.Forms.Application]::DoEvents()
}

function Fail-Step {
    param([Parameter(Mandatory=$true)][string]$Message)

    $status.Text = "ÉCHEC"
    $status.ForeColor = [System.Drawing.Color]::FromArgb(245, 150, 150)
    $log.AppendText("`r`nERREUR: $Message`r`n")
    $log.SelectionStart = $log.TextLength
    $log.ScrollToCaret()
    $closeButton.Enabled = $true
    [System.Windows.Forms.Application]::DoEvents()
}

$script:startupComplete = $false

$form.Add_Shown({
    try {
        Add-Step "Arrêt de l'ancien worker planifié…" 8
        $LegacyTask = Get-ScheduledTask -TaskName "EZScore STEM Worker" -ErrorAction SilentlyContinue
        if ($LegacyTask) {
            Stop-ScheduledTask -TaskName "EZScore STEM Worker" -ErrorAction SilentlyContinue
            Disable-ScheduledTask -TaskName "EZScore STEM Worker" -ErrorAction SilentlyContinue | Out-Null
            Add-Step "Ancien worker planifié désactivé." 14
        } else {
            Add-Step "Aucun ancien worker planifié actif." 14
        }

        Add-Step "Vérification du token worker…" 22
        & (Join-Path $PSScriptRoot "ensure_analysis_worker_token.ps1") | Out-Null

        $PrepareRuntime = Join-Path $PSScriptRoot "prepare_analysis_runtime.ps1"
        if (Test-Path $PrepareRuntime) {
            Add-Step "Vérification Python / RoFormer / CUDA…" 34
            & $PrepareRuntime | ForEach-Object {
                if ($_ -ne $null -and "$_".Trim() -ne "") {
                    $log.AppendText("    $_`r`n")
                    $log.SelectionStart = $log.TextLength
                    $log.ScrollToCaret()
                    [System.Windows.Forms.Application]::DoEvents()
                }
            }
        }

        Add-Step "Démarrage du serveur EZScore sur 127.0.0.1:$Port…" 55
        & (Join-Path $PSScriptRoot "start_ezscore_web.ps1") -Port $Port | ForEach-Object {
            if ($_ -ne $null -and "$_".Trim() -ne "") {
                $log.AppendText("    $_`r`n")
                [System.Windows.Forms.Application]::DoEvents()
            }
        }

        $Url = "http://127.0.0.1:$Port/fr/login"

        Add-Step "Attente du serveur web…" 68
        $Ready = $false
        for ($i = 0; $i -lt 30; $i++) {
            try {
                $Response = Invoke-WebRequest -UseBasicParsing -Uri $Url -TimeoutSec 2
                if ($Response.StatusCode -ge 200 -and $Response.StatusCode -lt 500) {
                    $Ready = $true
                    break
                }
            } catch {
                Start-Sleep -Milliseconds 400
            }
            [System.Windows.Forms.Application]::DoEvents()
        }

        if (-not $Ready) {
            throw "Le serveur web n'a pas répondu sur $Url."
        }

        Add-Step "Serveur web prêt." 76
        $env:EZSCORE_WORKER_URL = "http://127.0.0.1:$Port"

        Add-Step "Démarrage de EZScore Analysis Worker…" 84
        & (Join-Path $PSScriptRoot "start_analysis_worker_desktop.ps1") | ForEach-Object {
            if ($_ -ne $null -and "$_".Trim() -ne "") {
                $log.AppendText("    $_`r`n")
                [System.Windows.Forms.Application]::DoEvents()
            }
        }

        Add-Step "Ouverture du navigateur…" 94
        Start-Process $Url

        Add-Step "EZScore est prêt." 100
        $status.ForeColor = [System.Drawing.Color]::FromArgb(150, 230, 170)
        $closeButton.Enabled = $true
        $script:startupComplete = $true

        $timer = New-Object System.Windows.Forms.Timer
        $timer.Interval = 1600
        $timer.Add_Tick({
            $timer.Stop()
            if (-not $form.IsDisposed) {
                $form.Close()
            }
        })
        $timer.Start()
    }
    catch {
        Fail-Step $_.Exception.Message
    }
})

[void]$form.ShowDialog()
