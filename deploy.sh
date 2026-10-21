#!/usr/bin/env bash
# ---------------------------------------------------------------
# artcoatings.org — one-click deploy
#   Local WordPress edit -> static export to html/ -> flatten to repo root -> push GitHub
#   Repo:   https://github.com/merlincao/artcoatings  (branch: main)
#   Cloudflare Pages build output directory MUST be the repo ROOT ("/"),
#   because the repo root *is* the site root — there is no /html/ level online.
#
#   Site files live at the repo root; WordPress sources + pipeline scripts live
#   on the "source" branch only (kept out of main by .gitignore).
#
# Prereq: local site running at http://localhost:8080 (start-env.bat)
# Usage:  ./deploy.sh              # export + flatten + push
#         ./deploy.sh --push-only  # flatten and push existing html/
# ---------------------------------------------------------------
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_BIN="${PHP_BIN:-C:/Users/cjx/WorkBuddy/2026-10-06-00-30-33/meizhous/env/php/php.exe}"
PY_BIN="${PY_BIN:-C:/Users/cjx/.workbuddy/binaries/python/versions/3.13.12/python.exe}"
TOKEN_FILE="$ROOT/tools/.github_token"
REPO="merlincao/artcoatings"
BRANCH="main"

if [[ "${1:-}" == "--push-only" ]]; then
  echo "[skip] not re-exporting; publishing existing html/"
else
  echo "[1/4] static export (http://localhost:8080 -> html/) ..."
  if ! curl -sf -o /dev/null --noproxy '*' --max-time 5 http://localhost:8080/; then
    echo "!! local site not reachable on localhost:8080"
    echo "!! start it first with start-env.bat, then re-run this script."
    exit 1
  fi
  "$PHP_BIN" "$ROOT/tools/static-export.php"
  echo "[2/4] sitemaps + GEO files ..."
  "$PHP_BIN" "$ROOT/tools/build-seo.php" --base https://artcoatings.org
fi

echo "[3/4] flatten html/ -> repo root, then commit ..."
cd "$ROOT"
"$PY_BIN" "$ROOT/tools/root-sync.py" --apply
git add -A
if git diff --cached --quiet; then
  echo "  nothing to commit"
else
  git commit -q -m "chore: regenerate static site $(date '+%Y-%m-%d %H:%M')"
fi

echo "[4/4] push to GitHub ($REPO $BRANCH) ..."
if [[ ! -f "$TOKEN_FILE" ]]; then
  echo "!! missing $TOKEN_FILE (it should contain a single PAT line)"
  exit 1
fi
TOKEN="$(tr -d '\r\n' < "$TOKEN_FILE")"
git push "https://merlincao:${TOKEN}@github.com/${REPO}.git" "$BRANCH"

echo "done. Cloudflare Pages will publish within a minute or so."
