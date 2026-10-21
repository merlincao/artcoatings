#!/usr/bin/env bash
# ---------------------------------------------------------------
# artcoatings.org — one-click deploy
#   Local WordPress edit -> static export to html/ -> push GitHub
#   Repo:   https://github.com/merlincao/artcoatings  (branch: main)
#   Cloudflare Pages publish/build-output directory MUST be: html
#
# Prereq: local site running at http://127.0.0.1:8080 (start-env.bat)
# Usage:  ./deploy.sh              # export + push
#         ./deploy.sh --push-only  # push existing html/ only
# ---------------------------------------------------------------
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_BIN="${PHP_BIN:-C:/Users/cjx/WorkBuddy/2026-10-06-00-30-33/meizhous/env/php/php.exe}"
TOKEN_FILE="$ROOT/tools/.github_token"
REPO="merlincao/artcoatings"
BRANCH="main"

if [[ "${1:-}" == "--push-only" ]]; then
  echo "[skip] not re-exporting; pushing existing html/"
else
  echo "[1/3] static export (http://127.0.0.1:8080 -> html/) ..."
  if ! curl -sf -o /dev/null --noproxy '*' --max-time 5 http://127.0.0.1:8080/; then
    echo "!! local site not reachable on 127.0.0.1:8080"
    echo "!! start it first with start-env.bat, then re-run this script."
    exit 1
  fi
  "$PHP_BIN" "$ROOT/tools/static-export.php"
fi

echo "[2/3] commit ..."
cd "$ROOT"
git add html
git add site/wp-content/themes/artcoatings site/wp-content/plugins/artcoatings-core 2>/dev/null || true
if git diff --cached --quiet; then
  echo "  nothing to commit"
else
  git commit -q -m "chore: regenerate static site $(date '+%Y-%m-%d %H:%M')"
fi

echo "[3/3] push to GitHub ($REPO $BRANCH) ..."
if [[ ! -f "$TOKEN_FILE" ]]; then
  echo "!! missing $TOKEN_FILE (it should contain a single PAT line)"
  exit 1
fi
TOKEN="$(tr -d '\r\n' < "$TOKEN_FILE")"
git push "https://merlincao:${TOKEN}@github.com/${REPO}.git" "$BRANCH"

echo "done. Cloudflare Pages will publish within a minute or so."
