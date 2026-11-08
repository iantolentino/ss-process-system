param(
    [int]$MaxLinesPerSection = 3,
    [switch]$WriteDraft
)

$brainRoot = Split-Path -Parent $PSScriptRoot
$handoffPath = Join-Path $brainRoot 'sessions/LATEST_HANDOFF.md'
if (-not (Test-Path -LiteralPath $handoffPath)) {
    throw "Latest handoff not found: $handoffPath"
}

$handoff = Get-Content -LiteralPath $handoffPath -Raw
$sections = @('Objective', 'Completed', 'Current State', 'Decisions', 'Blockers', 'Relevant Files', 'Next Action', 'Warnings')
$draft = @('# Compact Session Handoff', '', '> Review this draft before replacing `LATEST_HANDOFF.md`. This tool never overwrites the active handoff.', '')

foreach ($section in $sections) {
    $escaped = [regex]::Escape($section)
    $match = [regex]::Match($handoff, "(?ms)^## $escaped\s*\r?\n(.*?)(?=^## |\z)")
    $lines = if ($match.Success) {
        @($match.Groups[1].Value -split "\r?\n" | Where-Object {
            -not [string]::IsNullOrWhiteSpace($_) -and $_ -notmatch '^\s*(?:-\s*)?\[[^\]]+\]\s*$'
        } | Select-Object -First $MaxLinesPerSection)
    } else { @() }

    $draft += "## $section"
    if ($lines.Count -gt 0) { $draft += $lines } else { $draft += '- None recorded.' }
    $draft += ''
}

$draft += '## Next Step'
$draft += 'After review, replace the handoff content if appropriate, then run `handoff-baseline.ps1 -UpdateHandoff`.'
$text = $draft -join [Environment]::NewLine
$text | Write-Output

if ($WriteDraft) {
    $draftPath = Join-Path $brainRoot 'sessions/HANDOFF_COMPACT_DRAFT.md'
    Set-Content -LiteralPath $draftPath -Value $text -Encoding utf8
    Write-Output "`nDraft written: $draftPath"
}
