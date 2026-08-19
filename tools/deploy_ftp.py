#!/usr/bin/env python3
"""
Deploy static-site + selected wp-theme files to Hostinger staging server via FTP.
Usage: python tools/deploy_ftp.py
Credentials are read from environment variables or the defaults below.
"""
import ftplib
import io
import os
import sys
from pathlib import Path

HOST = os.environ.get("EG_FTP_HOST", "45.84.207.84")
USER = os.environ.get("EG_FTP_USER", "u360353623.uwzghana.com")
PW   = os.environ.get("EG_FTP_PASS", "@Pa55w0rd61")

REPO = Path(__file__).resolve().parent.parent
STATIC = REPO / "static-site"
REMOTE_ROOT = "/public_html/eurasia"

SKIP = {".DS_Store", "Thumbs.db", ".gitkeep"}

def build_pairs():
    pairs = []
    for p in STATIC.rglob("*"):
        if not p.is_file():
            continue
        if p.name in SKIP:
            continue
        rel = p.relative_to(STATIC).as_posix()
        pairs.append((p, f"{REMOTE_ROOT}/{rel}"))

    # Extra wp-theme files
    extras = [
        (REPO / "wp-theme/eurasien-gesellschaft/assets/css/main.css",
         f"{REMOTE_ROOT}/app/wp-content/themes/eurasien-gesellschaft/assets/css/main.css"),
        (REPO / "wp-theme/eurasien-gesellschaft/functions.php",
         f"{REMOTE_ROOT}/app/wp-content/themes/eurasien-gesellschaft/functions.php"),
        (REPO / "wp-theme/eurasien-gesellschaft/content/p-regionen.html",
         f"{REMOTE_ROOT}/app/wp-content/themes/eurasien-gesellschaft/content/p-regionen.html"),
        (REPO / "wp-theme/eurasien-gesellschaft/content/p-news.html",
         f"{REMOTE_ROOT}/app/wp-content/themes/eurasien-gesellschaft/content/p-news.html"),
        (REPO / "wp-theme/eurasien-gesellschaft/content/p-mediathek.html",
         f"{REMOTE_ROOT}/app/wp-content/themes/eurasien-gesellschaft/content/p-mediathek.html"),
        (REPO / "wp-app/mu-plugins/eg-event-admin.php",
         f"{REMOTE_ROOT}/app/wp-content/mu-plugins/eg-event-admin.php"),
    ]
    pairs.extend((src, dest) for src, dest in extras if src.is_file())
    return pairs


def mkdirs(ftp: ftplib.FTP, path: str):
    """Best-effort mkdir -p on the remote."""
    acc = ""
    for part in path.strip("/").split("/"):
        acc += "/" + part
        try:
            ftp.mkd(acc)
        except Exception:
            pass


def main():
    pairs = build_pairs()
    print(f"Connecting to {HOST}…")
    ftp = ftplib.FTP()
    ftp.connect(HOST, 21, timeout=90)
    ftp.login(USER, PW)
    ftp.set_pasv(True)
    print(f"Logged in. Uploading {len(pairs)} files…")

    ok = 0
    failed = []
    for src, dest in pairs:
        parent = "/".join(dest.split("/")[:-1])
        mkdirs(ftp, parent)
        try:
            ftp.storbinary("STOR " + dest, io.BytesIO(src.read_bytes()))
            ok += 1
            if ok % 10 == 0 or ok == len(pairs):
                print(f"  {ok}/{len(pairs)} {dest}")
        except Exception as e:
            failed.append((dest, str(e)))
            print(f"  FAILED {dest}: {e}", file=sys.stderr)

    ftp.quit()
    print(f"\nDone: {ok} uploaded, {len(failed)} failed.")
    if failed:
        for dest, err in failed:
            print(f"  FAIL: {dest} — {err}", file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
