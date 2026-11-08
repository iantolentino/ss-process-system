param(
    [string]$BaseCommit,
    [switch]$NoWrite
)

$brainRoot = Split-Path -Parent $PSScriptRoot
$projectRoot = Split-Path -Parent $brainRoot
$handoffPath = Join-Path $brainRoot 'sessions/LATEST_HANDOFF.md'

function Invoke-Git([string[]]$Arguments) {
    $result = & git -C $projectRoot @Arguments 2>$null
    if ($LASTEXITCODE -ne 0) { throw "Git command failed: git $($Arguments -join ' ')" }
    return $result
}

if (-not $BaseCommit -and (Test-Path $handoffPath)) {
    $handoff = Get-Content $handoffPath -Raw
    $match = [regex]::Match($handoff, '(?im)^\s*-\s*Base commit:\s*([0-9a-f]{7,64})\s*$')
    if ($match.Success) { $BaseCommit = $match.Groups[1].Value }
}

$currentCommit = (Invoke-Git @('rev-parse', 'HEAD')).Trim()
$branch = (Invoke-Git @('branch', '--show-current')).Trim()
$files = [System.Collections.Generic.HashSet[string]]::new([System.StringComparer]::OrdinalIgnoreCase)
$notes = [System.Collections.Generic.List[string]]::new()

if ($BaseCommit) {
    & git -C $projectRoot cat-file -e "$BaseCommit^{commit}" 2>$null
    if ($LASTEXITCODE -ne 0) { throw "Base commit is not available locally: $BaseCommit" }
    @(Invoke-Git @('diff', '--name-only', "$BaseCommit..$currentCommit")) | ForEach-Object { if ($_){ [void]$files.Add($_) } }
    $notes.Add("Base commit: $BaseCommit")
} else {
    $notes.Add('No valid handoff baseline found. Run handoff-baseline.ps1 and paste its output into LATEST_HANDOFF.md.')
}

@(Invoke-Git @('diff', '--name-only')) | ForEach-Object { if ($_){ [void]$files.Add($_) } }
@(Invoke-Git @('diff', '--cached', '--name-only')) | ForEach-Object { if ($_){ [void]$files.Add($_) } }
@(Invoke-Git @('ls-files', '--others', '--exclude-standard')) | ForEach-Object { if ($_){ [void]$files.Add($_) } }

$output = @(
    '# Context Diff'
    ''
    "- Current commit: $currentCommit"
    "- Branch: $branch"
)
$output += $notes | ForEach-Object { "- $_" }
$output += @('', '## Changed files', '')
if ($files.Count -eq 0) { $output += '- No committed, staged, unstaged, or untracked file changes.' }
else { $output += $files | Sort-Object | ForEach-Object { "- ``$_``" } }
$output += @('', '## Use', '', 'Read only files that are directly relevant to the current task. The diff is an expansion aid, not permission to load every changed file.')

$text = $output -join [Environment]::NewLine
$text | Write-Output

if (-not $NoWrite) {
    Set-Content -Path (Join-Path $brainRoot 'CONTEXT_DIFF.md') -Value $text -Encoding utf8
    Write-Output "`nUpdated: CONTEXT_DIFF.md"
}
