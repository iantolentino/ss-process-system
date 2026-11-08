param(
    [Parameter(Mandatory = $true)]
    [string]$Path,
    [Parameter(Mandatory = $true)]
    [int]$StartLine,
    [Parameter(Mandatory = $true)]
    [int]$EndLine,
    [int]$MaxLines = 250
)

if ($StartLine -lt 1 -or $EndLine -lt $StartLine) { throw 'Use a valid 1-based line range.' }
if (($EndLine - $StartLine + 1) -gt $MaxLines) { throw "Requested range exceeds the $MaxLines-line safety limit. Narrow the range or explicitly raise -MaxLines." }
if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) { throw "File not found: $Path" }

$lines = Get-Content -LiteralPath $Path
if ($StartLine -gt $lines.Count) { throw "Start line $StartLine is beyond the end of the file ($($lines.Count) lines)." }
$lastLine = [Math]::Min($EndLine, $lines.Count)
Write-Output "# Slice: $Path (lines $StartLine-$lastLine)"
for ($number = $StartLine; $number -le $lastLine; $number++) {
    Write-Output ('{0,6}: {1}' -f $number, $lines[$number - 1])
}
