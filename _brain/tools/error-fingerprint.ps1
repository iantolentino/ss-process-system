param(
    [string]$ErrorText,
    [string]$ErrorFile
)

if (-not $ErrorText -and -not $ErrorFile) { throw 'Provide -ErrorText or -ErrorFile.' }
if ($ErrorFile) {
    if (-not (Test-Path -LiteralPath $ErrorFile -PathType Leaf)) { throw "Error file not found: $ErrorFile" }
    $ErrorText = Get-Content -LiteralPath $ErrorFile -Raw
}

$normalized = $ErrorText -replace '(?i)\b[0-9a-f]{8}-[0-9a-f-]{27,}\b', '<id>'
$normalized = $normalized -replace '\b\d+\b', '<n>'
$normalized = ($normalized -replace '\s+', ' ').Trim().ToLowerInvariant()
$bytes = [Text.Encoding]::UTF8.GetBytes($normalized)
$hash = ([Security.Cryptography.SHA256]::Create().ComputeHash($bytes) | ForEach-Object ToString x2) -join ''
$brainRoot = Split-Path -Parent $PSScriptRoot
$indexPath = Join-Path $brainRoot 'fixes/ERROR_FINGERPRINTS.md'

Write-Output "Error fingerprint: $hash"
if ((Test-Path -LiteralPath $indexPath) -and ((Get-Content -LiteralPath $indexPath -Raw) -match [regex]::Escape($hash))) {
    Write-Output "Known fingerprint found in: fixes/ERROR_FINGERPRINTS.md"
} else {
    Write-Output 'No known fingerprint. After resolving a recurring issue, record only the fingerprint, symptom, fix, and source files in fixes/ERROR_FINGERPRINTS.md.'
}
