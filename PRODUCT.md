# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Primary: owners/directors and front-desk admins of small private education centers in Tashkent, Uzbekistan — language schools, exam-prep (IELTS/SAT/DTM), and tutoring centers. They currently track students, payments, and attendance in Excel, paper notebooks, and WhatsApp threads; they call students individually to chase payment reminders and to ask about attendance; they have no 24/7 online presence for their own students.

Secondary (served by the product, not the audience of this homepage): the center's students/parents, who get a white-labeled online portal once a center signs up.

## Product Purpose

e-Lesson is a B2B SaaS LMS/CRM for running a private education center end-to-end: student records and course subscriptions, a payments/debt ledger with automated SMS reminders, course and video material delivery, online testing, study groups and attendance tracking, and a role-scoped admin panel (center_admin / teacher). It exists to replace the center owner's Excel+paper+WhatsApp workflow with one system, and to give the center's own students a 24/7 online study portal under the center's own brand.

Success for this specific surface (the platform's own homepage) is a qualified lead: a center owner submits a request to be contacted/demoed.

## Positioning

Not a generic Western SaaS: built around how Tashkent centers actually operate — desk cash/terminal payments (not Stripe-style auto-billing), Telegram/phone-based parent communication, mobile-first admin use by a busy receptionist. Each center gets its own white-labeled, subdomain-based (or custom-domain) student-facing site and admin panel, so the center looks professional under its own name while e-Lesson runs the infrastructure.

## Operating Context

The product has two faces already built and live in this codebase:

- **Admin panel** (`/admin/*`): dashboard KPIs, student management, course/material management, study groups + attendance marking, finance (cash ledger via `transactions`, legacy `payments` receipt flow, debtor tracking with in-place payment acceptance), automated SMS payment reminders (Eskiz.uz), role scoping (`center_admin` can do everything; `teacher` is restricted to their own groups/attendance and has no financial visibility).
- **Student-facing portal** (`/profile/*`, `/course/*`, per-tenant `index.php`): course catalog, video/PDF lessons, daily/weekly tests, leaderboard, personal balance.

Every tenant (school) is isolated by `tenant_id` and reachable at `{slug}.e-lesson.uz` or a custom domain (`php/tenant.php`). Visiting the bare apex domain (`e-lesson.uz` with no subdomain/custom-domain match) currently falls back to showing tenant #1's own branded site — there is no dedicated homepage for the platform itself. This surface fixes that gap.

## Capabilities and Constraints

- This new homepage markets **e-Lesson the platform**, not any one tenant's school — it must read as a B2B SaaS product page, not a student-enrollment landing page.
- Must be reachable at the platform's own production apex domain and not alter any existing tenant's site or break host-resolution fallback used by local dev / Playwright e2e (`localhost`).
- Technical stack: plain PHP templating + CSS + vanilla JS, matching the existing codebase (no new build pipeline/framework introduced for this).
- **No image-generation tool is available in this environment** — real photography comes from a stock source (e.g. Unsplash, which permits commercial use), not fabricated/AI-generated images. Icons from an existing icon set already used in the codebase (Lucide, used throughout `/admin`) or Font Awesome (used in the existing per-tenant template).
- **Pricing: explicitly not to be shown.** The founder confirmed (2026-10-02) the tiers in `PRODUCT_STRATEGY.md` ($30–50 / $80–120 / $150–250 per month) are an internal recommendation only, not a finalized public price. The homepage must not display any dollar figures or tier table; pricing is handled off-page (lead contacts the founder).
- **No social proof section.** The founder confirmed (2026-10-02) there are no public clients yet — no "trusted by N schools," no client logos, no testimonials, no real usage statistics. Do not fabricate any of these. A demo/screenshot of the interface itself is fine as an illustration, but must not be framed as a real client's data.
- **Contact channel: delegated to the assistant** ("сделай как считаешь нужным" — founder's words, 2026-10-02). No real company phone/Telegram/email was confirmed, so the homepage must not display an invented one. Decision: build a working on-page lead-capture form (name + phone, optionally a note) that persists submissions server-side (new `leads`-style table), rather than a contact string or external link that cannot be verified as real.
- Must prominently feature "beautiful photos, icons, and cards" per the founder's explicit request, and must be comfortable on mobile (mobile admin/student use is a recurring value proposition of the product itself).

## Brand Commitments

- Product name: **e-Lesson**.
- Visual direction is explicitly bounded by the founder to sit "between" two reference sites: **eduschool.uz** and **my-school.uz** ("не выходи за края этих сайтов" — don't go outside the edges of these two sites). This is a binding constraint on the visual world, to be resolved by inspecting both sites directly before designing.
- The existing per-tenant template (`index.php`, `css/index.css`) is tenant white-label infrastructure (Quicksand font, Font Awesome, per-tenant CSS-variable theming) — it is **not** e-Lesson's own brand identity and is not the visual starting point for this homepage; this surface gets its own, separate visual world.

## Evidence on Hand

None confirmed. No real client names, logos, testimonials, usage numbers, finalized public pricing, or a verified company contact channel exist yet for this product. Future work on this surface must not invent any of these — state absence plainly (e.g., via a working lead form) rather than fabricate.

## Product Principles

1. Sell the outcome (more students, more revenue collected, less manual admin work, a more professional look) — never pitch the technical architecture (multi-tenant, PDO, etc.) to this audience, per the founder's own sales strategy notes.
2. Localize for Tashkent-market reality: cash/terminal payments, Telegram and phone-based communication, mobile-first daily use — don't borrow assumptions from Western SaaS marketing patterns.
3. Never fabricate unverified facts — no invented pricing, clients, testimonials, stats, or contact details. Prefer an honest absence (e.g., "request pricing" instead of a price) over a plausible-looking invention.
4. Stay inside the existing multi-tenant PHP app without introducing a parallel build system, and without touching tenant-resolution behavior that existing tenants / e2e tests depend on.

## Accessibility & Inclusion

No formal standard specified. Mobile comfort is a product-level requirement (not just an accessibility nicety) since "manage it from your phone" is itself a value proposition in the product's own marketing message.
