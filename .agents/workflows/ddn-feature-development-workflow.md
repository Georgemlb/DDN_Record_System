---
description: DDN Feature Development Workflow
---

STEP 1 — UNDERSTAND

Read the user's request carefully.

Identify the relevant modules, files, database tables, backend logic, and frontend components.

If the task is unclear, ask for clarification before making significant changes.

STEP 2 — INSPECT

Before modifying code:

- Inspect relevant existing modules.
- Review similar functionality already implemented.
- Identify existing coding patterns.
- Check models, migrations, controllers, routes, validation, and frontend components when relevant.

Do not modify code during this step.

STEP 3 — PLAN

For medium or large tasks, provide a concise implementation plan that includes:

- Files likely to be changed
- Backend changes
- Frontend changes
- Database changes
- Potential impact on existing functionality

Wait for approval before implementing major architectural changes.

For small and straightforward tasks, proceed directly after inspection.

STEP 4 — IMPLEMENT

Implement the feature using the existing project architecture and patterns.

Make changes incrementally.

Reuse existing code and components when possible.

Do not modify unrelated functionality.

STEP 5 — VERIFY

After implementation:

- Check for errors.
- Verify the affected functionality.
- Run relevant tests or commands when available.
- Check routes and database functionality when applicable.
- Ensure frontend and backend integration works correctly.

STEP 6 — REVIEW

Before completing the task:

- Review all modified files.
- Confirm that changes follow existing project conventions.
- Check for unnecessary or duplicate code.

STEP 7 — REPORT

Provide a concise summary containing:

- What was implemented
- Files changed
- Database changes
- Tests/checks performed
- Any commands the user needs to run
- Any known limitations or next steps