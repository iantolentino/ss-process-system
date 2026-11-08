param(
    [int]$YellowAfterMinutes = 60,
    [int]$RedAfterMinutes = 120
)

$brainRoot = Split-Path -Parent $PSScriptRoot
$manifestDirectory = Join-Path $brainRoot 'sessions/manifests'
$latestManifest = if (Test-Path -LiteralPath $manifestDirectory) {
    Get-ChildItem -LiteralPath $manifestDirectory -Filter 'context-*.md' -File |
        Sort-Object LastWriteTime -Descending |
        Select-Object -First 1
}

if (-not $latestManifest) {
    Write-Output 'Session Health: GREEN'
    Write-Output 'No context manifest exists yet. Start by running the context selector.'
    exit 0
}

$elapsedMinutes = [math]::Floor(((Get-Date) - $latestManifest.LastWriteTime).TotalMinutes)
$health = if ($elapsedMinutes -ge $RedAfterMinutes) { 'RED' } elseif ($elapsedMinutes -ge $YellowAfterMinutes) { 'YELLOW' } else { 'GREEN' }

Write-Output "Session Health: $health"
Write-Output "Session age: $elapsedMinutes minutes (from $($latestManifest.Name))."

switch ($health) {
    'RED' { Write-Output 'Recommendation: generate a compact handoff and continue in a fresh session unless the task is nearly complete.' }
    'YELLOW' { Write-Output 'Session check: continue, compact the task context, generate a handoff, or start a fresh session.' }
    default { Write-Output 'Context is still within the normal focused-session window.' }
}
