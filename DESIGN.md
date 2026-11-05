---
name: e-Lesson Platform Marketing Homepage
description: Confident teal-emerald B2B SaaS homepage for Tashkent education-center owners, sitting between EduSchool's cool restraint and My School's playfulness
colors:
  ink: "#0b1220"
  ink-soft: "#475569"
  ink-faint: "#94a3b8"
  paper: "#f6f8f9"
  paper-alt: "#eef3f3"
  surface: "#ffffff"
  accent: "#0e9f8e"
  accent-dark: "#0b7f72"
  accent-tint: "#e3f6f3"
  tile-blue: "#2563eb"
  tile-amber: "#b45309"
  tile-rose: "#be123c"
  tile-violet: "#6d28d9"
  tile-slate: "#475569"
  footer-bg: "#0a1018"
  footer-text: "#e2e8f0"
typography:
  display:
    fontFamily: "Unbounded, Inter, system-ui, sans-serif"
    fontSize: "clamp(2.3rem, 4.6vw, 3.4rem)"
    fontWeight: 800
    lineHeight: 1.14
    letterSpacing: "-0.01em"
  headline:
    fontFamily: "Unbounded, Inter, system-ui, sans-serif"
    fontSize: "clamp(1.8rem, 3.4vw, 2.5rem)"
    fontWeight: 700
    lineHeight: 1.14
  body:
    fontFamily: "Inter, system-ui, sans-serif"
    fontSize: "16.5px"
    fontWeight: 400
    lineHeight: 1.6
  label:
    fontFamily: "Inter, system-ui, sans-serif"
    fontSize: "12.5px"
    fontWeight: 700
    letterSpacing: "0.04em"
rounded:
  card: "22px"
  pill: "999px"
  tile: "15px"
  input: "12px"
spacing:
  sm: "14px"
  md: "24px"
  lg: "56px"
  section: "96px"
components:
  button-primary:
    backgroundColor: "{colors.ink}"
    textColor: "#ffffff"
    rounded: "{rounded.pill}"
    padding: "14px 26px"
  button-primary-hover:
    backgroundColor: "{colors.accent-dark}"
  button-accent:
    backgroundColor: "{colors.accent}"
    textColor: "#ffffff"
    rounded: "{rounded.pill}"
    padding: "14px 26px"
  button-ghost:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.pill}"
    padding: "14px 26px"
  feature-card:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.card}"
    padding: "30px 26px"
---

# Design System: e-Lesson Platform Marketing Homepage

## Overview

**Creative North Star: "The Operator's Real Screen"**

This world exists to sell one claim: the dashboard on screen is the real, running product, not a mockup. Every material choice reinforces that — real screenshots in browser-chrome frames, pastel icon tiles that read as a product's own iconography rather than stock-art, a dark near-black footer that frames the page the way a device frame would. It was built to sit between two named competitors (eduschool.uz's cooler cyan-teal restraint, my-school.uz's brighter grass-green playfulness) and lands on a single confident teal-emerald (`#0e9f8e`), deliberately on a cool-neutral ground rather than either competitor's warmer paper tones — a B2B dashboard subject, not a bookish one.

Confirmed visual rejections, both corrected at finish review: no kicker/eyebrow pill above any heading (five were shipped, then all five removed — craft-floor bans the device outright, independent of brief wording), and no decorative section numbering (the FAQ's 01–05 was cut for the same reason). Both are durable prohibitions for this world now, not just a one-time fix.

This system is scoped to **marketing/home.php only**. Two other visual systems already live in this same codebase for different jobs and are explicitly out of scope here: the per-tenant customer template (`index.php`, Quicksand + Font Awesome + per-tenant CSS variables) and the `/admin/` + `/admin/super/` operate surfaces (Tailwind-CDN admin, Inter+Space Grotesk dark-CSS-variable super-admin). Do not generalize this DESIGN.md's rules onto those surfaces, and do not pull their patterns into this one.

**Key Characteristics:**
- Single confident accent (teal-emerald) rather than a multi-brand rainbow
- Cool-neutral ground, near-black ink, no warm/cream paper
- Rounded-2xl white cards with soft diffuse (never hard-offset) shadow
- Rounded-square pastel icon tiles, one hue per feature, drawn via Lucide SVGs
- Pill-shaped buttons, badges, and chips throughout
- Dark near-black-navy footer and two "ink" panels (pain strip, lead section) as the page's only dark surfaces
- No kickers, no decorative numbering, Unbounded reserved strictly for display/headline use

## Colors

A single teal-emerald accent carries all emphasis; five pastel tile hues exist only to differentiate feature icons, never as competing brand colors.

### Primary
- **Signal Teal** (`#0e9f8e`): the one accent — CTA buttons, links on hover, active FAQ chevron, showcase checkmarks, floating-chip icon color, footer brand-mark fill. Darkens to **Deep Teal** (`#0b7f72`) on hover/active states. Tinted to **Teal Mist** (`#e3f6f3`) as the icon-tile and success-icon background.

### Neutral
- **Near-Black Ink** (`#0b1220`): headline color, primary-button fill, and the two dark "ink" panel backgrounds (pain strip, lead-capture card) — doing double duty as both text color and a structural dark surface.
- **Slate Soft** (`#475569`): body copy, subheads, secondary paragraph text.
- **Slate Faint** (`#94a3b8`): captions, placeholder-weight text, nav-burger/field label color.
- **Cool Paper** (`#f6f8f9`): page background. Explicitly cool, not warm/cream — a B2B dashboard subject, not an editorial one.
- **Paper Alt** (`#eef3f3`): alternating section background (`.section-alt`) and the domain-bar chip fill, one step cooler-toned off the base paper.
- **Pure Surface** (`#ffffff`): card, input, and browser-chrome-frame fill against the paper ground.
- **Footer Ink** (`#0a1018`): the single darkest surface on the page, reserved for `.site-footer`.

### Tertiary (feature-tile hues, decorative-icon role only)
- **Tile Blue** (`#2563eb` on `#e8f0fe`), **Tile Amber** (`#b45309` on `#fef3e2`), **Tile Rose** (`#be123c` on `#fde8ec`), **Tile Violet** (`#6d28d9` on `#f0eafe`), **Tile Slate** (`#475569` on `#eef2f6`): each pairs one feature card with one hue, rotating through the six-card feature grid. These never appear as button, link, or heading colors — tile-only.

### Named Rules
**The One Accent Rule.** Teal-emerald is the only color used for interactive emphasis (buttons, links, active states). The five pastel tile hues are decorative icon-background differentiators only and never carry interactive or brand meaning.

## Typography

**Display Font:** Unbounded (weights 600/700/800, via Google Fonts)
**Body Font:** Inter (weights 400–700, via Google Fonts)

**Character:** Unbounded is geometric and confident with real display character — reserved strictly for headlines, the wordmark, and section titles, never for body copy or UI chrome. Inter carries everything a reader has to actually read at length: body paragraphs, nav links, buttons, form fields, labels.

### Hierarchy
- **Display** (800, `clamp(2.3rem, 4.6vw, 3.4rem)`, line-height 1.14): hero `h1` only, second line set in the accent teal.
- **Headline** (700, `clamp(1.8rem, 3.4vw, 2.5rem)`, line-height 1.14): section-head `h2` titles (pain strip, feature grid, showcase, FAQ, lead section).
- **Title** (700, 18–19px): feature-card and showcase `h3`, float-chip and domain-bar labels.
- **Body** (400–500, 14.5–17.5px, line-height 1.55–1.6): paragraph copy, FAQ answers, showcase points; hero subhead capped at `max-width: 50ch`.
- **Label** (700, 12.5px, uppercase, letter-spacing 0.04em): form field labels only.

### Named Rules
**The Unbounded-Never-Body Rule.** The display face appears only in `h1`–`h4` selectors. No button, input, nav link, or paragraph is ever set in Unbounded — it is a headline voice, not a UI typeface. This also means the system display face is never substituted in: both faces are sourced and loaded explicitly, never left to fall back to system-ui as a voice choice.

## Layout

A single `.container` (max-width 1180px, 24px side padding) governs all sections. Default section padding is 96px top/bottom, tightening to 64px under 600px viewports. The hero and lead sections break this rhythm deliberately (76–110px) to read as bracketing moments. Two-column grids (hero, showcase rows, white-label panel) collapse to a single column below 960px; nav links and the secondary CTA collapse into a burger-triggered mobile sheet below 860px. Feature and pain-item grids use `repeat(auto-fit, minmax(...), 1fr)` rather than fixed column counts, so card count adapts to viewport without a breakpoint per step.

## Elevation & Depth

Hybrid: a flat cool-paper canvas with cards and chips lifted on soft, diffuse, always-offset shadows — never a hard block shadow. Two dark "ink" panels (pain strip, lead-capture section) substitute tonal contrast for elevation instead of a shadow.

### Shadow Vocabulary
- **Ambient Small** (`0 2px 8px -2px rgba(11,18,32,0.08), 0 1px 3px rgba(11,18,32,0.06)`): resting state for feature cards and primary buttons.
- **Ambient Medium** (`0 20px 40px -20px rgba(11,18,32,0.22), 0 6px 16px -8px rgba(11,18,32,0.1)`): hover lift on feature cards, floating chips, showcase media, domain card.
- **Ambient Large** (`0 32px 64px -24px rgba(11,18,32,0.28)`): the hero browser-frame and the lead-capture card — the two highest-stakes surfaces on the page.

### Named Rules
**The Soft-Offset-Only Rule.** Every shadow in this world carries blur and a negative spread; zero-blur hard-offset shadows (the neobrutalist block-shadow) never appear — this is not a neobrutalist world.

## Shapes

Rounded-2xl is the house silhouette: 22px card radius on every surface card, domain card, FAQ item, and the lead-capture card; full-pill (999px) on every button, badge, and nav-burger-adjacent control; 11–15px on icon tiles and the brand-mark square (a rounded square, not a circle, to read as a product glyph rather than an avatar). The browser-chrome hero frame uses an 18px radius one step tighter than the card default, matching its role as a device frame rather than a content card. Hairline 1px borders (`rgba(11,18,32,0.08)`) edge most white cards against the paper ground; no colored border-left/right accents appear anywhere.

## Components

### Buttons
- **Shape:** full pill (`border-radius: 999px`), 14px/26px padding.
- **Primary:** near-black ink fill, white text, ambient-small shadow; hovers to accent-dark fill with ambient-medium shadow.
- **Accent:** solid teal fill, white text, a dedicated teal-tinted glow shadow (`0 16px 32px -14px rgba(14,159,142,0.55)`); used for the page's highest-intent CTAs (hero primary, nav "Оставить заявку").
- **Ghost:** white fill, hairline border, hovers to accent-teal border and text color.
- All buttons translate 1px down on `:active` as the only press feedback.

### Cards / Containers
- **Corner Style:** 22px (`--radius-card`).
- **Background:** white surface on paper or paper-alt ground; ink-dark background for the two dark panels.
- **Shadow Strategy:** ambient-small at rest, ambient-medium on hover (feature cards only have hover lift; static cards like FAQ items and the domain card do not).
- **Border:** 1px hairline in all cases except the dark ink panels.
- **Internal Padding:** 26–30px (feature cards), 10px outer / 14–18px inner (domain card), `clamp(22px,4vw,34px)` (lead card).

### Icon Tiles (signature component)
Rounded-square (15px radius), 50×50px, pastel background with a matching saturated icon color, one Lucide icon per card (24px). Six hues rotate across the feature grid (teal, blue, amber, rose, violet, slate) so no two adjacent cards share a tile color. This is the page's primary decorative-icon device and the thing that reads as "this product has its own visual identity" rather than borrowed stock iconography.

### Inputs / Fields
- **Style:** 12px radius, 1.5px hairline border, paper-tinted fill at rest.
- **Focus:** border shifts to accent teal and fill brightens to pure white — no glow/ring, just color-state change.
- **Error:** a labeled red banner (`#fef2f2` fill, `#b91c1c` text) above the field group, not per-field inline error styling.

### Navigation
Sticky, translucent-blurred (`rgba(246,248,249,0.82)`, 14px backdrop-blur) nav bar, 76px tall, hairline bottom border. Wordmark uses the rounded-square ink brand-mark plus Unbounded wordmark text. Links are Inter 600 in slate-soft, darkening to ink on hover — no underline, no active-state kicker. Below 860px, links and the secondary button collapse into a burger-triggered full-width mobile sheet with hairline-divided rows.

### Real-Screenshot Media Frame (signature component)
The hero visual and the three showcase rows frame real product screenshots (dashboard, debtors, attendance — genuine Demo Academy admin-panel captures, not generated images) in a browser-chrome shell (traffic-dot row + centered fake URL bar) at 18px radius with ambient-large shadow, slightly rotated (-1.4deg) in the hero only and leveling to 0deg under 720px. A floating stat chip (white pill, 16px radius, ambient-medium shadow) breaks the frame's corner with a real, labeled data point lifted from the same screenshot — never an invented statistic.

## Do's and Don'ts

### Do:
- **Do** keep Unbounded confined to headline-level elements; Inter carries every other role.
- **Do** source real admin-panel screenshots (with provenance preserved) for any future product-proof media on this page; never substitute a generated or stock mockup for "the real app."
- **Do** rotate the five pastel tile hues for new feature cards rather than introducing a sixth hue or repeating one hue on adjacent cards.
- **Do** use soft, blurred, offset shadows (ambient-small/medium/large) for all elevation; this is not a neobrutalist world.
- **Do** keep this system scoped to `marketing/home.php`; new tenant-facing or `/admin` screens follow their own existing systems, not this one.

### Don't:
- **Don't** add a kicker/eyebrow label above any heading on this page — confirmed removed at finish review (five instances cut), and the craft-floor ban applies regardless of future brief wording.
- **Don't** add decorative section numbering (01/02/03-style) — the FAQ's was cut for the same reason; a sequence may only carry a number if the order itself is informationally necessary.
- **Don't** introduce a second accent color competing with teal-emerald for interactive emphasis; the pastel tile hues are decorative-icon-only and must stay confined to that role.
- **Don't** reach for a hard-offset, zero-blur shadow anywhere on this page.
- **Don't** pull Quicksand/Font Awesome (the per-tenant template) or Space Grotesk (the admin/super operate surface) into this page's font or icon stack — three separate systems coexist in this codebase by design.
