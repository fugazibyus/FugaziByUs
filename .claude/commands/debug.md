---
description: Diagnose and fix a failing check or bug with the FugaziByUs debugger agent
argument-hint: <pr-number | failing test | error message>
---

Use the `fugazi-debugger` subagent to diagnose and fix: $ARGUMENTS

Reproduce the problem first (with `make test`, `make check` or the failing CI log via `gh run view --log-failed`), fix the root cause, and re-run the full check. Ask me before pushing.
