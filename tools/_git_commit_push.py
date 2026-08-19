#!/usr/bin/env python3
"""Commit and push all changes to GitHub."""
import subprocess, sys
from pathlib import Path

REPO = r"c:\Users\HP\Documents\Eurasian\Eurasien"
NAME = "d4rl1ngt0n"
EMAIL = "d4rl1ngt0n@users.noreply.github.com"
MSG = "Fix subscribe button, event filter nav, and region-specific events"

def run(args, **kw):
    r = subprocess.run(args, cwd=REPO, capture_output=True, text=True, timeout=60, **kw)
    print("CMD:", " ".join(args))
    if r.stdout.strip(): print("OUT:", r.stdout.strip())
    if r.stderr.strip(): print("ERR:", r.stderr.strip())
    print("RC:", r.returncode)
    return r

r = run(["git", "add", "-A"])
if r.returncode != 0:
    sys.exit(1)

r = run(["git", "-c", f"user.name={NAME}", "-c", f"user.email={EMAIL}", "commit", "-m", MSG])
# rc 1 means "nothing to commit" which is ok if already committed
if r.returncode not in (0, 1):
    sys.exit(1)

r = run(["git", "push", "origin", "main"])
if r.returncode != 0:
    sys.exit(1)

print("All done.")
