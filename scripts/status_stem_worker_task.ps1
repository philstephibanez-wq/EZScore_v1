$TaskName = "EZScore STEM Worker"
$Project = Split-Path -Parent $PSScriptRoot
$Log = Join-Path $Project "var\log\stem-worker-permanent.log"

$Task = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
if (-not $Task) {
    Write-Host "[ABSENT] $TaskName"
    exit 1
}

$Info = Get-ScheduledTaskInfo -TaskName $TaskName
Write-Host "Task:       $TaskName"
Write-Host "State:      $($Task.State)"
Write-Host "Last run:   $($Info.LastRunTime)"
Write-Host "Last result:$($Info.LastTaskResult)"
Write-Host "Next run:   $($Info.NextRunTime)"
Write-Host ""

if (Test-Path $Log) {
    Write-Host "Permanent worker log:"
    Get-Content $Log -Tail 30
} else {
    Write-Host "No supervisor log yet: $Log"
}
