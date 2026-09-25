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
$form.Size = New-Object System.Drawing.Size(680, 430)
$form.MinimumSize = New-Object System.Drawing.Size(680, 430)
$form.MaximizeBox = $false
$form.BackColor = [System.Drawing.Color]::FromArgb(18, 26, 32)
$form.ForeColor = [System.Drawing.Color]::White
$form.Font = New-Object System.Drawing.Font("Segoe UI", 10)

$title = New-Object System.Windows.Forms.Label
$title.Text = "EZScore_v1"
$title.Font = New-Object System.Drawing.Font("Segoe UI", 20, [System.Drawing.FontStyle]::Bold)
$title.Location = New-Object System.Drawing.Point(24, 18)
$title.AutoSize = $true
$form.Controls.Add($title)

$subtitle = New-Object System.Windows.Forms.Label
$subtitle.Text = "Démarrage local"
$subtitle.ForeColor = [System.Drawing.Color]::FromArgb(160, 180, 190)
$subtitle.Location = New-Object System.Drawing.Point(28, 60)
$subtitle.AutoSize = $true
$form.Controls.Add($subtitle)

$status = New-Object System.Windows.Forms.Label
$status.Text = "Initialisation…"
$status.Font = New-Object System.Drawing.Font("Segoe UI", 11, [System.Drawing.FontStyle]::Bold)
$status.Location = New-Object System.Drawing.Point(28, 96)
$status.Size = New-Object System.Drawing.Size(610, 26)
$form.Controls.Add($status)

$progress = New-Object System.Windows.Forms.ProgressBar
$progress.Location = New-Object System.Drawing.Point(28, 130)
$progress.Size = New-Object System.Drawing.Size(610, 20)
$progress.Minimum = 0
$progress.Maximum = 100
$progress.Value = 2
$form.Controls.Add($progress)

$log = New-Object System.Windows.Forms.TextBox
$log.Location = New-Object System.Drawing.Point(28, 168)
$log.Size = New-Object System.Drawing.Size(610, 160)
$log.Multiline = $true
$log.ReadOnly = $true
$log.ScrollBars = "Vertical"
$log.BackColor = [System.Drawing.Color]::FromArgb(7, 11, 15)
$log.ForeColor = [System.Drawing.Color]::FromArgb(200, 247, 210)
$log.Font = New-Object System.Drawing.Font("Consolas", 9)
$form.Controls.Add($log)

$closeButton = New-Object System.Windows.Forms.Button
$closeButton.Text = "Fermer"
$closeButton.Location = New-Object System.Drawing.Point(538, 342)
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

function Add-Output {
    param([object]$Line)

    if ($null -eq $Line) {
        return
    }

    $Text = [string]$Line
    if ([string]::IsNullOrWhiteSpace($Text)) {
        return
    }

    $log.AppendText("    $Text`r`n")
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

# Script scope avoids the R24.8 null closure bug in the WinForms timer callback.
$script:closeTimer = $null

$form.Add_Shown({
    try {
        Add-Step "Launcher visible." 5

        Add-Step "Arrêt de l'ancien worker planifié…" 10
        $LegacyTask = Get-ScheduledTask -TaskName "EZScore STEM Worker" -ErrorAction SilentlyContinue
        if ($null -ne $LegacyTask) {
            Stop-ScheduledTask -TaskName "EZScore STEM Worker" -ErrorAction SilentlyContinue
            Disable-ScheduledTask -TaskName "EZScore STEM Worker" -ErrorAction SilentlyContinue | Out-Null
            Add-Step "Ancien worker planifié désactivé." 16
        } else {
            Add-Step "Aucun ancien worker planifié." 16
        }

        Add-Step "Vérification du token worker…" 24
        & (Join-Path $PSScriptRoot "ensure_analysis_worker_token.ps1") | ForEach-Object { Add-Output $_ }

        # Startup must be fast. Runtime installation/repair is not repeated here.
        # The desktop Worker performs its own Python/RoFormer/CUDA capability probe.
        Add-Step "Démarrage du serveur EZScore 127.0.0.1:$Port…" 38
        & (Join-Path $PSScriptRoot "start_ezscore_web.ps1") -Port $Port | ForEach-Object { Add-Output $_ }

        $Url = "http://127.0.0.1:$Port/fr/login"

        Add-Step "Attente de la réponse du serveur…" 52
        $Ready = $false
        for ($i = 0; $i -lt 25; $i++) {
            try {
                $Response = Invoke-WebRequest -UseBasicParsing -Uri $Url -TimeoutSec 1
                if ($Response.StatusCode -ge 200 -and $Response.StatusCode -lt 500) {
                    $Ready = $true
                    break
                }
            } catch {
                Start-Sleep -Milliseconds 250
            }
            [System.Windows.Forms.Application]::DoEvents()
        }

        if (-not $Ready) {
            throw "Le serveur web ne répond pas sur $Url. Voir var\log\ezscore-web.err.log."
        }

        Add-Step "Serveur web prêt." 64
        $env:EZSCORE_WORKER_URL = "http://127.0.0.1:$Port"

        # Launch the visible backend as soon as the web server is ready.
        Add-Step "Ouverture de EZScore Analysis Worker…" 74
        & (Join-Path $PSScriptRoot "start_analysis_worker_desktop.ps1") | ForEach-Object { Add-Output $_ }

        Add-Step "Worker lancé ; vérification Python/CUDA dans sa fenêtre…" 84

        Add-Step "Ouverture du navigateur…" 92
        Start-Process $Url

        Add-Step "EZScore est prêt." 100
        $status.ForeColor = [System.Drawing.Color]::FromArgb(150, 230, 170)
        $closeButton.Enabled = $true

        $script:closeTimer = New-Object System.Windows.Forms.Timer
        $script:closeTimer.Interval = 2200
        $script:closeTimer.Add_Tick({
            if ($null -ne $script:closeTimer) {
                $script:closeTimer.Stop()
                $script:closeTimer.Dispose()
                $script:closeTimer = $null
            }

            if ($null -ne $form -and -not $form.IsDisposed) {
                $form.Close()
            }
        })
        $script:closeTimer.Start()
    }
    catch {
        Fail-Step $_.Exception.Message
    }
})

$form.Add_FormClosed({
    if ($null -ne $script:closeTimer) {
        $script:closeTimer.Stop()
        $script:closeTimer.Dispose()
        $script:closeTimer = $null
    }
})

[void]$form.ShowDialog()
