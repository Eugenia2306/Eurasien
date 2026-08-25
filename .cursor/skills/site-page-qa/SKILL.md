---
name: site-page-qa
description: >-
  Full-site QA for Eurasien Gesellschaft: mobile overflow, broken links,
  bilingual DE/EN visibility, SEO head tags, and /app/ chrome parity with the
  brochure. Use when the user asks to check all pages, run QA, smoke-test the
  site, or verify nothing is broken after a deploy.
---

# Full site page QA

## Scope

Public brochure (`static-site/`) and WordPress `/app/` members chrome.

## Checklist (do in order)

1. **Mobile layout** — follow `.cursor/skills/mobile-page-qa/SKILL.md` and run:

```bash
python tools/audit_mobile_pages.py
python tools/audit_mobile_pages.py --playwright --base-url https://eurasia.uwzghana.com
```

2. **Chrome parity** — `/app/` pages must use the same ubar + masthead + full footer as the brochure; only login/account links change with session.

3. **Language** — DE/EN toggle must work on brochure and `/app/login/` (source of truth: `data-eg-lang`, not WP `lang=en-US`).

4. **SEO** — public HTML has unique title/description/canonical; gated `/app/` pages are noindex. Discovery files: `robots.txt`, `sitemap.xml`, `llms.txt`.

5. **Events** — on phone width, event register CTA stacks under content and does not overflow horizontally.

## Output

Short report with pass/fail per area and any URLs still broken.
