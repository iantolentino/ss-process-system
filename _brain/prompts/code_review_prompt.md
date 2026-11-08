# Code Review Prompt

Use the `code-review` intent with `_brain/tools/select-context.ps1`. Provide the diff, branch, pull request, or exact files to review.

Read only the selected files, the review policy, and directly affected production code. Use `fixes/fix_log.md` only to avoid re-reporting a matching known issue.

Report actionable findings by severity with file and line references. Skip style-only concerns, unrelated code, build artifacts, and tests unless they are directly in scope.
