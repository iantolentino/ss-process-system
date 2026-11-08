$brainRoot = Split-Path -Parent $PSScriptRoot
$manifestDirectory = Join-Path $brainRoot 'sessions/manifests'
$latest = Get-ChildItem -LiteralPath $manifestDirectory -Filter 'context-*.md' -File -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending | Select-Object -First 1
if (-not $latest) { throw 'No context manifest found. Run select-context.ps1 first.' }
$content = Get-Content -LiteralPath $latest.FullName -Raw
$total = [regex]::Match($content, 'Total: (\d+) files, (\d+) lines, (\d+) characters\.').Groups
if ($total.Count -eq 0) { throw "Could not read totals from $($latest.Name)." }
$historyDirectory = Join-Path $brainRoot 'sessions/cost-history'
New-Item -ItemType Directory -Force -Path $historyDirectory | Out-Null
$historyPath = Join-Path $historyDirectory ((Get-Date -Format 'yyyy-MM') + '.md')
if (-not (Test-Path -LiteralPath $historyPath)) { Set-Content -LiteralPath $historyPath -Value '# Session Cost History' -Encoding utf8 }
if (-not ((Get-Content -LiteralPath $historyPath -Raw) -match [regex]::Escape($latest.Name))) {
    Add-Content -LiteralPath $historyPath -Value "`n## $(Get-Date -Format 'yyyy-MM-dd HH:mm')`n- Manifest: $($latest.Name)`n- Files: $($total[1].Value)`n- Lines: $($total[2].Value)`n- Characters: $($total[3].Value)`n- Estimated input tokens: $([math]::Ceiling([int]$total[3].Value / 4))"
}
Write-Output "Recorded session cost: sessions/cost-history/$((Get-Date -Format 'yyyy-MM')).md"
