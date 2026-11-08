param([int]$StaleDays = 60)
$brainRoot = Split-Path -Parent $PSScriptRoot
$cutoff = (Get-Date).AddDays(-$StaleDays)
$folders = 'architecture', 'decisions', 'modules', 'fixes'
$old = foreach ($folder in $folders) {
    $path = Join-Path $brainRoot $folder
    if (Test-Path $path) { Get-ChildItem $path -File -Recurse | Where-Object { $_.LastWriteTime -lt $cutoff } }
}
Write-Output '# Context Decay Report'
if (-not $old) { Write-Output "No knowledge files older than $StaleDays days require review." } else {
    Write-Output 'Review before archiving; this tool never moves or deletes files:'
    $old | ForEach-Object { Write-Output "- $($_.FullName.Substring($brainRoot.Length + 1)) (last changed $($_.LastWriteTime.ToString('yyyy-MM-dd')))" }
}
