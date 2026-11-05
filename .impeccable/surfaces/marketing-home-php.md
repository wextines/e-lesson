---
version: 1
slug: "marketing-home-php"
primary_target: "marketing/home.php"
related_targets: ["index.php"]
---

# Surface: platform homepage (marketing/home.php, served at the apex domain)

Mode: Persuade. Audience: Tashkent education-center owners/admins currently on Excel/paper/WhatsApp. Job: understand what e-Lesson replaces, believe it fits their actual workflow (cash payments, Telegram, phone-first), and submit a lead. Proof: real screenshots of the actually-built admin panel (dashboard, debtor SMS status, attendance grid), not invented data. Constraints: no pricing, no client logos/testimonials/stats, no fabricated contact channel — CTA is an on-page lead form only. Must not touch existing tenant-resolution behavior or any tenant's own site.

## Direction contract

**THESIS:** The center owner's actual, already-built control panel is the hero — not an illustration of a generic dashboard, not a stock photo of a smiling teacher. The category default here (both named references) is "laptop/browser mockup of *a* dashboard plus a feature-icon grid plus a client-logo wall"; this page refuses the logo wall (no real clients to show) and refuses an invented mockup (the product is real and running, so the hero ships an actual screenshot of it).

**OWN-WORLD:** Sits between the two named references' materials rather than cloning either: a single confident teal-emerald accent (`#0E9F8E` family) instead of EduSchool's cooler cyan-teal restraint or My School's brighter grass-green playfulness; near-black headlines on a cool neutral ground (`#f6f8f9`/`#eef3f3` — explicitly not cream/warm-paper, since this is a B2B dashboard subject, not a bookish one); pill buttons and badges; rounded-2xl cards with soft diffuse shadow; rounded-square pastel icon tiles (one accent hue per feature, EduSchool's device, not My School's circular rainbow badges); a dark near-black-navy footer. Display face: Unbounded (geometric, confident, real character) for headlines/wordmark only; Inter for body/UI copy, both via Google Fonts. No eyebrow/kicker pills anywhere on the page (craft-floor's outright ban — five were cut after finish review) and no decorative section numbering (FAQ's 01–05 cut for the same reason).

**STORY:** Visitor recognizes their own daily pain (Excel row that's half stale, chasing a debtor by phone, a cash drawer that's 50,000 so'm off) inside the first two viewports; sees their own future admin screen (real screenshot) doing the job instead; scans six concrete capabilities framed as outcomes, not architecture; sees one real differentiator (white-label, own subdomain) a generic CRM can't claim; answers their own objections in a compact FAQ; leaves a name+phone instead of hitting a dead contact link or an invented price.

**FIRST VIEWPORT:** Nav (wordmark left, three anchor links + "Оставить заявку" pill button right) over a two-column hero: left column carries the two-line headline directly (second line in the teal-emerald accent, no kicker above it), one pain-literate subhead sentence, primary+secondary CTA pair; right column carries a browser-chrome-framed real screenshot of the live admin dashboard, slightly offset/rotated for depth, with one small floating stat chip (real, labeled — the actual "17 200 000 сум / доход за 30 дней" visible in the same screenshot) breaking the frame's corner, plus a second chip naming the SMS-automation feature.

**FORM:** Brief-pinned direction (two named competitor URLs bound the visual world directly); concept-seed's dice-roll derivation was not run because the client already supplied the two concrete references to sit between, which the roll would only re-arrive at or be overridden by per "a user- or brief-pinned direction beats the roll, always." Reference inspection performed via live browser navigation to both sites (2026-10-02) in place of a seed key.

**FINISH:** unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance. Finish review completed 2026-10-02 (disposition: fix) — all five material fixes applied and re-verified; see DESIGN.md for the as-shipped system record.

## Unresolved decisions

None blocking; copy is authored in Russian only (matches the operating language of the already-built admin panel and this conversation), no i18n toggle for this first surface. `admin/super/leads.php` could not be exercised live this session (unknown super_admin credential; resetting it was correctly refused by the host's own safety classifier) — source-reviewed and syntax-checked only, flagged as an open gap rather than silently skipped.
