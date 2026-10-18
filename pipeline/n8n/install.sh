#!/bin/bash
set -euo pipefail

PIPE=/www/htdocs/w0220438/pipeline
N8N_DIR="$PIPE/n8n"
NODE_DIR="$PIPE/node"
NODE_VER=v20.19.5
NODE_DIST="node-${NODE_VER}-linux-x64"
NODE_URL="https://nodejs.org/dist/${NODE_VER}/${NODE_DIST}.tar.xz"

export HOME="$N8N_DIR/home"
mkdir -p "$PIPE" "$N8N_DIR/data" "$N8N_DIR/home" "$N8N_DIR/log" "$PIPE/secrets"

if [ ! -x "$NODE_DIR/bin/node" ]; then
  echo "Downloading Node ${NODE_VER}..."
  curl -fsSL "$NODE_URL" -o /tmp/eg-node.tar.xz
  rm -rf "$NODE_DIR"
  mkdir -p "$PIPE"
  tar -xJf /tmp/eg-node.tar.xz -C "$PIPE"
  mv "$PIPE/$NODE_DIST" "$NODE_DIR"
  rm -f /tmp/eg-node.tar.xz
fi

export PATH="$NODE_DIR/bin:$PATH"
echo "node $(node -v) npm $(npm -v)"

if [ ! -x "$NODE_DIR/bin/n8n" ] && [ ! -x "$N8N_DIR/home/.npm-global/bin/n8n" ]; then
  echo "Installing n8n@1 (this can take several minutes)..."
  mkdir -p "$HOME/.npm-global"
  npm config set prefix "$HOME/.npm-global"
  npm config set cache "$HOME/.npm"
  npm install -g n8n@1 --omit=dev
fi

export PATH="$HOME/.npm-global/bin:$NODE_DIR/bin:$PATH"
command -v n8n
n8n --version

python3 - <<'PY'
import base64, os, secrets, string
root = "/www/htdocs/w0220438/pipeline/n8n"
os.makedirs(root, exist_ok=True)
env_path = os.path.join(root, "env")
token_path = os.path.join(root, "keepalive.token")
owner_path = "/www/htdocs/w0220438/pipeline/secrets/n8n-owner.txt"
wp_pass = "PGSeuyevshNe1JQxHP5c3RAc"
wp_user = "eg-n8n"
basic = "Basic " + base64.b64encode(f"{wp_user}:{wp_pass}".encode()).decode()
alphabet = string.ascii_letters + string.digits
owner_pass = "Eg-" + "".join(secrets.choice(alphabet) for _ in range(18)) + "9!"
enc_key = secrets.token_urlsafe(36)
keep_token = secrets.token_urlsafe(32)
if os.path.isfile(env_path):
    print("env exists, not overwriting")
else:
    text = f"""N8N_HOST=127.0.0.1
N8N_PORT=5678
N8N_LISTEN_ADDRESS=127.0.0.1
N8N_PROTOCOL=http
N8N_EDITOR_BASE_URL=http://127.0.0.1:5678
WEBHOOK_URL=http://127.0.0.1:5678/
N8N_ENCRYPTION_KEY={enc_key}
N8N_USER_FOLDER={root}/data
N8N_RUNNERS_ENABLED=false
N8N_ENFORCE_SETTINGS_FILE_PERMISSIONS=false
N8N_DIAGNOSTICS_ENABLED=false
N8N_PERSONALIZATION_ENABLED=false
N8N_VERSION_NOTIFICATIONS_ENABLED=false
N8N_HIRING_BANNER_ENABLED=false
EXECUTIONS_DATA_PRUNE=true
EXECUTIONS_DATA_MAX_AGE=168
GENERIC_TIMEZONE=Europe/Berlin
TZ=Europe/Berlin
EG_WP_BASE=https://eurasiengesellschaft.de/app
EG_WP_USER={wp_user}
EG_WP_PASS={wp_pass}
EG_WP_BASIC={basic}
"""
    with open(env_path, "w") as f:
        f.write(text)
    os.chmod(env_path, 0o600)
    print("wrote env")
if not os.path.isfile(token_path):
    with open(token_path, "w") as f:
        f.write(keep_token)
    os.chmod(token_path, 0o600)
    print("wrote keepalive token")
if not os.path.isfile(owner_path):
    os.makedirs(os.path.dirname(owner_path), exist_ok=True)
    with open(owner_path, "w") as f:
        f.write("email=pipeline-n8n@eurasiengesellschaft.de\n")
        f.write(f"password={owner_pass}\n")
    os.chmod(owner_path, 0o600)
    print("wrote owner secrets")
print("token=" + open(token_path).read().strip())
print(open(owner_path).read())
PY

chmod +x "$N8N_DIR/start.sh" "$N8N_DIR/is-up.py" || true

if python3 "$N8N_DIR/is-up.py" 5678; then
  echo "n8n already listening on 5678"
else
  echo "starting n8n..."
  nohup "$N8N_DIR/start.sh" >> "$N8N_DIR/log/n8n.log" 2>&1 &
  echo "start pid $!"
fi

python3 - <<'PY'
import json, os, time, urllib.error, urllib.request
root = "/www/htdocs/w0220438/pipeline/n8n"
base = "http://127.0.0.1:5678"
ok = False
for i in range(60):
    try:
        urllib.request.urlopen(base + "/healthz", timeout=2)
        ok = True
        break
    except Exception:
        try:
            urllib.request.urlopen(base + "/rest/login", timeout=2)
            ok = True
            break
        except Exception:
            time.sleep(2)
print("n8n http", "up" if ok else "DOWN", "after wait")
if not ok:
    raise SystemExit(2)

owner = {}
with open("/www/htdocs/w0220438/pipeline/secrets/n8n-owner.txt") as f:
    for line in f:
        if "=" in line:
            k, v = line.strip().split("=", 1)
            owner[k] = v
email = owner["email"]
password = owner["password"]

def req(method, path, data=None, cookie=None):
    headers = {"Content-Type": "application/json", "Accept": "application/json"}
    if cookie:
        headers["Cookie"] = cookie
    body = None if data is None else json.dumps(data).encode()
    r = urllib.request.Request(base + path, data=body, headers=headers, method=method)
    try:
        with urllib.request.urlopen(r, timeout=30) as resp:
            raw = resp.read()
            ck = resp.headers.get("Set-Cookie", "")
            parsed = json.loads(raw.decode() or "null") if raw else None
            return resp.status, parsed, ck
    except urllib.error.HTTPError as e:
        raw = e.read()
        try:
            parsed = json.loads(raw.decode() or "null")
        except Exception:
            parsed = raw.decode(errors="replace")
        return e.code, parsed, e.headers.get("Set-Cookie", "")

code, payload, _ = req("POST", "/rest/owner/setup", {
    "email": email,
    "firstName": "Eurasien",
    "lastName": "Gesellschaft",
    "password": password,
})
print("owner setup", code, payload if not isinstance(payload, dict) else payload.get("code") or payload.get("id") or "ok")

code, payload, cookie = req("POST", "/rest/login", {
    "emailOrLdapLoginId": email,
    "password": password,
})
print("login", code)
if code >= 400:
    raise SystemExit("login failed: %s" % payload)
cookie = cookie.split(";", 1)[0] if cookie else cookie

code, payload, _ = req("GET", "/rest/workflows", cookie=cookie)
print("workflows list", code)
existing = []
if isinstance(payload, dict):
    existing = payload.get("data") or payload.get("workflows") or []
if isinstance(payload, list):
    existing = payload
names = [w.get("name") for w in existing if isinstance(w, dict)]
print("existing names", names)

wf_path = os.path.join(root, "eg-editorial-coordinator.json")
with open(wf_path, encoding="utf-8") as f:
    wf = json.load(f)

if "EG editorial coordinator" in names:
    print("workflow already present")
    wf_id = next(w.get("id") for w in existing if w.get("name") == "EG editorial coordinator")
else:
    code, payload, _ = req("POST", "/rest/workflows", {
        "name": wf.get("name"),
        "nodes": wf.get("nodes"),
        "connections": wf.get("connections"),
        "settings": wf.get("settings") or {"executionOrder": "v1"},
        "staticData": None,
    }, cookie=cookie)
    print("create workflow", code)
    if code >= 400:
        raise SystemExit(payload)
    wf_id = payload.get("id") if isinstance(payload, dict) else None
    print("workflow id", wf_id)

if wf_id:
    code, payload, _ = req("PATCH", f"/rest/workflows/{wf_id}", {"active": True}, cookie=cookie)
    print("activate", code, payload if not isinstance(payload, dict) else payload.get("active"))
print("DONE")
PY
