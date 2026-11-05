# CLAUDE.md

e-Lesson is a multi-tenant LMS (PHP 8.2 + MySQL 8.4, Docker). For full architecture, schema, and business context, see [README.md](README.md) — this file only holds the non-negotiable rules (condensed from README.md §21) and points to the automation that helps enforce them.

## Non-negotiable rules

1. **Tenant isolation.** Every query touching `users`, `courses`, `course_subs`, `materials`, `daily_tests`, `weekly_tests`, `daily_questions`, `weekly_questions`, `test_results`, `payments`, `transactions`, `books`, or `other_resources` MUST filter by `tenant_id`. There is no database-level foreign key enforcing this — it's application-only (README.md §5, §7).
2. **No direct storage links.** Never construct a public path into `storage/` or `uploads/`. All private files (video, PDF, receipts, question images) must be referenced via `/file.php?type={type}&id={id}`.
3. **Streaming gate.** Any file-serving code must call `session_write_close()` before writing output and preserve HTTP 206 `Range` support.
4. **`payments` ≠ `transactions`.** `payments` is the legacy receipt-upload flow (CURRENT, active). `transactions` is the new flat financial model (schema exists, PHP logic PLANNED). Don't conflate them, and don't break the legacy flow before the new one is fully wired up.
5. **Non-cutting access.** Under the new subscription model, an overdue `next_payment_date` must never cut off access for `status = 'active'` subscriptions — access is gated by `status`, not by a date comparison.
6. **Terminology.** Curriculum units are "Уроки" (RU) / "Darslar" (UZ) / "Lessons" (EN) — never "Видеоуроки" or "Материалы" in user-facing text.
7. **No destructive DB/volume ops.** Never run `DROP`/`TRUNCATE`/an unwhered `DELETE`, or wipe containers/volumes (`docker compose down -v`, `docker volume rm`) against the seeded demo tenants. A PreToolUse hook blocks the obvious cases (see below) — don't try to route around it.
8. **Keep the regression suites green.** `e2e/phase3g.spec.ts` (43 tests) and `e2e/student_functional_fixes.spec.ts` (7 tests) must keep passing after any change.
9. **Frontend verification via Playwright.** Whenever modifying or creating any frontend UI, you MUST run `node scripts/verify_ui.js <url_path>` before declaring the task done. The report must show 0 console errors, 0 uncaught exceptions, and no 390px horizontal overflow.

## Automation available in this repo

- `.claude/agents/elesson-invariant-reviewer.md` — read-only subagent that checks a change against rules 1–6.
- `.claude/agents/e2e-regression-runner.md` — runs the rule-8 suites in an isolated context and reports pass/fail.
- `.claude/skills/tenant-audit/` — `/tenant-audit <path>` grep-based sweep for rule 1.
- `.claude/settings.json` hooks — blocks destructive DB/volume commands (rule 7) and runs `php -l` on every edited PHP file.
