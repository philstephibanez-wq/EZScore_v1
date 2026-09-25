param(
    [int]$Port = 8501
)

$Project = Split-Path -Parent $PSScriptRoot
$LogDir = Join-Path $Project "var\log"
$RuntimeDir = Join-Path $Project "var\runtime"
$LauncherLog = Join-Path $LogDir "ezscore-launcher.log"
$StatusFile = Join-Path $RuntimeDir "launcher-status.json"
$Backend = Join-Path $PSScriptRoot "launch_ezscore_backend.ps1"

New-Item -ItemType Directory -Force -Path $LogDir,$RuntimeDir | Out-Null

function Write-LauncherLog {
    param([string]$Message)
    $stamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss.fff"
    Add-Content -Path $LauncherLog -Value "[$stamp] $Message" -Encoding UTF8
}

Write-LauncherLog "Launcher process started. Project=$Project Port=$Port"

try {
    Add-Type -AssemblyName System.Windows.Forms
    Add-Type -AssemblyName System.Drawing

    Write-LauncherLog "WinForms assemblies loaded."

    Remove-Item $StatusFile -Force -ErrorAction SilentlyContinue

    $form = New-Object System.Windows.Forms.Form
    $form.Text = "EZScore Launcher"
    $form.StartPosition = "CenterScreen"
    $form.FormBorderStyle = "FixedSingle"
    $form.MaximizeBox = $false
    $form.MinimizeBox = $false
    $form.ClientSize = New-Object System.Drawing.Size(660, 330)
    $form.BackColor = [System.Drawing.Color]::FromArgb(18, 26, 32)
    $form.ForeColor = [System.Drawing.Color]::White
    $form.Font = New-Object System.Drawing.Font("Segoe UI", 10)
    $form.TopMost = $true

    $title = New-Object System.Windows.Forms.Label
    $title.Text = "EZScore"
    $title.Font = New-Object System.Drawing.Font("Segoe UI", 24, [System.Drawing.FontStyle]::Bold)
    $title.Location = New-Object System.Drawing.Point(28, 22)
    $title.AutoSize = $true
    $form.Controls.Add($title)

    $version = New-Object System.Windows.Forms.Label
    $version.Text = "v1"
    $version.ForeColor = [System.Drawing.Color]::FromArgb(140, 160, 170)
    $version.Location = New-Object System.Drawing.Point(162, 43)
    $version.AutoSize = $true
    $form.Controls.Add($version)

    $subtitle = New-Object System.Windows.Forms.Label
    $subtitle.Text = "Demarrage de l'environnement local"
    $subtitle.ForeColor = [System.Drawing.Color]::FromArgb(145, 165, 176)
    $subtitle.Location = New-Object System.Drawing.Point(31, 76)
    $subtitle.AutoSize = $true
    $form.Controls.Add($subtitle)

    $status = New-Object System.Windows.Forms.Label
    $status.Text = "Initialisation..."
    $status.Font = New-Object System.Drawing.Font("Segoe UI", 11, [System.Drawing.FontStyle]::Bold)
    $status.Location = New-Object System.Drawing.Point(31, 117)
    $status.Size = New-Object System.Drawing.Size(595, 26)
    $form.Controls.Add($status)

    $progress = New-Object System.Windows.Forms.ProgressBar
    $progress.Location = New-Object System.Drawing.Point(31, 151)
    $progress.Size = New-Object System.Drawing.Size(595, 18)
    $progress.Minimum = 0
    $progress.Maximum = 100
    $progress.Value = 2
    $form.Controls.Add($progress)

    $percent = New-Object System.Windows.Forms.Label
    $percent.Text = "0 %"
    $percent.ForeColor = [System.Drawing.Color]::FromArgb(145, 165, 176)
    $percent.Location = New-Object System.Drawing.Point(580, 177)
    $percent.AutoSize = $true
    $form.Controls.Add($percent)

    $detail = New-Object System.Windows.Forms.Label
    $detail.Text = "Serveur local :`r`nphp -S 127.0.0.1:8501 -t $Project\public`r`n`r`nSite : https://ezscore.logandplay.com/"
    $detail.ForeColor = [System.Drawing.Color]::FromArgb(190, 238, 200)
    $detail.Font = New-Object System.Drawing.Font("Consolas", 9)
    $detail.Location = New-Object System.Drawing.Point(31, 207)
    $detail.Size = New-Object System.Drawing.Size(595, 80)
    $form.Controls.Add($detail)

    $closeButton = New-Object System.Windows.Forms.Button
    $closeButton.Text = "Fermer"
    $closeButton.Location = New-Object System.Drawing.Point(526, 288)
    $closeButton.Size = New-Object System.Drawing.Size(100, 30)
    $closeButton.Visible = $false
    $closeButton.Add_Click({ $form.Close() })
    $form.Controls.Add($closeButton)

    $script:readySince = $null

    $timer = New-Object System.Windows.Forms.Timer
    $timer.Interval = 250
    $timer.Add_Tick({
        try {
            if (-not (Test-Path $StatusFile)) {
                return
            }

            $raw = Get-Content $StatusFile -Raw -ErrorAction Stop
            if ([string]::IsNullOrWhiteSpace($raw)) {
                return
            }

            $data = $raw | ConvertFrom-Json -ErrorAction Stop

            $status.Text = [string]$data.message
            $pct = [Math]::Max(0, [Math]::Min(100, [int]$data.percent))
            $progress.Value = $pct
            $percent.Text = "$pct %"

            if (-not [string]::IsNullOrWhiteSpace([string]$data.detail)) {
                $detail.Text = [string]$data.detail
            }

            if ($data.state -eq "ready") {
                $status.ForeColor = [System.Drawing.Color]::FromArgb(150, 230, 170)
                $closeButton.Visible = $true

                if ($null -eq $script:readySince) {
                    $script:readySince = Get-Date
                    Write-LauncherLog "Backend reported READY."
                }

                if (((Get-Date) - $script:readySince).TotalMilliseconds -ge 2200) {
                    $timer.Stop()
                    $form.Close()
                }
            }
            elseif ($data.state -eq "error") {
                $status.ForeColor = [System.Drawing.Color]::FromArgb(245, 150, 150)
                $closeButton.Visible = $true
                $timer.Stop()
                Write-LauncherLog ("Backend reported ERROR: " + [string]$data.detail)
            }
        }
        catch {
            # Atomic status file replacement can race briefly.
        }
    })

    $form.Add_Shown({
        Write-LauncherLog "Splash shown."

        [System.Windows.Forms.Application]::DoEvents()

        if (-not (Test-Path $Backend)) {
            throw "Backend launcher missing: $Backend"
        }

        $args = @(
            "-NoProfile",
            "-ExecutionPolicy", "Bypass",
            "-File", "`"$Backend`"",
            "-Port", "$Port"
        )

        $backendProcess = Start-Process `
            -FilePath "powershell.exe" `
            -ArgumentList $args `
            -WindowStyle Hidden `
            -WorkingDirectory $Project `
            -PassThru

        Write-LauncherLog "Backend launched. PID=$($backendProcess.Id)"
        $timer.Start()
    })

    $form.Add_FormClosed({
        if ($timer.Enabled) {
            $timer.Stop()
        }
        $timer.Dispose()
        Write-LauncherLog "Splash closed."
    })

    Write-LauncherLog "Showing splash."
    [void]$form.ShowDialog()
}
catch {
    Write-LauncherLog ("FATAL: " + $_.Exception.ToString())

    try {
        Add-Type -AssemblyName System.Windows.Forms -ErrorAction SilentlyContinue
        [System.Windows.Forms.MessageBox]::Show(
            "Le launcher EZScore n'a pas pu démarrer.`r`n`r`n$($_.Exception.Message)`r`n`r`nLog : $LauncherLog",
            "EZScore Launcher",
            [System.Windows.Forms.MessageBoxButtons]::OK,
            [System.Windows.Forms.MessageBoxIcon]::Error
        ) | Out-Null
    }
    catch {
        # Last resort: the log still contains the failure.
    }

    exit 1
}
