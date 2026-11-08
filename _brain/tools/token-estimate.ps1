param([Parameter(Mandatory = $true)] [string[]]$Path, [ValidateSet('generic', 'openai', 'claude')] [string]$Provider = 'generic')
$files = $Path | ForEach-Object { if (-not (Test-Path -LiteralPath $_ -PathType Leaf)) { throw "File not found: $_" }; Get-Item $_ }
$characters = ($files | Measure-Object Length -Sum).Sum
$divisor = if ($Provider -eq 'generic') { 4 } else { 4 }
Write-Output "Provider: $Provider (rough estimate; not billing data)"
Write-Output "Files: $($files.Count); Characters: $characters; Estimated input tokens: $([math]::Ceiling($characters / $divisor))"
