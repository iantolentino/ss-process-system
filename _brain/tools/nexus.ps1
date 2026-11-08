param([Parameter(Position = 0, Mandatory = $true)] [ValidateSet('context', 'doctor', 'diff', 'handoff', 'metrics', 'route', 'gate', 'decay', 'audit')] [string]$Command, [Parameter(ValueFromRemainingArguments = $true)] [string[]]$Arguments)
$root = $PSScriptRoot
$map = @{ context = 'select-context.ps1'; doctor = 'brain-doctor.ps1'; diff = 'context-diff.ps1'; handoff = 'handoff-baseline.ps1'; metrics = 'context-metrics.ps1'; route = 'skill-router.ps1'; gate = 'workflow-gate.ps1'; decay = 'context-decay.ps1'; audit = 'knowledge-audit.ps1' }
& (Join-Path $root $map[$Command]) @Arguments
exit $LASTEXITCODE
