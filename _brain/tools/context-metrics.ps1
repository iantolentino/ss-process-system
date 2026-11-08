$brainRoot = Split-Path -Parent $PSScriptRoot
$activePaths = @('AI_BRAIN.md', 'BRAIN_INDEX.md', 'CURRENT_STATE.md', 'CONTINUE_PROMPT.md', 'CONTEXT_DIFF.md', 'daily', 'sessions', 'architecture', 'decisions', 'intents', 'modules', 'fixes')
$activeFiles = foreach ($path in $activePaths) {
    $target = Join-Path $brainRoot $path
    if (Test-Path $target -PathType Leaf) { Get-Item $target }
    elseif (Test-Path $target -PathType Container) { Get-ChildItem $target -File -Recurse | Where-Object { $_.FullName -notmatch '\\archive\\' } }
}
$historicalFiles = Get-ChildItem $brainRoot -File -Recurse | Where-Object { $_.FullName -match '\\archive\\|\\progress\\|\\memory\\|\\summaries\\|\\timelines\\' }

function Get-Metrics($files) {
    $items = @($files)
    [pscustomobject]@{
        Files = $items.Count
        Lines = ($items | ForEach-Object { (Get-Content $_.FullName | Measure-Object -Line).Lines } | Measure-Object -Sum).Sum
        Characters = ($items | ForEach-Object { $_.Length } | Measure-Object -Sum).Sum
    }
}

Write-Output 'Context Metrics'
Write-Output ('Active: ' + ((Get-Metrics $activeFiles) | ConvertTo-Json -Compress))
Write-Output ('Historical: ' + ((Get-Metrics $historicalFiles) | ConvertTo-Json -Compress))
