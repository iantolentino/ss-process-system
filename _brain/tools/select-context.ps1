param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('feature-development', 'bug-fix', 'refactor', 'investigation', 'code-review', 'framework-maintenance')]
    [string]$Intent,
    [string[]]$Files = @(),
    [string]$ErrorContext,
    [string]$Task,
    [string]$Module,
    [int]$MaxCharacters = 30000,
    [switch]$NoManifest
)

$brainRoot = Split-Path -Parent $PSScriptRoot
$projectRoot = Split-Path -Parent $brainRoot
$selected = [System.Collections.Generic.List[System.IO.FileInfo]]::new()
$reasons = @{}
$skipped = @('Unrelated daily logs', 'Archived handoffs', 'Legacy progress history', 'Unrelated architecture notes', 'Unrelated deployment history')

function Add-ContextFile([string]$path, [string]$reason) {
    if ([string]::IsNullOrWhiteSpace($path)) { return }
    $candidates = @($path, (Join-Path $projectRoot $path), (Join-Path $brainRoot $path))
    $match = $candidates | Where-Object { Test-Path $_ -PathType Leaf } | Select-Object -First 1
    if (-not $match) { throw "Context file was not found: $path" }
    $item = Get-Item $match
    if (-not ($selected.FullName -contains $item.FullName)) {
        $selected.Add($item)
        $reasons[$item.FullName] = $reason
    }
}

$baseline = if ($Intent -eq 'framework-maintenance') {
    @('AI_BRAIN.md', 'BRAIN_INDEX.md', "intents/$Intent.md", 'FRAMEWORK_STATE.md')
} else {
    @('AI_BRAIN.md', 'BRAIN_INDEX.md', 'CURRENT_STATE.md', 'sessions/LATEST_HANDOFF.md', "intents/$Intent.md")
}
$baseline | ForEach-Object { Add-ContextFile $_ 'Required active context or intent profile' }
$moduleProfile = if ($Module) { Join-Path $brainRoot "modules/$Module.md" }
if ($Module) {
    if (-not (Test-Path -LiteralPath $moduleProfile -PathType Leaf)) { throw "Module profile not found: modules/$Module.md" }
    Add-ContextFile $moduleProfile "Selected module profile: $Module"
}
$today = Join-Path $brainRoot ('daily/' + (Get-Date -Format 'yyyy-MM-dd') + '.md')
if (Test-Path $today -PathType Leaf) { Add-ContextFile $today "Today's activity" }
$contextDiff = Join-Path $brainRoot 'CONTEXT_DIFF.md'
if ((Test-Path $contextDiff -PathType Leaf) -and ((Get-Content $contextDiff -Raw) -match '(?m)^- Current commit: [0-9a-f]{7,64}$')) { Add-ContextFile $contextDiff 'Git changes since the previous handoff' }
if ($ErrorContext) { Add-ContextFile $ErrorContext 'Exact task error or reproduction context' }
$Files | ForEach-Object { Add-ContextFile $_ 'Directly named for this task' }

$rows = foreach ($item in $selected) {
    $relative = if ($item.FullName.StartsWith($projectRoot, [System.StringComparison]::OrdinalIgnoreCase)) { $item.FullName.Substring($projectRoot.Length).TrimStart('\') } else { $item.FullName }
    [pscustomobject]@{ File = $relative; Lines = (Get-Content $item.FullName | Measure-Object -Line).Lines; Characters = $item.Length; Reason = $reasons[$item.FullName] }
}

$totalLines = ($rows | Measure-Object -Property Lines -Sum).Sum
$totalCharacters = ($rows | Measure-Object -Property Characters -Sum).Sum
$budgetStatus = if ($totalCharacters -gt $MaxCharacters) { 'OVER BUDGET' } else { 'Within budget' }
$timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss K'
$level = if ($Files.Count -gt 0 -or $ErrorContext) { '2 - Current Work' } else { '1 - Minimal' }
$output = @('# Context Selection', '', "- Generated: $timestamp", "- Intent: $Intent", "- Context level: $level", '', '## Load', '', '| File | Lines | Characters |', '| --- | ---: | ---: |')
$output += $rows | ForEach-Object { "| ``$($_.File)`` | $($_.Lines) | $($_.Characters) |" }
$output += @('', "Total: $($rows.Count) files, $totalLines lines, $totalCharacters characters.", "Context budget: $budgetStatus ($totalCharacters / $MaxCharacters characters).")
if ($totalCharacters -gt $MaxCharacters) {
    $output += @('Recommendation: narrow the source files or load them incrementally before reading this entire packet.')
}
$output += @('', '## Why loaded', '')
$output += $rows | ForEach-Object { "- ``$($_.File)``: $($_.Reason)" }
$rankedChanges = @()
if ($Task -and (Test-Path $contextDiff -PathType Leaf)) {
    $terms = @($Task.ToLowerInvariant() -split '[^a-z0-9]+' | Where-Object { $_.Length -ge 3 } | Select-Object -Unique)
    $changed = @([regex]::Matches((Get-Content $contextDiff -Raw), '(?m)^- `([^`]+)`$') | ForEach-Object { $_.Groups[1].Value })
    $rankedChanges = $changed | ForEach-Object {
        $path = $_
        $score = @($terms | Where-Object { $path.ToLowerInvariant().Contains($_) }).Count
        [pscustomobject]@{ Path = $path; Score = $score }
    } | Where-Object { $_.Score -gt 0 } | Sort-Object -Property @{ Expression = 'Score'; Descending = $true }, @{ Expression = 'Path'; Descending = $false } | Select-Object -First 5
}
if ($rankedChanges.Count -gt 0) {
    $output += @('', '## Ranked changed-file candidates', '', 'These are suggestions only; add a file only when it is needed for the task.')
    $output += $rankedChanges | ForEach-Object { "- ``$($_.Path)`` (task-term matches: $($_.Score))" }
}
$output += @('', '## Deliberately skipped', '')
$output += $skipped | ForEach-Object { "- $_" }
$output += @('', '## Expansion rule', '', 'Load a mapped architecture record, ADR, contract, or historical file only after identifying the exact missing information.')

$output -join [Environment]::NewLine | Write-Output

if (-not $NoManifest) {
    $manifestDirectory = Join-Path $brainRoot 'sessions/manifests'
    New-Item -ItemType Directory -Force -Path $manifestDirectory | Out-Null
    $manifestName = 'context-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.md'
    $manifestPath = Join-Path $manifestDirectory $manifestName
    Set-Content -Path $manifestPath -Value $output -Encoding utf8
    Write-Output "`nManifest: sessions/manifests/$manifestName"
}
