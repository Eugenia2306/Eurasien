#!/usr/bin/env python3
"""
Deploy static-site + WordPress /app/ kit to Hostinger staging via FTP.

Streams:
  1. static-site/*  -> /public_html/eurasia/
  2. wp-theme + wp-app -> /public_html/eurasia/app/wp-content/...

Usage: python tools/deploy_ftp.py
Credentials: EG_FTP_HOST, EG_FTP_USER, EG_FTP_PASS (optional overrides).
"""
import ftplib
import io
import os
import sys
from pathlib import Path

HOST = os.environ.get("EG_FTP_HOST", "45.84.207.84")
USER = os.environ.get("EG_FTP_USER", "u360353623.uwzghana.com")
PW = os.environ.get("EG_FTP_PASS", "Pa55w0rd@137")

REPO = Path(__file__).resolve().parent.parent
STATIC = REPO / "static-site"
THEME = REPO / "wp-theme" / "eurasien-gesellschaft"
MU_PLUGINS = REPO / "wp-app" / "mu-plugins"
WP_APP_ROOT = REPO / "wp-app"
REMOTE_ROOT = "/public_html/eurasia"

SKIP_NAMES = {".DS_Store", "Thumbs.db", ".gitkeep"}
SKIP_DIR_PARTS = {"eg-private", ".git", "__pycache__"}


def should_skip(path: Path) -> bool:
    if path.name in SKIP_NAMES:
        return True
    if any(part in SKIP_DIR_PARTS for part in path.parts):
        return True
    if path.suffix == ".example":
        return True
    return False


def static_pairs():
    for p in STATIC.rglob("*"):
        if not p.is_file() or should_skip(p):
            continue
        rel = p.relative_to(STATIC).as_posix()
        yield p, f"{REMOTE_ROOT}/{rel}"


def theme_pairs():
    if not THEME.is_dir():
        return
    remote_base = f"{REMOTE_ROOT}/app/wp-content/themes/eurasien-gesellschaft"
    for p in THEME.rglob("*"):
        if not p.is_file() or should_skip(p):
            continue
        rel = p.relative_to(THEME).as_posix()
        yield p, f"{remote_base}/{rel}"


def mu_plugin_pairs():
    if not MU_PLUGINS.is_dir():
        return
    remote_base = f"{REMOTE_ROOT}/app/wp-content/mu-plugins"
    for p in MU_PLUGINS.rglob("*"):
        if not p.is_file() or should_skip(p):
            continue
        rel = p.relative_to(MU_PLUGINS).as_posix()
        yield p, f"{remote_base}/{rel}"


def wp_app_root_pairs():
    """Top-level /app/ PHP helpers (events JSON, auth status, handoffs)."""
    if not WP_APP_ROOT.is_dir():
        return
    for p in WP_APP_ROOT.glob("*.php"):
        if should_skip(p):
            continue
        yield p, f"{REMOTE_ROOT}/app/{p.name}"


def build_pairs():
    pairs = []
    seen = set()
    for gen in (static_pairs(), theme_pairs(), mu_plugin_pairs(), wp_app_root_pairs()):
        for src, dest in gen:
            if dest in seen:
                continue
            seen.add(dest)
            pairs.append((src, dest))
    return pairs


def mkdirs(ftp: ftplib.FTP, path: str):
    acc = ""
    for part in path.strip("/").split("/"):
        acc += "/" + part
        try:
            ftp.mkd(acc)
        except Exception:
            pass


def main():
    pairs = build_pairs()
    static_n = sum(1 for _, d in pairs if "/app/" not in d)
    app_n = len(pairs) - static_n
    print(f"Bundle: {static_n} static + {app_n} app = {len(pairs)} files")
    print(f"Connecting to {HOST}…")
    ftp = ftplib.FTP()
    ftp.connect(HOST, 21, timeout=120)
    ftp.login(USER, PW)
    ftp.set_pasv(True)
    print(f"Logged in. Uploading…")

    ok = 0
    failed = []
    for src, dest in pairs:
        parent = "/".join(dest.split("/")[:-1])
        mkdirs(ftp, parent)
        try:
            ftp.storbinary("STOR " + dest, io.BytesIO(src.read_bytes()))
            ok += 1
            if ok % 25 == 0 or ok == len(pairs):
                print(f"  {ok}/{len(pairs)} {dest}")
        except Exception as e:
            failed.append((dest, str(e)))
            print(f"  FAILED {dest}: {e}", file=sys.stderr)

    ftp.quit()
    print(f"\nDone: {ok} uploaded ({static_n} static, {app_n} app), {len(failed)} failed.")
    if failed:
        for dest, err in failed:
            print(f"  FAIL: {dest} — {err}", file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
