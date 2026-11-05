---
name: tenant-audit
description: Grep-based check for e-Lesson PHP files that query tenant-scoped tables without a tenant_id filter. Use when asked to audit a file or directory for tenant isolation, or before/after editing code that queries users, courses, course_subs, materials, tests, payments, or transactions.
---

# Tenant Audit

e-Lesson is multi-tenant with **no database-level foreign key enforcing `tenant_id`** — isolation is enforced entirely in application SQL (see CLAUDE.md, README.md §5 and §7). This skill is a fast, deterministic first pass to catch queries that forgot the filter. It is a heuristic grep sweep, not a full review — for anything ambiguous, use the `elesson-invariant-reviewer` subagent or read the code directly.

## Tenant-scoped tables to check

`users`, `courses`, `course_subs`, `materials`, `daily_tests`, `weekly_tests`, `daily_questions`, `weekly_questions`, `test_results`, `payments`, `transactions`, `books`, `other_resources`

## Steps

1. Determine the target path (a file or directory) from the request. Default to whatever file(s) were just edited if none is given.
2. For each `.php` file in scope, find SQL statements referencing any table above, e.g.:
   ```bash
   grep -n -iE "(SELECT|UPDATE|DELETE)[^;]*\b(users|courses|course_subs|materials|daily_tests|weekly_tests|daily_questions|weekly_questions|test_results|payments|transactions|books|other_resources)\b" <file>
   ```
3. For each match, read enough surrounding context to see the **full statement** (it may span multiple lines or a heredoc) and check whether `tenant_id` appears anywhere in it.
4. Before flagging, rule out legitimate exceptions:
   - The query is scoped via a join to another table/row that is itself already tenant-filtered in the same statement.
   - The query is a lookup by primary key that also carries `tenant_id = ?` elsewhere in the same prepared statement.
   - The code path is under `admin/super/`, which intentionally operates across all tenants.

## Output

A short table: `file:line`, table(s) touched, whether `tenant_id` filtering was found. Only flag statements that are genuinely missing the filter — don't flag a correctly-scoped query just because the string `tenant_id` isn't on the same line as the matched keyword. State plainly that this is a heuristic sweep and suggest a deeper look (via `elesson-invariant-reviewer` or manual read) for anything unclear.
