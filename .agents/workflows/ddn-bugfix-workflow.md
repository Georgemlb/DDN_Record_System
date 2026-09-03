---
description: DDN Bug Fix Workflow
---

STEP 1 — INVESTIGATE

Do not immediately modify code.

Read the error message carefully.

Inspect the relevant files and trace the problem.

STEP 2 — IDENTIFY

Determine the root cause.

Check whether the issue is caused by:

- Database configuration
- Migration issues
- Backend logic
- Validation
- Routes
- Frontend integration
- Environment configuration

STEP 3 — EXPLAIN

Briefly explain:

- What caused the issue
- Which files are affected
- What the proposed fix is

STEP 4 — FIX

Apply the smallest reasonable fix.

Do not refactor unrelated code while fixing a bug.

STEP 5 — VERIFY

Re-test the original issue.

Confirm that the fix does not introduce obvious regressions.

STEP 6 — REPORT

Summarize:

- Root cause
- Files changed
- Fix applied
- Verification performed