param(
    [ValidateSet('DISCOVER', 'PLAN', 'IMPLEMENT', 'TEST', 'REVIEW', 'COMPLETE')]
    [string]$Stage = 'DISCOVER',
    [switch]$TestsPassed,
    [switch]$Reviewed,
    [switch]$StateUpdated
)

$requirements = @{
    DISCOVER = 'Identify intent, scope, and minimum context.'
    PLAN = 'State the smallest safe implementation plan.'
    IMPLEMENT = 'Modify only scoped files.'
    TEST = 'Run relevant checks or record why they cannot run.'
    REVIEW = 'Review changed files and risks.'
    COMPLETE = 'Tests/review/state update must be recorded.'
}
Write-Output "Workflow Gate: $Stage"
Write-Output "Requirement: $($requirements[$Stage])"
if ($Stage -eq 'COMPLETE') {
    $missing = @()
    if (-not $TestsPassed) { $missing += 'tests/checks' }
    if (-not $Reviewed) { $missing += 'review' }
    if (-not $StateUpdated) { $missing += 'state or handoff update' }
    if ($missing.Count) { Write-Output "BLOCKED: record $($missing -join ', ') before declaring completion."; exit 2 }
}
Write-Output 'PASS'
