param(
    [switch]$UpdateHandoff
)

$brainRoot = Split-Path -Parent $PSScriptRoot
$projectRoot = Split-Path -Parent $brainRoot

function Invoke-Git([string[]]$Arguments) {
    $result = & git -C $projectRoot @Arguments 2>$null
    if ($LASTEXITCODE -ne 0) { throw "Git command failed: git $($Arguments -join ' ')" }
    return $result
}

$commit = (Invoke-Git @('rev-parse', 'HEAD')).Trim()
$branch = (Invoke-Git @('branch', '--show-current')).Trim()
$status = @(Invoke-Git @('status', '--short'))
$workingTree = if ($status.Count -eq 0) { 'clean' } else { 'has uncommitted changes' }

$baseline = @(
    '## Git Baseline'
    ''
    "- Base commit: $commit"
    "- Branch: $branch"
    "- Working tree at handoff: $workingTree"
    '- Verification: [tests/checks run, or not yet run]'
) -join [Environment]::NewLine

$baseline | Write-Output

if ($UpdateHandoff) {
    $handoffPath = Join-Path $brainRoot 'sessions/LATEST_HANDOFF.md'
    $handoff = if (Test-Path -LiteralPath $handoffPath) {
        Get-Content -LiteralPath $handoffPath -Raw
    } else {
        '# Session Handoff'
    }

    $baselineSection = '(?s)## Git Baseline\s*\r?\n.*?(?=(?:\r?\n## )|\z)'
    if ($handoff -match $baselineSection) {
        $handoff = [regex]::Replace($handoff, $baselineSection, $baseline)
    } else {
        $handoff = $handoff.TrimEnd() + [Environment]::NewLine + [Environment]::NewLine + $baseline + [Environment]::NewLine
    }

    Set-Content -LiteralPath $handoffPath -Value $handoff -Encoding utf8
    Write-Output "Updated: $handoffPath"
}
