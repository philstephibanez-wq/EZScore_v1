$TaskName = "EZScore STEM Worker"

$Task = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
if ($Task) {
    Stop-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
    Write-Host "[OK] Removed scheduled task: $TaskName"
} else {
    Write-Host "[INFO] Scheduled task not installed: $TaskName"
}
