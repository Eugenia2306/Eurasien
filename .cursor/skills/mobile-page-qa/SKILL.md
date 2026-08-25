---
name: mobile-page-qa
description: >-
  Audit Eurasien Gesellschaft static and /app/ pages for mobile layout breakage
  (horizontal overflow, event cards, headers, forms). Use when the user mentions
  mobile bugs, broken layout, overflow, phones, responsive issues, or asks to
  check/QA all pages; also after CSS or event-card changes.
---

# Mobile + page layout QA (Eurasien)

## When to run

- User reports mobile breakage (especially Veranstaltungen / event CTAs)
- After editing `static-site/assets/css/main.css`, event JS, headers, or forms
- User asks to check all pages / responsive QA

## Do this

1. **Fix the reported bug first** (smallest CSS/JS change that stops overflow).
2. **Run the audit script**:

```bash
python tools/audit_mobile_pages.py
python tools/audit_mobile_pages.py --playwright --base-url https://eurasia.uwzghana.com
```

3. **Manually spot-check** (hard refresh) at ~390px width:
   - `/veranstaltungen.html` — register CTA must wrap full width under title, not spill off-screen
   - `/` home events teaser if present
   - `/regionen.html`, `/analysen.html`, `/mitgliedschaft.html`
   - `/app/login/` — ubar + masthead + form fit; DE/EN works

4. **Sync CSS** to WordPress theme when `static-site/assets/css/main.css` changes:

```bash
Copy-Item -Force static-site/assets/css/main.css wp-theme/eurasien-gesellschaft/assets/css/main.css
```

5. **Bump cache query** on `main.css?v=` in static HTML when shipping CSS fixes.

## Known fragile patterns

| Pattern | Risk | Fix |
|---------|------|-----|
| `.ev` 3-col grid + `.ev__side { flex-direction: row }` on mobile | Long DE CTA overflows | 2-col grid + column side + `white-space: normal` on `.ev__reg` |
| Grid children without `min-width: 0` / `minmax(0,1fr)` | Track won't shrink | Add shrinkable tracks |
| Fixed `width: NNN px` in HTML | Horizontal scroll | Use `%` / `max-width: 100%` |
| Dual app chrome (slim header/footer) | Split UX | Keep brochure ubar + masthead + full footer |

## Report format

Return a short verdict:

```
Mobile QA
- Fixed: …
- Audit: N issues (list) or OK
- Spot-check: pages verified
```

Do not claim Playwright OK unless `--playwright` was run successfully.
