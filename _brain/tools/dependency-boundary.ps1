param(
    [Parameter(Mandatory = $true)]
    [string]$Path,
    [int]$MaxDependencies = 20
)

if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) { throw "File not found: $Path" }
$content = Get-Content -LiteralPath $Path -Raw
$matches = [regex]::Matches($content, '(?m)(?:from\s+|require\s*\(|import\s*\()["'']([^"'']+)["'']')
$dependencies = @($matches | ForEach-Object { $_.Groups[1].Value } | Where-Object { $_ -match '^(\.|/)' } | Select-Object -Unique | Select-Object -First $MaxDependencies)

Write-Output "# Dependency Boundary: $Path"
if ($dependencies.Count -eq 0) {
    Write-Output 'No direct local import paths detected. For another language, record direct dependencies in the module profile.'
} else {
    Write-Output 'Direct local dependencies (candidates, not automatic context):'
    $dependencies | ForEach-Object { Write-Output "- $_" }
}
