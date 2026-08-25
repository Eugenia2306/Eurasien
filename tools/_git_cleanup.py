#!/usr/bin/env python3
"""Remove plugin-zips, tmp scripts from git index and push cleanup commit."""
import subprocess, sys

REPO = r"c:\Users\HP\Documents\Eurasian\Eurasien"
NAME = "d4rl1ngt0n"
EMAIL = "d4rl1ngt0n@users.noreply.github.com"

def run(args, **kw):
    r = subprocess.run(args, cwd=REPO, capture_output=True, text=True, timeout=120, **kw)
    print("CMD:", " ".join(str(a) for a in args))
    if r.stdout.strip(): print("OUT:", r.stdout.strip()[:500])
    if r.stderr.strip(): print("ERR:", r.stderr.strip()[:500])
    print("RC:", r.returncode)
    return r

# Remove from index (but keep on disk)
run(["git", "rm", "-r", "--cached", "tools/plugin-zips/", "--ignore-unmatch"])
run(["git", "rm", "--cached", "tools/_git_commit_push.py", "--ignore-unmatch"])
run(["git", "rm", "--cached", "tools/_git_cleanup.py", "--ignore-unmatch"])
run(["git", "rm", "--cached", "_tmp_trace_nest.py", "_tmp_fix_nesting.py",
     "_tmp_inspect_membership.py", "_tmp_lang_check.py", "_tmp_expert_struct.py", "--ignore-unmatch"])

# Stage .gitignore update
run(["git", "add", ".gitignore"])

# Commit the removal
r = run(["git", "-c", f"user.name={NAME}", "-c", f"user.email={EMAIL}",
         "commit", "-m", "chore: remove plugin-zips and tmp scripts from tracking"])
if r.returncode not in (0, 1):
    print("Commit failed"); sys.exit(1)

# Push
r = run(["git", "push", "origin", "main"])
if r.returncode != 0:
    print("Push failed"); sys.exit(1)

print("Cleanup done.")
