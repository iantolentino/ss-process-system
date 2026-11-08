param([string]$SourceRoot = '.', [switch]$Write)
$brainRoot = Split-Path -Parent $PSScriptRoot
$projectRoot = Split-Path -Parent $brainRoot
$root = Join-Path $projectRoot $SourceRoot
if (-not (Test-Path $root)) { throw "Source root not found: $SourceRoot" }
$files = Get-ChildItem $root -File -Recurse | Where-Object { $_.Extension -in '.js', '.jsx', '.ts', '.tsx', '.py', '.php', '.cs' } | Select-Object -First 500
$rows = foreach ($file in $files) {
    $imports = [regex]::Matches((Get-Content $file.FullName -Raw), '(?m)(?:from\s+|require\s*\(|import\s*\()["'']([^"'']+)["'']') | ForEach-Object { $_.Groups[1].Value } | Where-Object { $_ -match '^(\.|/)' } | Select-Object -Unique
    if ($imports) { "- ``$($file.FullName.Substring($projectRoot.Length + 1))`` -> $($imports -join ', ')" }
}
$output = @('# Module Graph', '', 'Direct local import relationships only. Use as ranking candidates; do not load every dependency.', '') + $rows
$output | Write-Output
if ($Write) { $path = Join-Path $brainRoot 'modules/GRAPH.md'; Set-Content -LiteralPath $path -Value $output -Encoding utf8; Write-Output "Written: $path" }
