---
description: Review a pull request (or your current branch) with the FugaziByUs reviewer agent
argument-hint: [pr-number]
---

Use the `fugazi-reviewer` subagent to review ${ARGUMENTS:-the current branch against origin/develop}. Give me the findings as [blocking] / [should-fix] / [nit] with file:line references and the verdict. Don't post anything to GitHub unless I ask.
