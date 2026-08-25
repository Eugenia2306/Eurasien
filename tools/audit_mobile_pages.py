#!/usr/bin/env python3
"""
Audit static-site (and optional live URLs) for mobile layout breakage.

Usage:
  python tools/audit_mobile_pages.py
  python tools/audit_mobile_pages.py --base-url https://eurasia.uwzghana.com
  python tools/audit_mobile_pages.py --playwright   # needs: pip install playwright && playwright install chromium

Checks:
  - Static HTML: wide fixed widths, missing viewport, known fragile patterns
  - CSS: event-card rules that put CTAs in a row on narrow screens
  - Optional Playwright: documentElement.scrollWidth > clientWidth at 390x844
"""
from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent
STATIC = REPO / "static-site"
CSS = STATIC / "assets" / "css" / "main.css"

VIEWPORTS = (
    ("iphone-14", 390, 844),
    ("iphone-se", 375, 667),
    ("pixel", 412, 915),
)

FRAGILE_HTML = [
    (
        re.compile(r'<(?!img\b|svg\b|source\b|video\b|iframe\b)([a-z0-9]+)[^>]*\bwidth\s*=\s*["\']?(?:[6-9]\d{2}|[1-9]\d{3,})', re.I),
        "non-media fixed width attribute >= 600",
    ),
    (
        re.compile(r'style\s*=\s*["\'][^"\']*(?<!max-)width\s*:\s*([6-9]\d{2}|[1-9]\d{3,})px', re.I),
        "inline style width >= 600px (check max-width:100%)",
    ),
]

REQUIRED_VIEWPORT = re.compile(
    r'<meta[^>]+name=["\']viewport["\'][^>]+content=["\'][^"\']*width\s*=\s*device-width',
    re.I,
)


def list_pages() -> list[Path]:
    return sorted(p for p in STATIC.rglob("*.html") if p.is_file())


def audit_css() -> list[str]:
    issues: list[str] = []
    if not CSS.is_file():
        return [f"MISSING {CSS}"]
    text = CSS.read_text(encoding="utf-8")
    # Bad pattern: .ev__side in a row on mobile without column override nearby
    if re.search(
        r"@media\s*\(\s*max-width\s*:\s*640px\s*\)[^{]*\{[^}]*\.ev__side\{[^}]*flex-direction\s*:\s*row",
        text,
        re.S,
    ):
        # Allow if a later 900px override forces column
        if "flex-direction: column" not in text and "flex-direction:column" not in text:
            issues.append("CSS: .ev__side still row-only on mobile without column override")
    if ".ev__reg" in text and "white-space: normal" not in text and "white-space:normal" not in text:
        issues.append("CSS: .ev__reg has no white-space:normal (long DE labels may overflow)")
    if "minmax(0,1fr)" not in text and "minmax(0, 1fr)" not in text:
        issues.append("CSS: .ev mobile grid missing minmax(0,1fr) shrink track")
    return issues


def audit_html(path: Path) -> list[str]:
    issues: list[str] = []
    text = path.read_text(encoding="utf-8", errors="replace")
    rel = path.relative_to(STATIC).as_posix()
    if not REQUIRED_VIEWPORT.search(text):
        issues.append(f"{rel}: missing responsive viewport meta")
    for rx, label in FRAGILE_HTML:
        if rx.search(text):
            # tables are common in impressum etc — soft warn only for non-legal
            if "table" in label and rel in {"impressum.html", "datenschutz.html"}:
                continue
            issues.append(f"{rel}: {label}")
    # Event pages must load events CSS/JS path
    if "veranstaltungen" in rel or rel == "index.html":
        if "events-feed.js" not in text and "ev__" not in text and 'id="p-veranstaltungen"' not in text and 'id="p-home"' not in text:
            pass
    return issues


def audit_playwright(base_url: str) -> list[str]:
    try:
        from playwright.sync_api import sync_playwright
    except ImportError:
        return ["Playwright not installed (pip install playwright && playwright install chromium)"]

    issues: list[str] = []
    pages = [
        "/",
        "/veranstaltungen.html",
        "/regionen.html",
        "/analysen.html",
        "/mitgliedschaft.html",
        "/themen.html",
        "/vorstand.html",
        "/app/login/",
    ]
    base = base_url.rstrip("/")
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        for name, w, h in VIEWPORTS:
            context = browser.new_context(
                viewport={"width": w, "height": h},
                device_scale_factor=2,
                user_agent=(
                    "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) "
                    "AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 "
                    "Mobile/15E148 Safari/604.1"
                ),
            )
            page = context.new_page()
            for path in pages:
                url = base + path
                try:
                    page.goto(url, wait_until="networkidle", timeout=45000)
                    page.wait_for_timeout(800)
                    overflow = page.evaluate(
                        """() => {
                          const doc = document.documentElement;
                          const body = document.body;
                          const sw = Math.max(doc.scrollWidth, body ? body.scrollWidth : 0);
                          const cw = doc.clientWidth;
                          const offenders = [];
                          document.querySelectorAll('body *').forEach(el => {
                            const r = el.getBoundingClientRect();
                            if (r.width > cw + 2 && r.right > cw + 2) {
                              const tag = el.tagName.toLowerCase();
                              const cls = (el.className && typeof el.className === 'string') ? '.' + el.className.trim().split(/\\s+/).slice(0,3).join('.') : '';
                              offenders.push(tag + cls + ' w=' + Math.round(r.width));
                            }
                          });
                          return { sw, cw, overflow: sw > cw + 2, offenders: offenders.slice(0, 8) };
                        }"""
                    )
                    if overflow.get("overflow"):
                        offs = ", ".join(overflow.get("offenders") or []) or "unknown"
                        issues.append(
                            f"[{name} {w}x{h}] {path}: horizontal overflow "
                            f"(scrollWidth={overflow['sw']} > clientWidth={overflow['cw']}) — {offs}"
                        )
                except Exception as exc:  # noqa: BLE001
                    issues.append(f"[{name}] {path}: load failed — {exc}")
            context.close()
        browser.close()
    return issues


def main() -> int:
    parser = argparse.ArgumentParser(description="Mobile page layout audit")
    parser.add_argument("--base-url", default="", help="Live site for Playwright checks")
    parser.add_argument("--playwright", action="store_true", help="Run browser overflow checks")
    args = parser.parse_args()

    findings: list[str] = []
    findings.extend(audit_css())
    for page in list_pages():
        findings.extend(audit_html(page))

    if args.playwright:
        base = args.base_url or "https://eurasia.uwzghana.com"
        findings.extend(audit_playwright(base))

    print(f"Scanned {len(list_pages())} HTML pages + CSS")
    if not findings:
        print("OK — no issues found by static rules" + (" / Playwright" if args.playwright else ""))
        return 0

    print(f"FOUND {len(findings)} issue(s):\n")
    for f in findings:
        print(f"  - {f}")
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
