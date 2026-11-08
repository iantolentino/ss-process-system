$brainRoot = Split-Path -Parent $PSScriptRoot
$files = @('CURRENT_STATE.md') + @(Get-ChildItem $brainRoot -Path (Join-Path $brainRoot 'architecture'), (Join-Path $brainRoot 'decisions'), (Join-Path $brainRoot 'deployment') -File -Recurse -ErrorAction SilentlyContinue | ForEach-Object { $_.FullName })
$records = foreach ($file in $files) {
    $path = if ([IO.Path]::IsPathRooted($file)) { $file } else { Join-Path $brainRoot $file }
    if (Test-Path $path) {
        Get-Content $path | Where-Object { $_ -match '^\s*-\s*Fact:' } | ForEach-Object { [pscustomobject]@{ Fact = ($_ -replace '^\s*-\s*Fact:\s*', '').Trim(); File = $path } }
    }
}
Write-Output '# Knowledge Confidence and Conflict Audit'
Write-Output 'Use `- Fact: ...` plus `- Confidence: confirmed|assumption|outdated` and `- Source: ...` in durable records.'
$records | Group-Object Fact | Where-Object Count -gt 1 | ForEach-Object { Write-Output "Potential duplicate/conflict fact: $($_.Name)"; $_.Group | ForEach-Object { Write-Output "- $($_.File)" } }
if (-not $records) { Write-Output 'No structured facts found yet; no conflict claim was made.' }
