param(
    [Parameter(Mandatory = $true)] [string]$Task
)

$task = $Task.ToLowerInvariant()
$routes = @(
    @{ Name = 'bug-fix'; Terms = 'bug|error|fail|crash|broken|fix'; Files = 'intents/bug-fix.md' },
    @{ Name = 'database'; Terms = 'database|migration|schema|sql|query'; Files = 'decisions/, architecture/, relevant migration' },
    @{ Name = 'security'; Terms = 'security|auth|permission|secret|vulnerability'; Files = 'security/, relevant auth decision' },
    @{ Name = 'deployment'; Terms = 'deploy|release|production|ci|cd'; Files = 'deployment/, releases/' },
    @{ Name = 'code-review'; Terms = 'review|pull request|pr|diff'; Files = 'intents/code-review.md, skills/code_review_checklist.md' },
    @{ Name = 'feature-development'; Terms = 'feature|add|implement|build'; Files = 'intents/feature-development.md' }
)

$matched = $routes | Where-Object { $task -match $_.Terms }
if (-not $matched) { $matched = @(@{ Name = 'investigation'; Files = 'intents/investigation.md' }) }
Write-Output '# Skill Route'
Write-Output "Task: $Task"
Write-Output 'Load only these task-relevant instruction sources:'
$matched | ForEach-Object { Write-Output "- $($_.Name): $($_.Files)" }
Write-Output 'This is a routing recommendation, not permission to load every folder named above.'
