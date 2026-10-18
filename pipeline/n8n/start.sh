#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
# shellcheck disable=SC1091
set -a
. "$ROOT/env"
set +a
export HOME="$ROOT/home"
export PATH="$ROOT/../node/bin:$PATH"
export NODE_ENV=production
export NODE_OPTIONS="${NODE_OPTIONS:---max-old-space-size=512}"
mkdir -p "$N8N_USER_FOLDER" "$HOME"
cd "$ROOT"
exec n8n start
